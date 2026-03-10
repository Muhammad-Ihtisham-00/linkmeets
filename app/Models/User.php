<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    public function commentLikes()
    {
        return $this->hasMany(PostCommentLike::class);
    }

    public function sharedPosts()
    {
        return $this->belongsToMany(Post::class, 'post_shares')
            ->withPivot('caption')
            ->withTimestamps();
    }

    public function marketplaceProducts()
    {
        return $this->hasMany(MarketplaceProduct::class, 'user_id');
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



    // ─── CHAT AND CONVERSATIONS ────────────────────────────────────────────

    /**
     * Meri saari conversations
     * $user->conversations
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants', 'user_id', 'conversation_id')
            ->withPivot('role', 'last_read_at', 'joined_at', 'left_at', 'is_muted')
            ->withTimestamps()
            ->whereNull('conversation_participants.left_at'); // Leave kiye hue nahi
    }

    /**
     * Maine jo messages bheje
     * $user->sentMessages
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Maine jo groups banaye
     * $user->createdGroups
     */
    public function createdGroups(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    // ─── BLOCK ────────────────────────────────────────────────────

    /**
     * Maine jine block kiya
     * $user->blockedUsers
     */
    public function blockedUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocker_id');
    }

    /**
     * Mujhe jinhon ne block kiya
     * $user->blockedByUsers
     */
    public function blockedByUsers(): HasMany
    {
        return $this->hasMany(BlockedUser::class, 'blocked_id');
    }

    // ─── REPORT ───────────────────────────────────────────────────

    /**
     * Maine jo reports ki
     * $user->reportsMade
     */
    public function reportsMade(): HasMany
    {
        return $this->hasMany(ReportedUser::class, 'reporter_id');
    }

    /**
     * Mere against jo reports hain
     * $user->reportsReceived
     */
    public function reportsReceived(): HasMany
    {
        return $this->hasMany(ReportedUser::class, 'reported_id');
    }

    // ─── HELPER METHODS ───────────────────────────────────────────

    /**
     * Check karo k koi user block hai
     */
    public function hasBlocked(int $userId): bool
    {
        return $this->blockedUsers()->where('blocked_id', $userId)->exists();
    }

    /**
     * Check karo k mujhe block kiya gaya hai
     */
    public function isBlockedBy(int $userId): bool
    {
        return $this->blockedByUsers()->where('blocker_id', $userId)->exists();
    }
}
