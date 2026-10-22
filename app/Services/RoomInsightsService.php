<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Monthly room usage, type demand, idle rooms, and condition.
 * A room is one space, so these figures stay separate from equipment procurement.
 */
class RoomInsightsService
{
    private const CANCELLED_STATUSES = ['cancelled', 'canceled'];

    /**
     * @return array<string, mixed>
     */
    public static function build(DateTimeInterface $since, DateTimeInterface $until): array
    {
        if (! Schema::hasTable('rooms') || ! Schema::hasTable('reservation_rooms') || ! Schema::hasTable('reservation_details') || ! Schema::hasTable('reservations')) {
            return [];
        }

        $daysInMonth = max(1, Carbon::parse($since)->daysInMonth);
        $rooms = self::loadRooms();
        $bookingsKnown = true;
        $usageRows = [];

        try {
            $usageRows = self::loadUsageRows($since, $until);
        } catch (Throwable) {
            $bookingsKnown = false;
        }

        $reservationIds = [];
        $byRoom = [];
        $typesByReservation = [];

        foreach ($usageRows as $row) {
            $reservationId = (int) $row->reservation_id;
            $roomId = (int) $row->room_id;
            $reservationIds[$reservationId] = true;

            $byRoom[$roomId] ??= [
                'room_number' => self::roomLabel($row->room_number ?? null),
                'room_type' => self::roomType($row->room_type ?? null),
                'reservations' => [],
                'users' => [],
                'days' => [],
            ];
            $byRoom[$roomId]['reservations'][$reservationId] = true;
            $byRoom[$roomId]['users'][(int) $row->user_id] = true;

            $day = self::activityDay($row->activity_at ?? null, $row->created_at ?? null);
            if ($day !== null) {
                $byRoom[$roomId]['days'][$day] = true;
            }

            $typesByReservation[$reservationId][self::roomType($row->room_type ?? null)] = true;
        }

        $mostUsed = [];
        $typeCounts = [];
        $useTotal = 0;

        foreach ($byRoom as $stats) {
            $bookings = count($stats['reservations']);
            $useTotal += $bookings;
            $activityDays = $bookingsKnown ? count($stats['days']) : null;
            $mostUsed[] = [
                'room_number' => $stats['room_number'],
                'room_type' => $stats['room_type'],
                'bookings' => $bookings,
                'activity_days' => $activityDays,
                'occupancy' => self::occupancyPercent($activityDays, $daysInMonth),
                'requesters' => count($stats['users']),
            ];
        }

        foreach ($typesByReservation as $types) {
            foreach (array_keys($types) as $type) {
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            }
        }

        usort($mostUsed, static fn (array $a, array $b): int => [$b['bookings'], $a['room_number']] <=> [$a['bookings'], $b['room_number']]);

        $shareItems = [];
        foreach (array_slice($mostUsed, 0, 8) as $room) {
            $shareItems[] = [
                'room_number' => $room['room_number'],
                'booking_count' => $room['bookings'],
                'share_percent' => $useTotal > 0 ? round(($room['bookings'] / $useTotal) * 100, 1) : 0,
            ];
        }

        $typeDemand = [];
        $typeTotal = array_sum($typeCounts);
        foreach ($typeCounts as $type => $count) {
            $typeDemand[] = [
                'room_type' => $type,
                'booking_count' => $count,
                'share_percent' => $typeTotal > 0 ? round(($count / $typeTotal) * 100, 1) : 0,
            ];
        }
        usort($typeDemand, static fn (array $a, array $b): int => [$b['booking_count'], $a['room_type']] <=> [$a['booking_count'], $b['room_type']]);

        $bookedRoomIds = array_fill_keys(array_keys($byRoom), true);
        $idleRooms = [];
        foreach ($rooms as $room) {
            if (isset($bookedRoomIds[$room['room_id']])) {
                continue;
            }
            $idleRooms[] = [
                'room_number' => $room['room_number'],
                'room_type' => $room['room_type'],
            ];
        }

        [$openJobs, $incidents, $conditionRows, $underMaintenance] = self::loadCondition($rooms, $since, $until);

        return [
            'roomBookings' => $bookingsKnown ? count($reservationIds) : null,
            'roomsUsed' => $bookingsKnown ? count($byRoom) : null,
            'roomsUnderMaintenance' => $underMaintenance,
            'idleRoomCount' => $bookingsKnown ? count($idleRooms) : null,
            'idleRooms' => $bookingsKnown ? $idleRooms : [],
            'roomShareItems' => $shareItems,
            'roomTypeDemand' => $typeDemand,
            'mostUsedRooms' => $mostUsed,
            'roomCondition' => $conditionRows,
            'openJobsKnown' => $openJobs !== null,
            'incidentsKnown' => $incidents !== null,
        ];
    }

