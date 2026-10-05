<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers the hash of a setup token after the active row is deleted,
 * so a reused link can be distinguished from a link that never existed.
 * The raw token is not stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_setup_token_redemptions', function ($table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('consumed_at');
        });

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
  EXECUTE 'REVOKE ALL ON TABLE public.account_setup_token_redemptions FROM PUBLIC';
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
    EXECUTE 'REVOKE ALL ON TABLE public.account_setup_token_redemptions FROM anon';
  END IF;
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
    EXECUTE 'REVOKE ALL ON TABLE public.account_setup_token_redemptions FROM authenticated';
  END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_setup_token_redemptions');
    }
};
