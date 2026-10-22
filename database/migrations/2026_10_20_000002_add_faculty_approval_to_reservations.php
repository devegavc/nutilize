<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'faculty_approved_by_user_id')) {
                $table->unsignedBigInteger('faculty_approved_by_user_id')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'faculty_approved_at')) {
                $table->timestamp('faculty_approved_at')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'faculty_rejected_at')) {
                $table->timestamp('faculty_rejected_at')->nullable();
            }
            if (!Schema::hasColumn('reservations', 'faculty_rejection_reason')) {
                $table->string('faculty_rejection_reason', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            foreach (['faculty_rejection_reason', 'faculty_rejected_at', 'faculty_approved_at', 'faculty_approved_by_user_id'] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
