<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // USERS
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('username', 50);
            $table->string('password', 255);
            $table->string('role', 100);
            $table->string('email', 100)->unique();
            $table->timestamps();
        });

        // OFFICES
        Schema::create('offices', function (Blueprint $table) {
            $table->id('office_id');
            $table->string('department_name', 100);
            $table->string('officer_name', 100);
            $table->string('status_check_type', 50)->nullable();
            $table->string('short_code', 50)->nullable();
            $table->integer('order_sequence')->nullable();
            $table->timestamps();
        });

        // ITEM OWNERS
        Schema::create('item_owners', function (Blueprint $table) {
            $table->id('owner_id');
            $table->string('owner_name', 255);
            $table->string('department_affiliation', 255)->nullable();
            $table->timestamps();
        });

        // ROOMS
        Schema::create('rooms', function (Blueprint $table) {
            $table->id('room_id');
            $table->string('room_number', 255);
            $table->boolean('maintenance_status')->default(false);
            $table->boolean('availability_status')->default(true);
            $table->date('date_reserved')->nullable();
            $table->timestamps();
        });

        // ITEMS
        Schema::create('items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('item_name', 255);
            $table->string('category', 255)->nullable();
            $table->boolean('maintenance_status')->default(false);
            $table->boolean('availability_status')->default(true);
            $table->date('date_reserved')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('owner_id')->on('item_owners')->onDelete('cascade');
        });

        // RESERVATION ROOMS
        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->id('reservation_rooms_id');
            $table->unsignedBigInteger('room_id');
            $table->timestamps();

            $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
        });

        // RESERVATION ITEMS
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id('reservation_items_id');
            $table->unsignedBigInteger('item_id');
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('items')->onDelete('cascade');
        });

        // ROOM APPROVER OFFICES
        Schema::create('room_approver_offices', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->primary();
            $table->unsignedBigInteger('office_id');
            $table->timestamps();

            $table->foreign('room_id')->references('room_id')->on('rooms')->onDelete('cascade');
            $table->foreign('office_id')->references('office_id')->on('offices')->onDelete('cascade');
        });

        // RESERVATIONS
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('reservation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('activity_name', 255);
            $table->string('overall_status', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });

        // RESERVATION APPROVALS
        Schema::create('reservation_approvals', function (Blueprint $table) {
            $table->id('approval_id');
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('office_id');
            $table->string('status', 255);
            $table->date('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('cascade');
            $table->foreign('office_id')->references('office_id')->on('offices')->onDelete('cascade');
        });

        // RESERVATION DETAILS
        Schema::create('reservation_details', function (Blueprint $table) {
            $table->id('detail_id');
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('reservation_rooms_id')->nullable();
            $table->unsignedBigInteger('reservation_items_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('cascade');
            $table->foreign('reservation_rooms_id')->references('reservation_rooms_id')->on('reservation_rooms')->onDelete('cascade');
            $table->foreign('reservation_items_id')->references('reservation_items_id')->on('reservation_items')->onDelete('cascade');
        });

        // MAINTENANCE
        Schema::create('maintenance', function (Blueprint $table) {
            $table->id('maintenance_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('issue_description', 255);
            $table->string('action_taken', 255)->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->date('date_resolved')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('item_id')->on('items')->nullOnDelete();
            $table->foreign('room_id')->references('room_id')->on('rooms')->nullOnDelete();
        });

        // REPORTS
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('report_info', 255);
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('room_id')->references('room_id')->on('rooms')->nullOnDelete();
            $table->foreign('item_id')->references('item_id')->on('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('maintenance');
        Schema::dropIfExists('reservation_details');
        Schema::dropIfExists('reservation_approvals');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('room_approver_offices');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('reservation_rooms');
        Schema::dropIfExists('items');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('item_owners');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('users');
    }
};