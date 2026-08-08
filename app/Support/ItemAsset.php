<?php

namespace App\Support;

class ItemAsset
{
    public static function normalizeCode(string $code): string
    {
        return trim($code);
    }

    public static function fallbackCode(int $itemId): string
    {
        return '#ITEM-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT);
    }

    public static function temporaryUnitCode(int $itemId, int $unitNumber): string
    {
        return '#TMP-' . str_pad((string) $itemId, 4, '0', STR_PAD_LEFT) . '-U' . str_pad((string) max(1, $unitNumber), 3, '0', STR_PAD_LEFT);
    }

    public static function isTemporaryCode(string $code): bool
    {
        return str_starts_with(self::normalizeCode($code), '#TMP-');
    }

    public static function displayCode(?string $itemCode, int $itemId): string
    {
        $normalized = self::normalizeCode((string) $itemCode);

        return $normalized !== '' ? $normalized : self::fallbackCode($itemId);
    }

    public static function unitCode(string $itemCode, int $unitNumber, int $totalUnits): string
    {
        $base = self::normalizeCode($itemCode);

        if ($base === '') {
            return sprintf('U%03d', max(1, $unitNumber));
        }

        if ($totalUnits <= 1) {
            return $base;
        }

        return $base . '-' . str_pad((string) max(1, $unitNumber), 2, '0', STR_PAD_LEFT);
    }
}
