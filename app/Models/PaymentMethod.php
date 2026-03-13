<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'gateway_customer_id',
        'gateway_method_id',
        'gateway_token',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'card_holder_name',
        'paypal_email',
        'is_connected',
        'is_default',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'gateway_customer_id',
        'gateway_method_id',
        'gateway_token',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_method_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return match ($this->type) {
            'paypal' => 'PayPal' . ($this->paypal_email ? " ({$this->paypal_email})" : ''),
            'google_pay' => 'Google Pay',
            'apple_pay' => 'Apple Pay',
            'card' => ucfirst($this->card_brand ?? 'Card') . " •••• {$this->card_last_four}",
            default => ucfirst($this->type),
        };
    }
}
