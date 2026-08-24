<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $primaryKey = 'reservation_id';
    protected $fillable = ['user_id', 'activity_name', 'overall_status', 'date_of_activity', 'start_of_activity', 'Date_of_Activity', 'Start_of_activity'];
    protected $casts = [
        'date_of_activity' => 'datetime',
        'start_of_activity' => 'datetime',
        'Date_of_Activity' => 'datetime',
        'Start_of_activity' => 'datetime',
        'End_of_Activity' => 'datetime',
        'end_of_activity' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function approvals()
    {
        return $this->hasMany(ReservationApproval::class, 'reservation_id', 'reservation_id');
    }

    public function details()
    {
        return $this->hasMany(ReservationDetail::class, 'reservation_id', 'reservation_id');
    }

    /**
     * Check if this reservation has expired based on start_of_activity time
     */
    public function isExpired(): bool
    {
        $startTime = $this->Start_of_activity ?? $this->start_of_activity;
        
        if (is_null($startTime)) {
            return false;
        }

        return $startTime < now();
    }

    /**
     * True when the activity window has already passed, even if overall_status
     * was never flipped to expired (Hostinger cron for reservations:expire may not run).
     */
    public function isPastActivity(): bool
    {
        if ($this->isExpired()) {
            return true;
        }

        $endTime = $this->End_of_Activity ?? $this->end_of_activity;
        if (!is_null($endTime) && $endTime < now()) {
            return true;
        }

        $activityDate = $this->Date_of_Activity ?? $this->date_of_activity;
        if (is_null($activityDate)) {
            return false;
        }

        return \Carbon\Carbon::parse($activityDate)->endOfDay()->lt(now());
    }

    /**
     * Check if this reservation can be cancelled (not yet finalized)
     */
    public function canBeCancelled(): bool
    {
        $status = strtolower((string) $this->overall_status);
        
        return !in_array($status, [
            'approved',
            'rejected',
            'cancelled',
            'canceled',
            'expired',
            'returned',
            'damaged',
        ], true) && !str_starts_with($status, 'cancel');
    }

    /**
     * Mark this reservation as cancelled
     */
    public function markAsCancelled(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        return $this->update(['overall_status' => 'cancelled']);
    }

    /**
     * Mark this reservation as expired
     */
    public function markAsExpired(): bool
    {
        if ($this->overall_status && strtolower((string) $this->overall_status) !== 'pending_office_approvals' 
            && strtolower((string) $this->overall_status) !== 'awaiting_physical_facilities') {
            return false;
        }

        return $this->update(['overall_status' => 'expired']);
    }
}
