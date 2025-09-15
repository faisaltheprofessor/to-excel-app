<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackEdit extends Model
{
    protected $table = 'feedback_edits';
    protected $fillable = ['feedback_id','user_id','changes'];
    protected $casts = ['changes' => 'array'];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
