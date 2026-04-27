<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_approval_histories', function (Blueprint $table) {
            $table->id('history_id');
            $table->unsignedBigInteger('approval_id');
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedBigInteger('office_id');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->string('status', 255);
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('approval_id')->references('approval_id')->on('reservation_approvals')->onDelete('cascade');
            $table->foreign('reservation_id')->references('reservation_id')->on('reservations')->onDelete('cascade');
            $table->foreign('office_id')->references('office_id')->on('offices')->onDelete('cascade');
            $table->foreign('approved_by_user_id')->references('user_id')->on('users')->nullOnDelete();

            $table->unique('approval_id');
        });

        $finalizedApprovals = DB::table('reservation_approvals as approvals')
            ->whereNotNull('approvals.approved_at')
            ->whereIn(DB::raw("LOWER(COALESCE(approvals.status, ''))"), ['approved', 'rejected'])
            ->select([
                'approvals.approval_id',
                'approvals.reservation_id',
                'approvals.office_id',
                'approvals.approved_by_user_id',
                'approvals.status',
                'approvals.approved_at',
                'approvals.created_at',
                'approvals.updated_at',
            ])
            ->get();

        foreach ($finalizedApprovals as $approval) {
            DB::table('reservation_approval_histories')->insert([
                'approval_id' => (int) $approval->approval_id,
                'reservation_id' => (int) $approval->reservation_id,
                'office_id' => (int) $approval->office_id,
                'approved_by_user_id' => $approval->approved_by_user_id ? (int) $approval->approved_by_user_id : null,
                'status' => (string) $approval->status,
                'approved_at' => $approval->approved_at,
                'created_at' => $approval->created_at ?? now(),
                'updated_at' => $approval->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_approval_histories');
    }
};