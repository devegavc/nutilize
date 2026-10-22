<?php

namespace App\Support;

class RoleCatalog
{
    public const STUDENT = 'Student';

    public const FACULTY = 'Faculty';

    public const ITEM_OWNER = 'admin';

    /** @var array<string, string> chair role => program office department_name */
    public const PROGRAM_OFFICES = [
        'PC_MultimediaArts' => 'B Multimedia Arts',
        'PC_ComputerScience' => 'BS Computer Science',
        'PC_Architecture' => 'BS Architecture',
        'PC_CivilEngineering' => 'BS Civil Engineering',
        'PC_ComputerEngineering' => 'BS Computer Engineering',
        'PC_InformationTechnology' => 'BS Information Technology',
        'PC_Accountancy' => 'BS Accountancy',
        'PC_FinancialManagement' => 'BSBA Major in Financial Management',
        'PC_MarketingManagement' => 'BSBA Major in Marketing Management',
        'PC_ManagementAccounting' => 'BS Management Accounting',
        'PC_TourismManagement' => 'BS Tourism Management',
        'PC_Psychology' => 'BS Psychology',
        'PC_MedicalTechnology' => 'BS Medical Technology',
        'PC_Nursing' => 'BS Nursing',
    ];

    /** @var array<string, list<string>> */
    /**
     * Program Chair roles that exist but have no specified dean.
     * Do not assign PC_MultimediaArts to a dean until that mapping is confirmed.
     *
     * @var list<string>
     */
    public const CHAIRS_WITHOUT_DEAN = [
        'PC_MultimediaArts',
    ];

    public const DEAN_CHAIRS = [
        'Dean_SACE' => [
            'PC_ComputerScience',
            'PC_Architecture',
            'PC_CivilEngineering',
            'PC_ComputerEngineering',
            'PC_InformationTechnology',
        ],
        'Dean_SABM' => [
            'PC_Accountancy',
            'PC_FinancialManagement',
            'PC_MarketingManagement',
            'PC_ManagementAccounting',
            'PC_TourismManagement',
        ],
        'Dean_SAHS' => [
            'PC_Psychology',
            'PC_MedicalTechnology',
            'PC_Nursing',
        ],
    ];

    /** @var array<string, string> dean role => academic_programs.school_name */
    public const DEAN_SCHOOLS = [
        'Dean_SACE' => 'School of Architecture, Computing and Engineering',
        'Dean_SABM' => 'School of Accountancy, Business and Management',
        'Dean_SAHS' => 'School of Allied Health and Sciences',
    ];

    /** @var array<string, list<string>> office role => department names */
    public const OFFICE_NAMES = [
        'Gen_Ed' => ['general education'],
        'SDAO' => ['student development and activities office'],
        'Safety' => ['student discipline office', 'safety', 'discipline office'],
        'Security' => ['security'],
        'PFO' => ['physical facilities'],
    ];

    public static function isProgramChairCode(string $role): bool
    {
        return array_key_exists($role, self::PROGRAM_OFFICES);
    }

    public static function isDeanCode(string $role): bool
    {
        return array_key_exists($role, self::DEAN_CHAIRS);
    }

    public static function isOfficeRoleCode(string $role): bool
    {
        return array_key_exists($role, self::OFFICE_NAMES);
    }

    public static function deanForChair(string $chairRole): ?string
    {
        foreach (self::DEAN_CHAIRS as $dean => $chairs) {
            if (in_array($chairRole, $chairs, true)) {
                return $dean;
            }
        }

        return null;
    }

    public static function chairCodeForDepartment(?string $departmentName): ?string
    {
        $key = self::normalize($departmentName);
        if ($key === '' || $key === 'program chair') {
            return null;
        }

        foreach (self::PROGRAM_OFFICES as $code => $name) {
            if (self::normalize($name) === $key) {
                return $code;
            }
        }

        return null;
    }

