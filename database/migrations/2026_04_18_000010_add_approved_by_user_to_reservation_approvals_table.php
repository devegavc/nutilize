<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('office_id');
            $table->foreign('approved_by_user_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_approvals', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn('approved_by_user_id');
        });
    }
};