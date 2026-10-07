<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email changes stay blocked by private.guard_user_profile_changes()
 * unless this transaction presents the code stored for that account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('email_otps', 'purpose')) {
            Schema::table('email_otps', function (Blueprint $table) {
                $table->text('purpose')->nullable();
            });
        }

        $schema = $this->digestSchema();
        $withSetupToken = Schema::hasTable('account_setup_tokens');

        DB::unprepared($this->functionSql($schema, $withSetupToken));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
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
     OR lower(NEW.email) IS DISTINCT FROM lower(OLD.email)
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
SQL);
    }

    private function digestSchema(): string
    {
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
            throw new RuntimeException('PostgreSQL digest() was not found in extensions or public.');
        }

        return $schema;
    }

    private function functionSql(string $digestSchema, bool $withSetupToken): string
    {
        $setupBranch = $withSetupToken ? <<<SQL
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

SQL
        : '';

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

{$setupBranch}  IF lower(NEW.email) IS DISTINCT FROM lower(OLD.email)
     AND NEW.user_id IS NOT DISTINCT FROM OLD.user_id
     AND NEW.username IS NOT DISTINCT FROM OLD.username
     AND NEW.role IS NOT DISTINCT FROM OLD.role
     AND NEW.office_id IS NOT DISTINCT FROM OLD.office_id
     AND NEW.is_active IS NOT DISTINCT FROM OLD.is_active
     AND NEW.status_changed_at IS NOT DISTINCT FROM OLD.status_changed_at
     AND NEW.password IS NOT DISTINCT FROM OLD.password
     AND coalesce(current_setting('nutilize.email_change_code', true), '') <> ''
     AND EXISTS (
          SELECT 1
          FROM public.email_otps AS email_otp
          WHERE lower(email_otp.email) = lower(NEW.email)
            AND email_otp.purpose = 'email_change:' || OLD.user_id::text
            AND email_otp.code = encode(
                  {$digestSchema}.digest(
                      current_setting('nutilize.email_change_code', true),
                      'sha256'
                  ),
                  'hex'
            )
            AND email_otp.expires_at > now()
     ) THEN
    RETURN NEW;
  END IF;

  IF NEW.user_id IS DISTINCT FROM OLD.user_id
     OR lower(NEW.email) IS DISTINCT FROM lower(OLD.email)
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
};
