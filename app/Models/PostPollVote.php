<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostPollVote extends Model
{
    protected $fillable = [
        'poll_id',
        'option_id',
        'user_id'
    ];

    public function poll()
    {
        return $this->belongsTo(PostPoll::class, 'poll_id');
    }

    public function option()
    {
        return $this->belongsTo(PostPollOption::class, 'option_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
