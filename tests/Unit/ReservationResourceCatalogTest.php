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

    public function test_rooms_saved_in_one_submit_stay_one_request(): void
    {
        $clusters = ReservationResourceCatalog::clusterRows([
            ['reservation_id' => 5, 'user_id' => 9, 'activity_name' => 'Class', 'created_at' => '2026-10-25 08:00:04'],
            ['reservation_id' => 1, 'user_id' => 9, 'activity_name' => 'class', 'created_at' => '2026-10-25 08:00:00'],
            ['reservation_id' => 2, 'user_id' => 9, 'activity_name' => 'Class', 'created_at' => '2026-10-25 08:00:01'],
            ['reservation_id' => 8, 'user_id' => 9, 'activity_name' => 'Other', 'created_at' => '2026-10-25 08:00:01'],
            ['reservation_id' => 3, 'user_id' => 4, 'activity_name' => 'Class', 'created_at' => '2026-10-25 08:00:01'],
            ['reservation_id' => 6, 'user_id' => 9, 'activity_name' => 'Class', 'created_at' => '2026-10-25 08:05:00'],
        ]);

        $this->assertSame([
            [3],
            [1, 2, 5],
            [6],
            [8],
        ], $clusters);
    }
}
