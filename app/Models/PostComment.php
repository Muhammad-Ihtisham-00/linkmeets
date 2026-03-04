<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostComment extends Model
{
    use HasFactory;

    protected $table = 'post_comments';

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Comment belongs to post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    // Comment belongs to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Parent comment (if reply)
    public function parent()
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    // Replies to this comment
    public function replies()
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }

    // Likes
    public function likes()
    {
        return $this->hasMany(PostCommentLike::class, 'comment_id');
    }

    // Users who liked this comment
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'post_comment_likes', 'comment_id', 'user_id')
            ->withTimestamps();
    }
}
