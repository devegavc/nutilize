<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleImport extends Model
{
    protected $table = 'schedule_imports';
    protected $primaryKey = 'import_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'file_name',
        'status',
        'total_rows',
        'successful_imports',
        'failed_imports',
        'error_log',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ScheduleImportDetail::class, 'import_id', 'import_id');
    }
}
