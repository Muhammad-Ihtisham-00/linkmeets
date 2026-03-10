<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'type',
        'name',
        'image',
        'created_by',
        'appointment_id',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Kisne group banaya
     * $conversation->creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Appointment se linked hai toh
     * $conversation->appointment
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Last message
     * $conversation->lastMessage->body
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Conversation k saare messages
     * $conversation->messages
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /**
     * Conversation k saare participants (pivot)
     * $conversation->participants
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_id')
            ->withPivot('role', 'last_read_at', 'joined_at', 'left_at', 'is_muted', 'muted_until')
            ->withTimestamps();
    }

    /**
     * Conversation k participant records (full model)
     * $conversation->participantRecords
     */
    public function participantRecords(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    /**
     * Check karo k user is conversation mein hai
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->participantRecords()
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    /**
     * Is conversation mein unread messages count
     */
    public function unreadCount(int $userId): int
    {
        $participant = $this->participantRecords()
            ->where('user_id', $userId)
            ->first();

        if (!$participant || !$participant->last_read_at) {
            return $this->messages()->where('sender_id', '!=', $userId)->count();
        }

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('created_at', '>', $participant->last_read_at)
            ->count();
    }
}
