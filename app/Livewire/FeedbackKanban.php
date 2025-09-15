<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\FeedbackEdit;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FeedbackKanban extends Component
{
    /** Selected ticket id (from the board) */
    public ?int $selectedId = null;

    /** Selected Feedback model (fed to FeedbackShow) */
    public ?Feedback $selectedFeedback = null;

    /** Allowed statuses on board */
    public const STATUSES = [
        'open'        => 'Offen',
        'in_progress' => 'In Arbeit',
        'in_review'   => 'Im Review',
        'resolved'    => 'Gelöst',
        'closed'      => 'Geschlossen',
        'wontfix'     => 'Wird nicht behoben',
    ];

    public function mount(): void
    {
        $this->selectedId = null;
        $this->selectedFeedback = null;
    }

    public function selectTicket(int $id): void
    {
        $this->selectedId = $id;
        $this->selectedFeedback = Feedback::withTrashed()
            ->with(['assignee','user'])
            ->find($id);
    }

    public function closePanel(): void
    {
        $this->selectedId = null;
        $this->selectedFeedback = null;
    }

    /** Move card via DnD: update status */
    public function moveCard(int $id, string $toStatus): void
    {
        if (!array_key_exists($toStatus, self::STATUSES)) {
            return;
        }

        $f = Feedback::query()->find($id);
        if (!$f) return;

        // Do not allow moving closed tickets
        if ($f->status === 'closed') return;

        $from = $f->status;
        if ($from === $toStatus) return;

        $f->status = $toStatus;
        $f->save();

        // Optional: write to edit history if your table exists
        if (class_exists(FeedbackEdit::class)) {
            FeedbackEdit::create([
                'feedback_id' => $f->id,
                'user_id'     => Auth::id(),
                'changes'     => ['status' => [$from, $toStatus]],
                'snapshot'    => $f->only(['status','priority','tags','assigned_to_id']),
            ]);
        }

        // Keep right panel in sync if this card is open
        if ($this->selectedId === $f->id) {
            $this->selectedFeedback = $f->fresh(['assignee','user']);
        }
    }

    #[\Livewire\Attributes\Computed]
    public function columns(): array
    {
        $out = [];
        foreach (self::STATUSES as $status => $title) {
            $rows = Feedback::query()
                ->with(['assignee'])
                ->where('status', $status)
                ->latest()
                ->get();

            $cards = $rows->map(function (Feedback $f) {
                // Header color by type
                $typeClass = match ($f->type) {
                    'bug'        => 'bg-rose-500/10 dark:bg-rose-500/20 border-rose-300/60 dark:border-rose-400/30',
                    'suggestion' => 'bg-blue-500/10 dark:bg-blue-500/20 border-blue-300/60 dark:border-blue-400/30',
                    'question', 'feedback' => 'bg-zinc-500/10 dark:bg-zinc-500/20 border-zinc-300/60 dark:border-zinc-400/30',
                    default      => 'bg-emerald-500/10 dark:bg-emerald-500/20 border-emerald-300/60 dark:border-emerald-400/30',
                };

                $typeLabel = match ($f->type) {
                    'bug'        => 'Bug',
                    'suggestion' => 'Feature',
                    'question', 'feedback' => 'Feedback',
                    default      => ucfirst($f->type),
                };

                $prioColor = match ($f->priority) {
                    'urgent' => 'red',
                    'high'   => 'yellow',
                    'normal' => 'blue',
                    'low'    => 'zinc',
                    default  => 'zinc',
                };

                return [
                    'id'        => $f->id,
                    'title'     => $f->title,
                    'type'      => $f->type,
                    'typeLabel' => $typeLabel,
                    'typeClass' => $typeClass,
                    'priority'  => $f->priority,
                    'prioColor' => $prioColor,
                    'assignee'  => optional($f->assignee)->name,
                ];
            })->values()->all();

            $out[] = [
                'key'   => $status,
                'title' => $title,
                'cards' => $cards,
            ];
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.feedback-kanban');
    }
}
