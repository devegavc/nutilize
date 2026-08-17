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
            Schema::create('announcements', function (Blueprint $table) {
                $table->id('announcement_id');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('title', 180);
                $table->text('body');
                $table->boolean('is_active')->default(true);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
                $table->index(['is_active', 'published_at']);
            });

            return;
        }

        if (!Schema::hasColumn('announcements', 'published_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->after('is_active');
            });
        }

        DB::table('announcements')
            ->whereNull('published_at')
            ->update([
                'published_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'published_at')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('published_at');
            });
        }
    }
};
