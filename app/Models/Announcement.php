<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    protected $primaryKey = 'announcement_id';

    protected $fillable = [
        'created_by',
        'announcer_name',
        'title',
        'body',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
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

    public function isEdited(): bool
    {
        $publishedAt = $this->published_at ?? $this->created_at;
        $updatedAt = $this->updated_at;

        if (!$publishedAt || !$updatedAt) {
            return false;
        }

        return $updatedAt->gt($publishedAt);
    }

    public static function tableReady(): bool
    {
        return (bool) Cache::remember('schema.table.announcements', 3600, static function () {
            return Schema::hasTable('announcements');
        });
    }

    public static function hasAnnouncementsColumn(string $column): bool
    {
        $column = trim($column);
        if ($column === '') {
            return false;
        }

        return (bool) Cache::remember("schema.column.announcements.{$column}", 3600, static function () use ($column) {
            return Schema::hasColumn('announcements', $column);
        });
    }
}
