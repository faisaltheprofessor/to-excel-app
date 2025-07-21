<?php

namespace App\Livewire;

use Livewire\Component;

class TreeView extends Component
{
     public $tree = [];

    protected $listeners = ['addChild', 'removeChild', 'updateTitle'];

    public function mount()
    {
        $this->tree = [];
    }

    public function addChild($path = [])
    {
        $ref = &$this->tree;

        foreach ($path as $index) {
            if (!isset($ref[$index]['children'])) {
                $ref[$index]['children'] = [];
            }
            $ref = &$ref[$index]['children'];
        }

        $ref[] = [
            'title' => 'New Item',
            'children' => [],
        ];

        $this->tree = $this->tree; // trigger reactivity
    }

    public function removeChild($path)
    {
        if (empty($path)) return;

        $ref = &$this->tree;

        $lastIndex = array_pop($path);

        foreach ($path as $index) {
            $ref = &$ref[$index]['children'];
        }

        if (isset($ref[$lastIndex])) {
            array_splice($ref, $lastIndex, 1);
        }

        $this->tree = $this->tree;
    }

    public function updateTitle($path, $title)
    {
        $ref = &$this->tree;

        $lastIndex = array_pop($path);
        foreach ($path as $index) {
            $ref = &$ref[$index]['children'];
        }

        if (isset($ref[$lastIndex])) {
            $ref[$lastIndex]['title'] = $title;
        }

        $this->tree = $this->tree;
    }

    public function render()
    {
        return view('livewire.tree-view');
    }
}

