<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicProgram extends Model
{
    protected $primaryKey = 'program_id';

    protected $fillable = [
        'code',
        'school_name',
        'name',
        'office_id',
        'sort_order',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'program_id', 'program_id');
    }
}
