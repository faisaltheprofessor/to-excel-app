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

    /** Reply target for threaded responses (null = top-level) */
    public ?int $replyTo = null;

    /** Quick reaction palette */
    public array $quickEmojis = ['👍','❤️','🎉','🚀','👀'];

    /** Mentions UI state */
    public string $mentionQuery = '';
    public array $mentionResults = []; // [['id'=>..,'name'=>..], ...]
    public bool $mentionOpen = false;

    public function mount(Feedback $feedback): void
    {
        $this->feedback = $feedback;
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

    /** Livewire hook: runs when mentionQuery changes (from Alpine) */
    public function updatedMentionQuery(): void
    {
        $q = trim($this->mentionQuery);
        if ($q === '') {
            $this->mentionResults = [];
            $this->mentionOpen = false;
            return;
        }

        $this->mentionResults = User::query()
            ->where('name', 'like', $q . '%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->all();

        $this->mentionOpen = !empty($this->mentionResults);
    }

    public function closeMentions(): void
    {
        $this->mentionOpen = false;
    }

    public function render()
    {
        // Eager-load root comments (and nested children) with users & reactions
        $rootComments = $this->feedback
            ->comments()
            ->with([
                'user',
                'reactions',
                'children.user',
                'children.reactions',
            ])
            ->get();

        $attachments = $this->feedback->attachments ?? [];

        return view('livewire.feedback-show', compact('rootComments', 'attachments'));
    }
}
