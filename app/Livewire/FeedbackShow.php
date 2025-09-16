<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\FeedbackReaction;
use App\Models\FeedbackEdit;
use App\Models\FeedbackCommentEdit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class FeedbackShow extends Component
{
    use WithFileUploads;

    public Feedback $feedback;

    // composer
    #[Validate('required|string|min:1|max:5000')]
    public string $reply = '';
    public ?int $replyTo = null;

    /** Reply attachments */
    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $replyUploads = [];

    public string $commentSort = 'newest'; // 'newest' | 'oldest'

    // meta
    public string $status = 'open';
    public string $priority = 'medium'; // low|medium|high|critical
    public string $type = 'bug';        // bug|suggestion
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
    public bool $canModifyFeedback = false;
    public bool $canInteract = true;
    public bool $canEditStatus = false;
    public bool $canEditPriority = false;
    public bool $canEditAssignee = false;
    public bool $canEditType = false;

    // meta-dirty
    public bool $metaDirty = false;
    public string $previousStatus = 'open';
    public ?int $previousAssigneeId = null;
    public string $previousPriority = 'medium';
    public string $previousType = 'bug';

    // inline edit: feedback
    public bool $editingFeedback = false;
    public string $editTitle = '';
    public string $editMessage = '';

    /** Ticket edit: add new files & remove existing ones */
    public array $editUploads = [];                 // new uploads (TemporaryUploadedFile[])
    public array $removeFeedbackAttachmentIdx = []; // int[] of existing attachments to remove

    // inline edit: comment
    public ?int $editingCommentId = null;
    public string $editingCommentBody = '';
    public array $editingCommentUploads = [];       // new uploads during comment edit
    public array $removeCommentAttachmentIdx = [];  // int[] to remove from existing

    // history indicators
    public bool $feedbackEdited = false;
    public array $commentEditedMap = [];

    // history modal
    public bool $showHistoryModal = false;
    public string $historyTitle = '';
    public string $historyHtml = '';

    // close-warning modal
    public bool $showCloseModal = false;

    // delete confirm modal
    public bool $showDeleteConfirm = false;

    // assignment dropdown
    public array $assignableUsers = [];

    public function mount(Feedback $feedback): void
    {
        $this->feedback = $feedback->fresh();

        $this->status      = $this->feedback->status ?? 'open';
        $this->priority    = $this->feedback->priority ?? 'medium';
        $this->type        = $this->feedback->type ?? 'bug';
        $this->assigneeId  = $this->feedback->assigned_to_id;
        $this->tags        = $this->feedback->tags ?? [];

        $this->previousStatus     = $this->status;
        $this->previousPriority   = $this->priority;
        $this->previousType       = $this->type;
        $this->previousAssigneeId = $this->assigneeId;

        $this->recomputePermissions();
        $this->computeEditedFlags();
        $this->assignableUsers = User::query()
            ->orderBy('name')
            ->get(['id','name'])
            ->map(fn($u)=>['id'=>$u->id,'name'=>$u->name])
            ->all();
        $this->metaDirty = $this->isMetaDirty();
    }

    public function hydrate(): void
    {
        if ($this->feedback?->id) {
            $this->feedback   = Feedback::withTrashed()->findOrFail($this->feedback->id);
            $this->status     = $this->feedback->status ?? $this->status;
            $this->priority   = $this->feedback->priority ?? $this->priority;
            $this->type       = $this->feedback->type ?? $this->type;
            $this->assigneeId = $this->feedback->assigned_to_id;
            $this->recomputePermissions();
            $this->metaDirty = $this->isMetaDirty();
        }
    }

    private function recomputePermissions(): void
    {
        $uid       = Auth::id();
        $isOwner   = ($this->feedback->user_id === $uid);
        $isClosed  = ($this->feedback->status === 'closed');
        $isDeleted = !is_null($this->feedback->deleted_at ?? null);

        $this->canModifyFeedback = $isOwner && !$isClosed && !$isDeleted;
        $this->canInteract       = !$isClosed && !$isDeleted;
        $this->canEditStatus     = !$isClosed && !$isDeleted;
        $this->canEditPriority   = !$isClosed && !$isDeleted;
        $this->canEditAssignee   = !$isClosed && !$isDeleted;
        $this->canEditType       = !$isClosed && !$isDeleted;
    }

    private function computeEditedFlags(): void
    {
        $this->feedbackEdited = FeedbackEdit::where('feedback_id', $this->feedback->id)->exists();
        $commentIds = $this->feedback->comments()->pluck('id');
        $this->commentEditedMap = FeedbackCommentEdit::whereIn('comment_id', $commentIds)
            ->get()->groupBy('comment_id')->map(fn()=>true)->toArray();
    }

    private function isMetaDirty(): bool
    {
        $dirtyStatus   = ($this->status   !== ($this->feedback->status ?? 'open'));
        $dirtyPriority = ($this->priority !== ($this->feedback->priority ?? 'medium'));
        $dirtyType     = ($this->type     !== ($this->feedback->type ?? 'bug'));
        $dirtyAssignee = ($this->assigneeId !== ($this->feedback->assigned_to_id ?? null));
        return $dirtyStatus || $dirtyPriority || $dirtyType || $dirtyAssignee;
    }

    // ---------- Upload validation helpers ----------
    private function validateUploadsArray(array &$files, string $prefixForErrors = 'uploads'): void
    {
        $maxImage  = 10 * 1024 * 1024;  // 10 MB
        $maxPdf    = 20 * 1024 * 1024;  // 20 MB
        $maxOffice = 20 * 1024 * 1024;  // 20 MB
        $maxVideo  = 100 * 1024 * 1024; // 100 MB

        foreach ($files as $i => $file) {
            $mime = $file->getMimeType() ?? '';
            $size = $file->getSize() ?? 0;
            $ext  = strtolower($file->getClientOriginalExtension() ?? '');

            if (str_starts_with($mime, 'image/')) {
                if ($size > $maxImage) { $this->addError("$prefixForErrors.$i", 'Bild zu groß (max. 10 MB).'); unset($files[$i]); }
            } elseif (str_starts_with($mime, 'video/')) {
                if ($size > $maxVideo) { $this->addError("$prefixForErrors.$i", 'Video zu groß (max. 100 MB).'); unset($files[$i]); }
            } elseif ($mime === 'application/pdf' || $ext === 'pdf') {
                if ($size > $maxPdf)   { $this->addError("$prefixForErrors.$i", 'PDF zu groß (max. 20 MB).');   unset($files[$i]); }
            } elseif (in_array($ext, ['doc','docx','xls','xlsx'], true)) {
                if ($size > $maxOffice){ $this->addError("$prefixForErrors.$i", 'Office-Dokument zu groß (max. 20 MB).'); unset($files[$i]); }
            } else {
                $this->addError("$prefixForErrors.$i", 'Dateityp nicht erlaubt.');
                unset($files[$i]);
            }
        }
        $files = array_values($files);
    }

    private function storeFiles(array $files, string $subdir): array
    {
        $out = [];
        foreach ($files as $file) {
            $folder = 'feedback/' . now()->format('Y/m/d') . '/' . $subdir;
            $ext    = $file->getClientOriginalExtension();
            $name   = uniqid('', true) . '.' . $ext;
            $path   = $file->storeAs($folder, $name, 'public');
            $out[]  = [
                'path' => $path,
                'url'  => Storage::disk('public')->url($path),
                'mime' => $file->getMimeType(),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ];
        }
        return $out;
    }

    // ---------- Comments ----------
    public function setReplyTo(?int $commentId = null): void
    {
        if ($this->canInteract) $this->replyTo = $commentId;
    }

    public function removeReplyUpload(int $index): void
    {
        if (isset($this->replyUploads[$index])) {
            unset($this->replyUploads[$index]);
            $this->replyUploads = array_values($this->replyUploads);
        }
    }

    public function send(): void
    {
        if (!$this->canInteract) return;
        $this->validate(); // validates 'reply'

        // Validate attachments
        $this->validateUploadsArray($this->replyUploads, 'replyUploads');

        $stored = $this->storeFiles($this->replyUploads, 'comments');

        $comment = FeedbackComment::create([
            'feedback_id' => $this->feedback->id,
            'user_id'     => Auth::id(),
            'body'        => $this->reply,
            'parent_id'   => $this->replyTo,
            'attachments' => $stored, // requires json column + cast
        ]);

        $this->reply        = '';
        $this->replyTo      = null;
        $this->replyUploads = [];

        $this->dispatch('$refresh');
    }

    public function deleteComment(int $commentId): void
    {
        if (!$this->canInteract) return;
        $c = FeedbackComment::where('feedback_id',$this->feedback->id)->where('id',$commentId)->first();
        if ($c && $c->user_id === Auth::id()) {
            $c->delete();
            $this->dispatch('$refresh');
        }
    }

    // ----- Mentions -----
    public function updatedMentionQuery(): void
    {
        $q = trim($this->mentionQuery);
        if ($q === '') { $this->mentionResults=[]; $this->mentionOpen=false; return; }
        $this->mentionResults = User::where('name','like',$q.'%')
            ->orderBy('name')->limit(8)->get(['id','name','email'])
            ->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'email'=>$u->email])->all();
        $this->mentionOpen = !empty($this->mentionResults);
    }
    public function closeMentions(): void { $this->mentionOpen=false; }

    // ----- Reactions -----
    public function toggleReaction(string $emoji,?int $commentId=null): void
    {
        if(!$this->canInteract)return;
        $uid=Auth::id();
        $existing=FeedbackReaction::where('feedback_id',$this->feedback->id)
            ->where('comment_id',$commentId)->where('user_id',$uid)->where('emoji',$emoji)->first();
        if($existing) $existing->delete();
        else FeedbackReaction::create(['feedback_id'=>$this->feedback->id,'comment_id'=>$commentId,'user_id'=>$uid,'emoji'=>$emoji]);
        $this->dispatch('$refresh');
    }
    public function loadReactionUsers(string $emoji,?int $commentId=null): void
    {
        $key=$emoji.'|'.($commentId??'null');
        $rows=FeedbackReaction::where('feedback_id',$this->feedback->id)
            ->where('comment_id',$commentId)->where('emoji',$emoji)
            ->with('user:id,name')->orderByDesc('id')->limit(24)->get();
        $this->reactionHover[$key]=['names'=>$rows->map(fn($r)=>optional($r->user)->name??'Unbekannt')->values()->all()];
    }

    // ----- Tags -----
    protected function persistTags(): void
    {
        if(!$this->canModifyFeedback)return;
        $clean=array_values(array_unique(array_filter(array_map('trim',$this->tags))));
        $this->feedback->update(['tags'=>$clean]);
        $this->tags=$clean;
        $this->metaDirty=$this->isMetaDirty();
    }
    public function addTag(?string $t=null): void
    {
        if(!$this->canModifyFeedback)return;
        $t=trim($t??$this->tagInput??''); if($t==='')return;
        $this->tags=array_values(array_unique([...$this->tags,$t]));
        $this->tagInput=''; $this->persistTags();
    }
    public function removeTag(int $index): void
    {
        if(!$this->canModifyFeedback)return;
        unset($this->tags[$index]); $this->tags=array_values($this->tags); $this->persistTags();
    }

    // ----- Meta -----
    public function updatedStatus(string $v): void { if(!$this->canEditStatus){$this->status=$this->feedback->status??'open';return;} if($v==='closed')$this->showCloseModal=true; $this->metaDirty=$this->isMetaDirty();}
    public function updatedPriority(string $v): void { if(!$this->canEditPriority){$this->priority=$this->feedback->priority??'medium';return;} $this->metaDirty=$this->isMetaDirty();}
    public function updatedAssigneeId($v): void { if(!$this->canEditAssignee){$this->assigneeId=$this->feedback->assigned_to_id;return;} $this->metaDirty=$this->isMetaDirty();}
    public function updatedType(string $v): void { if(!$this->canEditType){$this->type=$this->feedback->type??'bug';return;} $this->metaDirty=$this->isMetaDirty();}
    public function confirmCloseInfo(): void { $this->showCloseModal=false; $this->metaDirty=$this->isMetaDirty(); }
    public function cancelCloseSelection(): void { $this->status=$this->previousStatus; $this->showCloseModal=false; $this->metaDirty=$this->isMetaDirty(); }
    public function updatedCommentSort(): void { $this->dispatch('$refresh'); }

    public function saveMeta(): void
    {
        if($this->feedback->status==='closed'||!is_null($this->feedback->deleted_at))return;

        $newStatus   = in_array($this->status,\App\Models\Feedback::STATUSES,true) ? $this->status   : ($this->feedback->status ?? 'open');
        $newPriority = in_array($this->priority,\App\Models\Feedback::PRIORITIES,true) ? $this->priority : ($this->feedback->priority ?? 'medium');
        $newType     = in_array($this->type, ['bug','suggestion'], true) ? $this->type : ($this->feedback->type ?? 'bug');
        $newAssignee = $this->assigneeId ? (int)$this->assigneeId : null;

        if($newStatus==='closed'&&!$this->showCloseModal){$this->showCloseModal=true;return;}

        $changes=[];
        if($newStatus!==$this->feedback->status)$changes['status']=[$this->feedback->status,$newStatus];
        if($newPriority!==$this->feedback->priority)$changes['priority']=[$this->feedback->priority,$newPriority];
        if($newType!==($this->feedback->type ?? 'bug'))$changes['type']=[$this->feedback->type,$newType];
        if($newAssignee!==($this->feedback->assigned_to_id??null)){
            $oldName=optional($this->feedback->assignee)->name??null;
            $newName=optional(User::find($newAssignee))->name??null;
            $changes['assigned_to']=[$oldName,$newName];
        }

        if(!empty($changes)){
            $this->feedback->forceFill([
                'status' => $newStatus,
                'priority' => $newPriority,
                'type' => $newType,
                'assigned_to_id' => $newAssignee,
            ])->save();

            FeedbackEdit::create([
                'feedback_id'=>$this->feedback->id,
                'user_id'=>Auth::id(),
                'changes'=>$changes,
                'snapshot'=>$this->feedback->only(['status','priority','type','tags','assigned_to_id']),
            ]);

            $this->feedback->refresh();
            $this->status     = $this->feedback->status;
            $this->priority   = $this->feedback->priority;
            $this->type       = $this->feedback->type;
            $this->assigneeId = $this->feedback->assigned_to_id;

            $this->recomputePermissions();
            $this->metaDirty=$this->isMetaDirty();
            $this->dispatch('notify',body:'Änderungen gespeichert.');
        }
    }

    // ----- Feedback edit -----
    public function startEditFeedback(): void
    {
        if(!$this->canModifyFeedback) return;
        $this->editingFeedback = true;
        $this->editTitle   = (string)($this->feedback->title ?? '');
        $this->editMessage = (string)($this->feedback->message ?? '');
        $this->editUploads = [];
        $this->removeFeedbackAttachmentIdx = [];
    }

    public function cancelEditFeedback(): void
    {
        $this->editingFeedback = false;
        $this->editTitle = '';
        $this->editMessage = '';
        $this->editUploads = [];
        $this->removeFeedbackAttachmentIdx = [];
    }

    public function removeExistingFeedbackAttachment(int $idx): void
    {
        // we mark it for removal and render UI accordingly
        $this->removeFeedbackAttachmentIdx[$idx] = true;
    }

    public function removeEditUpload(int $idx): void
    {
        if(isset($this->editUploads[$idx])){
            unset($this->editUploads[$idx]);
            $this->editUploads = array_values($this->editUploads);
        }
    }

    public function saveEditFeedback(): void
    {
        if(!$this->canModifyFeedback) return;

        $data = $this->validate([
            'editTitle'   => 'required|string|min:1|max:200',
            'editMessage' => 'required|string|min:1|max:10000',
        ]);

        // filter out removed existing attachments
        $existing = is_array($this->feedback->attachments ?? null) ? $this->feedback->attachments : [];
        $kept = [];
        foreach ($existing as $i => $row) {
            if (!isset($this->removeFeedbackAttachmentIdx[$i])) {
                $kept[] = $row;
            }
        }

        // validate + store new uploads
        $this->validateUploadsArray($this->editUploads, 'editUploads');
        $storedNew = $this->storeFiles($this->editUploads, 'ticket-edits');

        $finalAttachments = array_values([...$kept, ...$storedNew]);

        $changes = [];
        if (($this->feedback->title ?? '') !== $data['editTitle'])   $changes['title']   = [$this->feedback->title, $data['editTitle']];
        if (($this->feedback->message ?? '') !== $data['editMessage']) $changes['message'] = [$this->feedback->message, $data['editMessage']];
        if (json_encode($existing) !== json_encode($finalAttachments)) $changes['attachments'] = ['count:'.count($existing), 'count:'.count($finalAttachments)];

        if(!empty($changes)){
            $this->feedback->update([
                'title'       => $data['editTitle'],
                'message'     => $data['editMessage'],
                'attachments' => $finalAttachments,
            ]);

            FeedbackEdit::create([
                'feedback_id'=>$this->feedback->id,
                'user_id'=>Auth::id(),
                'changes'=>$changes,
                'snapshot'=>$this->feedback->only(['title','message','status','priority','type','tags','assigned_to_id','attachments']),
            ]);

            $this->feedbackEdited = true;
        }

        $this->editingFeedback = false;
        $this->feedback->refresh();
        $this->metaDirty = $this->isMetaDirty();
    }

    // ----- Comment edit -----
    public function startEditComment(int $id): void
    {
        if(!$this->canInteract)return;
        $c=FeedbackComment::find($id);
        if(!$c||$c->feedback_id!==$this->feedback->id)return;
        if($c->user_id!==Auth::id())return;

        $this->editingCommentId   = $c->id;
        $this->editingCommentBody = $c->body;
        $this->editingCommentUploads = [];
        $this->removeCommentAttachmentIdx = [];
    }

    public function removeExistingCommentAttachment(int $idx): void
    {
        $this->removeCommentAttachmentIdx[$idx] = true;
    }

    public function removeEditingCommentUpload(int $idx): void
    {
        if(isset($this->editingCommentUploads[$idx])){
            unset($this->editingCommentUploads[$idx]);
            $this->editingCommentUploads = array_values($this->editingCommentUploads);
        }
    }

    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->editingCommentUploads = [];
        $this->removeCommentAttachmentIdx = [];
    }

    public function saveEditComment(): void
    {
        if(!$this->canInteract)return;

        $this->validate([
            'editingCommentBody' => 'required|string|min:1|max:5000',
        ]);

        $c = FeedbackComment::find($this->editingCommentId);
        if(!$c || $c->feedback_id !== $this->feedback->id) return;
        if($c->user_id !== Auth::id()) return;

        $existing = is_array($c->attachments ?? null) ? $c->attachments : [];
        $kept = [];
        foreach ($existing as $i => $row) {
            if (!isset($this->removeCommentAttachmentIdx[$i])) {
                $kept[] = $row;
            }
        }

        $this->validateUploadsArray($this->editingCommentUploads, 'editingCommentUploads');
        $storedNew = $this->storeFiles($this->editingCommentUploads, 'comment-edits');
        $final = array_values([...$kept, ...$storedNew]);

        if ($c->body !== $this->editingCommentBody) {
            FeedbackCommentEdit::create([
                'comment_id' => $c->id,
                'user_id'    => Auth::id(),
                'old_body'   => $c->body,
                'new_body'   => $this->editingCommentBody,
            ]);
            $this->commentEditedMap[$c->id] = true;
        }

        $c->update([
            'body' => $this->editingCommentBody,
            'attachments' => $final,
        ]);

        $this->editingCommentId = null;
        $this->editingCommentBody = '';
        $this->editingCommentUploads = [];
        $this->removeCommentAttachmentIdx = [];

        $this->dispatch('$refresh');
    }

    // ----- Delete / Restore -----
    public function askDelete(): void { if($this->canModifyFeedback && is_null($this->feedback->deleted_at)) $this->showDeleteConfirm=true; }
    public function cancelDelete(): void { $this->showDeleteConfirm=false; }
    public function confirmDelete(): void
    {
        if(!$this->canModifyFeedback||!is_null($this->feedback->deleted_at))return;
        $this->feedback->delete();
        $this->feedback=Feedback::withTrashed()->findOrFail($this->feedback->id);
        $this->recomputePermissions(); $this->showDeleteConfirm=false;
        $this->dispatch('notify',body:'Feedback gelöscht. Du kannst es wiederherstellen.');
    }
    public function restoreFeedback(): void
    {
        if($this->feedback->user_id!==Auth::id())return;
        $this->feedback->restore();
        $this->feedback=Feedback::withTrashed()->findOrFail($this->feedback->id);
        $this->recomputePermissions();
        $this->dispatch('notify',body:'Feedback wiederhergestellt.');
    }

    // ----- History -----
    public function openFeedbackHistory(): void
    {
        $rows=FeedbackEdit::with('user:id,name')->where('feedback_id',$this->feedback->id)->orderByDesc('id')->limit(100)->get();
        $buf='';
        if($rows->isEmpty()){ $buf.='<div class="text-sm text-zinc-500">Keine Änderungen vorhanden.</div>'; }
        else {
            foreach($rows as $e){
                $buf.='<div class="mb-3">';
                $buf.='<div class="text-xs text-zinc-500">'.$e->created_at->format('d.m.Y H:i').' · '.e($e->user->name??'Unbekannt').'</div>';
                $buf.='<ul class="mt-1 list-disc list-inside text-sm">';
                foreach(($e->changes??[]) as $f=>$pair){ [$old,$new]=$pair;
                    $buf.='<li><span class="font-medium">'.e(ucfirst($f)).'</span>: <span class="line-through opacity-70">'.e(is_array($old)?implode(', ',$old):(string)$old).'</span> → <span>'.e(is_array($new)?implode(', ',$new):(string)$new).'</span></li>';
                }
                $buf.='</ul></div>';
            }
        }
        $this->historyTitle='Änderungshistorie'; $this->historyHtml=$buf; $this->showHistoryModal=true;
    }

    public function openCommentHistory(int $cid): void
    {
        $rows=FeedbackCommentEdit::with('user:id,name')->where('comment_id',$cid)->orderByDesc('id')->limit(100)->get();
        $buf='';
        if($rows->isEmpty()){ $buf.='<div class="text-sm text-zinc-500">Keine Änderungen vorhanden.</div>'; }
        else {
            foreach($rows as $e){
                $buf.='<div class="mb-3">';
                $buf.='<div class="text-xs text-zinc-500">'.$e->created_at->format('d.m.Y H:i').' · '.e($e->user->name ?? 'Unbekannt').'</div>';
                $buf.='<div class="mt-1 text-sm"><span class="font-medium">Vorher:</span> '.nl2br(e($e->old_body)).'</div>';
                $buf.='<div class="mt-1 text-sm"><span class="font-medium">Nachher:</span> '.nl2br(e($e->new_body)).'</div>';
                $buf.='</div>';
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
     $orderDir = $this->commentSort === 'newest' ? 'desc' : 'asc';

    $rootComments = $this->feedback
        ->comments()
        ->whereNull('parent_id')
        ->with([
            'user',
            'reactions.user:id,name',
            'children' => function ($q) {
                $q->orderBy('id', 'asc')
                  ->with(['user','reactions.user:id,name']);
            },
        ])
        ->orderBy('id', $orderDir)
        ->get();

    $attachments = $this->feedback->attachments ?? [];

    return view('livewire.feedback-show',[
        'rootComments'   => $rootComments,
        'attachments'    => $attachments,
        'tagSuggestions' => \App\Models\Feedback::TAG_SUGGESTIONS,
        'canModifyFeedback' => $this->canModifyFeedback,
        'canInteract'       => $this->canInteract,
        'canEditStatus'     => $this->canEditStatus,
        'canEditPriority'   => $this->canEditPriority,
        'canEditAssignee'   => $this->canEditAssignee,
        'canEditType'       => $this->canEditType,
        'feedbackEdited'    => $this->feedbackEdited,
        'commentEditedMap'  => $this->commentEditedMap,
        'metaDirty'         => $this->isMetaDirty(),
        'assignableUsers'   => $this->assignableUsers,
    ]);
}
}