    public static function officeRoleForDepartment(?string $departmentName, ?string $shortCode = null): ?string
    {
        $short = strtoupper(trim((string) $shortCode));
        if ($short === 'DO') {
            return 'Safety';
        }
        if ($short === 'GENED') {
            return 'Gen_Ed';
        }
        if ($short === 'SDAO') {
            return 'SDAO';
        }
        if ($short === 'SEC') {
            return 'Security';
        }

        $key = self::normalize($departmentName);
        if ($key === '') {
            return null;
        }

        foreach (self::OFFICE_NAMES as $role => $names) {
            if (in_array($key, $names, true)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Dean who may act for this program office, or null when the stored school disagrees.
     */
    public static function deanForProgramOffice(?string $departmentName, ?string $schoolName = null): ?string
    {
        $chair = self::chairCodeForDepartment($departmentName);
        if ($chair === null) {
            return null;
        }

        $dean = self::deanForChair($chair);
        if ($dean === null) {
            return null;
        }

        $school = trim((string) $schoolName);
        if ($school !== '' && strcasecmp($school, self::DEAN_SCHOOLS[$dean]) !== 0) {
            return null;
        }

        return $dean;
    }

    /**
     * @return array{role: string, needs_review: bool}
     */
    public static function migrationFor(string $role, ?string $departmentName, ?string $shortCode): array
    {
        $current = trim($role);
        $canonical = self::canonicalCode($current);
        if ($canonical !== null) {
            return ['role' => $canonical, 'needs_review' => false];
        }

        $lower = strtolower($current);

        if (in_array($lower, ['user', 'student'], true)) {
            return ['role' => self::STUDENT, 'needs_review' => false];
        }

        if ($lower === 'faculty') {
            return ['role' => self::FACULTY, 'needs_review' => false];
        }

        $short = strtoupper(trim((string) $shortCode));
        $departmentKey = self::normalize($departmentName);
        $isItemOwnerOffice = $short === 'IO' || $departmentKey === 'item owner';

        if ($isItemOwnerOffice && in_array($lower, ['admin', 'item_owner'], true)) {
            return ['role' => self::ITEM_OWNER, 'needs_review' => false];
        }

        if ($lower === 'pc_admin') {
            $chair = self::chairCodeForDepartment($departmentName);
            if ($chair !== null) {
                return ['role' => $chair, 'needs_review' => false];
            }

            return ['role' => $current, 'needs_review' => true];
        }

        if (in_array($lower, ['admin', 'pf_admin'], true)) {
            $officeRole = self::officeRoleForDepartment($departmentName, $shortCode);
            if ($officeRole !== null) {
                return ['role' => $officeRole, 'needs_review' => false];
            }

            return ['role' => $current, 'needs_review' => true];
        }

        return ['role' => $current !== '' ? $current : self::STUDENT, 'needs_review' => true];
    }

    /**
     * @return array{allowed: bool, on_behalf: bool, code: string}
     */
    public static function interpretProgramChairDecision(
        string $actorRole,
        int $actorUserId,
        ?int $actorOfficeId,
        int $approvalOfficeId,
        ?int $requesterUserId,
        string $requesterRole,
        bool $deanRequired,
        bool $alreadyDecided,
        ?string $chairRoleForOffice,
        ?string $deanRoleForOffice,
    ): array {
        if ($alreadyDecided) {
            return ['allowed' => false, 'on_behalf' => false, 'code' => 'already_decided'];
        }

        if ($chairRoleForOffice === null) {
            return ['allowed' => false, 'on_behalf' => false, 'code' => 'not_program_chair_stage'];
        }

        $actorIsRequester = $requesterUserId !== null && $actorUserId === $requesterUserId;
        if ($actorIsRequester && (self::isProgramChairCode($requesterRole) || strtolower($requesterRole) === 'pc_admin')) {
            return ['allowed' => false, 'on_behalf' => false, 'code' => 'self_approval'];
        }

        $actorIsDean = $deanRoleForOffice !== null && $actorRole === $deanRoleForOffice;
        $actorIsChair = $actorRole === $chairRoleForOffice && (int) $actorOfficeId === $approvalOfficeId;
        $actorIsLegacyChair = strtolower($actorRole) === 'pc_admin' && (int) $actorOfficeId === $approvalOfficeId;

        if ($deanRequired) {
            if ($actorIsDean) {
                return ['allowed' => true, 'on_behalf' => false, 'code' => 'dean_required'];
            }

            return ['allowed' => false, 'on_behalf' => false, 'code' => 'dean_required'];
        }

        if ($actorIsChair || $actorIsLegacyChair) {
            return ['allowed' => true, 'on_behalf' => false, 'code' => 'chair'];
        }

        if ($actorIsDean) {
            return ['allowed' => true, 'on_behalf' => true, 'code' => 'on_behalf'];
        }

        return ['allowed' => false, 'on_behalf' => false, 'code' => 'outside_school'];
    }

    /**
     * The office this role must use. Students and faculty are not office accounts.
     *
     * @return array{kind: 'none'|'department'|'short_code'|'unknown', department_name?: string, short_code?: string}
     */
    public static function linkedOffice(string $role): array
    {
        $role = self::canonicalCode($role) ?? $role;

        if (in_array($role, [self::STUDENT, self::FACULTY], true)) {
            return ['kind' => 'none'];
        }

        if (isset(self::PROGRAM_OFFICES[$role])) {
            return ['kind' => 'department', 'department_name' => self::PROGRAM_OFFICES[$role]];
        }

        $shortCode = match ($role) {
            'Dean_SACE' => 'DEAN_SACE',
            'Dean_SABM' => 'DEAN_SABM',
            'Dean_SAHS' => 'DEAN_SAHS',
            'Gen_Ed' => 'GENED',
            'SDAO' => 'SDAO',
            'Safety' => 'DO',
            'Security' => 'SEC',
            'PFO' => 'PF',
            default => null,
        };

        if ($shortCode !== null) {
            return ['kind' => 'short_code', 'short_code' => $shortCode];
        }

        return ['kind' => 'unknown'];
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::STUDENT => 'Student',
            self::FACULTY => 'Faculty',
            'Gen_Ed' => 'General Education',
            'SDAO' => 'SDAO',
            'Safety' => 'Safety',
            'Security' => 'Security',
            'PFO' => 'PFO',
            'Dean_SACE' => 'Dean of SACE',
            'Dean_SABM' => 'Dean of SABM',
            'Dean_SAHS' => 'Dean of SAHS',
            default => self::isProgramChairCode($role)
                ? 'Program Chair, '.self::PROGRAM_OFFICES[$role]
                : $role,
        };
    }

    /**
     * @return array<string, array<string, string>> group => [code => label]
     */
    public static function groupedAssignable(): array
    {
        $chairs = [];
        foreach (array_keys(self::PROGRAM_OFFICES) as $code) {
            $chairs[$code] = self::label($code);
        }

        $deans = [];
        foreach (array_keys(self::DEAN_CHAIRS) as $code) {
            $deans[$code] = self::label($code);
        }

        $offices = [];
        foreach (array_keys(self::OFFICE_NAMES) as $code) {
            $offices[$code] = self::label($code);
        }

        return [
            'General users' => [
                self::STUDENT => 'Student',
                self::FACULTY => 'Faculty',
            ],
            'Program Chairs' => $chairs,
            'Deans' => $deans,
            'Office roles' => $offices,
            'Service' => [
                'item_owner' => 'Item Owner',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        $values = [];
        foreach (self::groupedAssignable() as $options) {
            foreach (array_keys($options) as $code) {
                $values[] = $code;
            }
        }

        return $values;
    }

    public static function isStaffRole(string $role): bool
    {
        return self::isProgramChairCode($role)
            || self::isDeanCode($role)
            || self::isOfficeRoleCode($role)
            || in_array(strtolower($role), ['admin', 'pc_admin', 'pf_admin'], true);
    }

    public static function canonicalCode(string $role): ?string
    {
        $known = array_merge(
            [self::STUDENT, self::FACULTY],
            array_keys(self::PROGRAM_OFFICES),
            array_keys(self::DEAN_CHAIRS),
            array_keys(self::OFFICE_NAMES),
        );

        foreach ($known as $code) {
            if (strcasecmp($role, $code) === 0) {
                return $code;
            }
        }

        return null;
    }

    public static function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
