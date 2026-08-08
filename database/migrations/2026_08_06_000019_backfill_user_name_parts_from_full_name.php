<?php

use App\Services\UserNameService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'first_name')) {
            return;
        }

        DB::table('users')
            ->select(['user_id', 'full_name', 'first_name', 'last_name', 'middle_initial'])
            ->orderBy('user_id')
            ->get()
            ->each(function ($user) {
                if (!empty($user->first_name) && !empty($user->last_name)) {
                    return;
                }

                $fullName = trim((string) ($user->full_name ?? ''));
                if ($fullName === '') {
                    return;
                }

                $split = UserNameService::splitFullName($fullName);

                DB::table('users')
                    ->where('user_id', (int) $user->user_id)
                    ->update([
                        'first_name' => $user->first_name ?: $split['first_name'],
                        'middle_initial' => $user->middle_initial ?: $split['middle_initial'],
                        'last_name' => $user->last_name ?: $split['last_name'],
                        'full_name' => $split['full_name'] ?? $fullName,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Non-destructive backfill; no rollback needed.
    }
};
