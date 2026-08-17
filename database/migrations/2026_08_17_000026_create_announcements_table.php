<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements')) {
            return;
        }

        Schema::create('announcements', function (Blueprint $table) {
            $table->id('announcement_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
