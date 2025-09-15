<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackCommentEdit extends Model
{
    protected $table = 'feedback_comment_edits';
    protected $fillable = ['comment_id','user_id','old_body','new_body'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(FeedbackComment::class, 'comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
