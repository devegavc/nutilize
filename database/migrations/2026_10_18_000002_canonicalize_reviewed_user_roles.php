<?php

use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replica = false;
        $disabled = false;

        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('SET session_replication_role = replica');
                $replica = true;
            } catch (\Throwable) {
                try {
                    DB::statement('ALTER TABLE public.users DISABLE TRIGGER USER');
                    $disabled = true;
                } catch (\Throwable) {
                    $disabled = false;
                }
            }
        }

        try {
            $this->canonicalize();
        } finally {
            if ($replica) {
                DB::statement('SET session_replication_role = DEFAULT');
            }
            if ($disabled) {
                DB::statement('ALTER TABLE public.users ENABLE TRIGGER USER');
            }
        }
    }

    private function canonicalize(): void
    {
        DB::table('users')
            ->select(['user_id', 'role', 'office_id'])
            ->where('role_needs_review', true)
            ->orderBy('user_id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $office = DB::table('offices')->where('office_id', $user->office_id)->first(['department_name', 'short_code']);
                    $mapped = RoleCatalog::migrationFor(
                        (string) $user->role,
                        $office->department_name ?? null,
                        $office->short_code ?? null,
                    );

                    DB::table('users')
                        ->where('user_id', $user->user_id)
                        ->update([
                            'role' => $mapped['role'],
                            'role_needs_review' => $mapped['needs_review'],
                        ]);
                }
            }, 'user_id');
    }

    public function down(): void
    {
        // Role corrections are not reversed. The previous value was not a defined role code.
    }
};
