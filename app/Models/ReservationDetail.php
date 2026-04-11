<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationDetail extends Model
{
    use HasFactory;

    protected $primaryKey = 'detail_id';
    protected $fillable = [
        'reservation_id',
        'reservation_rooms_id',
        'reservation_items_id',
        'quantity',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }
}
