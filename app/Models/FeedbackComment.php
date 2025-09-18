<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackComment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'feedback_id',
        'user_id',
        'body',
        'parent_id',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function reactions()
    {
        return $this->hasMany(FeedbackReaction::class, 'comment_id');
    }

    public function feedback()
    {
        // Ensure the parent feedback is available even when soft-deleted
        return $this->belongsTo(\App\Models\Feedback::class)->withTrashed();
    }
}
