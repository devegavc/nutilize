<?php

namespace App\Services;

use App\Models\AcademicTerm;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Equipment demand for a calendar month or a configured academic term.
 *
 * Usage is the scheduled activity interval, not reservations.created_at.
 * A reservation counts when that interval overlaps the period. It is counted
 * once, at its full line quantity. Detail rows are summed by reservation and
 * item before any other use, so approval history and unit assignments cannot
 * multiply quantities.
 */
class EquipmentDemandAnalyticsService
{
    public const TIMEZONE = 'Asia/Manila';

    public const PER_PAGE = 15;

    /**
     * Post-approval statuses observed in this app.
     * overdue and "to return" have an approved approval chain and are still
     * committed bookings. "timed out" and "pending approval" are not.
     *
     * @var array<int, string>
     */
    public const APPROVED_STATUSES = [
        'approved',
        'returned',
        'damaged',
        'overdue',
        'to return',
    ];

    private const USAGE_START_SQL = 'COALESCE(r."Start_of_activity", r."Date_of_Activity")';

    private const USAGE_END_SQL = <<<'SQL'
CASE
    WHEN r."End_of_Activity" IS NOT NULL
     AND r."End_of_Activity" > COALESCE(r."Start_of_activity", r."Date_of_Activity")
    THEN r."End_of_Activity"
    ELSE (DATE(COALESCE(r."Start_of_activity", r."Date_of_Activity")) + INTERVAL '1 day')
END
SQL;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function dashboard(array $input): array
    {
        $rank = ($input['rank'] ?? '') === 'quantity' ? 'quantity' : 'reservations';
        $chartItemId = self::positiveInt($input['chart_item_id'] ?? null);
        $terms = self::listTerms();
        $period = self::resolvePeriod($input, $terms);

        if (isset($period['empty_reason'])) {
            return self::emptyDashboard($period, $terms, $rank);
        }

        $lines = self::fetchOverlappingLines($period['start'], $period['end']);

        return self::assemble($period, $terms, $lines, $rank, $chartItemId);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function drillDown(array $input): array
    {
        $terms = self::listTerms();
        $period = self::resolvePeriod($input, $terms);
        $page = max(1, (int) ($input['page'] ?? 1));
        $itemId = self::positiveInt($input['item_id'] ?? null);
        $basis = ($input['basis'] ?? '') === 'requested' ? 'requested' : 'approved';

        if (isset($period['empty_reason'])) {
            return [
                'ok' => true,
                'page' => 1,
                'per_page' => self::PER_PAGE,
                'total' => 0,
                'reservation_count' => 0,
                'item_quantity' => null,
                'basis' => $basis,
                'rows' => [],
            ];
        }

        return self::fetchDrillDown($period['start'], $period['end'], $itemId, $basis, $page);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listTerms(): array
    {
        if (!Schema::hasTable('academic_terms')) {
            return [];
        }

        return AcademicTerm::query()
            ->orderByDesc('starts_on')
            ->orderBy('name')
            ->get()
            ->map(fn (AcademicTerm $term): array => self::presentTerm($term))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentTerm(AcademicTerm $term): array
    {
        $window = self::termWindow(
            $term->starts_on->toDateString(),
            $term->ends_on->toDateString()
        );

        return [
            'id' => (int) $term->id,
            'academic_year' => (string) $term->academic_year,
            'name' => (string) $term->name,
            'starts_on' => $term->starts_on->toDateString(),
            'ends_on' => $term->ends_on->toDateString(),
            'weeks' => self::spanWeeks($window['start'], $window['end']),
            'label' => trim($term->name.' '.$term->academic_year),
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public static function monthWindow(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1, 0, 0, 0, self::TIMEZONE);

        return [
            'start' => $start,
            'end' => $start->copy()->addMonth(),
        ];
    }

    /**
     * ends_on is an inclusive calendar date. The window ends at the next midnight.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function termWindow(string $startsOn, string $endsOn): array
    {
        $start = Carbon::parse($startsOn, self::TIMEZONE)->startOfDay();
        $end = Carbon::parse($endsOn, self::TIMEZONE)->startOfDay()->addDay();

        return ['start' => $start, 'end' => $end];
    }

    public static function spanWeeks(Carbon $start, Carbon $end): float
    {
        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());

        return round($seconds / 604800, 1);
    }

    /**
     * @return array{start: Carbon, end: Carbon, calendar_day: bool}|null
     */
    public static function usageBounds(?string $startOfActivity, ?string $dateOfActivity, ?string $endOfActivity): ?array
    {
        $startRaw = self::blankToNull($startOfActivity) ?? self::blankToNull($dateOfActivity);
        if ($startRaw === null) {
            return null;
        }

        $start = Carbon::parse($startRaw, self::TIMEZONE);
        $endRaw = self::blankToNull($endOfActivity);
        $calendarDay = true;
        $end = null;

        if ($endRaw !== null) {
            $parsedEnd = Carbon::parse($endRaw, self::TIMEZONE);
            if ($parsedEnd->greaterThan($start)) {
                $end = $parsedEnd;
                $calendarDay = false;
            }
        }

        if ($end === null) {
            $end = $start->copy()->startOfDay()->addDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'calendar_day' => $calendarDay,
        ];
    }

    public static function overlaps(Carbon $start, Carbon $end, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return $start->lt($periodEnd) && $end->gt($periodStart);
    }

    public static function normalizeQuantity(mixed $quantity): int
    {
        if ($quantity === null || $quantity === '') {
            return 0;
        }

        $value = (int) $quantity;

        return $value > 0 ? $value : 0;
    }

    public static function countedQuantity(mixed $quantity, bool $overlaps): int
    {
        if (!$overlaps) {
            return 0;
        }

        return self::normalizeQuantity($quantity);
    }

    public static function isApprovedStatus(?string $status): bool
    {
        return in_array(self::normalizeStatus($status), self::APPROVED_STATUSES, true);
    }

    /**
     * Sum detail quantities once per reservation and item.
     * Extra copies of the same line (from a join) are not left multiplied;
     * their quantities are added a single time into that pair.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function aggregateEquipmentLines(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $reservationId = (int) ($row['reservation_id'] ?? 0);
            $itemId = (int) ($row['item_id'] ?? 0);
            $key = $reservationId.':'.$itemId;

            if (!isset($grouped[$key])) {
                $grouped[$key] = $row;
                $grouped[$key]['reservation_id'] = $reservationId;
                $grouped[$key]['item_id'] = $itemId;
                $grouped[$key]['quantity'] = self::normalizeQuantity($row['quantity'] ?? null);
                continue;
            }

            $grouped[$key]['quantity'] += self::normalizeQuantity($row['quantity'] ?? null);
        }

        return array_values($grouped);
    }

    /**
     * Sweep interval endpoints. An end at the same timestamp as a start is
     * applied first, so back-to-back bookings are not overlapping.
     *
     * @param  array<int, array{start: Carbon, end: Carbon, quantity: int, activity?: string}>  $intervals
     * @return array{peak: int, activities: array<int, string>}
     */
    public static function peakDemand(array $intervals): array
    {
        $events = [];

        foreach ($intervals as $index => $interval) {
            $quantity = self::normalizeQuantity($interval['quantity'] ?? 0);
            $start = $interval['start']->getTimestamp();
            $end = $interval['end']->getTimestamp();

            if ($end <= $start) {
                continue;
            }

            $activity = trim((string) ($interval['activity'] ?? ''));
            $events[] = ['time' => $start, 'kind' => 1, 'quantity' => $quantity, 'activity' => $activity, 'index' => $index];
            $events[] = ['time' => $end, 'kind' => 0, 'quantity' => $quantity, 'activity' => $activity, 'index' => $index];
        }

        usort($events, static function (array $left, array $right): int {
            return [$left['time'], $left['kind'], $left['index']] <=> [$right['time'], $right['kind'], $right['index']];
        });

        $current = 0;
        $peak = 0;
        $open = [];
        $peakActivities = [];

        foreach ($events as $event) {
            if ($event['kind'] === 0) {
                unset($open[$event['index']]);
                $current -= $event['quantity'];
                continue;
            }

            $open[$event['index']] = $event['activity'];
            $current += $event['quantity'];

            if ($current <= $peak) {
                continue;
            }

            $peak = $current;
            $peakActivities = [];
            foreach ($open as $activity) {
                if ($activity !== '' && !in_array($activity, $peakActivities, true)) {
                    $peakActivities[] = $activity;
                }
            }
        }

        return ['peak' => $peak, 'activities' => $peakActivities];
    }

    /**
     * Monday-start weeks in Asia/Manila that intersect the half-open period.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    public static function weekBuckets(Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            return [];
        }

        $cursor = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $buckets = [];

        while ($cursor->lt($periodEnd) && count($buckets) < 120) {
            $weekEnd = $cursor->copy()->addWeek();
            if ($weekEnd->gt($periodStart)) {
                $lastDay = $weekEnd->copy()->subDay();
                $buckets[] = [
                    'start' => $cursor->copy(),
                    'end' => $weekEnd,
                    'label' => $cursor->format('M j, Y').' – '.$lastDay->format('M j, Y'),
                ];
            }
            $cursor = $weekEnd;
        }

        return $buckets;
    }

    /**
     * Calendar months in Asia/Manila that intersect the half-open period.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    public static function monthBuckets(Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            return [];
        }

        $cursor = $periodStart->copy()->startOfMonth();
        $buckets = [];

        while ($cursor->lt($periodEnd) && count($buckets) < 24) {
            $monthEnd = $cursor->copy()->addMonth();
            if ($monthEnd->gt($periodStart)) {
                $buckets[] = [
                    'start' => $cursor->copy(),
                    'end' => $monthEnd,
                    'label' => $cursor->format('M Y'),
                ];
            }
            $cursor = $monthEnd;
        }

        return $buckets;
    }

    /**
     * Each overlapping event contributes its full quantity to every bucket it
     * touches. With no item, the value is distinct approved reservations.
     *
     * @param  array<int, array{start: Carbon, end: Carbon, label: string}>  $buckets
     * @param  array<int, array{start: Carbon, end: Carbon, reservation_id: int, item_id: int, quantity: int}>  $events
     * @return array<int, array{label: string, start: string, end: string, value: int}>
     */
    public static function weeklySeries(array $buckets, array $events, ?int $itemId): array
    {
        $series = [];

        foreach ($buckets as $bucket) {
            $reservationIds = [];
            $quantity = 0;

            foreach ($events as $event) {
                if (!self::overlaps($event['start'], $event['end'], $bucket['start'], $bucket['end'])) {
                    continue;
                }

                if ($itemId !== null && (int) $event['item_id'] !== $itemId) {
                    continue;
                }

                $reservationIds[(int) $event['reservation_id']] = true;
                if ($itemId !== null) {
                    $quantity += self::normalizeQuantity($event['quantity'] ?? 0);
                }
            }

            $lastDay = $bucket['end']->copy()->subDay();
            $series[] = [
                'label' => $bucket['label'],
                'start' => $bucket['start']->toDateString(),
                'end' => $lastDay->toDateString(),
                'value' => $itemId === null ? count($reservationIds) : $quantity,
            ];
        }

        return $series;
    }

    /**
     * @param  array<int, string>  $peakActivities
     * @return array<int, string>
     */
    public static function insightSentences(
        string $itemName,
        int $reservationCount,
        int $approvedQuantity,
        int $peakQuantity,
        ?int $serviceableStock,
        array $peakActivities,
        string $periodLabel
    ): array {
        $reservationWord = $reservationCount === 1 ? 'reservation' : 'reservations';
        $unitWord = $approvedQuantity === 1 ? 'unit' : 'units';
        $stockText = $serviceableStock === null
            ? 'current serviceable stock not recorded'
            : $serviceableStock.' currently serviceable units';

        $sentences = [
            sprintf(
                'Approved demand for %s is %d %s during %s, totaling %d %s. Peak simultaneous demand reached %d compared with %s.',
                $itemName,
                $reservationCount,
                $reservationWord,
                $periodLabel,
                $approvedQuantity,
                $unitWord,
                $peakQuantity,
                $stockText
            ),
        ];

        $activities = array_values(array_filter(array_map(
            static fn (string $activity): string => trim($activity),
            $peakActivities
        )));

        if ($activities !== []) {
            $shown = array_slice($activities, 0, 5);
            $extra = count($activities) - count($shown);
            $list = implode(', ', $shown);
            if ($extra > 0) {
                $list .= ' and '.$extra.' more';
            }
            $sentences[] = 'Events in that peak window: '.$list.'.';
        }

        if ($serviceableStock === null) {
            $sentences[] = 'Historical inventory is not recorded, so this peak is not an exact shortage on the event date.';
        } elseif ($peakQuantity > $serviceableStock) {
            $sentences[] = 'Review stock or scheduling: peak simultaneous demand is above currently serviceable stock. This comparison uses current stock, not a count from the event date.';
        }

        return $sentences;
    }

    /**
     * @return array<int, string>
     */
    public static function explainOverlap(Carbon $periodStart, Carbon $periodEnd): array
    {
        if (DB::getDriverName() !== 'pgsql' || !self::tablesReady()) {
            return [];
        }

        $rows = DB::select(
            'EXPLAIN '.self::overlapFromSql(),
            [$periodEnd->format('Y-m-d H:i:s'), $periodStart->format('Y-m-d H:i:s')]
        );

        return array_map(static function (object $row): string {
            $values = array_values((array) $row);

            return (string) ($values[0] ?? '');
        }, $rows);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<string, mixed>
     */
    private static function resolvePeriod(array $input, array $terms): array
    {
        $mode = ($input['mode'] ?? '') === 'term' ? 'term' : 'month';

        if ($mode === 'term') {
            if (!Schema::hasTable('academic_terms')) {
                return self::unboundedPeriod('term', 'Per term', 'terms_unavailable');
            }

            if ($terms === []) {
                return self::unboundedPeriod('term', 'Per term', 'no_terms');
            }

            $termId = (int) ($input['term_id'] ?? 0);
            $selected = null;
            foreach ($terms as $term) {
                if ($termId > 0 && (int) $term['id'] === $termId) {
                    $selected = $term;
                    break;
                }
            }

            if ($termId > 0 && $selected === null) {
                throw ValidationException::withMessages([
                    'term_id' => 'That academic term was not found.',
                ]);
            }

            $selected ??= $terms[0];
            $window = self::termWindow($selected['starts_on'], $selected['ends_on']);

            return [
                'mode' => 'term',
                'label' => $selected['label'],
                'start' => $window['start'],
                'end' => $window['end'],
                'weeks' => self::spanWeeks($window['start'], $window['end']),
                'term_id' => (int) $selected['id'],
                'starts_on' => $selected['starts_on'],
                'ends_on' => $selected['ends_on'],
            ];
        }

        $now = Carbon::now(self::TIMEZONE);
        $year = array_key_exists('year', $input) && $input['year'] !== '' && $input['year'] !== null
            ? (int) $input['year']
            : (int) $now->year;
        $month = array_key_exists('month', $input) && $input['month'] !== '' && $input['month'] !== null
            ? (int) $input['month']
            : (int) $now->month;

        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => 'Choose a valid month and year.',
            ]);
        }

        $window = self::monthWindow($year, $month);

        return [
            'mode' => 'month',
            'label' => $window['start']->format('F Y'),
            'start' => $window['start'],
            'end' => $window['end'],
            'weeks' => self::spanWeeks($window['start'], $window['end']),
            'year' => $year,
            'month' => $month,
            'starts_on' => $window['start']->toDateString(),
            'ends_on' => $window['end']->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $period
     * @param  array<int, array<string, mixed>>  $terms
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private static function assemble(array $period, array $terms, array $lines, string $rank, ?int $chartItemId): array
    {
        $approvedReservationIds = [];
        $returnedReservationIds = [];
        $requestedReservationIds = [];
        $items = [];
        $approvedEvents = [];

        foreach ($lines as $line) {
            $reservationId = (int) $line['reservation_id'];
            $itemId = (int) $line['item_id'];
            $quantity = self::normalizeQuantity($line['quantity'] ?? 0);
            $requestedReservationIds[$reservationId] = true;

            $items[$itemId] ??= [
                'item_id' => $itemId,
                'item_name' => (string) $line['item_name'],
                'requested_quantity' => 0,
                'approved_quantity' => 0,
                'approved_reservations' => [],
                'intervals' => [],
            ];
            $items[$itemId]['requested_quantity'] += $quantity;

            if (!$line['approved']) {
                continue;
            }

            $approvedReservationIds[$reservationId] = true;
            if (($line['status'] ?? '') === 'returned') {
                $returnedReservationIds[$reservationId] = true;
            }

            $items[$itemId]['approved_quantity'] += $quantity;
            $items[$itemId]['approved_reservations'][$reservationId] = true;
            $items[$itemId]['intervals'][] = [
                'start' => $line['start'],
                'end' => $line['end'],
                'quantity' => $quantity,
                'activity' => (string) ($line['activity_name'] ?? ''),
            ];
            $approvedEvents[] = [
                'start' => $line['start'],
                'end' => $line['end'],
                'reservation_id' => $reservationId,
                'item_id' => $itemId,
                'quantity' => $quantity,
            ];
        }

        $ranked = [];
        foreach ($items as $item) {
            $reservationCount = count($item['approved_reservations']);
            if ($reservationCount === 0 && (int) $item['approved_quantity'] === 0) {
                continue;
            }
            $ranked[] = $item;
        }

        usort($ranked, static function (array $left, array $right) use ($rank): int {
            $leftReservations = count($left['approved_reservations']);
            $rightReservations = count($right['approved_reservations']);

            if ($rank === 'quantity') {
                return [$right['approved_quantity'], $rightReservations, $left['item_name']]
                    <=> [$left['approved_quantity'], $leftReservations, $right['item_name']];
            }

            return [$rightReservations, $right['approved_quantity'], $left['item_name']]
                <=> [$leftReservations, $left['approved_quantity'], $right['item_name']];
        });

        $top = array_slice($ranked, 0, 10);
        $stock = self::serviceableStock(array_map(static fn (array $item): int => (int) $item['item_id'], $ranked));
        $stockKnown = $stock !== null;

        $itemRows = [];
        $availability = [];
        $insights = [];

        foreach ($top as $item) {
            $itemId = (int) $item['item_id'];
            $peak = self::peakDemand($item['intervals']);
            $serviceable = $stockKnown ? (int) ($stock[$itemId] ?? 0) : null;
            $reservationCount = count($item['approved_reservations']);
            $shortage = $serviceable !== null && $peak['peak'] > $serviceable;

            $itemRows[] = [
                'item_id' => $itemId,
                'item_name' => $item['item_name'],
                'reservation_count' => $reservationCount,
                'approved_quantity' => (int) $item['approved_quantity'],
                'requested_quantity' => (int) $item['requested_quantity'],
                'issued_quantity' => null,
                'issued_label' => 'Not recorded',
            ];

            $availability[] = [
                'item_id' => $itemId,
                'item_name' => $item['item_name'],
                'peak_quantity' => $peak['peak'],
                'serviceable_stock' => $serviceable,
                'shortage' => $shortage,
                'comparison' => self::comparisonText($peak['peak'], $serviceable),
                'peak_activities' => $peak['activities'],
            ];

            if (count($insights) >= 5 || (count($insights) >= 3 && !$shortage)) {
                continue;
            }

            $insights[] = [
                'item_id' => $itemId,
                'sentences' => self::insightSentences(
                    $item['item_name'],
                    $reservationCount,
                    (int) $item['approved_quantity'],
                    $peak['peak'],
                    $serviceable,
                    $peak['activities'],
                    $period['label']
                ),
            ];
        }

        $chartOptions = [];
        foreach (array_slice($ranked, 0, 100) as $item) {
            $chartOptions[] = [
                'item_id' => (int) $item['item_id'],
                'item_name' => $item['item_name'],
            ];
        }

        $chartItem = null;
        if ($chartItemId !== null) {
            foreach ($chartOptions as $option) {
                if ($option['item_id'] === $chartItemId) {
                    $chartItem = $option;
                    break;
                }
            }
        }

        $weeklyItemId = $chartItem['item_id'] ?? null;
        $monthly = ($period['mode'] ?? '') === 'term';
        $buckets = $monthly
            ? self::monthBuckets($period['start'], $period['end'])
            : self::weekBuckets($period['start'], $period['end']);
        $series = self::weeklySeries($buckets, $approvedEvents, $weeklyItemId);
        // #region agent log
        file_put_contents(base_path('debug-f0ec29.log'), json_encode(['sessionId' => 'f0ec29', 'hypothesisId' => 'A', 'location' => 'EquipmentDemandAnalyticsService.php:assemble', 'message' => 'demand trend grain', 'data' => ['mode' => $period['mode'] ?? null, 'grain' => $monthly ? 'month' : 'week', 'points' => count($series), 'labels' => array_column($series, 'label')], 'timestamp' => (int) round(microtime(true) * 1000)])."\n", FILE_APPEND);
        // #endregion
        $topItem = $itemRows[0] ?? null;
        if ($rank === 'quantity' && $ranked !== []) {
            $byFrequency = $ranked;
            usort($byFrequency, static function (array $left, array $right): int {
                return [count($right['approved_reservations']), $right['approved_quantity'], $left['item_name']]
                    <=> [count($left['approved_reservations']), $left['approved_quantity'], $right['item_name']];
            });
            $frequent = $byFrequency[0];
            $topItem = [
                'item_name' => $frequent['item_name'],
                'reservation_count' => count($frequent['approved_reservations']),
            ];
        }

        $approvedCount = count($approvedReservationIds);

        return [
            'ok' => true,
            'empty' => $approvedCount === 0,
            'empty_reason' => $approvedCount === 0 ? 'no_approved_demand' : null,
            'period' => self::presentPeriod($period),
            'terms' => $terms,
            'terms_available' => Schema::hasTable('academic_terms'),
            'rank' => $rank,
            'summary' => [
                'approved_reservations' => $approvedCount,
                'distinct_items' => count(array_filter(
                    $items,
                    static fn (array $item): bool => count($item['approved_reservations']) > 0
                )),
                'marked_returned' => count($returnedReservationIds),
                'marked_returned_label' => 'Marked returned. Checkout quantity is not recorded.',
                'requested_reservations' => count($requestedReservationIds),
                'top_item_name' => $topItem['item_name'] ?? null,
                'top_item_reservations' => (int) ($topItem['reservation_count'] ?? 0),
            ],
            'items' => $itemRows,
            'chart_items' => $chartOptions,
            'weekly' => [
                'item_id' => $weeklyItemId,
                'item_name' => $chartItem['item_name'] ?? null,
                'grain' => $monthly ? 'month' : 'week',
                'title' => $monthly ? 'Monthly demand' : 'Weekly demand',
                'unit' => $weeklyItemId === null ? 'Approved reservations' : 'Approved quantity',
                'note' => $monthly
                    ? 'Each point repeats a booking in every month it overlaps. Do not add the points together to get the term total.'
                    : 'Each point repeats a booking in every week it overlaps. Do not add the points together to get the month total.',
                'points' => $series,
            ],
            'availability' => $availability,
            'insights' => $insights,
            'definitions' => self::definitions(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function definitions(): array
    {
        return [
            'Demand uses the scheduled activity interval: Start of activity, or Date of activity when the start is missing. The reservation submission time is not the usage date.',
            'The interval ends at End of activity when that time is after the start. Otherwise the booking occupies its activity calendar day and ends at the next midnight.',
            'A booking is included when its interval overlaps the month or term. It is counted once, at its full line quantity, even if it crosses the period boundary.',
            'Approved demand includes approved, returned, damaged, overdue, and to return. Rejected, cancelled, timed out, expired, and still-pending requests are excluded from approved demand.',
            'Requested demand is every submitted equipment line in the period, labeled separately. It is not the planning default.',
            'Checkout quantities are not recorded. Assigned units are not treated as issued quantities. Marked returned is a completion status only.',
            'Serviceable stock is the current count of units that are available or in use. Maintenance, damaged, and retired units are excluded. A peak above that count is a review signal against current stock, not proof of a shortage on the event date.',
            'When one booking ends at the same time another starts, the end is applied first, so consecutive bookings are not treated as overlapping.',
            'Quantities are kept on each equipment type. They are not added together into one overall unit total.',
        ];
    }

    private static function comparisonText(int $peak, ?int $serviceable): string
    {
        if ($serviceable === null) {
            return 'Current serviceable stock is not recorded.';
        }

        if ($peak > $serviceable) {
            return 'Peak is above current serviceable stock.';
        }

        return 'Peak does not exceed current serviceable stock.';
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, int>|null
     */
    private static function serviceableStock(array $itemIds): ?array
    {
        if (!Schema::hasTable('item_units')) {
            return null;
        }

        $query = DB::table('item_units')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('available', 'in_use')")
            ->groupBy('item_id')
            ->selectRaw('item_id, COUNT(*) as serviceable');

        if ($itemIds !== []) {
            $query->whereIn('item_id', $itemIds);
        }

        $stock = [];
        foreach ($query->get() as $row) {
            $stock[(int) $row->item_id] = (int) $row->serviceable;
        }

        return $stock;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function fetchOverlappingLines(Carbon $periodStart, Carbon $periodEnd): array
    {
        if (!self::tablesReady() || DB::getDriverName() !== 'pgsql') {
            return [];
        }

        $rows = DB::select(
            self::overlapFromSql(),
            [$periodEnd->format('Y-m-d H:i:s'), $periodStart->format('Y-m-d H:i:s')]
        );

        $normalized = [];
        foreach ($rows as $row) {
            $bounds = self::usageBounds(
                self::sqlTimestamp($row->start_of_activity ?? null),
                self::sqlTimestamp($row->date_of_activity ?? null),
                self::sqlTimestamp($row->end_of_activity ?? null)
            );

            if ($bounds === null || !self::overlaps($bounds['start'], $bounds['end'], $periodStart, $periodEnd)) {
                continue;
            }

            $normalized[] = [
                'reservation_id' => (int) $row->reservation_id,
                'item_id' => (int) $row->item_id,
                'item_name' => trim((string) ($row->item_name ?? '')) ?: 'Unnamed item',
                'quantity' => self::normalizeQuantity($row->quantity ?? 0),
                'activity_name' => trim((string) ($row->activity_name ?? '')),
                'status' => self::normalizeStatus($row->status ?? ''),
                'approved' => self::isApprovedStatus($row->status ?? ''),
                'start' => $bounds['start'],
                'end' => $bounds['end'],
                'calendar_day' => $bounds['calendar_day'],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fetchDrillDown(
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $itemId,
        string $basis,
        int $page
    ): array {
        $empty = [
            'ok' => true,
            'page' => 1,
            'per_page' => self::PER_PAGE,
            'total' => 0,
            'reservation_count' => 0,
            'item_quantity' => $itemId === null ? null : 0,
            'basis' => $basis,
            'rows' => [],
        ];

        if (!self::tablesReady() || DB::getDriverName() !== 'pgsql') {
            return $empty;
        }

        $where = [
            self::USAGE_START_SQL.' IS NOT NULL',
            self::USAGE_START_SQL.' < ?',
            '('.self::USAGE_END_SQL.') > ?',
        ];
        $bindings = [
            $periodEnd->format('Y-m-d H:i:s'),
            $periodStart->format('Y-m-d H:i:s'),
        ];

        if ($itemId !== null) {
            $where[] = 'lines.item_id = ?';
            $bindings[] = $itemId;
        }

        if ($basis === 'approved') {
            $placeholders = implode(', ', array_fill(0, count(self::APPROVED_STATUSES), '?'));
            $where[] = "LOWER(TRIM(COALESCE(r.overall_status, ''))) IN ({$placeholders})";
            array_push($bindings, ...self::APPROVED_STATUSES);
        }

        $whereSql = implode(' AND ', $where);
        $from = self::equipmentFromSql();

        $totals = DB::selectOne(
            "SELECT COUNT(*) AS line_count, COUNT(DISTINCT lines.reservation_id) AS reservation_count, COALESCE(SUM(lines.quantity), 0) AS quantity FROM {$from} WHERE {$whereSql}",
            $bindings
        );

        $total = (int) ($totals->line_count ?? 0);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        $pageBindings = $bindings;
        $pageBindings[] = self::PER_PAGE;
        $pageBindings[] = $offset;

        $rows = DB::select(
            'SELECT lines.reservation_id, lines.item_id, items.item_name, lines.quantity, r.activity_name, '
            ."LOWER(TRIM(COALESCE(r.overall_status, ''))) AS status, "
            .'r."Start_of_activity" AS start_of_activity, r."Date_of_Activity" AS date_of_activity, r."End_of_Activity" AS end_of_activity '
            ."FROM {$from} WHERE {$whereSql} "
            .'ORDER BY '.self::USAGE_START_SQL.' ASC, lines.reservation_id ASC, items.item_name ASC '
            .'LIMIT ? OFFSET ?',
            $pageBindings
        );

        $presented = [];
        foreach ($rows as $row) {
            $bounds = self::usageBounds(
                self::sqlTimestamp($row->start_of_activity ?? null),
                self::sqlTimestamp($row->date_of_activity ?? null),
                self::sqlTimestamp($row->end_of_activity ?? null)
            );
            if ($bounds === null) {
                continue;
            }

            $presented[] = [
                'reservation_id' => (int) $row->reservation_id,
                'activity_name' => trim((string) ($row->activity_name ?? '')) ?: 'Untitled activity',
                'item_name' => trim((string) ($row->item_name ?? '')) ?: 'Unnamed item',
                'quantity' => self::normalizeQuantity($row->quantity ?? 0),
                'status' => self::statusLabel((string) ($row->status ?? '')),
                'usage_start' => self::formatInstant($bounds['start'], $bounds['calendar_day']),
                'usage_end' => $bounds['calendar_day']
                    ? $bounds['start']->format('M j, Y').' (calendar day)'
                    : self::formatInstant($bounds['end'], false),
            ];
        }

        return [
            'ok' => true,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'reservation_count' => (int) ($totals->reservation_count ?? 0),
            'item_quantity' => $itemId === null ? null : self::normalizeQuantity($totals->quantity ?? 0),
            'basis' => $basis,
            'rows' => $presented,
        ];
    }

    private static function overlapFromSql(): string
    {
        return 'SELECT lines.reservation_id, lines.item_id, items.item_name, lines.quantity, r.activity_name, '
            ."LOWER(TRIM(COALESCE(r.overall_status, ''))) AS status, "
            .'r."Start_of_activity" AS start_of_activity, r."Date_of_Activity" AS date_of_activity, r."End_of_Activity" AS end_of_activity '
            .'FROM '.self::equipmentFromSql().' '
            .'WHERE '.self::USAGE_START_SQL.' IS NOT NULL '
            .'AND '.self::USAGE_START_SQL.' < ? '
            .'AND ('.self::USAGE_END_SQL.') > ?';
    }

    private static function equipmentFromSql(): string
    {
        return <<<'SQL'
(
    SELECT
        d.reservation_id,
        ri.item_id,
        SUM(COALESCE(d.quantity, 0))::bigint AS quantity
    FROM reservation_details d
    INNER JOIN reservation_items ri
        ON ri.reservation_items_id = d.reservation_items_id
    WHERE d.reservation_items_id IS NOT NULL
    GROUP BY d.reservation_id, ri.item_id
) lines
INNER JOIN reservations r ON r.reservation_id = lines.reservation_id
INNER JOIN items ON items.item_id = lines.item_id
SQL;
    }

    private static function tablesReady(): bool
    {
        return Schema::hasTable('reservations')
            && Schema::hasTable('reservation_details')
            && Schema::hasTable('reservation_items')
            && Schema::hasTable('items');
    }

    /**
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    private static function presentPeriod(array $period): array
    {
        /** @var Carbon $start */
        $start = $period['start'];
        /** @var Carbon $end */
        $end = $period['end'];

        return [
            'mode' => $period['mode'],
            'label' => $period['label'],
            'starts_on' => $period['starts_on'],
            'ends_on' => $period['ends_on'],
            'end_exclusive' => $end->format('Y-m-d H:i:s'),
            'weeks' => $period['weeks'],
            'timezone' => self::TIMEZONE,
            'year' => $period['year'] ?? null,
            'month' => $period['month'] ?? null,
            'term_id' => $period['term_id'] ?? null,
            'boundary' => 'Included when usage starts before the period ends and ends after the period starts. Counted once at the full line quantity.',
        ];
    }

    /**
     * @param  array<string, mixed>  $period
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<string, mixed>
     */
    private static function emptyDashboard(array $period, array $terms, string $rank): array
    {
        return [
            'ok' => true,
            'empty' => true,
            'empty_reason' => $period['empty_reason'],
            'period' => [
                'mode' => $period['mode'],
                'label' => $period['label'],
                'starts_on' => null,
                'ends_on' => null,
                'end_exclusive' => null,
                'weeks' => null,
                'timezone' => self::TIMEZONE,
                'year' => null,
                'month' => null,
                'term_id' => null,
                'boundary' => 'Included when usage starts before the period ends and ends after the period starts. Counted once at the full line quantity.',
            ],
            'terms' => $terms,
            'terms_available' => Schema::hasTable('academic_terms'),
            'rank' => $rank,
            'summary' => null,
            'items' => [],
            'chart_items' => [],
            'weekly' => null,
            'availability' => [],
            'insights' => [],
            'definitions' => self::definitions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function unboundedPeriod(string $mode, string $label, string $reason): array
    {
        return [
            'mode' => $mode,
            'label' => $label,
            'empty_reason' => $reason,
        ];
    }

    private static function normalizeStatus(?string $status): string
    {
        return strtolower(trim((string) $status));
    }

    private static function statusLabel(string $status): string
    {
        $normalized = self::normalizeStatus($status);

        return match ($normalized) {
            'approved' => 'Approved',
            'returned' => 'Returned',
            'damaged' => 'Damaged',
            'overdue' => 'Overdue',
            'to return' => 'To return',
            'rejected' => 'Rejected',
            'cancelled', 'canceled' => 'Cancelled',
            'expired', 'timed out' => 'Timed out',
            'pending approval', 'pending_office_approvals', 'awaiting_physical_facilities' => 'Pending',
            '' => 'Blank',
            default => ucfirst($normalized),
        };
    }

    private static function formatInstant(Carbon $instant, bool $calendarDay): string
    {
        if ($calendarDay) {
            return $instant->format('M j, Y');
        }

        return $instant->format('M j, Y g:i A');
    }

    private static function sqlTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }
}
