<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $primaryKey = 'office_id';
    protected $fillable = ['department_name', 'officer_name', 'status_check_type', 'short_code', 'order_sequence'];

    public function users()
    {
        return $this->hasMany(User::class, 'office_id', 'office_id');
    }

    public function reservationApprovals()
    {
        return $this->hasMany(ReservationApproval::class, 'office_id', 'office_id');
    }

    public function isPhysicalFacilities()
    {
        return strtolower($this->department_name) === 'physical facilities';
    }
}
