<?php

namespace App\Livewire;

use Livewire\Component;

class TreeNode extends Component
{
       public $node;
    public $path = [];
    public $expanded = true;
    public $editing = false;
    public $title;

    public function mount()
    {
        $this->title = $this->node['title'];
    }

    public function toggle()
    {
        $this->expanded = !$this->expanded;
    }

    public function add()
    {
        $this->dispatch('addChild', $this->path);
    }

    public function remove()
    {
        $this->dispatch('removeChild', $this->path);
    }

    public function startEditing()
    {
        $this->editing = true;
    }

    public function saveTitle()
    {
        $this->editing = false;
        $this->dispatch('updateTitle', $this->path, $this->title);
    }

    public function render()
    {
        return view('livewire.tree-node');
    }
}

