<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackReaction extends Model
{
    protected $fillable = ['feedback_id','comment_id','user_id','emoji'];

    public function feedback() { return $this->belongsTo(Feedback::class); }
    public function comment()  { return $this->belongsTo(FeedbackComment::class); }
    public function user()     { return $this->belongsTo(\App\Models\User::class); }
}
