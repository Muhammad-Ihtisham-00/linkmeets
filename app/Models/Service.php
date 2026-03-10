<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Service extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'price',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'duration_minutes' => 'integer',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Jis user ki yeh service hai
     * $service->user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Is service pe bani saari appointments
     * $service->appointments
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }

    /**
     * Is service ke saare reviews
     * $service->reviews
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'service_id');
    }

    // ─── SCOPES ───────────────────────────────────────────────────

    // Sirf active services: Service::active()->get()
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Type filter: Service::ofType('video_call')->get()
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
