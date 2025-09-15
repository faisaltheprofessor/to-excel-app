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

    // meta (now: status & priority & assignee editable by everyone until closed/deleted)
    public string $status = 'open';
    public string $priority = 'normal';
    public ?int $assigneeId = null;

    public array $tags = [];
    public string $tagInput = '';

    // reactions
    public array $quickEmojis = ['👍', '❤️', '🎉', '🚀', '👀'];
    public array $reactionHover = [];

    // mentions
    public string $mentionQuery = '';
    public array $mentionResults = [];
    public bool $mentionOpen = false;

    // permissions
    public bool $canModifyFeedback = false; // owner + not closed + not deleted (title/message/tags/delete)
    public bool $canInteract = true;  // not closed + not deleted (comments/reactions)
    public bool $canEditStatus = false; // everyone until closed/deleted
    public bool $canEditPriority = false; // everyone until closed/deleted
    public bool $canEditAssignee = false; // everyone until closed/deleted

    // meta-dirty handling
    public bool $metaDirty = false;
    public string $previousStatus = 'open';
    public ?int $previousAssigneeId = null;
    public string $previousPriority = 'normal';

    // inline edit: feedback (owner only)
    public bool $editingFeedback = false;
    public string $editTitle = '';
    public string $editMessage = '';

    // inline edit: comment (owner only)
    public ?int $editingCommentId = null;
    public string $editingCommentBody = '';

    // history indicators
    public bool $feedbackEdited = false;     // feedback has any edit rows
    public array $commentEditedMap = [];     // [comment_id => true]

    // history modal (wire:model)
    public bool $showHistoryModal = false;
    public string $historyTitle = '';
    public string $historyHtml = '';

    // close-warning modal (wire:model)
    public bool $showCloseModal = false;

    // delete confirm modal (wire:model)
    public bool $showDeleteConfirm = false;

    // for assignment dropdown
    public array $assignableUsers = []; // [ ['id'=>..., 'name'=>...], ... ]

    public function mount(Feedback $feedback): void
    {
        $this->feedback = $feedback->fresh();
        $this->status = $this->feedback->status ?? 'open';
        $this->priority = $this->feedback->priority ?? 'normal';
        $this->assigneeId = $this->feedback->assigned_to_id;

        $this->tags = $this->feedback->tags ?? ['UI Improvement'];

        $this->previousStatus = $this->status;
        $this->previousPriority = $this->priority;
        $this->previousAssigneeId = $this->assigneeId;

        $this->recomputePermissions();
        $this->computeEditedFlags();
        $this->assignableUsers = User::query()->orderBy('name')->get(['id', 'name'])->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->all();
        $this->metaDirty = $this->isMetaDirty();
    }

    public function hydrate(): void
    {
        if ($this->feedback?->id) {
            $this->feedback = Feedback::withTrashed()->findOrFail($this->feedback->id);
            // keep UI in sync after server-side changes
            $this->status = $this->feedback->status ?? $this->status;
            $this->priority = $this->feedback->priority ?? $this->priority;
            $this->assigneeId = $this->feedback->assigned_to_id;
            $this->recomputePermissions();
            $this->metaDirty = $this->isMetaDirty();
        }
    }

    private function recomputePermissions(): void
    {
        $uid = Auth::id();
        $isOwner = ($this->feedback->user_id === $uid);
        $isClosed = ($this->feedback->status === 'closed'); // "abgeschlossen"
        $isDeleted = !is_null($this->feedback->deleted_at ?? null);

        $this->canModifyFeedback = $isOwner && !$isClosed && !$isDeleted; // title/message/tags/delete
        $this->canInteract = !$isClosed && !$isDeleted;

        // NEW: everyone can edit these until closed/deleted
        $this->canEditStatus = !$isClosed && !$isDeleted;
        $this->canEditPriority = !$isClosed && !$isDeleted;
        $this->canEditAssignee = !$isClosed && !$isDeleted;
    }

    private function computeEditedFlags(): void
    {
        $this->feedbackEdited = FeedbackEdit::where('feedback_id', $this->feedback->id)->exists();

        $commentIds = $this->feedback->comments()->pluck('id');
        $this->commentEditedMap = FeedbackCommentEdit::whereIn('comment_id', $commentIds)
            ->get()
            ->groupBy('comment_id')
            ->map(fn() => true)
            ->toArray();
    }

    private function isMetaDirty(): bool
    {
        $dirtyStatus = ($this->status !== ($this->feedback->status ?? 'open'));
        $dirtyPriority = ($this->priority !== ($this->feedback->priority ?? 'normal'));
        $dirtyAssignee = ($this->assigneeId !== ($this->feedback->assigned_to_id ?? null));
        return $dirtyStatus || $dirtyPriority || $dirtyAssignee;
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
            'user_id' => Auth::id(),
            'body' => $this->reply,
            'parent_id' => $this->replyTo,
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
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->all();

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

        if ($existing) $existing->delete();
        else FeedbackReaction::create([
            'feedback_id' => $this->feedback->id,
            'comment_id' => $commentId,
            'user_id' => $userId,
            'emoji' => $emoji,
        ]);

        $this->dispatch('$refresh');
    }

    public function loadReactionUsers(string $emoji, ?int $commentId = null): void
    {
        $key = $emoji . '|' . ($commentId ?? 'null');

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

    // ----- Tags (owner only) -----

    protected function persistTags(): void
    {
        if (!$this->canModifyFeedback) return;

        $clean = array_values(array_unique(array_filter(array_map('trim', $this->tags))));
        if (empty($clean)) $clean = ['UI Improvement'];

        $old = $this->feedback->tags ?? [];
        if ($old !== $clean) {
            FeedbackEdit::create([
                'feedback_id' => $this->feedback->id,
                'user_id' => Auth::id(),
                'changes' => ['tags' => [$old, $clean]],
                'snapshot' => [
                    'status' => $this->feedback->status,
                    'priority' => $this->feedback->priority,
                    'assigned_to_id' => $this->feedback->assigned_to_id,
                    'tags' => $clean,
                ],
            ]);
            $this->feedbackEdited = true;
        }

        $this->feedback->update(['tags' => $clean]);
        $this->tags = $clean;
        $this->metaDirty = $this->isMetaDirty();
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

    // ----- Meta selects (status/priority/assignee) -----

    public function updatedStatus(string $value): void
    {
        if (!$this->canEditStatus) {
            $this->status = $this->feedback->status ?? 'open';
            return;
        }
        if ($value === 'closed') $this->showCloseModal = true;
        $this->metaDirty = $this->isMetaDirty();
    }

    public function updatedPriority(string $value): void
    {
        if (!$this->canEditPriority) {
            $this->priority = $this->feedback->priority ?? 'normal';
            return;
        }
        $this->metaDirty = $this->isMetaDirty();
    }

    public function updatedAssigneeId($value): void
    {
        if (!$this->canEditAssignee) {
            $this->assigneeId = $this->feedback->assigned_to_id;
            return;
        }
        $this->metaDirty = $this->isMetaDirty();
    }

    public function confirmCloseInfo(): void
    {
        $this->showCloseModal = false;
        $this->metaDirty = $this->isMetaDirty();
    }

    public function cancelCloseSelection(): void
    {
        $this->status = $this->previousStatus;
        $this->showCloseModal = false;
        $this->metaDirty = $this->isMetaDirty();
    }

    public function saveMeta(): void
    {
        // hard locks
        if ($this->feedback->status === 'closed' || !is_null($this->feedback->deleted_at)) {
            logger()->warning('saveMeta blocked (closed/deleted)', ['id' => $this->feedback->id]);
            return;
        }

        // normalize inputs (all users allowed until closed/deleted)
        $newStatus = in_array($this->status, \App\Models\Feedback::STATUSES, true) ? $this->status : ($this->feedback->status ?? 'open');
        $newPriority = in_array($this->priority, \App\Models\Feedback::PRIORITIES, true) ? $this->priority : ($this->feedback->priority ?? 'normal');
        $newAssignee = $this->assigneeId ? (int)$this->assigneeId : null;

        // if attempting to close, require confirmation modal
        if ($newStatus === 'closed' && !$this->showCloseModal) {
            $this->showCloseModal = true;
            // do NOT save yet; user must confirm then click save again
            logger()->info('saveMeta halted for close confirmation', ['id' => $this->feedback->id]);
            return;
        }

        $changes = [];

        if ($newStatus !== $this->feedback->status) {
            $changes['status'] = [$this->feedback->status, $newStatus];
        }
        if ($newPriority !== $this->feedback->priority) {
            $changes['priority'] = [$this->feedback->priority, $newPriority];
        }
        if ($newAssignee !== ($this->feedback->assigned_to_id ?? null)) {
            $oldName = optional($this->feedback->assignee)->name ?? null;
            $newName = optional(\App\Models\User::find($newAssignee))->name ?? null;
            $changes['assigned_to'] = [$oldName, $newName];
        }

        if (empty($changes)) {
            logger()->info('saveMeta no changes', [
                'id' => $this->feedback->id,
                'incoming' => compact('newStatus', 'newPriority', 'newAssignee'),
                'current' => [
                    'status' => $this->feedback->status,
                    'priority' => $this->feedback->priority,
                    'assigned_to_id' => $this->feedback->assigned_to_id,
                ],
            ]);
            $this->metaDirty = $this->isMetaDirty();
            return;
        }

        try {
            $this->feedback->forceFill([
                'status' => $newStatus,
                'priority' => $newPriority,
                'assigned_to_id' => $newAssignee,
            ])->save();

            \App\Models\FeedbackEdit::create([
                'feedback_id' => $this->feedback->id,
                'user_id' => \Auth::id(),
                'changes' => $changes,
                'snapshot' => $this->feedback->only(['status', 'priority', 'tags', 'assigned_to_id']),
            ]);

            $this->feedback->refresh();

            // sync UI & flags
            $this->status = $this->feedback->status;
            $this->priority = $this->feedback->priority;
            $this->assigneeId = $this->feedback->assigned_to_id;
            $this->recomputePermissions();
            $this->metaDirty = $this->isMetaDirty();

            $this->dispatch('notify', body: 'Änderungen gespeichert.');
            logger()->info('saveMeta OK', ['id' => $this->feedback->id, 'changes' => $changes]);
        } catch (\Throwable $e) {
            logger()->error('saveMeta failed', ['id' => $this->feedback->id, 'err' => $e->getMessage()]);
            $this->dispatch('notify', body: 'Speichern fehlgeschlagen.');
        }
    }


    // ----- Feedback inline edit (owner only) -----

    public function startEditFeedback(): void
    {
        if (!$this->canModifyFeedback) return;
        $this->editingFeedback = true;
        $this->editTitle = (string)($this->feedback->title ?? '');
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

        $data = $this->validate([
            'editTitle' => 'required|string|min:1|max:200',
            'editMessage' => 'required|string|min:1|max:10000',
        ]);

        $changes = [];
        if (($this->feedback->title ?? '') !== $data['editTitle']) {
            $changes['title'] = [$this->feedback->title, $data['editTitle']];
        }
        if (($this->feedback->message ?? '') !== $data['editMessage']) {
            $changes['message'] = [$this->feedback->message, $data['editMessage']];
        }

        if (!empty($changes)) {
            $this->feedback->update([
                'title' => $data['editTitle'],
                'message' => $data['editMessage'],
            ]);

            FeedbackEdit::create([
                'feedback_id' => $this->feedback->id,
                'user_id' => Auth::id(),
                'changes' => $changes,
                'snapshot' => $this->feedback->only(['title', 'message', 'status', 'priority', 'tags', 'assigned_to_id']),
            ]);

            $this->feedbackEdited = true;
        }

        $this->editingFeedback = false;
        $this->feedback->refresh();
        $this->metaDirty = $this->isMetaDirty();
    }

    // ----- Comment inline edit (owner only) -----

    public function startEditComment(int $commentId): void
    {
        if (!$this->canInteract) return;

        $c = FeedbackComment::find($commentId);
        if (!$c || $c->feedback_id !== $this->feedback->id) return;
        if ($c->user_id !== Auth::id()) return;

        $this->editingCommentId = $c->id;
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

        if ($c->body !== $this->editingCommentBody) {
            FeedbackCommentEdit::create([
                'comment_id' => $c->id,
                'user_id' => Auth::id(),
                'old_body' => $c->body,
                'new_body' => $this->editingCommentBody,
            ]);
            $this->commentEditedMap[$c->id] = true;
        }

        $c->update(['body' => $this->editingCommentBody]);

        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->dispatch('$refresh');
    }

    // ----- Soft delete / restore (owner only) -----

    public function askDelete(): void
    {
        if (!$this->canModifyFeedback || !is_null($this->feedback->deleted_at)) return;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirm = false;
    }

    public function confirmDelete(): void
    {
        if (!$this->canModifyFeedback || !is_null($this->feedback->deleted_at)) return;

        $this->feedback->delete();
        $this->feedback = Feedback::withTrashed()->findOrFail($this->feedback->id);

        $this->recomputePermissions();
        $this->showDeleteConfirm = false;
        $this->dispatch('notify', body: 'Feedback gelöscht. Du kannst es wiederherstellen.');
    }

    public function restoreFeedback(): void
    {
        if ($this->feedback->user_id !== Auth::id()) return;

        $this->feedback->restore();
        $this->feedback = Feedback::withTrashed()->findOrFail($this->feedback->id);
        $this->recomputePermissions();
        $this->dispatch('notify', body: 'Feedback wiederhergestellt.');
    }

    // ----- History modal -----

    public function openFeedbackHistory(): void
    {
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
                $buf .= '<div class="text-xs text-zinc-500">' . e($e->created_at->format('d.m.Y H:i')) . ' · ' . e($e->user->name ?? 'Unbekannt') . '</div>';
                $buf .= '<ul class="mt-1 list-disc list-inside text-sm">';
                foreach (($e->changes ?? []) as $field => $pair) {
                    [$old, $new] = $pair;
                    $buf .= '<li><span class="font-medium">' . e(ucfirst($field)) . '</span>: ';
                    $buf .= '<span class="line-through opacity-70">' . e(is_array($old) ? implode(', ', $old) : (string)$old) . '</span> ';
                    $buf .= '→ <span>' . e(is_array($new) ? implode(', ', $new) : (string)$new) . '</span></li>';
                }
                $buf .= '</ul></div>';
            }
        }

        $this->historyTitle = 'Änderungshistorie';
        $this->historyHtml = $buf;
        $this->showHistoryModal = true;
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
                $buf .= '<div class="text-xs text-zinc-500">' . e($e->created_at->format('d.m.Y H:i')) . ' · ' . e($e->user->name ?? 'Unbekannt') . '</div>';
                $buf .= '<div class="mt-1 text-sm"><span class="font-medium">Vorher:</span> ' . nl2br(e($e->old_body)) . '</div>';
                $buf .= '<div class="mt-1 text-sm"><span class="font-medium">Nachher:</span> ' . nl2br(e($e->new_body)) . '</div>';
                $buf .= '</div>';
            }
        }

        $this->historyTitle = 'Änderungshistorie (Kommentar)';
        $this->historyHtml = $buf;
        $this->showHistoryModal = true;
    }

    public function closeHistory(): void
    {
        $this->showHistoryModal = false;
        $this->historyTitle = '';
        $this->historyHtml = '';
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
            'rootComments' => $rootComments,
            'attachments' => $attachments,
            'tagSuggestions' => \App\Models\Feedback::TAG_SUGGESTIONS,
            'canModifyFeedback' => $this->canModifyFeedback,
            'canInteract' => $this->canInteract,
            'canEditStatus' => $this->canEditStatus,
            'canEditPriority' => $this->canEditPriority,
            'canEditAssignee' => $this->canEditAssignee,
            'feedbackEdited' => $this->feedbackEdited,
            'commentEditedMap' => $this->commentEditedMap,
            'metaDirty' => $this->metaDirty,
            'assignableUsers' => $this->assignableUsers,
        ]);
    }
}
