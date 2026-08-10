<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    protected $fillable = ['user_id', 'type', 'title', 'message', 'related_id', 'read'];
    protected $attributes = [
        'read' => false,
    ];
    protected $casts = [
        'read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Postgres stores `read` as boolean — Laravel's where('read', false) binds 0 and fails.
     */
    public function scopeUnread($query)
    {
        return $query->whereRaw('"read" = false');
    }

    public function scopeRead($query)
    {
        return $query->whereRaw('"read" = true');
    }

    public function markAsRead(): void
    {
        DB::table('notifications')
            ->where('notification_id', (int) $this->notification_id)
            ->update([
                'read' => DB::raw('true'),
                'updated_at' => now(),
            ]);
    }

    public function markAsUnread(): void
    {
        DB::table('notifications')
            ->where('notification_id', (int) $this->notification_id)
            ->update([
                'read' => DB::raw('false'),
                'updated_at' => now(),
            ]);
    }
}
