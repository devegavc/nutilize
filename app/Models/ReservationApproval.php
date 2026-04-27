<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationApproval extends Model
{
    use HasFactory;

    protected $primaryKey = 'approval_id';
    protected $fillable = ['reservation_id', 'office_id', 'approved_by_user_id', 'status', 'approved_at'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id', 'user_id');
    }
}
