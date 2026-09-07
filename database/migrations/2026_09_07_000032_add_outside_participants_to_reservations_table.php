<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations') || Schema::hasColumn('reservations', 'outside_participants')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('outside_participants')->default(false);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reservations') || !Schema::hasColumn('reservations', 'outside_participants')) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('outside_participants');
        });
    }
};
