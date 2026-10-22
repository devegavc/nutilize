<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements') || !Schema::hasColumn('announcements', 'expires_at')) {
            return;
        }

        try {
            DB::statement('DROP INDEX IF EXISTS announcements_expires_at_index');
        } catch (Throwable) {
            // Index name can differ by driver; dropping the column removes it.
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('announcements') || Schema::hasColumn('announcements', 'expires_at')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('published_at');
        });
    }
};
