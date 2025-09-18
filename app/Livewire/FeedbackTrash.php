<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\FeedbackComment;
use App\Models\FeedbackCommentEdit;
use App\Models\FeedbackEdit;
use App\Models\FeedbackReaction;
use Flux; // PHP helper/facade you mentioned: Flux::toast('Message')
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FeedbackTrash extends Component
{
    use WithPagination;

    // Filters (persist in URL where it makes sense)
    #[Url] public string $q = '';
    public array $type = [];      // ['bug','suggestion','feedback','question']
    public array $priority = [];  // ['low','normal','high','urgent']

    // Selection state
    public array $selected = [];  // selected ticket IDs (across pages)
    public bool $selectPage = false; // header checkbox (select all on current page)

    /* ===========================
       Query helpers
       =========================== */

    /** Paginator source used by the table */
    public function getRowsProperty()
    {
        return $this->baseQuery()->paginate(15);
    }

    /** Total count of current filtered list */
    public function getTotalProperty(): int
    {
        return (int) $this->baseQuery()->count();
    }

    /** Base builder used for both table + bulk ops (NO paginate here) */
    protected function baseQuery()
    {
        return Feedback::onlyTrashed()
            ->with(['assignee','user'])
            ->when($this->q !== '', function ($q) {
                $q->where(function ($w) {
                    $w->where('title', 'like', '%'.$this->q.'%')
                      ->orWhere('message', 'like', '%'.$this->q.'%');
                });
            })
            ->when(!empty($this->type), fn($q) => $q->whereIn('type', $this->type))
            ->when(!empty($this->priority), fn($q) => $q->whereIn('priority', $this->priority))
            ->latest('deleted_at');
    }

    /* ===========================
       Selection (pure Livewire)
       =========================== */

    /** When header "select page" checkbox toggles, sync $selected */
    public function updatedSelectPage(bool $checked): void
    {
        $pageIds = $this->rows->pluck('id')->all();
        if (empty($pageIds)) return;

        if ($checked) {
            $this->selected = array_values(array_unique(array_merge($this->selected, $pageIds)));
        } else {
            $this->selected = array_values(array_diff($this->selected, $pageIds));
        }
    }

    /* ===========================
       Single-item actions
       =========================== */

    public function restoring(int $id): void
    {
        $fb = Feedback::withTrashed()->findOrFail($id);
        $fb->restore();

        // Clean selection and UI
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->selectPage = false;
        $this->resetPage();

        Flux::toast(variant: 'success', heading: 'Wiederhergestellt', text: "Ticket #{$id} wiederhergestellt.");

    }

    public function destroyForever(int $id): void
    {
        $this->hardDeleteMany(collect([$id]));

        // Clean selection and UI
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->selectPage = false;
        $this->resetPage();

        Flux::toast(variant: "success",  heading: 'Gelöscht',    text: "Ticket #{$id} gelöscht.",);
    }

    /* ===========================
       Bulk actions
       =========================== */

    public function restoreBulk(): void
    {
        $ids = $this->selectedIdsOrAll();
        if ($ids->isEmpty()) return;

        DB::transaction(function () use ($ids) {
            Feedback::withTrashed()->whereIn('id', $ids)->restore();
        });

        // Clean selection and UI
        $this->selected   = array_values(array_diff($this->selected, $ids->all()));
        $this->selectPage = false;
        $this->resetPage();

        $n = $ids->count();
        Flux::toast(variant: 'success', heading: 'Wiederhergestellt', text: $n === 1 ? '1 Ticket wiederhergestellt.' : "{$n} Tickets wiederhergestellt.");


    }

    public function deleteBulk(): void
    {
        $ids = $this->selectedIdsOrAll();
        if ($ids->isEmpty()) return;

        $this->hardDeleteMany($ids);

        // Clean selection and UI
        $this->selected   = array_values(array_diff($this->selected, $ids->all()));
        $this->selectPage = false;
        $this->resetPage();

        $n = $ids->count();
        Flux::toast(variant: 'success', heading: 'Gelöscht', text: $n === 1 ? '1 Ticket gelöscht.' : "{$n} Tickets gelöscht.");

    }

    /** If any selected → act on selected; else act on ALL currently listed by filters */
    protected function selectedIdsOrAll()
    {
        return !empty($this->selected)
            ? collect($this->selected)
            : $this->baseQuery()->pluck('id');
    }

    /* ===========================
       Hard delete (files + relations)
       =========================== */

    protected function hardDeleteMany($ids): void
    {
        $disk = Storage::disk('public');

        DB::transaction(function () use ($ids, $disk) {
            // 1) Gather comment IDs for these feedbacks (include soft-deleted)
            $commentIds = FeedbackComment::withTrashed()
                ->whereIn('feedback_id', $ids)
                ->pluck('id');

            // 2) Delete comment attachments by stored path
            FeedbackComment::withTrashed()
                ->whereIn('id', $commentIds)
                ->get(['attachments'])
                ->each(function ($c) use ($disk) {
                    foreach ((array) $c->attachments as $att) {
                        if (!empty($att['path'])) {
                            $disk->delete($att['path']);
                        }
                    }
                });

            // 3) Delete ticket attachments by stored path
            Feedback::withTrashed()
                ->whereIn('id', $ids)
                ->get(['attachments'])
                ->each(function ($fb) use ($disk) {
                    foreach ((array) $fb->attachments as $att) {
                        if (!empty($att['path'])) {
                            $disk->delete($att['path']);
                        }
                    }
                });

            // 4) Clean relational rows
            FeedbackReaction::whereIn('feedback_id', $ids)->delete();
            FeedbackEdit::whereIn('feedback_id', $ids)->delete();
            FeedbackCommentEdit::whereIn('feedback_comment_id', $commentIds)->delete();
            FeedbackComment::withTrashed()->whereIn('id', $commentIds)->forceDelete();

            // 5) Finally remove feedback rows
            Feedback::withTrashed()->whereIn('id', $ids)->forceDelete();
        });
    }

    /* ===========================
       Render
       =========================== */

    public function render()
    {
        return view('livewire.feedback-trash');
    }
}
