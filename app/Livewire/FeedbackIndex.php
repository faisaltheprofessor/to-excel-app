<?php

namespace App\Livewire;

use App\Models\Feedback;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FeedbackIndex extends Component
{
    use WithPagination;

    #[Url] public string $q = '';
    #[Url] public string $type = 'all';
    #[Url] public string $status = 'all';
    #[Url] public string $tag = 'all';
    #[Url] public string $priority = 'all'; // NEW

    public function updatingQ(){ $this->resetPage(); }
    public function updatingType(){ $this->resetPage(); }
    public function updatingStatus(){ $this->resetPage(); }
    public function updatingTag(){ $this->resetPage(); }
    public function updatingPriority(){ $this->resetPage(); } // NEW

    public function render()
    {
        $query = Feedback::query()->with('user')->latest();

        if ($this->type !== 'all')    $query->where('type', $this->type);
        if ($this->status !== 'all')  $query->where('status', $this->status);
        if ($this->priority !== 'all') $query->where('priority', $this->priority); // NEW
        if ($this->q !== '') {
            $query->where(function($w){
                $w->where('message', 'like', '%'.$this->q.'%')
                  ->orWhere('url', 'like', '%'.$this->q.'%');
            });
        }
        if ($this->tag !== 'all') $query->whereJsonContains('tags', $this->tag);

        $items = $query->paginate(12);

        return view('livewire.feedback-index', [
            'items' => $items,
            'allTags' => Feedback::TAG_SUGGESTIONS,
        ]);
    }
}
