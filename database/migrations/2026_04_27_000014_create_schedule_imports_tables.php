<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SCHEDULE IMPORTS - Tracks each CSV file upload batch
        Schema::create('schedule_imports', function (Blueprint $table) {
            $table->id('import_id');
            $table->unsignedBigInteger('user_id');
            $table->string('file_name', 255);
            $table->string('status', 50)->default('pending'); // pending, processing, completed, failed
            $table->integer('total_rows')->default(0);
            $table->integer('successful_imports')->default(0);
            $table->integer('failed_imports')->default(0);
            $table->longText('error_log')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });

        // SCHEDULE IMPORT DETAILS - Tracks individual rows from each CSV upload
        Schema::create('schedule_import_details', function (Blueprint $table) {
            $table->id('detail_id');
            $table->unsignedBigInteger('import_id');
            $table->unsignedBigInteger('reservation_id')->nullable();
            $table->integer('row_number');
            $table->string('status', 50); // success, failed, skipped
            $table->text('error_message')->nullable();
            $table->json('raw_data'); // Store the original CSV row as JSON
            $table->timestamps();

            $table->foreign('import_id')->references('import_id')->on('schedule_imports')->onDelete('cascade');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_import_details');
        Schema::dropIfExists('schedule_imports');
    }
};
