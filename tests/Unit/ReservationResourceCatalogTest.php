<?php

namespace Tests\Unit;

use App\Services\ReservationResourceCatalog;
use PHPUnit\Framework\TestCase;

class ReservationResourceCatalogTest extends TestCase
{
    public function test_one_date_stays_a_single_day(): void
    {
        $this->assertSame(
            'October 25, 2026',
            ReservationResourceCatalog::dateLabel(['2026-10-25'], 1)
        );
    }

    public function test_several_days_in_one_month_become_a_range(): void
    {
        $this->assertSame(
            'October 25–30, 2026',
            ReservationResourceCatalog::dateLabel([
                '2026-10-30',
                '2026-10-25',
                '2026-10-28',
                '2026-10-28',
            ], 5)
        );
    }

    public function test_dates_in_different_years_stay_a_schedule_count(): void
    {
        $this->assertSame(
            'Multiple dates (2 schedules)',
            ReservationResourceCatalog::dateLabel(['2026-12-22', '2027-01-03'], 2)
        );
    }

    public function test_equipment_follows_the_venue_id_rather_than_the_room_name(): void
    {
        $room = function (int $venueId, string $name): array {
            return [
                'type' => 'room',
                'name' => $name,
                'location' => 'Classroom',
                'room_type' => 'Classroom',
                'venue_id' => $venueId,
                'date' => 'November 11, 2026',
                'start_time' => '8:00 AM',
                'end_time' => '9:00 AM',
                'status' => 'Available',
            ];
        };
        $item = function (int $venueId, string $name, int $quantity): array {
            return [
                'type' => 'item',
                'name' => $name,
                'location' => 'Room 302',
                'quantity' => $quantity,
                'venue_id' => $venueId,
                'associated' => true,
                'date' => 'November 11, 2026',
                'start_time' => '8:00 AM',
                'end_time' => '9:00 AM',
            ];
        };

        $grouped = ReservationResourceCatalog::groupResources([
            $room(60, 'Room 302'),
            $room(61, 'Room 302'),
            $item(61, 'Jasmin Chairs', 6),
            $item(60, 'Extension Cords', 1),
        ]);

        $this->assertSame(60, $grouped['venue_groups'][0]['venue_id']);
        $this->assertSame('Extension Cords', $grouped['venue_groups'][0]['equipment'][0]['name']);
        $this->assertSame(1, $grouped['venue_groups'][0]['equipment'][0]['quantity']);
        $this->assertSame(61, $grouped['venue_groups'][1]['venue_id']);
        $this->assertSame('Jasmin Chairs', $grouped['venue_groups'][1]['equipment'][0]['name']);
        $this->assertSame([], $grouped['unassigned_equipment']);
    }

    public function test_equipment_without_a_recorded_room_link_stays_unassigned(): void
    {
        $grouped = ReservationResourceCatalog::groupResources([
            [
                'type' => 'room',
                'name' => 'Room 302',
                'location' => 'Classroom',
                'link_key' => 'room-link:9',
                'date' => 'October 25, 2026',
                'start_time' => '8:00 AM',
                'end_time' => '9:00 AM',
                'status' => null,
            ],
            [
                'type' => 'item',
                'name' => 'Extension Cords',
                'location' => 'Room 302',
                'quantity' => 2,
                'associated' => false,
                'date' => 'October 25, 2026',
                'start_time' => '8:00 AM',
                'end_time' => '9:00 AM',
            ],
            [
                'type' => 'item',
                'name' => 'Jasmin Chairs',
                'quantity' => 4,
                'venue_id' => 60,
                'link_key' => 'room-link:9',
                'associated' => true,
            ],
        ]);

        $this->assertSame([], $grouped['venue_groups'][0]['equipment']);
        $this->assertSame('Extension Cords', $grouped['unassigned_equipment'][0]['name']);
        $this->assertSame('Jasmin Chairs', $grouped['unassigned_equipment'][1]['name']);
    }

    public function test_a_legacy_detail_that_records_both_ids_keeps_that_link(): void
    {
        $grouped = ReservationResourceCatalog::groupResources([
            [
                'type' => 'room',
                'name' => 'Room 201',
                'room_type' => 'Laboratory',
                'link_key' => 'room-link:15',
                'date' => 'October 25, 2026',
                'start_time' => '1:00 PM',
                'end_time' => '2:00 PM',
                'status' => null,
            ],
            [
                'type' => 'item',
                'name' => 'Projector',
                'quantity' => 1,
                'link_key' => 'room-link:15',
                'associated' => true,
            ],
        ]);

        $this->assertSame('Projector', $grouped['venue_groups'][0]['equipment'][0]['name']);
        $this->assertSame([], $grouped['unassigned_equipment']);
    }

}
