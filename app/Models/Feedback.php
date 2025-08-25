<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = [
        'user_id','title', 'type','message','url','user_agent','attachments','status','tags','priority',
    ];

    protected $casts = [
        'attachments' => 'array',
        'tags'        => 'array',
    ];

    public const TAG_SUGGESTIONS = [
        'UI Improvement','Performance','Bug','Accessibility','Docs','DevEx','Feature',
        'Security','API','Design','Mobile','Onboarding','Search',
    ];

    public const STATUSES   = ['open','in_progress','resolved','closed','wontfix'];
    public const PRIORITIES = ['low','normal','high','urgent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class)->whereNull('parent_id')->orderBy('id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FeedbackReaction::class);
    }

    public function userHasReacted(int $userId, ?int $commentId, string $emoji): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->where('comment_id', $commentId)
            ->exists();
    }
}
