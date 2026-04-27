<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleImportDetail extends Model
{
    protected $table = 'schedule_import_details';
    protected $primaryKey = 'detail_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'import_id',
        'reservation_id',
        'row_number',
        'status',
        'error_message',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scheduleImport(): BelongsTo
    {
        return $this->belongsTo(ScheduleImport::class, 'import_id', 'import_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }
}
