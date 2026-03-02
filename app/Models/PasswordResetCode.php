<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'password_reset_codes';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'code',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Check if the code has expired (15 minutes)
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->created_at->diffInMinutes(now()) > 15;
    }

    /**
     * Get the user associated with this reset code
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }



    /**
     * Delete expired codes for a specific email
     */
    public static function deleteExpiredForEmail(string $email): void
    {
        static::where('email', $email)
            ->where('created_at', '<', now()->subMinutes(15))
            ->delete();
    }

    /**
     * Delete all codes for a specific email
     */
    public static function deleteAllForEmail(string $email): void
    {
        static::where('email', $email)->delete();
    }
}
