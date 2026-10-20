<?php

namespace App\Services;

use App\Models\User;
use App\Support\OpenReservationScope;
use App\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacultyApprovalService
{
    private static ?bool $ready = null;

    /** @var array<int, true>|null */
    private static ?array $gateOfficeIds = null;

    /** @var array<int, true> */
    private static array $held = [];

    /** @var array<int, true> */
    private static array $primed = [];

    public static function forget(): void
    {
        self::$ready = null;
        self::$gateOfficeIds = null;
        self::$held = [];
        self::$primed = [];
    }

    public static function ready(): bool
    {
        if (self::$ready === null) {
            self::$ready = Schema::hasTable('reservations')
                && Schema::hasColumn('reservations', 'faculty_approved_at')
                && Schema::hasColumn('reservations', 'faculty_rejected_at');
        }

        return self::$ready;
    }

    /**
     * Student requests stay out of the program-chair and General Education steps
     * until a faculty member has approved them.
     */
    public static function blocksOffice(int $reservationId, int $officeId): bool
    {
        if ($reservationId <= 0 || $officeId <= 0 || !self::ready() || !self::isGateOffice($officeId)) {
            return false;
        }

        self::prime([$reservationId]);

        return isset(self::$held[$reservationId]);
    }

    /**
     * @param  array<int, int>  $reservationIds
     * @return array<int, int>
     */
    public static function withoutFacultyHold(array $reservationIds, int $officeId): array
    {
        if (!self::isGateOffice($officeId) || !self::ready()) {
            return array_values(array_map('intval', $reservationIds));
        }

        self::prime($reservationIds);

        return array_values(array_filter(
            array_map('intval', $reservationIds),
            static fn (int $id): bool => !isset(self::$held[$id])
        ));
    }

    /**
     * @param  array<int, int>  $reservationIds
     */
    public static function prime(array $reservationIds): void
    {
        $reservationIds = array_values(array_unique(array_filter(array_map('intval', $reservationIds))));
        $missing = array_values(array_filter(
            $reservationIds,
            static fn (int $id): bool => !isset(self::$primed[$id])
        ));

        foreach ($reservationIds as $id) {
            self::$primed[$id] = true;
        }

        if ($missing === [] || !self::ready()) {
            return;
        }

        $rows = DB::table('reservations as r')
            ->join('users as u', 'u.user_id', '=', 'r.user_id')
            ->whereIn('r.reservation_id', $missing)
            ->get(['r.reservation_id', 'u.role', 'r.faculty_approved_at', 'r.faculty_rejected_at']);

        foreach ($rows as $row) {
            $role = strtolower(trim((string) $row->role));
            $student = $role === strtolower(RoleCatalog::STUDENT) || in_array($role, ['user', 'student'], true);
            $approved = $row->faculty_approved_at !== null && $row->faculty_rejected_at === null;
            if ($student && !$approved) {
                self::$held[(int) $row->reservation_id] = true;
            }
        }

        if (self::$held !== [] && $missing !== []) {
            $alreadyApproved = DB::table('reservation_approvals as ra')
                ->join('users as actor', 'actor.user_id', '=', 'ra.approved_by_user_id')
                ->whereIn('ra.reservation_id', array_keys(self::$held))
                ->whereRaw("LOWER(actor.role) = 'faculty'")
                ->whereNotNull('ra.approved_at')
                ->whereRaw("LOWER(COALESCE(ra.status, '')) = 'approved'")
                ->pluck('ra.reservation_id');

            foreach ($alreadyApproved as $reservationId) {
                unset(self::$held[(int) $reservationId]);
            }
        }

        $heldNow = 0;
        foreach ($missing as $id) {
            if (isset(self::$held[$id])) {
                $heldNow++;
            }
        }

        // #region agent log
        try {
            file_put_contents(base_path('debug-28e049.log'), json_encode([
                'sessionId' => '28e049',
                'runId' => 'faculty-gate',
                'hypothesisId' => 'H1',
                'location' => 'FacultyApprovalService.php:prime',
                'message' => 'faculty hold evaluated',
                'data' => [
                    'checked' => count($missing),
                    'held' => $heldNow,
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
        }
        // #endregion
    }

    public static function facultyMatchesReservation(User $faculty, int $reservationId): bool
    {
        if (!$faculty->isFaculty() || $reservationId <= 0) {
            return false;
        }

        $programIds = self::programIdsForFaculty($faculty);
        if ($programIds === []) {
            return false;
        }

        return DB::table('reservations as r')
            ->join('users as u', 'u.user_id', '=', 'r.user_id')
            ->where('r.reservation_id', $reservationId)
            ->whereIn('u.program_id', $programIds)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public static function programIdsForFaculty(User $faculty): array
    {
        $programIds = [];
        if (!is_null($faculty->program_id)) {
            $programIds[] = (int) $faculty->program_id;
        }

        if (!is_null($faculty->office_id) && Schema::hasTable('academic_programs')) {
            $fromOffice = DB::table('academic_programs')
                ->where('office_id', (int) $faculty->office_id)
                ->pluck('program_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $programIds = array_merge($programIds, $fromOffice);
        }

        return array_values(array_unique(array_filter($programIds)));
    }

    private static function isGateOffice(int $officeId): bool
    {
        if (self::$gateOfficeIds === null) {
            self::$gateOfficeIds = [];
            $rows = DB::table('offices')->get(['office_id', 'short_code', 'department_name']);
            foreach ($rows as $row) {
                $short = strtoupper(trim((string) ($row->short_code ?? '')));
                $name = RoleCatalog::normalize((string) ($row->department_name ?? ''));
                if (
                    $short === 'PC'
                    || $short === 'GENED'
                    || $name === 'general education'
                    || RoleCatalog::chairCodeForDepartment($row->department_name) !== null
                ) {
                    self::$gateOfficeIds[(int) $row->office_id] = true;
                }
            }
        }

        return isset(self::$gateOfficeIds[$officeId]);
    }

    public static function openRequestsForFaculty(User $faculty)
    {
        $programIds = self::programIdsForFaculty($faculty);
        if ($programIds === [] || !self::ready()) {
            return collect();
        }

        $query = DB::table('reservations as r')
            ->join('users as u', 'u.user_id', '=', 'r.user_id')
            ->whereIn('u.program_id', $programIds)
            ->whereRaw("LOWER(u.role) IN ('student', 'user', ?)", [strtolower(RoleCatalog::STUDENT)])
            ->whereNull('r.faculty_approved_at')
            ->whereNull('r.faculty_rejected_at')
            ->orderByDesc('r.created_at')
            ->limit(40)
            ->select([
                'r.reservation_id',
                'r.activity_name',
                'r.created_at',
                'r.Date_of_Activity',
                'u.full_name',
                'u.username',
                'u.first_name',
                'u.last_name',
            ]);
        OpenReservationScope::apply($query, 'r.overall_status');

        return $query->get();
    }
}
