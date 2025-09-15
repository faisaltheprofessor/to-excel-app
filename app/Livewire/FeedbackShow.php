<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\FeedbackReaction;
use App\Models\FeedbackEdit;
use App\Models\FeedbackCommentEdit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FeedbackShow extends Component
{
    public Feedback $feedback;

    // composer
    #[Validate('required|string|min:1|max:5000')]
    public string $reply = '';
    public ?int $replyTo = null;

    // meta
    public string $status = 'open';
    public array $tags = [];
    public string $priority = 'normal';

    public string $tagInput = '';

    // reactions
    public array $quickEmojis = ['👍','❤️','🎉','🚀','👀'];
    public array $reactionHover = [];

    // mentions
    public string $mentionQuery = '';
    public array $mentionResults = [];
    public bool $mentionOpen = false;

    // permissions
    public bool $canModifyFeedback = false; // owner + not closed + not deleted
    public bool $canInteract       = true;  // not closed + not deleted

    // inline edit: feedback
    public bool $editingFeedback = false;
    public string $editTitle = '';
    public string $editMessage = '';

    // inline edit: comment
    public ?int $editingCommentId = null;
    public string $editingCommentBody = '';

    // history view state (Flux modal content is a pre-rendered HTML string)
    public bool $historyOpen = false;
    public string $historyTitle = '';
    public string $historyHtml = '';

    // quick “edited” flags for badges
    public bool $feedbackEdited = false;             // true if history exists
    public array $commentEditedMap = [];             // [comment_id => true]

    public function mount(Feedback $feedback): void
    {
        $this->feedback = $feedback->fresh();
        $this->status   = $this->feedback->status ?? 'open';
        $this->tags     = $this->feedback->tags ?? ['UI Improvement'];
        $this->priority = $this->feedback->priority ?? 'normal';

        $this->recomputePermissions();
        $this->computeEditedFlags();
    }

    private function recomputePermissions(): void
    {
        $uid       = Auth::id();
        $isOwner   = ($this->feedback->user_id === $uid);
        $isClosed  = ($this->feedback->status === 'closed');
        $isDeleted = !is_null($this->feedback->deleted_at ?? null);

        $this->canModifyFeedback = $isOwner && !$isClosed && !$isDeleted;
        $this->canInteract       = !$isClosed && !$isDeleted;
    }

    private function computeEditedFlags(): void
    {
        $this->feedbackEdited = FeedbackEdit::where('feedback_id', $this->feedback->id)->exists();

        $this->commentEditedMap = FeedbackCommentEdit::query()
            ->whereIn('comment_id', $this->feedback->comments()->pluck('id'))
            ->get()
            ->groupBy('comment_id')
            ->map(fn($g) => true)
            ->toArray();
    }

    // ----- Comments & mentions -----

    public function setReplyTo(?int $commentId = null): void
    {
        if (!$this->canInteract) return;
        $this->replyTo = $commentId;
    }

    public function send(): void
    {
        if (!$this->canInteract) return;
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

    public function deleteComment(int $commentId): void
    {
        if (!$this->canInteract) return;

        $c = FeedbackComment::query()
            ->where('feedback_id', $this->feedback->id)
            ->where('id', $commentId)
            ->first();

        if ($c && $c->user_id === Auth::id()) {
            $c->delete();
            $this->dispatch('$refresh');
        }
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

    // ----- Reactions -----

    public function toggleReaction(string $emoji, ?int $commentId = null): void
    {
        if (!$this->canInteract) return;

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

    // ----- Tags & Meta -----

    protected function persistTags(): void
    {
        if (!$this->canModifyFeedback) return;

        $clean = array_values(array_unique(array_filter(array_map('trim', $this->tags))));
        if (empty($clean)) $clean = ['UI Improvement'];

        // history (tags change)
        $old = $this->feedback->tags ?? [];
        if ($old !== $clean) {
            FeedbackEdit::create([
                'feedback_id' => $this->feedback->id,
                'user_id'     => Auth::id(),
                'changes'     => ['tags' => [$old, $clean]],
            ]);
            $this->feedbackEdited = true;
        }

        $this->feedback->update(['tags' => $clean]);
        $this->tags = $clean;
    }

    public function addTag(?string $t = null): void
    {
        if (!$this->canModifyFeedback) return;
        $t = trim($t ?? $this->tagInput ?? '');
        if ($t === '') return;

        $this->tags = array_values(array_unique([...$this->tags, $t]));
        $this->tagInput = '';
        $this->persistTags();
    }

    public function removeTag(int $index): void
    {
        if (!$this->canModifyFeedback) return;
        if (!isset($this->tags[$index])) return;

        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
        $this->persistTags();
    }

public function saveMeta(): void
{
    $status = in_array($this->status, \App\Models\Feedback::STATUSES, true) ? $this->status : 'open';
    $prio   = in_array($this->priority, \App\Models\Feedback::PRIORITIES, true) ? $this->priority : 'normal';

    $old = $this->feedback->only(['status','priority','tags']);

    $this->feedback->update([
        'status'   => $status,
        'priority' => $prio,
    ]);

    $new = $this->feedback->only(['status','priority','tags']);

    \App\Models\FeedbackEdit::create([
        'feedback_id' => $this->feedback->id,
        'user_id'     => \Auth::id(),
        'changes'     => [
            'status'   => [$old['status'], $new['status']],
            'priority' => [$old['priority'], $new['priority']],
            'tags'     => [$old['tags'], $new['tags']],
        ],
        'snapshot'    => $new, // ✅ always provide full snapshot
    ]);

    $this->dispatch('notify', body: 'Änderungen gespeichert.');
}

    // ----- Feedback inline edit -----

    public function startEditFeedback(): void
    {
        if (!$this->canModifyFeedback) return;
        $this->editingFeedback = true;
        $this->editTitle   = (string)($this->feedback->title ?? '');
        $this->editMessage = (string)($this->feedback->message ?? '');
    }

    public function cancelEditFeedback(): void
    {
        $this->editingFeedback = false;
        $this->editTitle = '';
        $this->editMessage = '';
    }

public function saveEditFeedback(): void
{
    if (!$this->canModifyFeedback) return;

    $old = $this->feedback->only(['title','message','status','priority','tags']);

    $data = $this->validate([
        'editTitle'   => 'required|string|min:1|max:200',
        'editMessage' => 'required|string|min:1|max:10000',
    ]);

    $this->feedback->update([
        'title'   => $data['editTitle'],
        'message' => $data['editMessage'],
    ]);

    $new = $this->feedback->only(['title','message','status','priority','tags']);

    \App\Models\FeedbackEdit::create([
        'feedback_id' => $this->feedback->id,
        'user_id'     => \Auth::id(),
        'changes'     => [
            'title'   => [$old['title'], $new['title']],
            'message' => [$old['message'], $new['message']],
        ],
        'snapshot'    => $new,
    ]);

    $this->editingFeedback = false;
    $this->feedback->refresh();
}


    // ----- Comment inline edit -----

    public function startEditComment(int $commentId): void
    {
        if (!$this->canInteract) return;

        $c = FeedbackComment::find($commentId);
        if (!$c || $c->feedback_id !== $this->feedback->id) return;
        if ($c->user_id !== Auth::id()) return;

        $this->editingCommentId   = $c->id;
        $this->editingCommentBody = $c->body;
    }

    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
    }

    public function saveEditComment(): void
    {
        if (!$this->canInteract) return;

        $this->validate([
            'editingCommentBody' => 'required|string|min:1|max:5000',
        ]);

        $c = FeedbackComment::find($this->editingCommentId);
        if (!$c || $c->feedback_id !== $this->feedback->id) return;
        if ($c->user_id !== Auth::id()) return;

        // history
        if ($c->body !== $this->editingCommentBody) {
            FeedbackCommentEdit::create([
                'comment_id' => $c->id,
                'user_id'    => Auth::id(),
                'old_body'   => $c->body,
                'new_body'   => $this->editingCommentBody,
            ]);
            $this->commentEditedMap[$c->id] = true;
        }

        $c->update(['body' => $this->editingCommentBody]);

        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->dispatch('$refresh');
    }

    // ----- Soft delete / restore -----

    public function deleteFeedback(): void
    {
        if (!$this->canModifyFeedback) return;
        if (method_exists($this->feedback, 'delete')) {
            $this->feedback->delete();
            $this->feedback->refresh();
            $this->recomputePermissions();
        }
    }

    public function restoreFeedback(): void
    {
        if ($this->feedback->user_id !== Auth::id()) return;
        if (method_exists($this->feedback, 'restore')) {
            $this->feedback->restore();
            $this->feedback->refresh();
            $this->recomputePermissions();
        }
    }

    // ----- History modal API -----

    public function openFeedbackHistory(): void
    {
        // Build safe HTML (no Blade loops inside flux:modal)
        $rows = FeedbackEdit::with('user:id,name')
            ->where('feedback_id', $this->feedback->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $buf = '';
        if ($rows->isEmpty()) {
            $buf .= '<div class="text-sm text-zinc-500">Keine Änderungen vorhanden.</div>';
        } else {
            foreach ($rows as $e) {
                $buf .= '<div class="mb-3">';
                $buf .= '<div class="text-xs text-zinc-500">'.e($e->created_at->format('d.m.Y H:i')).' · '.e($e->user->name ?? 'Unbekannt').'</div>';
                $buf .= '<ul class="mt-1 list-disc list-inside text-sm">';
                foreach (($e->changes ?? []) as $field => $pair) {
                    [$old,$new] = $pair;
                    $buf .= '<li><span class="font-medium">'.e(ucfirst($field)).'</span>: ';
                    $buf .= '<span class="line-through opacity-70">'.e(is_array($old)? implode(', ',$old) : (string)$old).'</span> ';
                    $buf .= '→ <span>'.e(is_array($new)? implode(', ',$new) : (string)$new).'</span></li>';
                }
                $buf .= '</ul></div>';
            }
        }

        $this->historyTitle = 'Änderungshistorie (Feedback)';
        $this->historyHtml  = $buf;
        $this->historyOpen  = true;
    }

    public function openCommentHistory(int $commentId): void
    {
        $rows = FeedbackCommentEdit::with('user:id,name')
            ->where('comment_id', $commentId)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $buf = '';
        if ($rows->isEmpty()) {
            $buf .= '<div class="text-sm text-zinc-500">Keine Änderungen vorhanden.</div>';
        } else {
            foreach ($rows as $e) {
                $buf .= '<div class="mb-3">';
                $buf .= '<div class="text-xs text-zinc-500">'.e($e->created_at->format('d.m.Y H:i')).' · '.e($e->user->name ?? 'Unbekannt').'</div>';
                $buf .= '<div class="mt-1 text-sm"><span class="font-medium">Vorher:</span> '.nl2br(e($e->old_body)).'</div>';
                $buf .= '<div class="mt-1 text-sm"><span class="font-medium">Nachher:</span> '.nl2br(e($e->new_body)).'</div>';
                $buf .= '</div>';
            }
        }

        $this->historyTitle = 'Änderungshistorie (Kommentar)';
        $this->historyHtml  = $buf;
        $this->historyOpen  = true;
    }

    public function closeHistory(): void
    {
        $this->historyOpen = false;
        $this->historyHtml = '';
        $this->historyTitle = '';
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
            'rootComments'      => $rootComments,
            'attachments'       => $attachments,
            'tagSuggestions'    => \App\Models\Feedback::TAG_SUGGESTIONS,
            'canModifyFeedback' => $this->canModifyFeedback,
            'canInteract'       => $this->canInteract,
            'feedbackEdited'    => $this->feedbackEdited,
            'commentEditedMap'  => $this->commentEditedMap,
            'historyOpen'       => $this->historyOpen,
            'historyTitle'      => $this->historyTitle,
            'historyHtml'       => $this->historyHtml,
        ]);
    }
}
