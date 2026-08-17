<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('announcements')) {
            return;
        }

        if (!Schema::hasColumn('announcements', 'expires_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('published_at');
                $table->index('expires_at');
            });
        }

        DB::table('announcements')
            ->whereNull('expires_at')
            ->update([
                'expires_at' => DB::raw("COALESCE(published_at, created_at) + INTERVAL '7 days'"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'expires_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('expires_at');
            });
        }
    }
};
