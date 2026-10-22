<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('academic_terms') || !Schema::hasColumn('academic_terms', 'term_key')) {
            return;
        }

        DB::table('academic_terms')
            ->where(function ($query): void {
                $query->whereNull('term_key')->orWhere('term_key', '');
            })
            ->delete();
    }

    public function down(): void
    {
        // The leftover row had no term key and duplicated 1st Term. It is not restored.
    }
};
