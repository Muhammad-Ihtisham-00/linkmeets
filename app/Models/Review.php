<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Review extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'review_text',
        'would_recommend',
    ];

    protected $casts = [
        'rating' => 'integer',
        'would_recommend' => 'boolean',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Konsi appointment ka review hai
     * $review->appointment->appointment_date
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Konsi service ka review hai
     * $review->service->title
     * $review->service->type
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Kisne review diya (client)
     * $review->reviewer->first_name
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Kisko review mila (provider)
     * $review->reviewee->first_name
     */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
