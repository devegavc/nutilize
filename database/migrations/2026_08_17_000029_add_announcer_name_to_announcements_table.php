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

        if (!Schema::hasColumn('announcements', 'announcer_name')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->string('announcer_name', 180)->nullable()->after('created_by');
            });
        }

        if (!Schema::hasTable('users')) {
            return;
        }

        $rows = DB::table('announcements as announcements')
            ->leftJoin('users as users', 'users.user_id', '=', 'announcements.created_by')
            ->where(function ($query) {
                $query->whereNull('announcements.announcer_name')
                    ->orWhere('announcements.announcer_name', '');
            })
            ->select([
                'announcements.announcement_id',
                'users.first_name',
                'users.middle_initial',
                'users.last_name',
                'users.suffix',
                'users.full_name',
                'users.username',
            ])
            ->get();

        foreach ($rows as $row) {
            $name = \App\Models\User::formatDisplayName($row, '');
            if ($name === '' || $name === 'Unknown') {
                $name = trim((string) ($row->username ?? '')) ?: 'Physical Facilities staff';
            }

            DB::table('announcements')
                ->where('announcement_id', (int) $row->announcement_id)
                ->update(['announcer_name' => $name]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'announcer_name')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('announcer_name');
            });
        }
    }
};
