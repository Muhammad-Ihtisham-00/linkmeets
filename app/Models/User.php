<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'phone',
        'address',
        'dob',
        'profile_picture',
        'intro_video',
        'bio',
        'about',
        'role',
        'account_type',
        'kyc_verified',
        'kyc_verified_at',
    ];

    public function interests()
    {
        return $this->belongsToMany(Interest::class);
    }

    public function galleries()
    {
        return $this->hasMany(Gallery::class);
    }

    public function businessCard()
    {
        return $this->hasOne(BusinessCard::class);
    }

    public function privacySettings()
    {
        return $this->hasOne(PrivacySetting::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dob' => 'date',
            'kyc_verified' => 'boolean',
            'kyc_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    // ─── APPOINTMENTS ─────────────────────────────────────────────────

   

    // ─── SERVICES ─────────────────────────────────────────────────

    /**
     * User ki saari services
     * $user->services
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'user_id');
    }

    /**
     * User ki sirf active services
     * $user->activeServices
     */
    public function activeServices(): HasMany
    {
        return $this->hasMany(Service::class, 'user_id')
            ->where('is_active', true);
    }

    // ─── APPOINTMENTS ─────────────────────────────────────────────

    /**
     * Maine jo appointments book ki hain (main client hoon)
     * $user->bookedAppointments
     */
    public function bookedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /**
     * Mere saath jo appointments book hui hain (main provider hoon)
     * $user->receivedAppointments
     */
    public function receivedAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'provider_id');
    }

    // ─── REVIEWS ──────────────────────────────────────────────────

    /**
     * Maine jo reviews diye hain
     * $user->givenReviews
     */
    public function givenReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * Mujhe jo reviews mile hain
     * $user->receivedReviews
     */
    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }
}
