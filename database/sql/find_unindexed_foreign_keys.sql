-- Lists foreign keys in `public` whose leading column(s) are NOT covered by any index.
-- Run in Supabase -> SQL Editor. Every row returned is a candidate for a new index.

SELECT
    c.conrelid::regclass  AS table_name,
    c.conname             AS fk_constraint,
    ARRAY(
        SELECT a.attname
        FROM unnest(c.conkey) WITH ORDINALITY AS k(attnum, ord)
        JOIN pg_attribute a
          ON a.attrelid = c.conrelid
         AND a.attnum = k.attnum
        ORDER BY k.ord
    ) AS fk_columns,
    pg_size_pretty(pg_relation_size(c.conrelid)) AS table_size
FROM pg_constraint c
JOIN pg_class t ON t.oid = c.conrelid
JOIN pg_namespace n ON n.oid = t.relnamespace
WHERE c.contype = 'f'
  AND n.nspname = 'public'
  AND NOT EXISTS (
      SELECT 1
      FROM pg_index i
      WHERE i.indrelid = c.conrelid
        -- index must start with the FK columns (leading-column rule)
        AND (i.indkey::smallint[])[0:array_length(c.conkey, 1) - 1] @> c.conkey
  )
ORDER BY pg_relation_size(c.conrelid) DESC, table_name;
