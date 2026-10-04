<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * public.users email changes were rejected by private.guard_user_profile_changes()
 * even for the signed-in account. Email stays unique. Role, username, password,
 * and office remain protected. Supabase Auth users still cannot set a different
 * email through the API because users_update_own_profile requires the new email
 * to match the JWT email.
 */
return new class extends Migration
{
    public function up(): void
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
};
