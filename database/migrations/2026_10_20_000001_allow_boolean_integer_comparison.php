<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION public.nutilize_bool_eq_int(boolean, integer)
RETURNS boolean
LANGUAGE sql
IMMUTABLE
PARALLEL SAFE
AS $$ SELECT $1 = ($2 <> 0) $$;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_operator
    WHERE oprname = '='
      AND oprleft = 'boolean'::regtype
      AND oprright = 'integer'::regtype
  ) THEN
    CREATE OPERATOR public.= (
      FUNCTION = public.nutilize_bool_eq_int,
      LEFTARG = boolean,
      RIGHTARG = integer
    );
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
DROP OPERATOR IF EXISTS public.= (boolean, integer);
DROP FUNCTION IF EXISTS public.nutilize_bool_eq_int(boolean, integer);
SQL);
    }
};
