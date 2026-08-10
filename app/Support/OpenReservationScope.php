<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

class OpenReservationScope
{
    /** @var array<int, string> */
    public const CLOSED_STATUSES = [
        'approved',
        'rejected',
        'returned',
        'damaged',
        'cancelled',
        'canceled',
        'expired',
    ];

    /**
     * Apply the "still open" filter for reservations.overall_status.
     *
     * The SQL is emitted with inlined literals rather than bindings so that it is
     * byte-for-byte identical to the predicate of the partial index
     * `reservations_open_created_at_idx`. Postgres only uses a partial index when it
     * can prove the query predicate implies the index predicate, and the cheapest
     * proof is structural equality — a bound parameter would break that match.
     */
    public static function apply(QueryBuilder|EloquentBuilder $query, string $column = 'overall_status'): QueryBuilder|EloquentBuilder
    {
        return $query->whereRaw(self::rawPredicate($column));
    }

    /**
     * The predicate shared by the query builder and the partial index definition.
     * Keep this the single source of truth: changing it requires rebuilding the index.
     */
    public static function rawPredicate(string $column = 'overall_status'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new InvalidArgumentException("Unsupported column reference: {$column}");
        }

        $normalized = "LOWER(COALESCE({$column}, ''))";
        $closed = implode(', ', array_map(
            static fn (string $status): string => "'" . str_replace("'", "''", $status) . "'",
            self::CLOSED_STATUSES
        ));

        return "({$normalized} NOT IN ({$closed}) AND {$normalized} NOT LIKE 'cancel%')";
    }
}
