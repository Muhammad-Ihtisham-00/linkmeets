<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'type',
        'visibility',
        'content',
        'views_count',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class);
    }

    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'post_likes')
            ->withTimestamps();
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class)
            ->whereNull('parent_id'); // root comments only
    }

    public function shares()
    {
        return $this->hasMany(PostShare::class);
    }

    public function sharedByUsers()
    {
        return $this->belongsToMany(User::class, 'post_shares')
            ->withPivot('caption')
            ->withTimestamps();
    }
}
