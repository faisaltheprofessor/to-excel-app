<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class FeedbackKanban extends Component
{
    public ?int $selectedId = null;
    public ?Feedback $selectedFeedback = null;

    public array $assigneeFilter = [];
    public string $assigneeSearch = '';
    public array $priorityFilter = [];
    public array $typeFilter = [];
    public array $tagFilter = [];

    public int $numberOfTickets = 0;

    public function mount()
    {
        $this->numberOfTickets = Feedback::count();
    }

    public function selectTicket(int $id): void
    {
        $this->selectedId = $id;
        $this->selectedFeedback = Feedback::with(['user','assignee'])->find($id);
    }

    public function closePanel(): void
    {
        $this->selectedId = null;
        $this->selectedFeedback = null;
    }

    public function moveTicket(int $ticketId, string $newStatus): void
    {
        if (!in_array($newStatus, Feedback::STATUSES, true)) return;

        $fb = Feedback::find($ticketId);
        if (!$fb) return;
        if ($fb->status === 'closed') return;

        if ($fb->status !== $newStatus) {
            $fb->status = $newStatus;
            $fb->save();
        }

        if ($this->selectedFeedback?->id === $fb->id) {
            $this->selectedFeedback = $fb->fresh(['user','assignee']);
        }
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->when($this->assigneeSearch, fn($q) =>
                $q->where('name', 'like', '%'.$this->assigneeSearch.'%')
            )
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name']);
    }

    #[Computed]
    public function columns(): array
    {
        $statusTitles = [
            'open'        => 'Offen',
            'in_progress' => 'In Arbeit',
            'resolved'    => 'Gelöst',
            'in_review'   => 'Im Review',
            'closed'      => 'Geschlossen',
            'wontfix'     => 'Wird nicht behoben',
        ];

        $typeClassMap = [
            'bug'        => 'bg-rose-500/10 border-rose-300/60 dark:bg-rose-500/20 dark:border-rose-400/30',
            'suggestion' => 'bg-blue-500/10 border-blue-300/60 dark:bg-blue-500/20 dark:border-blue-400/30',
            'question'   => 'bg-zinc-500/10 border-zinc-300/60 dark:bg-zinc-500/20 dark:border-zinc-400/30',
            'feedback'   => 'bg-zinc-500/10 border-zinc-300/60 dark:bg-zinc-500/20 dark:border-zinc-400/30',
        ];
        $prioColorMap = [
            'urgent' => 'red',
            'high'   => 'yellow',
            'normal' => 'blue',
            'low'    => 'zinc',
        ];

        $cols = [];
        foreach ($statusTitles as $status => $label) {
            $q = Feedback::query()
                ->with(['assignee'])
                ->where('status', $status);

            if (!empty($this->assigneeFilter)) {
                $vals = $this->assigneeFilter;
                $q->where(function ($w) use ($vals) {
                    foreach ($vals as $val) {
                        if ($val === 'me') {
                            $w->orWhere('assigned_to_id', auth()->id());
                        } elseif ($val === 'none') {
                            $w->orWhereNull('assigned_to_id');
                        } elseif (is_numeric($val)) {
                            $w->orWhere('assigned_to_id', (int) $val);
                        }
                    }
                });
            }

            if (!empty($this->priorityFilter)) {
                $q->whereIn('priority', $this->priorityFilter);
            }

            if (!empty($this->typeFilter)) {
                $q->whereIn('type', $this->typeFilter);
            }

            if (!empty($this->tagFilter)) {
                $tags = $this->tagFilter;
                $q->where(function ($sub) use ($tags) {
                    foreach ($tags as $tag) {
                        $sub->orWhereJsonContains('tags', $tag);
                    }
                });
            }

            $cards = $q->latest()->get()->map(function (Feedback $f) use ($typeClassMap, $prioColorMap) {
                return [
                    'id'         => $f->id,
                    'title'      => $f->title,
                    'message'    => $f->message,
                    'type'       => $f->type,
                    'typeClass'  => $typeClassMap[$f->type] ?? 'bg-emerald-500/10 border-emerald-300/60 dark:bg-emerald-500/20 dark:border-emerald-400/30',
                    'priority'   => $f->priority,
                    'prioColor'  => $prioColorMap[$f->priority] ?? 'blue',
                    'assignee'   => $f->assignee?->name,
                ];
            })->all();

            $cols[] = [
                'key'   => $status,
                'title' => $label,
                'cards' => $cards,
            ];
        }

        return $cols;
    }

    #[On('ticket-deleted')]
    public function onTicketDeleted(int $id): void
    {
        if ($this->selectedId === $id) {
            $this->closePanel();
        }
    }

    public function render()
    {
        return view('livewire.feedback-kanban', [
            'allTags' => \App\Models\Feedback::TAG_SUGGESTIONS,
        ]);
    }
}
