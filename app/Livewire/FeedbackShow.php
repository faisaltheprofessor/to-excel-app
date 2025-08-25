<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\FeedbackReaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FeedbackShow extends Component
{
    public Feedback $feedback;

    #[Validate('required|string|min:1|max:5000')]
    public string $reply = '';
    public ?int $replyTo = null;

    public string $status = 'open';
    public array $tags = [];
    public string $priority = 'normal';

    /** Tag input field (used by wire:model) */
    public string $tagInput = ''; // <-- FIX: define the property

    public array $quickEmojis = ['👍','❤️','🎉','🚀','👀'];

    public string $mentionQuery = '';
    public array $mentionResults = [];
    public bool $mentionOpen = false;

    public array $reactionHover = [];

    public function mount(Feedback $feedback): void
    {
        $this->feedback = $feedback->fresh();
        $this->status   = $this->feedback->status ?? 'open';
        $this->tags     = $this->feedback->tags ?? ['UI Improvement'];
        $this->priority = $this->feedback->priority ?? 'normal';
    }

    public function setReplyTo(?int $commentId = null): void
    {
        $this->replyTo = $commentId;
    }

    public function send(): void
    {
        $this->validate();

        FeedbackComment::create([
            'feedback_id' => $this->feedback->id,
            'user_id'     => Auth::id(),
            'body'        => $this->reply,
            'parent_id'   => $this->replyTo,
        ]);

        $this->reply = '';
        $this->replyTo = null;
        $this->dispatch('$refresh');
    }

    public function toggleReaction(string $emoji, ?int $commentId = null): void
    {
        $userId = Auth::id();

        $existing = FeedbackReaction::query()
            ->where('feedback_id', $this->feedback->id)
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            FeedbackReaction::create([
                'feedback_id' => $this->feedback->id,
                'comment_id'  => $commentId,
                'user_id'     => $userId,
                'emoji'       => $emoji,
            ]);
        }

        $this->dispatch('$refresh');
    }

    public function loadReactionUsers(string $emoji, ?int $commentId = null): void
    {
        $key = $emoji.'|'.($commentId ?? 'null');

        $rows = FeedbackReaction::query()
            ->where('feedback_id', $this->feedback->id)
            ->where('comment_id', $commentId)
            ->where('emoji', $emoji)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        $this->reactionHover[$key] = [
            'names' => $rows->map(fn($r) => optional($r->user)->name ?? 'Unbekannt')->values()->all(),
        ];
    }

    public function updatedMentionQuery(): void
    {
        $q = trim($this->mentionQuery);
        if ($q === '') { $this->mentionResults = []; $this->mentionOpen = false; return; }

        $this->mentionResults = User::query()
            ->where('name', 'like', $q.'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id','name'])
            ->map(fn($u)=>['id'=>$u->id,'name'=>$u->name])->all();

        $this->mentionOpen = !empty($this->mentionResults);
    }

    public function closeMentions(): void
    {
        $this->mentionOpen = false;
    }

    /** Add a tag – can be called via button param or form using $tagInput */
    public function addTag(?string $t = null): void
    {
        $t = $t ?? $this->tagInput ?? '';
        $t = trim($t);
        if ($t === '') return;

        $this->tags = array_values(array_unique([...($this->tags ?? []), $t]));
        $this->tagInput = '';
    }

    public function saveMeta(): void
    {
        $status   = in_array($this->status, \App\Models\Feedback::STATUSES, true) ? $this->status : 'open';
        $prio     = in_array($this->priority, \App\Models\Feedback::PRIORITIES, true) ? $this->priority : 'normal';
        $tags     = array_values(array_unique(array_filter(array_map('trim', $this->tags))));

        $this->feedback->update([
            'status'   => $status,
            'priority' => $prio,
            'tags'     => $tags ?: ['UI Improvement'],
        ]);

        $this->dispatch('notify', body: 'Gespeichert.');
    }

    public function render()
    {
        $rootComments = $this->feedback
            ->comments()
            ->with([
                'user',
                'reactions.user:id,name',
                'children.user',
                'children.reactions.user:id,name',
            ])->get();

        $attachments = $this->feedback->attachments ?? [];

        return view('livewire.feedback-show', [
            'rootComments'   => $rootComments,
            'attachments'    => $attachments,
            'tagSuggestions' => \App\Models\Feedback::TAG_SUGGESTIONS,
        ]);
    }
}
