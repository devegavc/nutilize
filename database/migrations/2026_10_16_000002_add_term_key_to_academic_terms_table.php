<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->string('term_key', 40)->nullable()->after('name');
            $table->unique(['academic_year', 'term_key']);
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropUnique(['academic_year', 'term_key']);
            $table->dropColumn('term_key');
        });
    }
};
