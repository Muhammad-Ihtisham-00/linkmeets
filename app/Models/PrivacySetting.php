<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacySetting extends Model
{
    protected $fillable = [
        'user_id',
        'profile_private',
        'allow_comments',
        'allow_tagging',
        'post_visibility',
        'email_visibility',
        'phone_visibility',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
