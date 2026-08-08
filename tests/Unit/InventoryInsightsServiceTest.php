<?php

namespace Tests\Unit;

use App\Services\InventoryInsightsService;
use PHPUnit\Framework\TestCase;

class InventoryInsightsServiceTest extends TestCase
{
    public function test_shortage_when_more_units_are_out_than_owned(): void
    {
        $result = InventoryInsightsService::calculateRestockSuggestion(
            stock: 1,
            inUse: 6,
            timesBorrowed: 0,
            unitsBorrowed: 0,
        );

        $this->assertSame(6, $result['suggested_qty']);
        $this->assertSame(5, $result['gap']);
        $this->assertSame('6 out − 1 owned + 1 spare', $result['formula']);
        $this->assertSame('critical', $result['priority']);
    }

    public function test_shortage_when_borrowed_units_exceed_stock(): void
    {
        $result = InventoryInsightsService::calculateRestockSuggestion(
            stock: 5,
            inUse: 2,
            timesBorrowed: 8,
            unitsBorrowed: 8,
        );

        $this->assertSame(4, $result['suggested_qty']);
        $this->assertSame(3, $result['gap']);
        $this->assertSame('8 borrowed − 5 owned + 1 spare', $result['formula']);
    }

    public function test_suggests_one_spare_when_all_units_are_out(): void
    {
        $result = InventoryInsightsService::calculateRestockSuggestion(
            stock: 2,
            inUse: 2,
            timesBorrowed: 3,
            unitsBorrowed: 2,
        );

        $this->assertSame(1, $result['suggested_qty']);
        $this->assertSame('All units out + 1 spare', $result['formula']);
        $this->assertSame('medium', $result['priority']);
    }

    public function test_suggests_one_spare_when_borrowed_units_hit_high_demand_threshold(): void
    {
        $result = InventoryInsightsService::calculateRestockSuggestion(
            stock: 5,
            inUse: 1,
            timesBorrowed: 4,
            unitsBorrowed: 4,
        );

        $this->assertSame(1, $result['suggested_qty']);
        $this->assertSame('4 borrowed of 5 owned + 1 spare', $result['formula']);
    }

    public function test_no_suggestion_when_stock_covers_demand(): void
    {
        $result = InventoryInsightsService::calculateRestockSuggestion(
            stock: 10,
            inUse: 2,
            timesBorrowed: 3,
            unitsBorrowed: 3,
        );

        $this->assertSame(0, $result['suggested_qty']);
        $this->assertSame('', $result['formula']);
    }
}
