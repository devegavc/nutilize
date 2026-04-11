<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $primaryKey = 'reservation_id';
    protected $fillable = ['user_id', 'activity_name', 'overall_status'];

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
}
