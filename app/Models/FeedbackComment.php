<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackComment extends Model
{
    use SoftDeletes;
    protected $fillable = ['feedback_id','user_id','body','parent_id'];

    public function feedback() { return $this->belongsTo(Feedback::class); }
    public function user()     { return $this->belongsTo(\App\Models\User::class); }
    public function parent()   { return $this->belongsTo(FeedbackComment::class, 'parent_id'); }
    public function children() { return $this->hasMany(FeedbackComment::class, 'parent_id')->orderBy('created_at'); }
    public function reactions(){ return $this->hasMany(FeedbackReaction::class, 'comment_id'); }
}
