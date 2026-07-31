<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_programs', function (Blueprint $table) {
            $table->id('program_id');
            $table->string('code', 80)->unique();
            $table->string('school_name', 150);
            $table->string('name', 255);
            $table->unsignedBigInteger('office_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('office_id')->references('office_id')->on('offices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_programs');
    }
};
