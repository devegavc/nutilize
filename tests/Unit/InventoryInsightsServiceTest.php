<?php

namespace Tests\Unit;

use App\Services\InventoryInsightsService;
use PHPUnit\Framework\TestCase;

class InventoryInsightsServiceTest extends TestCase
{
    public function test_it_reports_no_peak_without_any_bookings(): void
    {
        $this->assertSame(0, InventoryInsightsService::peakConcurrentQuantity([]));
    }

    public function test_sequential_bookings_do_not_stack(): void
    {
        $intervals = [
            [100, 200, 1],
            [200, 300, 1],
            [300, 400, 1],
        ];

        $this->assertSame(1, InventoryInsightsService::peakConcurrentQuantity($intervals));
    }

    public function test_overlapping_bookings_stack_into_a_peak(): void
    {
        $intervals = [
            [100, 300, 1],
            [200, 400, 1],
            [250, 260, 2],
        ];

        $this->assertSame(4, InventoryInsightsService::peakConcurrentQuantity($intervals));
    }

    public function test_peak_reflects_the_busiest_window_not_the_total(): void
    {
        $intervals = [
            [100, 150, 3],
            [400, 450, 5],
            [420, 430, 2],
        ];

        $this->assertSame(7, InventoryInsightsService::peakConcurrentQuantity($intervals));
    }

    public function test_a_fully_contained_booking_adds_to_the_surrounding_one(): void
    {
        $intervals = [
            [100, 900, 2],
            [300, 400, 3],
        ];

        $this->assertSame(5, InventoryInsightsService::peakConcurrentQuantity($intervals));
    }
}
