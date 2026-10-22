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

    /** @var array<int, string> */
    public const TERM_KEYS = ['term-1', 'term-2', 'term-3'];

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
        if (($input['mode'] ?? '') === 'term') {
            self::ensureUpcomingAcademicYears();
        }
        $terms = self::listTerms();
        $period = self::resolvePeriod($input, $terms);

        if (isset($period['empty_reason'])) {
            return self::emptyDashboard($period, $terms, $rank);
        }

        $lines = self::fetchOverlappingLines($period['start'], $period['end']);

        return self::assemble($period, $terms, $lines, $rank, $chartItemId);
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
            ->whereIn('term_key', self::TERM_KEYS)
            ->orderByDesc('academic_year')
            ->orderBy('term_key')
            ->get()
            ->map(fn (AcademicTerm $term): array => self::presentTerm($term))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentTerm(AcademicTerm $term): array
    {
        $startsOn = $term->starts_on?->toDateString();
        $endsOn = $term->ends_on?->toDateString();
        $complete = $startsOn !== null && $endsOn !== null;
        $window = $complete ? self::termWindow($startsOn, $endsOn) : null;

        return [
            'id' => (int) $term->id,
            'academic_year' => (string) $term->academic_year,
            'name' => (string) $term->name,
            'term_key' => trim((string) ($term->term_key ?? '')),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'complete' => $complete,
            'date_note' => $term->date_note ? (string) $term->date_note : null,
            'weeks' => $window ? self::spanWeeks($window['start'], $window['end']) : null,
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

    public static function normalizeTermKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';

        return trim($key, '-');
    }

    /**
     * Earlier academic years that share the configured matching key and have already ended.
     * Display name is not used. A term that has not ended is excluded.
     *
     * @param  array<string, mixed>  $target
     * @param  array<int, array<string, mixed>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    public static function historicalMatches(array $target, array $catalog, string $today): array
    {
        $key = trim((string) ($target['term_key'] ?? ''));
        if ($key === '' || empty($target['starts_on'])) {
            return [];
        }

        $todayDate = Carbon::parse($today, self::TIMEZONE)->startOfDay();
        $targetStart = Carbon::parse((string) $target['starts_on'], self::TIMEZONE)->startOfDay();
        $matches = [];

        foreach ($catalog as $term) {
            if ((int) ($term['id'] ?? 0) === (int) ($target['id'] ?? 0)) {
                continue;
            }
            if (trim((string) ($term['term_key'] ?? '')) !== $key) {
                continue;
            }
            if ((string) ($term['academic_year'] ?? '') === (string) ($target['academic_year'] ?? '')) {
                continue;
            }
            if (empty($term['starts_on']) || empty($term['ends_on'])) {
                continue;
            }

            $start = Carbon::parse((string) $term['starts_on'], self::TIMEZONE)->startOfDay();
            $end = Carbon::parse((string) $term['ends_on'], self::TIMEZONE)->startOfDay();
            if ($end->greaterThanOrEqualTo($todayDate) || $start->greaterThanOrEqualTo($targetStart)) {
                continue;
            }

            $matches[] = $term;
        }

        usort($matches, static fn (array $left, array $right): int => strcmp((string) $left['starts_on'], (string) $right['starts_on']));

        return $matches;
    }

    public static function stockGap(int $baseline, ?int $serviceableStock): ?int
    {
        if ($serviceableStock === null) {
            return null;
        }

        return max(0, $baseline - max(0, $serviceableStock));
    }

    /**
     * The 2026–2027 dates are stored records, not a rule for every future year.
     * Term 1 includes finals week and the break through the day before Term 2.
     * February and June days are not invented.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function officialCalendar(string $academicYear): ?array
    {
        if ($academicYear !== '2026-2027') {
            return null;
        }

        return [
            [
                'term_key' => 'term-1',
                'name' => '1st Term',
                'starts_on' => '2026-07-13',
                'ends_on' => '2026-11-02',
                'date_note' => null,
            ],
            [
                'term_key' => 'term-2',
                'name' => '2nd Term',
                'starts_on' => '2026-11-03',
                'ends_on' => null,
                'date_note' => 'The official end is February 2027. The exact day is not set.',
            ],
            [
                'term_key' => 'term-3',
                'name' => '3rd Term',
                'starts_on' => null,
                'ends_on' => null,
                'date_note' => 'Starts the day after 2nd Term ends, in February 2027, and runs through June 2027. Exact days are not set.',
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<int, array<string, mixed>>
     */
    public static function shiftedTermRows(array $terms): array
    {
        $rows = [];
        foreach ($terms as $term) {
            $startsOn = self::shiftYear($term['starts_on'] ?? null);
            $endsOn = self::shiftYear($term['ends_on'] ?? null);
            $rows[] = [
                'term_key' => (string) ($term['term_key'] ?? ''),
                'name' => (string) ($term['name'] ?? ''),
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'date_note' => ($startsOn !== null && $endsOn !== null)
                    ? 'Copied from the previous academic year. Change any date that moved.'
                    : ((string) ($term['date_note'] ?? '') !== ''
                        ? (string) $term['date_note']
                        : 'Exact dates are still needed.'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $terms
     */
    public static function overlapMessage(array $terms): ?string
    {
        $complete = array_values(array_filter(
            $terms,
            static fn (array $term): bool => !empty($term['starts_on']) && !empty($term['ends_on'])
        ));
        usort($complete, static fn (array $left, array $right): int => strcmp((string) $left['starts_on'], (string) $right['starts_on']));

        $count = count($complete);
        for ($index = 0; $index < $count - 1; $index++) {
            if ((string) $complete[$index + 1]['starts_on'] <= (string) $complete[$index]['ends_on']) {
                return $complete[$index]['name'].' and '.$complete[$index + 1]['name'].' overlap. A term ends the day before the next one starts.';
            }
        }

        return null;
    }

    public static function nextAcademicYear(string $academicYear): string
    {
        [$start, $end] = array_map('intval', explode('-', $academicYear));

        return ($start + 1).'-'.($end + 1);
    }

    public static function dayBefore(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date, self::TIMEZONE)->startOfDay()->subDay()->toDateString();
    }

    public static function dayAfter(?string $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date, self::TIMEZONE)->startOfDay()->addDay()->toDateString();
    }

    /**
     * The term that contains $today. An open term (a start and no end) contains
     * every later day until a following term starts.
     *
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<string, mixed>|null
     */
    public static function termContainingDate(array $terms, string $today): ?array
    {
        $dated = array_values(array_filter(
            $terms,
            static fn (array $term): bool => !empty($term['starts_on'])
        ));
        usort($dated, static fn (array $left, array $right): int => strcmp((string) $left['starts_on'], (string) $right['starts_on']));

        $containing = null;
        foreach ($dated as $index => $term) {
            if ((string) $term['starts_on'] > $today) {
                break;
            }

            $end = $term['ends_on'] ?? null;
            if ($end === null || $end === '') {
                $nextStart = null;
                for ($ahead = $index + 1; $ahead < count($dated); $ahead++) {
                    if (!empty($dated[$ahead]['starts_on'])) {
                        $nextStart = (string) $dated[$ahead]['starts_on'];
                        break;
                    }
                }
                if ($nextStart === null || $today < $nextStart) {
                    $containing = $term;
                }
                continue;
            }

            if ($today <= (string) $end) {
                $containing = $term;
            }
        }

        return $containing;
    }

    /**
     * Create the academic year after the one that contains today, when it is missing.
     * Existing dates are left as stored. A failure leaves the report on the terms already saved.
     */
    public static function ensureUpcomingAcademicYears(?Carbon $today = null): void
    {
        if (!Schema::hasTable('academic_terms')) {
            return;
        }

        try {
            $todayString = ($today ?? Carbon::now(self::TIMEZONE))->toDateString();
            $currentYear = AcademicTerm::query()
                ->where('term_key', 'term-1')
                ->whereNotNull('starts_on')
                ->whereDate('starts_on', '<=', $todayString)
                ->orderByDesc('starts_on')
                ->value('academic_year');

            if (!is_string($currentYear) || $currentYear === '') {
                return;
            }

            DB::transaction(function () use ($currentYear): void {
                self::ensureYearCopiedFrom(self::nextAcademicYear($currentYear), $currentYear);
                self::fillOpenBoundaries();
            });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public static function ensureYearCopiedFrom(string $academicYear, string $sourceYear): void
    {
        $source = AcademicTerm::query()
            ->where('academic_year', $sourceYear)
            ->whereIn('term_key', self::TERM_KEYS)
            ->get()
            ->keyBy(static fn (AcademicTerm $term): string => (string) $term->term_key);

        $templates = $source->isEmpty()
            ? self::emptyTermSlots()
            : self::shiftedTermRows($source->map(static fn (AcademicTerm $term): array => [
                'term_key' => (string) $term->term_key,
                'name' => (string) $term->name,
                'starts_on' => $term->starts_on?->toDateString(),
                'ends_on' => $term->ends_on?->toDateString(),
                'date_note' => $term->date_note,
            ])->values()->all());

        $byKey = [];
        foreach ($templates as $row) {
            $key = (string) ($row['term_key'] ?? '');
            if (in_array($key, self::TERM_KEYS, true)) {
                $byKey[$key] = $row;
            }
        }
        foreach (self::emptyTermSlots() as $slot) {
            $byKey[$slot['term_key']] ??= $slot;
        }

        foreach (self::TERM_KEYS as $key) {
            AcademicTerm::query()->firstOrCreate(
                ['academic_year' => $academicYear, 'term_key' => $key],
                $byKey[$key]
            );
        }
    }

    /**
     * Fill a missing boundary from the next stored start. Stored dates stay put.
     */
    public static function fillOpenBoundaries(): void
    {
        $terms = AcademicTerm::query()
            ->whereIn('term_key', self::TERM_KEYS)
            ->orderBy('academic_year')
            ->orderBy('term_key')
            ->get();

        $indexed = [];
        foreach ($terms as $term) {
            $indexed[$term->academic_year.'|'.$term->term_key] = $term;
        }

        foreach ($terms as $term) {
            $next = self::followingTerm($indexed, (string) $term->academic_year, (string) $term->term_key);
            if ($next === null || $next->starts_on === null || $term->ends_on !== null) {
                continue;
            }

            $end = self::dayBefore($next->starts_on->toDateString());
            if ($term->starts_on !== null && $end < $term->starts_on->toDateString()) {
                continue;
            }

            $term->ends_on = $end;
            $term->save();
        }
    }

    /**
     * @param  array<string, AcademicTerm>  $indexed
     */
    private static function followingTerm(array $indexed, string $academicYear, string $termKey): ?AcademicTerm
    {
        $nextKey = match ($termKey) {
            'term-1' => 'term-2',
            'term-2' => 'term-3',
            'term-3' => 'term-1',
            default => null,
        };
        if ($nextKey === null) {
            return null;
        }

        $year = $termKey === 'term-3' ? self::nextAcademicYear($academicYear) : $academicYear;

        return $indexed[$year.'|'.$nextKey] ?? null;
    }

    /**
     * Moving a start pulls the previous term's end to the day before.
     * Moving an end pushes the next term's start to the next day.
     *
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<int, array<string, mixed>>
     */
    public static function withBoundaryMoves(array $terms, string $academicYear, string $termKey, ?string $startsOn, ?string $endsOn): array
    {
        $projected = $terms;
        foreach ($projected as $index => $term) {
            if ((string) ($term['academic_year'] ?? '') === $academicYear && (string) ($term['term_key'] ?? '') === $termKey) {
                $projected[$index]['starts_on'] = $startsOn;
                $projected[$index]['ends_on'] = $endsOn;
            }
        }

        if ($startsOn !== null) {
            $previous = self::adjacentKey($academicYear, $termKey, false);
            $projected = self::setProjectedDate($projected, $previous['year'], $previous['key'], 'ends_on', self::dayBefore($startsOn));
        }

        if ($endsOn !== null) {
            $next = self::adjacentKey($academicYear, $termKey, true);
            $projected = self::setProjectedDate($projected, $next['year'], $next['key'], 'starts_on', self::dayAfter($endsOn));
        }

        return $projected;
    }

    /**
     * @return array{year: string, key: string}
     */
    public static function adjacentKey(string $academicYear, string $termKey, bool $next): array
    {
        $order = self::TERM_KEYS;
        $index = array_search($termKey, $order, true);
        if ($index === false) {
            return ['year' => $academicYear, 'key' => $termKey];
        }

        if ($next) {
            if ($index === count($order) - 1) {
                return ['year' => self::nextAcademicYear($academicYear), 'key' => $order[0]];
            }

            return ['year' => $academicYear, 'key' => $order[$index + 1]];
        }

        if ($index === 0) {
            return ['year' => self::previousAcademicYear($academicYear), 'key' => $order[count($order) - 1]];
        }

        return ['year' => $academicYear, 'key' => $order[$index - 1]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $terms
     * @return array<int, array<string, mixed>>
     */
    private static function setProjectedDate(array $terms, string $academicYear, string $termKey, string $field, ?string $value): array
    {
        foreach ($terms as $index => $term) {
            if ((string) ($term['academic_year'] ?? '') === $academicYear && (string) ($term['term_key'] ?? '') === $termKey) {
                $terms[$index][$field] = $value;
            }
        }

        return $terms;
    }

    public static function saveTermDates(AcademicTerm $term, ?string $startsOn, ?string $endsOn): AcademicTerm
    {
        $stored = AcademicTerm::query()
            ->whereIn('term_key', self::TERM_KEYS)
            ->get();
        $rows = $stored->map(static fn (AcademicTerm $row): array => [
            'id' => (int) $row->id,
            'academic_year' => (string) $row->academic_year,
            'term_key' => (string) $row->term_key,
            'name' => (string) $row->name,
            'starts_on' => $row->starts_on?->toDateString(),
            'ends_on' => $row->ends_on?->toDateString(),
        ])->all();

        $projected = self::withBoundaryMoves($rows, (string) $term->academic_year, (string) $term->term_key, $startsOn, $endsOn);

        foreach ($projected as $row) {
            if (!empty($row['starts_on']) && !empty($row['ends_on']) && (string) $row['ends_on'] < (string) $row['starts_on']) {
                throw ValidationException::withMessages([
                    'starts_on' => $row['name'].' would end before it starts.',
                ]);
            }
        }

        $years = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['academic_year'], $projected)));
        foreach ($years as $year) {
            $overlap = self::overlapMessage(array_values(array_filter(
                $projected,
                static fn (array $row): bool => (string) $row['academic_year'] === $year
            )));
            if ($overlap !== null) {
                throw ValidationException::withMessages([
                    'ends_on' => $overlap,
                ]);
            }
        }

        $original = [];
        foreach ($rows as $row) {
            $original[(int) $row['id']] = $row;
        }

        DB::transaction(function () use ($projected, $original): void {
            foreach ($projected as $row) {
                $id = (int) ($row['id'] ?? 0);
                $before = $original[$id] ?? null;
                if ($before === null) {
                    continue;
                }
                if (($before['starts_on'] ?? null) === ($row['starts_on'] ?? null) && ($before['ends_on'] ?? null) === ($row['ends_on'] ?? null)) {
                    continue;
                }

                $model = AcademicTerm::query()->find($id);
                if ($model === null) {
                    continue;
                }
                $model->starts_on = $row['starts_on'] ?: null;
                $model->ends_on = $row['ends_on'] ?: null;
                if ($model->starts_on !== null && $model->ends_on !== null) {
                    $model->date_note = null;
                }
                $model->save();
            }
        });

        return $term->refresh();
    }

    public static function createAcademicYear(string $academicYear): void
    {
        $rows = self::officialCalendar($academicYear);
        if ($rows === null) {
            $previous = self::previousAcademicYear($academicYear);
            $existing = AcademicTerm::query()
                ->where('academic_year', $previous)
                ->whereIn('term_key', self::TERM_KEYS)
                ->orderBy('term_key')
                ->get()
                ->map(static fn (AcademicTerm $term): array => [
                    'term_key' => (string) $term->term_key,
                    'name' => (string) $term->name,
                    'starts_on' => $term->starts_on?->toDateString(),
                    'ends_on' => $term->ends_on?->toDateString(),
                    'date_note' => $term->date_note,
                ])
                ->all();
            $rows = $existing !== []
                ? self::shiftedTermRows($existing)
                : self::emptyTermSlots();
        }

        foreach ($rows as $row) {
            AcademicTerm::query()->create($row + ['academic_year' => $academicYear]);
        }
    }

    public static function previousAcademicYear(string $academicYear): string
    {
        [$start, $end] = array_map('intval', explode('-', $academicYear));

        return ($start - 1).'-'.($end - 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function emptyTermSlots(): array
    {
        return [
            ['term_key' => 'term-1', 'name' => '1st Term', 'starts_on' => null, 'ends_on' => null, 'date_note' => 'Set the start and end dates.'],
            ['term_key' => 'term-2', 'name' => '2nd Term', 'starts_on' => null, 'ends_on' => null, 'date_note' => 'Set the start and end dates.'],
            ['term_key' => 'term-3', 'name' => '3rd Term', 'starts_on' => null, 'ends_on' => null, 'date_note' => 'Set the start and end dates.'],
        ];
    }

    private static function shiftYear(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, self::TIMEZONE)->addYear()->toDateString();
    }

    /**
     * Highest observed peak across completed matching terms.
     *
     * @param  array<int, array{label: string, peak: int, reservations: int, approved_quantity: int}>  $observations
     * @return array{peak: int, reservation_count: int, approved_quantity: int, source_label: string, source_labels: array<int, string>, terms_at_peak: int, term_count: int}|null
     */
    public static function conservativeBaseline(array $observations): ?array
    {
        if ($observations === []) {
            return null;
        }

        $peak = max(array_map(static fn (array $row): int => (int) $row['peak'], $observations));
        $atPeak = array_values(array_filter(
            $observations,
            static fn (array $row): bool => (int) $row['peak'] === $peak
        ));
        $source = $atPeak[count($atPeak) - 1];

        return [
            'peak' => $peak,
            'reservation_count' => (int) $source['reservations'],
            'approved_quantity' => (int) $source['approved_quantity'],
            'source_label' => (string) $source['label'],
            'source_labels' => array_map(static fn (array $row): string => (string) $row['label'], $atPeak),
            'terms_at_peak' => count($atPeak),
            'term_count' => count($observations),
        ];
    }

    /**
     * @param  array<int, string>  $sourceLabels
     * @return array<int, string>
     */
    public static function planningSentences(
        string $itemName,
        string $targetLabel,
        int $peak,
        string $sourceLabel,
        array $sourceLabels,
        int $termCount,
        int $termsAtPeak,
        ?int $serviceableStock,
        ?int $historicalGap,
        bool $hasConfirmed,
        ?int $confirmedPeak,
        ?int $confirmedGap
    ): array {
        $sentences = [];

        if ($serviceableStock === null || $historicalGap === null) {
            $sentences[] = sprintf(
                'Current serviceable stock is not recorded, so a historical planning gap cannot be calculated for %s. During %s, peak simultaneous approved demand was %d units.',
                $itemName,
                $sourceLabel,
                $peak
            );
        } elseif ($historicalGap > 0) {
            $unitWord = $historicalGap === 1 ? 'unit' : 'units';
            $sentences[] = sprintf(
                'Potential shortage for %s: %s reached a peak simultaneous demand of %d units during %s. Current serviceable stock is %d units. If similar demand recurs, there would be a gap of %d %s. Consider adding %d serviceable %s or adjusting reservation schedules.',
                $targetLabel,
                $itemName,
                $peak,
                $sourceLabel,
                $serviceableStock,
                $historicalGap,
                $unitWord,
                $historicalGap,
                $unitWord
            );
        } else {
            $sentences[] = sprintf(
                'Historical demand covered by current stock. %s reached a peak simultaneous demand of %d units during %s, and current serviceable stock is %d units.',
                $itemName,
                $peak,
                $sourceLabel,
                $serviceableStock
            );
        }

        if ($hasConfirmed && $confirmedGap !== null && $confirmedGap > 0) {
            $confirmedUnit = $confirmedGap === 1 ? 'unit' : 'units';
            $sentences[] = sprintf(
                'Confirmed bookings for %s during %s reach a separate peak of %d %s. The confirmed booking gap is %d %s.',
                $itemName,
                $targetLabel,
                (int) $confirmedPeak,
                (int) $confirmedPeak === 1 ? 'unit' : 'units',
                $confirmedGap,
                $confirmedUnit
            );
        }

        return $sentences;
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

            if ($termId <= 0) {
                $selected = self::termContainingDate($terms, Carbon::now(self::TIMEZONE)->toDateString());
            }

            if ($termId > 0 && $selected === null) {
                throw ValidationException::withMessages([
                    'term_id' => 'That academic term was not found.',
                ]);
            }

            $selected ??= $terms[0];
            if (empty($selected['starts_on']) || empty($selected['ends_on'])) {
                return [
                    'mode' => 'term',
                    'label' => $selected['label'],
                    'empty_reason' => 'incomplete_term',
                    'date_note' => $selected['date_note']
                        ?? 'The exact start or end day is not set.',
                    'term_id' => (int) $selected['id'],
                    'starts_on' => $selected['starts_on'] ?? null,
                    'ends_on' => $selected['ends_on'] ?? null,
                ];
            }

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
                'serviceable_stock' => $serviceable,
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
            'planning' => ($period['mode'] ?? '') === 'term'
                ? self::buildPlanning($period, $terms, $lines)
                : self::planningNotApplicable(),
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
     * @param  array<string, mixed>  $period
     * @param  array<int, array<string, mixed>>  $terms
     * @param  array<int, array<string, mixed>>  $targetLines
     * @return array<string, mixed>
     */
    private static function buildPlanning(array $period, array $terms, array $targetLines): array
    {
        $target = null;
        foreach ($terms as $term) {
            if ((int) ($term['id'] ?? 0) === (int) ($period['term_id'] ?? 0)) {
                $target = $term;
                break;
            }
        }

        $basis = self::planningBasis();
        if ($target === null) {
            return self::planningInsufficient('Insufficient historical data for term planning.', 'The selected term is not in the saved term list.', null, $basis);
        }

        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $matches = self::historicalMatches($target, $terms, $today);
        $targetView = self::planningTarget($target);

        if (trim((string) ($target['term_key'] ?? '')) === '') {
            return self::planningInsufficient(
                'Insufficient historical data for term planning.',
                'This term has no matching key. Set the same matching key on this term and on the completed term from an earlier academic year. The display name is not used for matching.',
                $targetView,
                $basis
            );
        }

        if ($matches === []) {
            return self::planningInsufficient(
                'Insufficient historical data for term planning.',
                'No completed term from an earlier academic year uses the matching key “'.$target['term_key'].'”. A term that has not ended is not used as history.',
                $targetView,
                $basis
            );
        }

        $historicalByTerm = [];
        foreach ($matches as $match) {
            $window = self::termWindow((string) $match['starts_on'], (string) $match['ends_on']);
            $historicalByTerm[] = [
                'term' => $match,
                'items' => self::approvedItemStats(self::fetchOverlappingLines($window['start'], $window['end'])),
            ];
        }

        $confirmed = self::approvedItemStats($targetLines);
        $itemIds = [];
        foreach ($historicalByTerm as $block) {
            foreach ($block['items'] as $itemId => $stats) {
                $itemIds[(int) $itemId] = (string) $stats['item_name'];
            }
        }

        $stock = $itemIds === [] ? [] : self::serviceableStock(array_keys($itemIds));
        $stockKnown = $itemIds !== [] && $stock !== null;
        $rows = [];

        foreach ($itemIds as $itemId => $fallbackName) {
            $observations = [];
            $name = $fallbackName;
            foreach ($historicalByTerm as $block) {
                $stats = $block['items'][$itemId] ?? null;
                if ($stats !== null) {
                    $name = (string) $stats['item_name'];
                }
                $observations[] = [
                    'label' => (string) $block['term']['label'],
                    'peak' => (int) ($stats['peak'] ?? 0),
                    'reservations' => (int) ($stats['reservation_count'] ?? 0),
                    'approved_quantity' => (int) ($stats['approved_quantity'] ?? 0),
                ];
            }

            $baseline = self::conservativeBaseline($observations);
            if ($baseline === null) {
                continue;
            }

            $serviceable = $stockKnown ? (int) ($stock[$itemId] ?? 0) : null;
            $gap = self::stockGap($baseline['peak'], $serviceable);
            $confirmedStats = $confirmed[$itemId] ?? null;
            $hasConfirmed = $confirmedStats !== null && (int) $confirmedStats['reservation_count'] > 0;
            $confirmedPeak = $hasConfirmed ? (int) $confirmedStats['peak'] : null;
            $confirmedGap = $hasConfirmed ? self::stockGap((int) $confirmedPeak, $serviceable) : null;

            $rows[] = [
                'item_id' => $itemId,
                'item_name' => $name,
                'historical_reservation_count' => $baseline['reservation_count'],
                'historical_approved_quantity' => $baseline['approved_quantity'],
                'historical_peak' => $baseline['peak'],
                'baseline_term' => $baseline['source_label'],
                'baseline_terms' => $baseline['source_labels'],
                'terms_at_peak' => $baseline['terms_at_peak'],
                'historical_term_count' => $baseline['term_count'],
                'history' => $observations,
                'serviceable_stock' => $serviceable,
                'historical_gap' => $gap,
                'issued_label' => 'Not recorded',
                'confirmed_peak' => $confirmedPeak,
                'confirmed_reservations' => $hasConfirmed ? (int) $confirmedStats['reservation_count'] : null,
                'confirmed_gap' => $confirmedGap,
                'sentences' => self::planningSentences(
                    $name,
                    (string) $targetView['label'],
                    $baseline['peak'],
                    $baseline['source_label'],
                    $baseline['source_labels'],
                    $baseline['term_count'],
                    $baseline['terms_at_peak'],
                    $serviceable,
                    $gap,
                    $hasConfirmed,
                    $confirmedPeak,
                    $confirmedGap
                ),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [($right['historical_gap'] ?? -1), $right['historical_peak'], $left['item_name']]
                <=> [($left['historical_gap'] ?? -1), $left['historical_peak'], $right['item_name']];
        });

        $hasGap = false;
        foreach ($rows as $row) {
            if (($row['historical_gap'] ?? 0) > 0) {
                $hasGap = true;
                break;
            }
        }

        return [
            'applicable' => true,
            'status' => $rows === [] ? 'insufficient_history' : ($hasGap ? 'potential_shortage' : 'covered'),
            'message' => $rows === []
                ? 'Insufficient historical data for term planning.'
                : ($hasGap
                    ? 'Some items would exceed current serviceable stock if similar demand recurs.'
                    : 'Historical demand covered by current stock.'),
            'detail' => $rows === []
                ? 'The completed matching terms have no approved equipment reservations.'
                : 'The historical gap is the highest peak from the completed matching terms, compared with current stock. Confirmed bookings already in this term are listed separately and are not added to that peak.',
            'target' => $targetView,
            'references' => array_map(static fn (array $match): array => [
                'id' => (int) $match['id'],
                'label' => (string) $match['label'],
                'academic_year' => (string) $match['academic_year'],
                'name' => (string) $match['name'],
                'term_key' => (string) $match['term_key'],
                'starts_on' => (string) $match['starts_on'],
                'ends_on' => (string) $match['ends_on'],
            ], $matches),
            'items' => $rows,
            'basis' => $basis,
            'unmet' => self::unmetDemandNote(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $target
     * @return array<string, mixed>
     */
    private static function planningInsufficient(string $message, string $detail, ?array $target, string $basis): array
    {
        return [
            'applicable' => true,
            'status' => 'insufficient_history',
            'message' => $message,
            'detail' => $detail,
            'target' => $target,
            'references' => [],
            'items' => [],
            'basis' => $basis,
            'unmet' => self::unmetDemandNote(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function planningNotApplicable(): array
    {
        return [
            'applicable' => false,
            'status' => 'not_applicable',
            'message' => '',
            'detail' => '',
            'target' => null,
            'references' => [],
            'items' => [],
            'basis' => '',
            'unmet' => self::unmetDemandNote(),
        ];
    }

    /**
     * @param  array<string, mixed>  $term
     * @return array<string, mixed>
     */
    private static function planningTarget(array $term): array
    {
        return [
            'id' => (int) ($term['id'] ?? 0),
            'label' => (string) ($term['label'] ?? ''),
            'academic_year' => (string) ($term['academic_year'] ?? ''),
            'name' => (string) ($term['name'] ?? ''),
            'term_key' => (string) ($term['term_key'] ?? ''),
            'starts_on' => (string) ($term['starts_on'] ?? ''),
            'ends_on' => (string) ($term['ends_on'] ?? ''),
        ];
    }

    private static function planningBasis(): string
    {
        return 'This is a rule-based historical planning indicator, not a forecast. It does not mean an item will be reserved again. The baseline is the highest peak simultaneous approved quantity in a completed matching term. Cumulative term quantity is not treated as the number of units to own. Current serviceable stock is today\'s count of available and in-use units, not the stock on those past dates. Checkout quantities are not recorded.';
    }

    /**
     * @return array{available: bool, note: string}
     */
    private static function unmetDemandNote(): array
    {
        return [
            'available' => false,
            'note' => 'Rejections store a free-text note, not a reason code for insufficient equipment. Rejected and cancelled requests are excluded from approved demand and are not counted as unmet stock. Approved history may understate total demand.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private static function approvedItemStats(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            if (!($line['approved'] ?? false)) {
                continue;
            }

            $itemId = (int) $line['item_id'];
            $quantity = self::normalizeQuantity($line['quantity'] ?? 0);
            $items[$itemId] ??= [
                'item_id' => $itemId,
                'item_name' => (string) ($line['item_name'] ?? 'Unnamed item'),
                'reservations' => [],
                'approved_quantity' => 0,
                'intervals' => [],
            ];
            $items[$itemId]['approved_quantity'] += $quantity;
            $items[$itemId]['reservations'][(int) $line['reservation_id']] = true;
            $items[$itemId]['intervals'][] = [
                'start' => $line['start'],
                'end' => $line['end'],
                'quantity' => $quantity,
                'activity' => (string) ($line['activity_name'] ?? ''),
            ];
        }

        foreach ($items as $itemId => $item) {
            $peak = self::peakDemand($item['intervals']);
            $items[$itemId] = [
                'item_id' => $itemId,
                'item_name' => $item['item_name'],
                'reservation_count' => count($item['reservations']),
                'approved_quantity' => (int) $item['approved_quantity'],
                'peak' => $peak['peak'],
            ];
        }

        return $items;
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
                'date_note' => $period['date_note'] ?? null,
                'starts_on' => $period['starts_on'] ?? null,
                'ends_on' => $period['ends_on'] ?? null,
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
            'detail' => $period['date_note'] ?? null,
            'period' => [
                'mode' => $period['mode'],
                'label' => $period['label'],
                'starts_on' => $period['starts_on'] ?? null,
                'ends_on' => $period['ends_on'] ?? null,
                'end_exclusive' => null,
                'weeks' => null,
                'timezone' => self::TIMEZONE,
                'year' => null,
                'month' => null,
                'term_id' => $period['term_id'] ?? null,
                'date_note' => $period['date_note'] ?? null,
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
            'planning' => self::planningNotApplicable(),
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
