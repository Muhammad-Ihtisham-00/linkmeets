<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportedUser extends Model
{
    protected $fillable = [
        'reporter_id',
        'reported_id',
        'reason',
        'description',
        'status',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    // ─── RELATIONS ────────────────────────────────────────────────

    /**
     * Kisne report kiya
     * $report->reporter
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Kise report kiya
     * $report->reported
     */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_id');
    }
}
