<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_allowances', function (Blueprint $table) {
            if (!Schema::hasColumn('registration_allowances', 'role')) {
                $table->string('role', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('registration_allowances', function (Blueprint $table) {
            if (Schema::hasColumn('registration_allowances', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
