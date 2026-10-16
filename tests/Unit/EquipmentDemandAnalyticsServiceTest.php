<?php

namespace Tests\Unit;

use App\Services\EquipmentDemandAnalyticsService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class EquipmentDemandAnalyticsServiceTest extends TestCase
{
    public function test_month_window_is_half_open_in_manila(): void
    {
        $window = EquipmentDemandAnalyticsService::monthWindow(2026, 10);

        $this->assertSame('2026-10-01 00:00:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 00:00:00', $window['end']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $window['start']->timezoneName);
    }

    public function test_term_window_treats_the_end_date_as_inclusive(): void
    {
        $window = EquipmentDemandAnalyticsService::termWindow('2026-08-03', '2026-11-08');

        $this->assertSame('2026-08-03 00:00:00', $window['start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-09 00:00:00', $window['end']->format('Y-m-d H:i:s'));
        $this->assertSame(14.0, EquipmentDemandAnalyticsService::spanWeeks($window['start'], $window['end']));
    }

    public function test_usage_without_a_later_end_occupies_the_activity_calendar_day(): void
    {
        $bounds = EquipmentDemandAnalyticsService::usageBounds(
            '2026-10-16 08:00:00',
            '2026-10-16 00:00:00',
            null
        );

        $this->assertNotNull($bounds);
        $this->assertTrue($bounds['calendar_day']);
        $this->assertSame('2026-10-17 00:00:00', $bounds['end']->format('Y-m-d H:i:s'));
    }

    public function test_an_end_that_is_not_after_the_start_does_not_collapse_to_zero_length(): void
    {
        $bounds = EquipmentDemandAnalyticsService::usageBounds(
            '2026-10-16 08:00:00',
            null,
            '2026-10-16 08:00:00'
        );

        $this->assertNotNull($bounds);
        $this->assertTrue($bounds['end']->greaterThan($bounds['start']));
    }

    public function test_boundary_touching_reservations_are_outside_the_period(): void
    {
        $period = EquipmentDemandAnalyticsService::monthWindow(2026, 10);
        $endingAtStart = EquipmentDemandAnalyticsService::usageBounds('2026-09-30 08:00:00', null, '2026-10-01 00:00:00');
        $startingAtEnd = EquipmentDemandAnalyticsService::usageBounds('2026-11-01 00:00:00', null, '2026-11-01 02:00:00');

        $this->assertFalse(EquipmentDemandAnalyticsService::overlaps(
            $endingAtStart['start'],
            $endingAtStart['end'],
            $period['start'],
            $period['end']
        ));
        $this->assertFalse(EquipmentDemandAnalyticsService::overlaps(
            $startingAtEnd['start'],
            $startingAtEnd['end'],
            $period['start'],
            $period['end']
        ));
    }

    public function test_a_spanning_reservation_is_counted_once_at_its_full_quantity_in_each_period_it_overlaps(): void
    {
        $event = EquipmentDemandAnalyticsService::usageBounds('2026-10-31 20:00:00', null, '2026-11-02 08:00:00');
        $october = EquipmentDemandAnalyticsService::monthWindow(2026, 10);
        $november = EquipmentDemandAnalyticsService::monthWindow(2026, 11);

        $this->assertSame(9, EquipmentDemandAnalyticsService::countedQuantity(
            9,
            EquipmentDemandAnalyticsService::overlaps($event['start'], $event['end'], $october['start'], $october['end'])
        ));
        $this->assertSame(9, EquipmentDemandAnalyticsService::countedQuantity(
            9,
            EquipmentDemandAnalyticsService::overlaps($event['start'], $event['end'], $november['start'], $november['end'])
        ));
    }

    public function test_null_and_zero_quantities_stay_zero(): void
    {
        $this->assertSame(0, EquipmentDemandAnalyticsService::normalizeQuantity(null));
        $this->assertSame(0, EquipmentDemandAnalyticsService::normalizeQuantity(0));
        $this->assertSame(0, EquipmentDemandAnalyticsService::countedQuantity(null, true));
    }

    public function test_duplicate_detail_rows_collapse_to_one_quantity_per_reservation_and_item(): void
    {
        $aggregated = EquipmentDemandAnalyticsService::aggregateEquipmentLines([
            ['reservation_id' => 1, 'item_id' => 5, 'quantity' => 2],
            ['reservation_id' => 1, 'item_id' => 5, 'quantity' => 3],
            ['reservation_id' => 1, 'item_id' => 5, 'quantity' => null],
            ['reservation_id' => 2, 'item_id' => 5, 'quantity' => 0],
            ['reservation_id' => 1, 'item_id' => 6, 'quantity' => 4],
        ]);

        $byKey = [];
        foreach ($aggregated as $row) {
            $byKey[$row['reservation_id'].':'.$row['item_id']] = $row['quantity'];
        }

        $this->assertCount(3, $aggregated);
        $this->assertSame(5, $byKey['1:5']);
        $this->assertSame(0, $byKey['2:5']);
        $this->assertSame(4, $byKey['1:6']);
    }

    public function test_approved_demand_uses_observed_post_approval_statuses_only(): void
    {
        foreach (['approved', 'Returned', 'damaged', 'Overdue', 'To Return'] as $status) {
            $this->assertTrue(EquipmentDemandAnalyticsService::isApprovedStatus($status), $status);
        }

        foreach ([
            'rejected',
            'cancelled',
            'canceled',
            'expired',
            'timed out',
            'pending approval',
            'pending_office_approvals',
            'awaiting_physical_facilities',
            '',
            null,
        ] as $status) {
            $this->assertFalse(EquipmentDemandAnalyticsService::isApprovedStatus($status));
        }
    }

    public function test_consecutive_bookings_that_touch_are_not_counted_as_overlapping(): void
    {
        $touch = Carbon::parse('2026-10-16 10:00:00', 'Asia/Manila');
        $peak = EquipmentDemandAnalyticsService::peakDemand([
            [
                'start' => $touch->copy()->subHours(2),
                'end' => $touch->copy(),
                'quantity' => 5,
                'activity' => 'Morning setup',
            ],
            [
                'start' => $touch->copy(),
                'end' => $touch->copy()->addHours(2),
                'quantity' => 7,
                'activity' => 'Afternoon program',
            ],
        ]);

        $this->assertSame(7, $peak['peak']);
        $this->assertSame(['Afternoon program'], $peak['activities']);
    }

    public function test_overlapping_bookings_add_quantities_and_keep_both_events(): void
    {
        $peak = EquipmentDemandAnalyticsService::peakDemand([
            [
                'start' => Carbon::parse('2026-10-16 08:00:00', 'Asia/Manila'),
                'end' => Carbon::parse('2026-10-16 12:00:00', 'Asia/Manila'),
                'quantity' => 4,
                'activity' => 'Rehearsal',
            ],
            [
                'start' => Carbon::parse('2026-10-16 10:00:00', 'Asia/Manila'),
                'end' => Carbon::parse('2026-10-16 14:00:00', 'Asia/Manila'),
                'quantity' => 6,
                'activity' => 'Orientation',
            ],
        ]);

        $this->assertSame(10, $peak['peak']);
        $this->assertSame(['Rehearsal', 'Orientation'], $peak['activities']);
    }

    public function test_weekly_series_keeps_zero_weeks_and_repeats_a_spanning_quantity(): void
    {
        $buckets = EquipmentDemandAnalyticsService::weekBuckets(
            Carbon::parse('2026-10-01 00:00:00', 'Asia/Manila'),
            Carbon::parse('2026-11-01 00:00:00', 'Asia/Manila')
        );
        $event = [
            'start' => Carbon::parse('2026-10-04 08:00:00', 'Asia/Manila'),
            'end' => Carbon::parse('2026-10-11 10:00:00', 'Asia/Manila'),
            'reservation_id' => 15,
            'item_id' => 3,
            'quantity' => 10,
        ];

        $series = EquipmentDemandAnalyticsService::weeklySeries($buckets, [$event], 3);
        $values = array_column($series, 'value');

        $this->assertGreaterThan(3, count($series));
        $this->assertContains(0, $values);
        $this->assertSame(10, $values[0]);
        $this->assertSame(10, $values[1]);
        $this->assertSame(20, array_sum($values));
    }

    public function test_overall_weekly_series_counts_reservations_instead_of_adding_item_quantities(): void
    {
        $bucket = [[
            'start' => Carbon::parse('2026-10-05 00:00:00', 'Asia/Manila'),
            'end' => Carbon::parse('2026-10-12 00:00:00', 'Asia/Manila'),
            'label' => 'Oct 5, 2026 – Oct 11, 2026',
        ]];
        $events = [
            [
                'start' => Carbon::parse('2026-10-06 08:00:00', 'Asia/Manila'),
                'end' => Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'),
                'reservation_id' => 1,
                'item_id' => 3,
                'quantity' => 40,
            ],
            [
                'start' => Carbon::parse('2026-10-06 08:00:00', 'Asia/Manila'),
                'end' => Carbon::parse('2026-10-06 10:00:00', 'Asia/Manila'),
                'reservation_id' => 1,
                'item_id' => 4,
                'quantity' => 15,
            ],
        ];

        $series = EquipmentDemandAnalyticsService::weeklySeries($bucket, $events, null);

        $this->assertSame(1, $series[0]['value']);
    }

    public function test_insights_quote_the_calculated_figures_and_do_not_forecast(): void
    {
        $sentences = EquipmentDemandAnalyticsService::insightSentences(
            'Chairs',
            12,
            600,
            120,
            100,
            ['Orientation'],
            'First Semester 2026-2027'
        );
        $text = implode(' ', $sentences);

        $this->assertStringContainsString('Approved demand for Chairs is 12 reservations during First Semester 2026-2027, totaling 600 units.', $text);
        $this->assertStringContainsString('Peak simultaneous demand reached 120 compared with 100 currently serviceable units.', $text);
        $this->assertStringContainsString('Orientation', $text);
        $this->assertStringContainsString('Review stock or scheduling', $text);
        $this->assertStringNotContainsString('increase', strtolower($text));
        $this->assertStringNotContainsString('forecast', strtolower($text));
        $this->assertStringNotContainsString('will rise', strtolower($text));
    }

    public function test_insights_omit_the_review_sentence_when_current_stock_covers_the_peak(): void
    {
        $sentences = EquipmentDemandAnalyticsService::insightSentences(
            'Projector',
            2,
            2,
            1,
            4,
            [],
            'October 2026'
        );

        $this->assertStringNotContainsString('Review stock', implode(' ', $sentences));
    }
}
