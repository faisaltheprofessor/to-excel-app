<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use SoftDeletes;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id','title','type','message','url','user_agent','attachments',
        'status','tags','priority',
    ];

    protected $casts = [
        'attachments' => 'array',
        'tags'        => 'array',
    ];

    // Kanban-ready ordered list
    public const STATUSES = [
        'open',
        'in_progress',
        'in_review',   // NEW (Im Review)
        'in_test',     // NEW (Im Test)
        'resolved',
        'closed',
        'wontfix',
    ];

    public const PRIORITIES = ['low','normal','high','urgent'];

    public const TAG_SUGGESTIONS = ['UI','Performance','Bug','Importer','Excel','Vorschlag'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    // Only root comments here (children via relation on comment)
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class)
            ->whereNull('parent_id')
            ->orderBy('id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FeedbackReaction::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(FeedbackEdit::class)->latest();
    }

    public function userHasReacted(int $userId, ?int $commentId, string $emoji): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->where('comment_id', $commentId)
            ->exists();
    }

    /** Helper: is this feedback locked (Geschlossen)? */
    public function isLocked(): bool
    {
        return ($this->status === 'closed');
    }

     public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();

        return static::withTrashed()
            ->where($field, $value)
            ->firstOrFail();
    }
}
