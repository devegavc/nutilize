<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationResourceCatalog
{
    /**
     * Every room and equipment row linked to these reservations.
     * Institutional events store each room's own date on institutional_event_venues.
     * Older reservations keep the single schedule on the reservation row.
     *
     * @param  array<int, int>  $reservationIds
     * @return array<int, array<string, mixed>>
     */
    public static function forReservations(array $reservationIds): array
    {
        $reservationIds = array_values(array_unique(array_filter(array_map('intval', $reservationIds))));
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
        $coveredRoomIds = [];

        if (self::institutionalTablesExist()) {
            self::appendInstitutionalResources(
                $reservationIds,
                $summaries,
                $seenItemLinks,
                $coveredRoomIds,
            );
        }

        foreach ($detailRows as $row) {
            $reservationId = (int) $row->reservation_id;
            $roomId = (int) ($row->room_id ?? 0);
            if ($roomId > 0 && isset($coveredRoomIds[$reservationId][$roomId])) {
                if (!empty($row->reservation_rooms_id)) {
                    $seenRoomLinks[$reservationId][(int) $row->reservation_rooms_id] = true;
                }
                continue;
            }

            $itemLinkId = (int) ($row->reservation_items_id ?? 0);
            if ($itemLinkId > 0 && isset($seenItemLinks[$reservationId][$itemLinkId])) {
                continue;
            }

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
                'rooms.room_id',
                'rooms.room_number',
                'rooms.room_type',
                'rooms.maintenance_status as room_maintenance',
                'rooms.availability_status as room_available',
            ]);

        foreach ($linkedRooms as $row) {
            $reservationId = (int) $row->reservation_id;
            $linkId = (int) $row->reservation_rooms_id;
            if (isset($seenRoomLinks[$reservationId][$linkId]) || isset($coveredRoomIds[$reservationId][(int) ($row->room_id ?? 0)])) {
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

        foreach ($summaries as $reservationId => $summary) {
            $resources = $summary['resources'];
            usort($resources, function (array $left, array $right): int {
                return strcmp(
                    ($left['date_key'] ?? '').($left['start_time'] ?? '').($left['name'] ?? ''),
                    ($right['date_key'] ?? '').($right['start_time'] ?? '').($right['name'] ?? ''),
                );
            });
            $summaries[$reservationId] = self::finalize($resources);
        }

        return $summaries;
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

    private static function institutionalTablesExist(): bool
    {
        $found = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->whereIn('table_name', [
                'institutional_event_venues',
                'institutional_event_equipment',
            ])
            ->count();

        return (int) $found === 2;
    }

    /**
     * @param  list<int>  $reservationIds
     * @param  array<int, array<string, mixed>>  $summaries
     * @param  array<int, array<int, true>>  $seenItemLinks
     * @param  array<int, array<int, true>>  $coveredRoomIds
     */
    private static function appendInstitutionalResources(
        array $reservationIds,
        array &$summaries,
        array &$seenItemLinks,
        array &$coveredRoomIds,
    ): void {
        $venues = DB::table('institutional_event_venues as venues')
            ->join('rooms as rooms', 'rooms.room_id', '=', 'venues.room_id')
            ->whereIn('venues.reservation_id', $reservationIds)
            ->where('venues.status', 'included')
            ->orderBy('venues.start_time')
            ->orderBy('venues.event_venue_id')
            ->get([
                'venues.event_venue_id',
                'venues.reservation_id',
                'venues.room_id',
                'venues.activity_date',
                'venues.start_time',
                'venues.end_time',
                'rooms.room_number',
                'rooms.room_type',
                'rooms.maintenance_status as room_maintenance',
                'rooms.availability_status as room_available',
            ]);

        $venueRooms = [];
        foreach ($venues as $venue) {
            $reservationId = (int) $venue->reservation_id;
            $roomId = (int) $venue->room_id;
            $coveredRoomIds[$reservationId][$roomId] = true;
            $venueRooms[(int) $venue->event_venue_id] = self::roomName((string) $venue->room_number);
            $summaries[$reservationId]['resources'][] = self::makeResource(
                'room',
                self::roomName((string) $venue->room_number),
                trim((string) ($venue->room_type ?? '')),
                1,
                $venue->activity_date,
                $venue->start_time,
                $venue->end_time,
                self::statusLabel($venue->room_maintenance, $venue->room_available),
            );
        }

        $equipmentRows = DB::table('institutional_event_equipment as equipment')
            ->join('institutional_event_venues as venues', 'venues.event_venue_id', '=', 'equipment.event_venue_id')
            ->join('reservation_items as reservationItems', 'reservationItems.reservation_items_id', '=', 'equipment.reservation_items_id')
            ->join('items as items', 'items.item_id', '=', 'reservationItems.item_id')
            ->leftJoin('reservation_details as details', 'details.reservation_items_id', '=', 'equipment.reservation_items_id')
            ->whereIn('equipment.reservation_id', $reservationIds)
            ->where('equipment.status', 'included')
            ->where('venues.status', 'included')
            ->orderBy('venues.start_time')
            ->orderBy('equipment.event_equipment_id')
            ->get([
                'equipment.event_equipment_id',
                'equipment.reservation_id',
                'equipment.reservation_items_id',
                'equipment.event_venue_id',
                'details.quantity',
                'venues.activity_date',
                'venues.start_time',
                'venues.end_time',
                'items.item_name',
                'items.maintenance_status as item_maintenance',
                'items.availability_status as item_available',
            ]);

        $seenEquipment = [];
        foreach ($equipmentRows as $row) {
            $equipmentId = (int) $row->event_equipment_id;
            if (isset($seenEquipment[$equipmentId])) {
                continue;
            }
            $seenEquipment[$equipmentId] = true;

            $reservationId = (int) $row->reservation_id;
            $itemLinkId = (int) $row->reservation_items_id;
            $seenItemLinks[$reservationId][$itemLinkId] = true;
            $name = trim((string) ($row->item_name ?? ''));
            $summaries[$reservationId]['resources'][] = self::makeResource(
                'item',
                $name !== '' ? $name : 'Equipment',
                $venueRooms[(int) $row->event_venue_id] ?? '',
                max(1, (int) ($row->quantity ?? 1)),
                $row->activity_date,
                $row->start_time,
                $row->end_time,
                self::statusLabel($row->item_maintenance, $row->item_available),
            );
        }
    }
}
