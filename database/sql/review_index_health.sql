-- Index + vacuum health review for NUTilize (Supabase).
-- READ-ONLY. Run in Supabase -> SQL Editor. Nothing here drops anything.

-- 1) Unused indexes (candidates for removal AFTER a representative observation period).
--    Do NOT drop immediately: idx_scan resets on stats reset / restart.
SELECT
    s.relname            AS table_name,
    s.indexrelname       AS index_name,
    s.idx_scan           AS times_used,
    pg_size_pretty(pg_relation_size(s.indexrelid)) AS index_size,
    i.indisunique        AS is_unique,
    i.indisprimary       AS is_primary
FROM pg_stat_user_indexes s
JOIN pg_index i ON i.indexrelid = s.indexrelid
WHERE s.schemaname = 'public'
  AND NOT i.indisprimary
  AND NOT i.indisunique          -- never drop constraint-backing indexes
ORDER BY s.idx_scan ASC, pg_relation_size(s.indexrelid) DESC;

-- 2) When were stats last reset? (context for the idx_scan numbers above)
SELECT stats_reset FROM pg_stat_database WHERE datname = current_database();

-- 3) Dead tuples / autovacuum health.
SELECT
    relname                AS table_name,
    n_live_tup             AS live_rows,
    n_dead_tup             AS dead_rows,
    CASE WHEN n_live_tup > 0
         THEN round(100.0 * n_dead_tup / n_live_tup, 2)
         ELSE 0 END        AS dead_pct,
    last_autovacuum,
    last_autoanalyze
FROM pg_stat_user_tables
WHERE schemaname = 'public'
ORDER BY n_dead_tup DESC;

-- 4) Long-running transactions (these block autovacuum and hold connections).
SELECT
    pid,
    now() - xact_start AS transaction_age,
    state,
    left(query, 160)   AS query_preview
FROM pg_stat_activity
WHERE datname = current_database()
  AND xact_start IS NOT NULL
  AND now() - xact_start > interval '30 seconds'
ORDER BY xact_start;

-- 5) Connection usage vs limit (connection pressure check).
SELECT
    (SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()) AS active_connections,
    current_setting('max_connections')                                          AS max_connections;
