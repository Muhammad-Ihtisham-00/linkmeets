<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'available_balance',
        'pending_balance',
        'total_earning',
        'total_withdrawn',
        'currency',
        'is_active',
        'is_frozen',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_earning' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'is_active' => 'boolean',
        'is_frozen' => 'boolean',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'wallet_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    public function isUsable(): bool
    {
        return $this->is_active && !$this->is_frozen;
    }
}
