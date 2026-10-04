<?php

namespace App\Support;

use Closure;

final class InventoryInput
{
    public static function itemNameRules(): array
    {
        return ['required', 'string', 'max:255', self::labelRule('The item name contains characters that are not allowed.')];
    }

    public static function tableTypeRules(): array
    {
        return ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,40}$/'];
    }

    public static function countRules(): array
    {
        return ['required', 'integer', 'min:0', 'max:999999'];
    }

    public static function label(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 255) {
            return false;
        }

        if (preg_match('/[;\\\\]|--|\/\*|\*\/|\x00/', $value) === 1) {
            return false;
        }

        if (preg_match('/[\'"]\s*(?:or|and)\b/i', $value) === 1) {
            return false;
        }

        if (preg_match('/\b(?:or|and)\b\s+[\'"]?[0-9]+[\'"]?\s*=\s*[\'"]?[0-9]+/i', $value) === 1) {
            return false;
        }

        if (preg_match('/\b(?:randomblob|pg_sleep|sleep|benchmark|waitfor|information_schema|sqlite_version)\b/i', $value) === 1) {
            return false;
        }

        return true;
    }

    public static function tableType(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]{0,40}$/', trim($value)) === 1;
    }

    public static function count(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0 && $value <= 999999;
        }

        return is_string($value) && preg_match('/^\d{1,6}$/', $value) === 1;
    }

    private static function labelRule(string $message): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($message): void {
            if (!self::label($value)) {
                $fail($message);
            }
        };
    }
}
