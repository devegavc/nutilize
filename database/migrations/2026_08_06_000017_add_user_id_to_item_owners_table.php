<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('item_owners') || Schema::hasColumn('item_owners', 'user_id')) {
            return;
        }

        Schema::table('item_owners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('owner_name');
            $table->foreign('user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->unique('user_id');
        });

        DB::table('item_owners')
            ->orderBy('owner_id')
            ->get(['owner_id', 'department_affiliation'])
            ->each(function ($row) {
                $affiliation = trim((string) ($row->department_affiliation ?? ''));

                if ($affiliation === '' || !preg_match('/^user:(\d+)$/i', $affiliation, $matches)) {
                    return;
                }

                $userId = (int) $matches[1];

                if ($userId <= 0) {
                    return;
                }

                DB::table('item_owners')
                    ->where('owner_id', (int) $row->owner_id)
                    ->update([
                        'user_id' => $userId,
                        'department_affiliation' => null,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('item_owners') || !Schema::hasColumn('item_owners', 'user_id')) {
            return;
        }

        DB::table('item_owners')
            ->whereNotNull('user_id')
            ->orderBy('owner_id')
            ->get(['owner_id', 'user_id'])
            ->each(function ($row) {
                DB::table('item_owners')
                    ->where('owner_id', (int) $row->owner_id)
                    ->update([
                        'department_affiliation' => 'user:' . (int) $row->user_id,
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('item_owners', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
