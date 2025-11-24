<?php

namespace App\Livewire;

use App\Models\OrganizationStructure;
use Livewire\Component;

class TreeIndex extends Component
{
    public string $search = '';

    public function render()
    {
        $q = OrganizationStructure::query();

        if ($this->search !== '') {
            $q->where('title', 'like', '%'.$this->search.'%');
        }

        $trees = $q->latest()->get();

        return view('livewire.tree-index', compact('trees'));
    }
}
