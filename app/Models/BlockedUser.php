<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class BlockedUser extends Model
{
    protected $fillable = [
        'blocker_id',
        'blocked_id',
        'blocked_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Kisne block kiya
     * $blockedUser->blocker
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * Kise block kiya
     * $blockedUser->blocked
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
