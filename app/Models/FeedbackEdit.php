<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackEdit extends Model
{
protected $fillable = ['feedback_id','user_id','changes','snapshot'];

    protected $casts = [
        'changes'  => 'array',
        'snapshot' => 'array',
    ];

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
