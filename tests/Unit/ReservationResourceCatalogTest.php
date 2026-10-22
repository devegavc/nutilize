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

}
