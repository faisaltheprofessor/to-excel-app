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
    #[Url] public string $type = 'all'; // all|bug|suggestion|question

    public function updatingQ() { $this->resetPage(); }
    public function updatingType() { $this->resetPage(); }

    public function render()
    {
        $query = Feedback::query()->latest();

        if ($this->type !== 'all') {
            $query->where('type', $this->type);
        }
        if ($this->q !== '') {
            $query->where(function($w){
                $w->where('message', 'like', '%'.$this->q.'%')
                  ->orWhere('url', 'like', '%'.$this->q.'%');
            });
        }

        $items = $query->paginate(12);

        return view('livewire.feedback-index', [
            'items' => $items,
        ]);
    }
}

