<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'receiver_id',
        'type',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'description',
        'reference_number',
        'appointment_id',
        'payment_method_id',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_response',
        'status',
        'completed_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gateway_response' => 'array',
        'completed_at' => 'datetime',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Send money mein receiver
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // Appointment se linked
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    // Payment method used
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // ─── HELPERS ──────────────────────────────────────────────────

    public function isCredit(): bool
    {
        return in_array($this->type, ['add_money', 'earning', 'refund']);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, ['send_money', 'withdraw', 'fee']);
    }
}
