<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    public const DEFAULT_TTL_DAYS = 7;

    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'created_by',
        'announcer_name',
        'title',
        'body',
        'is_active',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function announcerLabel(): string
    {
        $stored = trim((string) ($this->announcer_name ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if ($this->author) {
            return $this->author->displayName();
        }

        return 'Physical Facilities staff';
    }

    public static function purgeExpired(): int
    {
        if (!Schema::hasTable('announcements') || !Schema::hasColumn('announcements', 'expires_at')) {
            return 0;
        }

        return (int) static::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
