<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
}
