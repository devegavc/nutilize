<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Units that still fall inside items.quantity_total were left as `retired` after
 * quantity edits. Those slots are part of active stock and should be borrowable
 * again (`available`), unless a PF admin already marked them maintenance/damaged
 * (those statuses are never retired by this repair).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('item_units') || !Schema::hasTable('items')) {
            return;
        }

        DB::statement("
            UPDATE item_units AS units
            SET status = 'available',
                updated_at = NOW()
            FROM items
            WHERE items.item_id = units.item_id
              AND LOWER(COALESCE(units.status, '')) = 'retired'
              AND units.unit_number <= GREATEST(COALESCE(items.quantity_total, 0), 0)
              AND COALESCE(items.quantity_total, 0) > 0
        ");
    }

    public function down(): void
    {
        // Intentionally empty: re-retiring active stock would hide borrowable units.
    }
};
