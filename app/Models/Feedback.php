<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'type',       // bug | suggestion | question
        'message',
        'url',
        'user_agent',
        'attachments', // JSON array of stored paths
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function comments()  { return $this->hasMany(\App\Models\FeedbackComment::class)->whereNull('parent_id')->orderBy('created_at'); }
    public function reactions() { return $this->hasMany(\App\Models\FeedbackReaction::class);}

}



