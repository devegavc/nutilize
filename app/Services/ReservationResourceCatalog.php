<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationResourceCatalog
{
    /**
     * The mobile app saves one reservation row per room or item in a single submit.
     * Rows from that submit share a user, activity name, and a short create time.
     */
    public const SUBMISSION_WINDOW_SECONDS = 90;

    /**
     * Every room and equipment row linked to these reservations.
     * A room's date is the reservation it was saved on. Rooms saved in one submit
     * are listed together even when each one has its own reservation id.
     *
     * @param  array<int, int>  $reservationIds
     * @return array<int, array<string, mixed>>
     */
    public static function forReservations(array $reservationIds): array
    {
        $originalIds = array_values(array_unique(array_filter(array_map('intval', $reservationIds))));
        $members = self::submissionMembers($originalIds);
        $reservationIds = [];
        foreach ($members as $group) {
            foreach ($group as $memberId) {
                $reservationIds[$memberId] = $memberId;
            }
        }
        $reservationIds = array_values($reservationIds);
        $summaries = [];
        foreach ($reservationIds as $reservationId) {
            $summaries[$reservationId] = self::emptySummary();
        }

        if ($reservationIds === []) {
            return $summaries;
        }

        $detailRows = DB::table('reservation_details as details')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'details.reservation_id')
            ->leftJoin('reservation_rooms as reservationRooms', 'reservationRooms.reservation_rooms_id', '=', 'details.reservation_rooms_id')
            ->leftJoin('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->leftJoin('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'details.reservation_items_id')
            ->leftJoin('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->whereIn('details.reservation_id', $reservationIds)
            ->orderBy('details.detail_id')
            ->get([
                'details.detail_id',
                'details.reservation_id',
                'details.quantity',
                'details.reservation_rooms_id',
                'details.reservation_items_id',
                'reservations.Date_of_Activity',
                'reservations.Start_of_activity',
                'reservations.End_of_Activity',
                'rooms.room_id',
                'rooms.room_number',
                'rooms.room_type',
                'rooms.maintenance_status as room_maintenance',
                'rooms.availability_status as room_available',
                'items.item_id',
                'items.item_name',
                'items.maintenance_status as item_maintenance',
                'items.availability_status as item_available',
            ]);

        $seenRoomLinks = [];
        $seenItemLinks = [];

        foreach ($detailRows as $row) {
            $reservationId = (int) $row->reservation_id;
            $resource = self::resourceFromDetail($row);
            if ($resource === null) {
                continue;
            }

            if ($resource['type'] === 'room' && !empty($row->reservation_rooms_id)) {
                $seenRoomLinks[$reservationId][(int) $row->reservation_rooms_id] = true;
            }
            if ($resource['type'] === 'item' && !empty($row->reservation_items_id)) {
                $seenItemLinks[$reservationId][(int) $row->reservation_items_id] = true;
            }

            $summaries[$reservationId]['resources'][] = $resource;
        }

        $linkedRooms = DB::table('reservation_rooms as reservationRooms')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'reservationRooms.reservation_id')
            ->join('rooms as rooms', 'rooms.room_id', '=', 'reservationRooms.room_id')
            ->whereIn('reservationRooms.reservation_id', $reservationIds)
            ->orderBy('reservationRooms.reservation_rooms_id')
            ->get([
                'reservationRooms.reservation_rooms_id',
                'reservationRooms.reservation_id',
                'reservations.Date_of_Activity',
                'reservations.Start_of_activity',
                'reservations.End_of_Activity',
                'rooms.room_number',
                'rooms.room_type',
                'rooms.maintenance_status as room_maintenance',
                'rooms.availability_status as room_available',
            ]);

        foreach ($linkedRooms as $row) {
            $reservationId = (int) $row->reservation_id;
            $linkId = (int) $row->reservation_rooms_id;
            if (isset($seenRoomLinks[$reservationId][$linkId])) {
                continue;
            }

            $summaries[$reservationId]['resources'][] = self::makeResource(
                'room',
                self::roomName($row->room_number),
                trim((string) ($row->room_type ?? '')),
                1,
                $row->Date_of_Activity,
                $row->Start_of_activity,
                $row->End_of_Activity,
                self::statusLabel($row->room_maintenance, $row->room_available),
            );
        }

        $linkedItems = DB::table('reservation_items as reservationItems')
            ->join('reservations as reservations', 'reservations.reservation_id', '=', 'reservationItems.reservation_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->whereIn('reservationItems.reservation_id', $reservationIds)
            ->orderBy('reservationItems.reservation_items_id')
            ->get([
                'reservationItems.reservation_items_id',
                'reservationItems.reservation_id',
                'reservations.Date_of_Activity',
                'reservations.Start_of_activity',
                'reservations.End_of_Activity',
                'items.item_name',
                'items.maintenance_status as item_maintenance',
                'items.availability_status as item_available',
            ]);

        foreach ($linkedItems as $row) {
            $reservationId = (int) $row->reservation_id;
            $linkId = (int) $row->reservation_items_id;
            if (isset($seenItemLinks[$reservationId][$linkId])) {
                continue;
            }

            $name = trim((string) ($row->item_name ?? ''));
            $summaries[$reservationId]['resources'][] = self::makeResource(
                'item',
                $name !== '' ? $name : 'Equipment',
                '',
                1,
                $row->Date_of_Activity,
                $row->Start_of_activity,
                $row->End_of_Activity,
                self::statusLabel($row->item_maintenance, $row->item_available),
            );
        }

        $loaded = [];
        foreach ($summaries as $reservationId => $summary) {
            $loaded[$reservationId] = $summary['resources'];
        }

        $result = [];
        foreach ($originalIds as $reservationId) {
            $group = $members[$reservationId] ?? [$reservationId];
            $resources = [];
            foreach ($group as $memberId) {
                foreach ($loaded[$memberId] ?? [] as $resource) {
                    $resources[] = $resource;
                }
            }

            $merged = self::finalize(self::combineResources($resources));
            $merged['reservation_ids'] = array_values(array_unique(array_map('intval', $group)));
            sort($merged['reservation_ids']);
            $result[$reservationId] = $merged;
        }

        return $result;
    }

    /**
     * Reservation ids saved in the same submit as this one, including itself.
     *
     * @return list<int>
     */
    public static function submissionIds(int $reservationId): array
    {
        $members = self::submissionMembers([$reservationId]);

        return $members[$reservationId] ?? [$reservationId];
    }

    /**
     * @param  list<object|array<string, mixed>>  $rows
     * @return list<list<int>>
     */
    public static function clusterRows(array $rows): array
    {
        usort($rows, function (object|array $left, object|array $right): int {
            $byUser = self::rowInt($left, 'user_id') <=> self::rowInt($right, 'user_id');
            if ($byUser !== 0) {
                return $byUser;
            }

            $byActivity = strcmp(self::rowActivity($left), self::rowActivity($right));
            if ($byActivity !== 0) {
                return $byActivity;
            }

            return strcmp(self::rowCreated($left), self::rowCreated($right));
        });

        $clusters = [];
        $current = [];
        $anchor = null;

        foreach ($rows as $row) {
            $id = self::rowInt($row, 'reservation_id');
            if ($id <= 0) {
                continue;
            }

            if ($anchor !== null && self::sameSubmission($anchor, $row)) {
                $current[] = $id;
                continue;
            }

            if ($current !== []) {
                $clusters[] = $current;
            }

            $current = [$id];
            $anchor = $row;
        }

        if ($current !== []) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    /**
     * @param  list<string>  $dateKeys
     */
    public static function dateLabel(array $dateKeys, int $scheduleCount): string
    {
        $unique = array_values(array_unique(array_filter($dateKeys)));
        sort($unique);

        if ($unique === []) {
            return 'N/A';
        }

        $first = Carbon::parse($unique[0]);
        $last = Carbon::parse($unique[count($unique) - 1]);

        if (count($unique) === 1) {
            return $first->format('F j, Y');
        }

        if ($first->year === $last->year && $first->month === $last->month) {
            return $first->format('F j').'–'.$last->format('j, Y');
        }

        if ($first->year === $last->year) {
            return $first->format('F j').'–'.$last->format('F j, Y');
        }

        $count = max($scheduleCount, count($unique));

        return 'Multiple dates ('.$count.' schedules)';
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     * @return array<string, mixed>
     */
    private static function finalize(array $resources): array
    {
        $rooms = array_values(array_filter($resources, fn (array $resource): bool => $resource['type'] === 'room'));
        $items = array_values(array_filter($resources, fn (array $resource): bool => $resource['type'] === 'item'));
        $dateKeys = array_map(fn (array $resource): string => (string) ($resource['date_key'] ?? ''), $resources);
        $scheduleCount = count(array_filter($dateKeys));
        $distinctDates = count(array_unique(array_filter($dateKeys)));

        return [
            'resources' => $resources,
            'rooms' => $rooms,
            'items' => $items,
            'room_count' => count($rooms),
            'item_count' => count($items),
            'schedule_count' => $scheduleCount,
            'distinct_dates' => $distinctDates,
            'date_label' => self::dateLabel($dateKeys, $scheduleCount),
            'multiple_schedules' => $distinctDates > 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySummary(): array
    {
        return self::finalize([]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resourceFromDetail(object $row): ?array
    {
        $roomNumber = trim((string) ($row->room_number ?? ''));
        if ($roomNumber !== '') {
            return self::makeResource(
                'room',
                self::roomName($roomNumber),
                trim((string) ($row->room_type ?? '')),
                max(1, (int) ($row->quantity ?? 1)),
                $row->Date_of_Activity,
                $row->Start_of_activity,
                $row->End_of_Activity,
                self::statusLabel($row->room_maintenance, $row->room_available),
            );
        }

        if ($row->reservation_items_id === null && trim((string) ($row->item_name ?? '')) === '') {
            return null;
        }

        $name = trim((string) ($row->item_name ?? ''));

        return self::makeResource(
            'item',
            $name !== '' ? $name : 'Equipment',
            '',
            max(1, (int) ($row->quantity ?? 1)),
            $row->Date_of_Activity,
            $row->Start_of_activity,
            $row->End_of_Activity,
            self::statusLabel($row->item_maintenance, $row->item_available),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function makeResource(
        string $type,
        string $name,
        string $location,
        int $quantity,
        mixed $dateOfActivity,
        mixed $startsAt,
        mixed $endsAt,
        ?string $status,
    ): array {
        $start = self::moment($startsAt) ?? self::moment($dateOfActivity);
        $end = self::moment($endsAt);
        $dateKey = $start?->format('Y-m-d') ?? '';
        $dateLabel = $start ? $start->format('F j, Y') : 'N/A';
        $startLabel = $start ? $start->format('g:i A') : 'N/A';
        $endLabel = $end ? $end->format('g:i A') : 'N/A';
        $schedule = $dateLabel;
        if ($start) {
            $schedule .= ' · '.$startLabel.($end ? '–'.$endLabel : '');
        }

        return [
            'type' => $type,
            'label' => $name,
            'name' => $name,
            'location' => $location,
            'quantity' => $quantity,
            'unit' => '',
            'date' => $dateLabel,
            'date_key' => $dateKey,
            'start_time' => $startLabel,
            'end_time' => $endLabel,
            'schedule' => $schedule,
            'status' => $status,
        ];
    }

    private static function roomName(string $roomNumber): string
    {
        $roomNumber = trim($roomNumber);
        if ($roomNumber === '') {
            return 'Room';
        }

        if (preg_match('/^room\b/i', $roomNumber)) {
            return $roomNumber;
        }

        return 'Room '.$roomNumber;
    }

    private static function moment(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function statusLabel(mixed $maintenance, mixed $available): ?string
    {
        if (self::isTrue($maintenance)) {
            return 'Under maintenance';
        }

        if ($available === null || $available === '') {
            return null;
        }

        return self::isTrue($available) ? 'Available' : 'Unavailable';
    }

    private static function isTrue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    /**
     * @param  list<int>  $reservationIds
     * @return array<int, list<int>>
     */
    private static function submissionMembers(array $reservationIds): array
    {
        $map = [];
        foreach ($reservationIds as $reservationId) {
            $map[$reservationId] = [$reservationId];
        }

        if ($reservationIds === []) {
            return $map;
        }

        $seeds = DB::table('reservations')
            ->whereIn('reservation_id', $reservationIds)
            ->get(['reservation_id', 'user_id', 'activity_name', 'created_at']);

        if ($seeds->isEmpty()) {
            return $map;
        }

        $earliest = null;
        $latest = null;
        foreach ($seeds as $seed) {
            $created = strtotime((string) $seed->created_at);
            if ($created === false) {
                continue;
            }

            $earliest = $earliest === null ? $created : min($earliest, $created);
            $latest = $latest === null ? $created : max($latest, $created);
        }

        if ($earliest === null || $latest === null) {
            return $map;
        }

        $userIds = $seeds->pluck('user_id')->filter(fn ($id) => $id !== null && $id !== '')->unique()->values()->all();
        $candidates = DB::table('reservations')
            ->whereIn('user_id', $userIds !== [] ? $userIds : [-1])
            ->where('created_at', '>=', date('Y-m-d H:i:s', $earliest - self::SUBMISSION_WINDOW_SECONDS))
            ->where('created_at', '<=', date('Y-m-d H:i:s', $latest + self::SUBMISSION_WINDOW_SECONDS))
            ->get(['reservation_id', 'user_id', 'activity_name', 'created_at']);

        $byId = [];
        foreach (self::clusterRows($candidates->all()) as $cluster) {
            foreach ($cluster as $memberId) {
                $byId[$memberId] = $cluster;
            }
        }

        foreach ($reservationIds as $reservationId) {
            $map[$reservationId] = $byId[$reservationId] ?? [$reservationId];
        }

        return $map;
    }

    /**
     * Identical lines from a split submit become one line. Different rooms and dates stay separate.
     *
     * @param  list<array<string, mixed>>  $resources
     * @return list<array<string, mixed>>
     */
    private static function combineResources(array $resources): array
    {
        $combined = [];
        foreach ($resources as $resource) {
            $key = implode('|', [
                (string) ($resource['type'] ?? ''),
                mb_strtolower(trim((string) ($resource['name'] ?? ''))),
                (string) ($resource['date_key'] ?? ''),
                (string) ($resource['start_time'] ?? ''),
                (string) ($resource['end_time'] ?? ''),
                mb_strtolower(trim((string) ($resource['location'] ?? ''))),
            ]);

            if (!isset($combined[$key])) {
                $combined[$key] = $resource;
                continue;
            }

            $combined[$key]['quantity'] = (int) ($combined[$key]['quantity'] ?? 1) + (int) ($resource['quantity'] ?? 1);
        }

        return array_values($combined);
    }

    private static function sameSubmission(object|array $anchor, object|array $row): bool
    {
        if (self::rowInt($anchor, 'user_id') !== self::rowInt($row, 'user_id')) {
            return false;
        }

        if (self::rowActivity($anchor) !== self::rowActivity($row)) {
            return false;
        }

        $start = strtotime(self::rowCreated($anchor));
        $created = strtotime(self::rowCreated($row));
        if ($start === false || $created === false) {
            return false;
        }

        return abs($created - $start) <= self::SUBMISSION_WINDOW_SECONDS;
    }

    private static function rowInt(object|array $row, string $key): int
    {
        $value = is_array($row) ? ($row[$key] ?? 0) : ($row->{$key} ?? 0);

        return (int) $value;
    }

    private static function rowActivity(object|array $row): string
    {
        $value = is_array($row) ? ($row['activity_name'] ?? '') : ($row->activity_name ?? '');

        return mb_strtolower(trim((string) $value));
    }

    private static function rowCreated(object|array $row): string
    {
        $value = is_array($row) ? ($row['created_at'] ?? '') : ($row->created_at ?? '');

        return trim((string) $value);
    }
}
