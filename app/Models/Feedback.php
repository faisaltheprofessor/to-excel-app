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
        'user_id','assigned_to_id','title','type','message','url','user_agent',
        'attachments','status','tags','priority', 'assigned_to_id'
    ];


    protected $casts = [
  'attachments'    => 'array',
  'tags'           => 'array',
  'user_id'        => 'integer',
  'assigned_to_id' => 'integer',
];

    public const TAG_SUGGESTIONS = ['UI','Performance','Bug','Importer','Excel','Vorschlag'];
    public const STATUSES   = ['open','in_progress','resolved','in_review', 'closed','wontfix'];
    public const PRIORITIES = ['low','normal','high','urgent'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_id'); }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class)->whereNull('parent_id')->orderBy('id');
    }

    public function reactions(): HasMany { return $this->hasMany(FeedbackReaction::class); }

    public function userHasReacted(int $userId, ?int $commentId, string $emoji): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->where('comment_id', $commentId)
            ->exists();
    }

    // make route binding allow trashed rows (prevents 404 on restore)
   public function resolveRouteBinding($value, $field = null)
{
    $field = $field ?: $this->getRouteKeyName();
    return static::withTrashed()->where($field, $value)->firstOrFail();
}
}
