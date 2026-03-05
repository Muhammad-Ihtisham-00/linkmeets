<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;   
use Illuminate\Database\Eloquent\Relations\HasOne;
class Appointment extends Model
{
    protected $fillable = [
        'client_id',
        'provider_id',
        'service_id',
        'full_name',
        'reason',
        'appointment_date',
        'start_time',
        'end_time',
        'event_location_name',
        'event_address',
        'event_distance_km',
        'event_image',
        'call_channel_id',
        'call_started_at',
        'call_ended_at',
        'call_duration_seconds',
        'is_recording_enabled',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'rescheduled_from_id',
        'rescheduled_at',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'call_started_at' => 'datetime',
        'call_ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rescheduled_at' => 'datetime',
        'is_recording_enabled' => 'boolean',
        'event_distance_km' => 'decimal:2',
    ];

    // ─── USER RELATIONS ───────────────────────────────────────────

    /**
     * Jo user appointment book kar raha hai
     * $appointment->client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Jis user se appointment book ho rahi hai
     * $appointment->provider
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Kisne appointment cancel ki — client ya provider
     * $appointment->cancelledBy
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ─── SERVICE RELATION ─────────────────────────────────────────

    /**
     * Konsi service book ki — type/price/duration yahan se milega
     * $appointment->service->type
     * $appointment->service->price
     * $appointment->service->duration_minutes
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    // ─── RESCHEDULE RELATIONS ─────────────────────────────────────

    /**
     * Purani appointment jis se yeh nayi bani
     * $appointment->rescheduledFrom->appointment_date
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'rescheduled_from_id');
    }

    /**
     * Nayi appointment jo is se bani
     * $appointment->rescheduledTo->status
     */
    public function rescheduledTo(): HasOne
    {
        return $this->hasOne(Appointment::class, 'rescheduled_from_id');
    }

    // ─── REVIEW RELATION ──────────────────────────────────────────

    /**
     * Is appointment ka review
     * $appointment->review->rating
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'appointment_id');
    }

    // ─── HELPER METHODS ───────────────────────────────────────────

    /**
     * Kya yeh call based appointment hai
     * Service se type check hoga
     */
    public function isCallBased(): bool
    {
        return $this->service &&
            in_array($this->service->type, ['voice_call', 'video_call']);
    }

    /**
     * Kya yeh event appointment hai
     */
    public function isEvent(): bool
    {
        return $this->service && $this->service->type === 'event';
    }

    /**
     * Kya review diya ja sakta hai
     */
    public function canBeReviewed(): bool
    {
        return $this->status === 'completed' && !$this->review;
    }
}