    /**
     * @return array<int, int>|null
     */
    public static function monthlyTrend(DateTimeInterface $endMonth): ?array
    {
        if (! self::canQueryBookings()) {
            return null;
        }

        $end = Carbon::parse($endMonth)->startOfMonth();
        $start = $end->copy()->subMonths(11);
        $countsByMonth = [];

        try {
            foreach (self::loadUsageRows($start->copy()->startOfMonth(), $end->copy()->endOfMonth()) as $row) {
                $monthKey = Carbon::parse((string) $row->created_at)->format('Y-m');
                $countsByMonth[$monthKey][(int) $row->reservation_id] = true;
            }
        } catch (Throwable) {
            return null;
        }

        $counts = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $counts[] = count($countsByMonth[$cursor->format('Y-m')] ?? []);
            $cursor->addMonth();
        }

        return $counts;
    }

    public static function occupancyPercent(?int $activityDays, int $daysInMonth): ?float
    {
        if ($activityDays === null || $daysInMonth <= 0) {
            return null;
        }

        return min(100, round(($activityDays / $daysInMonth) * 100, 1));
    }

    public static function conditionLabel(bool $available, bool $maintenanceFlag, int $openJobs): string
    {
        if (! $available) {
            return 'Unavailable';
        }

        if ($maintenanceFlag || $openJobs > 0) {
            return 'Under maintenance';
        }

        return 'Available';
    }

    public static function suggestedAction(string $condition, ?int $openJobs, ?int $incidents): string
    {
        $unresolved = ($openJobs ?? 0) > 0 || $condition === 'Under maintenance';
        $repeated = $incidents !== null && $incidents >= 2;

        if ($unresolved || $repeated) {
            return 'Repair';
        }

        if ($condition === 'Unavailable') {
            return 'Review availability';
        }

        return '—';
    }

    private static function canQueryBookings(): bool
    {
        return Schema::hasTable('rooms')
            && Schema::hasTable('reservation_rooms')
            && Schema::hasTable('reservation_details')
            && Schema::hasTable('reservations');
    }

    /**
     * @return array<int, object>
     */
    private static function loadUsageRows(DateTimeInterface $since, DateTimeInterface $until): array
    {
        $activityColumn = self::activityColumn();
        $select = [
            'rooms.room_id',
            'rooms.room_number',
            'rooms.room_type',
            'reservations.reservation_id',
            'reservations.user_id',
            'reservations.created_at',
        ];

        if ($activityColumn !== null) {
            $select[] = 'reservations.'.$activityColumn.' as activity_at';
        }

        return DB::table('reservation_details as details')
            ->join('reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->join('reservation_rooms as bookedRooms', 'bookedRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->join('rooms', 'rooms.room_id', '=', 'bookedRooms.room_id')
            ->whereNotNull('details.reservation_rooms_id')
            ->whereBetween('reservations.created_at', [$since, $until])
            ->whereNotIn(DB::raw("LOWER(TRIM(COALESCE(reservations.overall_status, '')))"), self::CANCELLED_STATUSES)
            ->select($select)
            ->get()
            ->all();
    }

    /**
     * @return array<int, array{room_id:int, room_number:string, room_type:string, available:bool, maintenance_flag:bool}>
     */
    private static function loadRooms(): array
    {
        $rooms = [];

        foreach (DB::table('rooms')->select(['room_id', 'room_number', 'room_type', 'availability_status', 'maintenance_status'])->orderBy('room_number')->get() as $row) {
            $rooms[(int) $row->room_id] = [
                'room_id' => (int) $row->room_id,
                'room_number' => self::roomLabel($row->room_number ?? null),
                'room_type' => self::roomType($row->room_type ?? null),
                'available' => self::asBool($row->availability_status ?? null, true),
                'maintenance_flag' => self::asBool($row->maintenance_status ?? null, false),
            ];
        }

        return $rooms;
    }

    /**
     * @param  array<int, array{room_id:int, room_number:string, room_type:string, available:bool, maintenance_flag:bool}>  $rooms
     * @return array{0:?array<int, int>, 1:?array<int, int>, 2:array<int, array<string, mixed>>, 3:?int}
     */
    private static function loadCondition(array $rooms, DateTimeInterface $since, DateTimeInterface $until): array
    {
        $openJobs = [];
        $openJobsKnown = true;

        try {
            if (Schema::hasTable('maintenance') && Schema::hasColumn('maintenance', 'room_id')) {
                $query = DB::table('maintenance')->whereNotNull('room_id');
                if (Schema::hasColumn('maintenance', 'date_resolved')) {
                    $query->whereNull('date_resolved');
                }
                foreach ($query->selectRaw('room_id, COUNT(*) as open_jobs')->groupBy('room_id')->get() as $row) {
                    $openJobs[(int) $row->room_id] = (int) $row->open_jobs;
                }
            }
        } catch (Throwable) {
            $openJobsKnown = false;
            $openJobs = [];
        }

        $incidents = [];
        $incidentsKnown = true;

        try {
            if (Schema::hasTable('maintenance') && Schema::hasColumn('maintenance', 'room_id') && Schema::hasColumn('maintenance', 'created_at')) {
                foreach (DB::table('maintenance')->whereNotNull('room_id')->whereBetween('created_at', [$since, $until])->selectRaw('room_id, COUNT(*) as incidents')->groupBy('room_id')->get() as $row) {
                    $incidents[(int) $row->room_id] = ($incidents[(int) $row->room_id] ?? 0) + (int) $row->incidents;
                }
            }

            if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'room_id')) {
                $reportDate = Schema::hasColumn('reports', 'generated_at') ? 'generated_at' : (Schema::hasColumn('reports', 'created_at') ? 'created_at' : null);
                if ($reportDate !== null) {
                    foreach (DB::table('reports')->whereNotNull('room_id')->whereBetween($reportDate, [$since, $until])->selectRaw('room_id, COUNT(*) as incidents')->groupBy('room_id')->get() as $row) {
                        $incidents[(int) $row->room_id] = ($incidents[(int) $row->room_id] ?? 0) + (int) $row->incidents;
                    }
                }
            }
        } catch (Throwable) {
            $incidentsKnown = false;
            $incidents = [];
        }

        $rows = [];
        $underMaintenance = 0;

        foreach ($rooms as $roomId => $room) {
            $openJobCount = $openJobsKnown ? (int) ($openJobs[$roomId] ?? 0) : null;
            $incidentCount = $incidentsKnown ? (int) ($incidents[$roomId] ?? 0) : null;
            $condition = self::conditionLabel(
                $room['available'],
                $room['maintenance_flag'],
                $openJobCount ?? 0
            );

            if ($condition === 'Under maintenance') {
                $underMaintenance++;
            }

            $needsRow = $condition !== 'Available'
                || ($openJobCount !== null && $openJobCount > 0)
                || ($incidentCount !== null && $incidentCount > 0);

            if (! $needsRow) {
                continue;
            }

            $rows[] = [
                'room_number' => $room['room_number'],
                'condition' => $condition,
                'open_jobs' => $openJobCount,
                'incidents' => $incidentCount,
                'action' => self::suggestedAction($condition, $openJobCount, $incidentCount),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $rank = ['Under maintenance' => 0, 'Unavailable' => 1, 'Available' => 2];

            return [
                $rank[$a['condition']] ?? 3,
                -1 * (int) ($b['incidents'] ?? 0),
                $a['room_number'],
            ] <=> [
                $rank[$b['condition']] ?? 3,
                -1 * (int) ($a['incidents'] ?? 0),
                $b['room_number'],
            ];
        });

        return [
            $openJobsKnown ? $openJobs : null,
            $incidentsKnown ? $incidents : null,
            $rows,
            $underMaintenance,
        ];
    }

    private static function activityColumn(): ?string
    {
        if (! Schema::hasTable('reservations')) {
            return null;
        }

        foreach (Schema::getColumnListing('reservations') as $column) {
            if (strcasecmp($column, 'Date_of_Activity') === 0) {
                return $column;
            }
        }

        return null;
    }

    private static function activityDay(mixed $activityAt, mixed $createdAt): ?string
    {
        $value = $activityAt ?: $createdAt;
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private static function roomLabel(mixed $roomNumber): string
    {
        $label = trim((string) $roomNumber);

        return $label !== '' ? $label : 'Room';
    }

    private static function roomType(mixed $roomType): string
    {
        $type = trim((string) $roomType);

        return $type !== '' ? $type : 'Unspecified';
    }

    private static function asBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 't', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($text, ['0', 'f', 'false', 'no'], true)) {
            return false;
        }

        return $default;
    }
}
