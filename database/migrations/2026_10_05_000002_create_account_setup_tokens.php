<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First-time password setup tokens, plus a narrow exception on
 * private.guard_user_profile_changes() so only that setup transaction
 * can change public.users.password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_setup_tokens', function ($table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  EXECUTE 'REVOKE ALL ON TABLE public.account_setup_tokens FROM PUBLIC';
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    EXECUTE 'REVOKE ALL ON TABLE public.account_setup_tokens FROM anon';
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    EXECUTE 'REVOKE ALL ON TABLE public.account_setup_tokens FROM authenticated';
  END IF;
END $$;
SQL);

        $digestSchema = DB::selectOne(<<<'SQL'
SELECT n.nspname AS schema
FROM pg_proc p
JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE p.proname = 'digest'
  AND n.nspname IN ('extensions', 'public')
ORDER BY CASE n.nspname WHEN 'extensions' THEN 0 ELSE 1 END
LIMIT 1
SQL);

        $schema = $digestSchema->schema ?? null;
        if (!in_array($schema, ['extensions', 'public'], true)) {
            throw new RuntimeException('PostgreSQL digest() was not found in extensions or public. The setup-token trigger was not installed.');
        }

        DB::unprepared($this->guardFunctionSql($schema));
    }

    public function down(): void
    {
        DB::unprepared($this->previousGuardFunctionSql());

        Schema::dropIfExists('account_setup_tokens');
    }

    private function guardFunctionSql(string $digestSchema): string
    {
        return <<<SQL
CREATE OR REPLACE FUNCTION private.guard_user_profile_changes()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path TO 'pg_catalog', 'public', 'auth'
AS \$function\$
BEGIN
  IF auth.jwt() ->> 'role' = 'service_role'
     OR private.is_global_admin() THEN
    RETURN NEW;
  END IF;

  IF NEW.user_id IS NOT DISTINCT FROM OLD.user_id
     AND NEW.username IS NOT DISTINCT FROM OLD.username
     AND NEW.role IS NOT DISTINCT FROM OLD.role
     AND NEW.office_id IS NOT DISTINCT FROM OLD.office_id
     AND NEW.is_active IS NOT DISTINCT FROM OLD.is_active
     AND NEW.status_changed_at IS NOT DISTINCT FROM OLD.status_changed_at
     AND NEW.password IS DISTINCT FROM OLD.password
     AND coalesce(current_setting('nutilize.setup_token', true), '') <> ''
     AND EXISTS (
          SELECT 1
          FROM public.account_setup_tokens AS setup_token
          WHERE setup_token.user_id = OLD.user_id
            AND setup_token.token_hash = encode(
                  {$digestSchema}.digest(
                      current_setting('nutilize.setup_token', true),
                      'sha256'
                  ),
                  'hex'
            )
            AND setup_token.expires_at > now()
     ) THEN
    RETURN NEW;
  END IF;

  IF NEW.user_id IS DISTINCT FROM OLD.user_id
     OR NEW.username IS DISTINCT FROM OLD.username
     OR NEW.role IS DISTINCT FROM OLD.role
     OR NEW.office_id IS DISTINCT FROM OLD.office_id
     OR NEW.is_active IS DISTINCT FROM OLD.is_active
     OR NEW.status_changed_at IS DISTINCT FROM OLD.status_changed_at
     OR NEW.password IS DISTINCT FROM OLD.password THEN
    RAISE EXCEPTION 'Only administrators may change protected profile fields.';
  END IF;

  RETURN NEW;
END;
\$function\$;
SQL;
    }

    private function previousGuardFunctionSql(): string
    {
        return <<<'SQL'
CREATE OR REPLACE FUNCTION private.guard_user_profile_changes()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path TO 'pg_catalog', 'public', 'auth'
AS $function$
BEGIN
  IF auth.jwt() ->> 'role' = 'service_role'
     OR private.is_global_admin() THEN
    RETURN NEW;
  END IF;

  IF NEW.user_id IS DISTINCT FROM OLD.user_id
     OR NEW.username IS DISTINCT FROM OLD.username
     OR NEW.role IS DISTINCT FROM OLD.role
     OR NEW.office_id IS DISTINCT FROM OLD.office_id
     OR NEW.is_active IS DISTINCT FROM OLD.is_active
     OR NEW.status_changed_at IS DISTINCT FROM OLD.status_changed_at
     OR NEW.password IS DISTINCT FROM OLD.password THEN
    RAISE EXCEPTION 'Only administrators may change protected profile fields.';
  END IF;

  RETURN NEW;
END;
$function$;
SQL;
    }
};
