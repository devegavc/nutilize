<?php

namespace App\Services;

class UserNameService
{
    /**
     * @return array{first_name: ?string, middle_initial: ?string, last_name: ?string, full_name: ?string}
     */
    public static function splitFullName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        if ($fullName === '') {
            return [
                'first_name' => null,
                'middle_initial' => null,
                'last_name' => null,
                'full_name' => null,
            ];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $first = array_shift($parts);
        $last = count($parts) > 0 ? (string) array_pop($parts) : null;
        $middle = count($parts) > 0 ? strtoupper(substr((string) $parts[0], 0, 1)) : null;

        $displayFullName = trim(implode(' ', array_filter([
            $first,
            $middle ? $middle . '.' : null,
            $last,
        ])));

        return [
            'first_name' => $first,
            'middle_initial' => $middle,
            'last_name' => $last,
            'full_name' => $displayFullName !== '' ? $displayFullName : $fullName,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyToUserData(array $data): array
    {
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            if (empty($data['full_name'])) {
                $middleInitial = !empty($data['middle_initial'])
                    ? strtoupper(substr((string) $data['middle_initial'], 0, 1)) . '.'
                    : null;

                $data['full_name'] = trim(implode(' ', array_filter([
                    $data['first_name'],
                    $middleInitial,
                    $data['last_name'],
                ])));
            }

            return $data;
        }

        $fullName = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            return $data;
        }

        $split = self::splitFullName($fullName);

        $data['first_name'] = $data['first_name'] ?? $split['first_name'];
        $data['middle_initial'] = $data['middle_initial'] ?? $split['middle_initial'];
        $data['last_name'] = $data['last_name'] ?? $split['last_name'];
        $data['full_name'] = $split['full_name'] ?? $fullName;

        return $data;
    }
}
