<?php

use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'role_needs_review')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('role_needs_review')->default(false);
            });
        }

        if (Schema::hasTable('reservation_approvals')) {
            Schema::table('reservation_approvals', function (Blueprint $table) {
                if (!Schema::hasColumn('reservation_approvals', 'approved_by_role')) {
                    $table->string('approved_by_role', 100)->nullable();
                }
                if (!Schema::hasColumn('reservation_approvals', 'on_behalf_of_program_chair')) {
                    $table->boolean('on_behalf_of_program_chair')->default(false);
                }
                if (!Schema::hasColumn('reservation_approvals', 'dean_required')) {
                    $table->boolean('dean_required')->default(false);
                }
            });
        }

        if (Schema::hasTable('reservation_approval_histories')) {
            Schema::table('reservation_approval_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('reservation_approval_histories', 'approved_by_role')) {
                    $table->string('approved_by_role', 100)->nullable();
                }
                if (!Schema::hasColumn('reservation_approval_histories', 'on_behalf_of_program_chair')) {
                    $table->boolean('on_behalf_of_program_chair')->default(false);
                }
            });
        }

        if (!Schema::hasTable('users')) {
            return;
        }

        $this->withoutUserGuard(function (): void {
            $offices = DB::table('offices')
                ->get(['office_id', 'department_name', 'short_code'])
                ->keyBy('office_id');

            DB::table('users')
                ->select(['user_id', 'role', 'office_id'])
                ->orderBy('user_id')
                ->chunkById(200, function ($users) use ($offices): void {
                    foreach ($users as $user) {
                        $office = $offices->get($user->office_id);
                        $mapped = RoleCatalog::migrationFor(
                            (string) $user->role,
                            $office->department_name ?? null,
                            $office->short_code ?? null,
                        );

                        if ($mapped['role'] === (string) $user->role && !$mapped['needs_review']) {
                            continue;
                        }

                        DB::table('users')
                            ->where('user_id', $user->user_id)
                            ->update([
                                'role' => $mapped['role'],
                                'role_needs_review' => $mapped['needs_review'],
                            ]);
                    }
                }, 'user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('reservation_approval_histories')) {
            Schema::table('reservation_approval_histories', function (Blueprint $table) {
                if (Schema::hasColumn('reservation_approval_histories', 'on_behalf_of_program_chair')) {
                    $table->dropColumn('on_behalf_of_program_chair');
                }
                if (Schema::hasColumn('reservation_approval_histories', 'approved_by_role')) {
                    $table->dropColumn('approved_by_role');
                }
            });
        }

        if (Schema::hasTable('reservation_approvals')) {
            Schema::table('reservation_approvals', function (Blueprint $table) {
                if (Schema::hasColumn('reservation_approvals', 'dean_required')) {
                    $table->dropColumn('dean_required');
                }
                if (Schema::hasColumn('reservation_approvals', 'on_behalf_of_program_chair')) {
                    $table->dropColumn('on_behalf_of_program_chair');
                }
                if (Schema::hasColumn('reservation_approvals', 'approved_by_role')) {
                    $table->dropColumn('approved_by_role');
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_needs_review')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role_needs_review');
            });
        }
    }

    private function withoutUserGuard(callable $callback): void
    {
        $driver = DB::getDriverName();
        $disabled = false;
        $replica = false;

        if ($driver === 'pgsql') {
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
            $callback();
        } finally {
            if ($replica) {
                DB::statement('SET session_replication_role = DEFAULT');
            }
            if ($disabled) {
                DB::statement('ALTER TABLE public.users ENABLE TRIGGER USER');
            }
        }
    }
};
