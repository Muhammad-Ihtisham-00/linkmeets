<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; 

class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'joined_at',
        'left_at',
        'is_muted',
        'muted_until',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'joined_at'    => 'datetime',
        'left_at'      => 'datetime',
        'muted_until'  => 'datetime',
        'is_muted'     => 'boolean',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Konsi conversation hai
     * $participant->conversation
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Konsa user hai
     * $participant->user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    /**
     * Admin hai ya nahi
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Group leave kar chuka hai
     */
    public function hasLeft(): bool
    {
        return !is_null($this->left_at);
    }
}
