<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'duration_seconds',
        'latitude',
        'longitude',
        'location_name',
        'is_live_location',
        'live_location_expires_at',
        'live_location_stopped',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'is_live_location' => 'boolean',
        'live_location_stopped' => 'boolean',
        'live_location_expires_at' => 'datetime',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Konsi conversation ka message hai
     * $message->conversation
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Kisne bheja
     * $message->sender->first_name
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Kisne padha — read receipts
     * $message->reads
     */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    /**
     * Kisi user ne padha ya nahi
     */
    public function isReadBy(int $userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * File based message hai
     */
    public function hasFile(): bool
    {
        return in_array($this->type, ['image', 'document', 'audio']);
    }

    /**
     * Location based message hai
     */
    public function isLocation(): bool
    {
        return in_array($this->type, ['location', 'live_location']);
    }
}
