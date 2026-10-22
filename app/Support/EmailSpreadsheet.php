<?php

namespace App\Support;

use InvalidArgumentException;
use ZipArchive;

class EmailSpreadsheet
{
    private const MAX_ROWS = 20000;

    /**
     * Values from the column whose header is Email. The header itself is omitted.
     *
     * @return array<int, string>
     */
    public static function emailColumn(string $path, string $extension): array
    {
        $rows = match (strtolower($extension)) {
            'csv', 'txt' => self::csvRows($path),
            'xlsx' => self::xlsxRows($path),
            default => throw new InvalidArgumentException('Upload a CSV or Excel file.'),
        };

        return self::emailValues($rows);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, string>
     */
    public static function emailValues(array $rows): array
    {
        $headerIndex = null;
        $headerRow = null;

        foreach ($rows as $index => $row) {
            if (self::rowIsEmpty($row)) {
                continue;
            }
            $headerRow = $index;
            foreach ($row as $column => $value) {
                if (strcasecmp(trim($value), 'Email') === 0) {
                    $headerIndex = $column;
                    break;
                }
            }
            break;
        }

        if ($headerIndex === null) {
            throw new InvalidArgumentException('The file needs a column named Email.');
        }

        $values = [];
        foreach ($rows as $index => $row) {
            if ($headerRow !== null && $index <= $headerRow) {
                continue;
            }
            $value = trim((string) ($row[$headerIndex] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('The file could not be read.');
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            throw new InvalidArgumentException('The file needs a column named Email.');
        }
        rewind($handle);

        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
            }
            $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function xlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The Excel file could not be read.');
        }

        $shared = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $sheetName = self::firstSheetPath($zip);
        $sheet = $sheetName === null ? false : $zip->getFromName($sheetName);
        $zip->close();

        if ($sheet === false || $sheet === '') {
            throw new InvalidArgumentException('The Excel file has no worksheet.');
        }

        $xml = simplexml_load_string($sheet);
        if ($xml === false) {
            throw new InvalidArgumentException('The Excel file could not be read.');
        }

        $rows = [];
        foreach ($xml->sheetData->row ?? [] as $row) {
            $cells = [];
            foreach ($row->c ?? [] as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $column = self::columnIndex($reference);
                $type = (string) ($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $value = $shared[(int) ($cell->v ?? 0)] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $cells[$column] = trim($value);
            }
            if ($cells !== []) {
                ksort($cells);
                $rows[] = $cells;
            }
            if (count($rows) > self::MAX_ROWS) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $parsed = simplexml_load_string($xml);
        if ($parsed === false) {
            return [];
        }

        $strings = [];
        foreach ($parsed->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }
            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) ($run->t ?? '');
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function firstSheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return $zip->locateName('xl/worksheets/sheet1.xml') !== false
                ? 'xl/worksheets/sheet1.xml'
                : null;
        }

        $workbookXml = simplexml_load_string($workbook);
        $relsXml = simplexml_load_string($rels);
        if ($workbookXml === false || $relsXml === false) {
            return null;
        }

        $namespaces = $workbookXml->getNamespaces(true);
        $rid = '';
        if (isset($namespaces['r'])) {
            $workbookXml->registerXPathNamespace('m', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $workbookXml->registerXPathNamespace('r', $namespaces['r']);
            $nodes = $workbookXml->xpath('//m:sheet[1]');
            if (is_array($nodes) && isset($nodes[0])) {
                $attributes = $nodes[0]->attributes($namespaces['r']);
                $rid = (string) ($attributes['id'] ?? '');
            }
        }

        foreach ($relsXml->Relationship as $relationship) {
            if ($rid !== '' && (string) $relationship['Id'] !== $rid) {
                continue;
            }
            $target = (string) $relationship['Target'];
            if (!str_contains($target, 'worksheets/')) {
                continue;
            }
            $target = ltrim($target, '/');
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/'.ltrim($target, '/');
            }

            return $target;
        }

        return null;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    /**
     * @param  array<int, string>  $row
     */
    private static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
