<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRelationship extends Model
{
    use HasFactory;

    protected $table = 'user_relationships';

    protected $fillable = [
        'user_id',
        'related_user_id',
        'type',
    ];

    const FOLLOW = 1;
    const BLOCK = 2;

    /**
     * The user who initiated the relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who is the target of the relationship
     */
    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }
}
