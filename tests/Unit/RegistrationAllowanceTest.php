<?php

namespace Tests\Unit;

use App\Services\RegistrationAllowanceService;
use App\Support\EmailSpreadsheet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class RegistrationAllowanceTest extends TestCase
{
    public function test_email_column_reads_csv_and_ignores_other_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'allow');
        file_put_contents($path, "Name,Email\nAda,Ada@School.edu\n,\nBea,bea@school.edu\n");

        $values = EmailSpreadsheet::emailColumn($path, 'csv');
        unlink($path);

        $this->assertSame(['Ada@School.edu', 'bea@school.edu'], $values);
    }

    public function test_email_column_requires_an_email_header(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file needs a column named Email.');

        $path = tempnam(sys_get_temp_dir(), 'allow');
        file_put_contents($path, "Name\nAda\n");
        try {
            EmailSpreadsheet::emailColumn($path, 'csv');
        } finally {
            unlink($path);
        }
    }

    public function test_email_column_reads_xlsx_shared_strings(): void
    {
        $path = $this->workbook([
            ['Name', 'Email'],
            ['Ada', 'ada@school.edu'],
        ]);

        $values = EmailSpreadsheet::emailColumn($path, 'xlsx');
        unlink($path);

        $this->assertSame(['ada@school.edu'], $values);
    }

    public function test_import_message_names_each_result(): void
    {
        $message = RegistrationAllowanceService::importMessage([
            'added' => 2,
            'already_listed' => 1,
            'invalid' => 3,
        ]);

        $this->assertSame(
            'Added 2 emails. 1 was already listed. 3 rows were not valid email addresses.',
            $message
        );
    }

    public function test_stored_role_keeps_the_admin_choice(): void
    {
        $this->assertSame('Student', RegistrationAllowanceService::storedRole(null));
        $this->assertSame('Student', RegistrationAllowanceService::storedRole('user'));
        $this->assertSame('Faculty', RegistrationAllowanceService::storedRole('faculty'));
        $this->assertSame('PC_InformationTechnology', RegistrationAllowanceService::storedRole('PC_InformationTechnology'));
        $this->assertSame('item_owner', RegistrationAllowanceService::storedRole('item_owner'));
    }

    public function test_normalize_email_is_lowercase_and_trimmed(): void
    {
        $this->assertSame('ada@school.edu', RegistrationAllowanceService::normalizeEmail('  Ada@School.edu  '));
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function workbook(array $rows): string
    {
        $strings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $strings[] = $value;
            }
        }
        $unique = array_values(array_unique($strings));
        $index = array_flip($unique);

        $shared = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($unique).'" uniqueCount="'.count($unique).'">';
        foreach ($unique as $value) {
            $shared .= '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
        }
        $shared .= '</sst>';

        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowNumber => $row) {
            $sheet .= '<row r="'.($rowNumber + 1).'">';
            foreach ($row as $column => $value) {
                $letter = chr(65 + $column);
                $sheet .= '<c r="'.$letter.($rowNumber + 1).'" t="s"><v>'.$index[$value].'</v></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?>'.$shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'.$sheet);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->close();

        return $path;
    }
}
