<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TreeComponent extends Component
{
    public $tree = [];
    public $newNodeName = '';
    public $selectedNodePath = null;  // to keep track of selected node (path as array)
    public $addWithStructure = false;
    public $generatedJson = '';
    public string $downloadFilename = '';

    // Predefined subtree structure
    protected $predefinedStructure = [
        [
            'name' => 'Ltg',
            'children' => [],
        ],
        [
            'name' => 'Alg',
            'children' => [],
        ],
        [
            'name' => 'AblgOE',
            'children' => [
                ['name' => 'PoEing', 'children' => []],
                ['name' => 'SB', 'children' => []],
            ],
        ],
    ];

    public function mount()
    {
        $this->tree = [];
    }

    public function addNode()
    {
        if (empty($this->newNodeName)) return;

        $newNode = ['name' => $this->newNodeName, 'children' => []];

        if ($this->addWithStructure) {

            $newNode['children'] = $this->predefinedStructure;
        }

        if ($this->selectedNodePath === null) {
            // Add to root level
            $this->tree[] = $newNode;
        } else {
            // Add as child to selected node
            $this->addChildAtPath($this->tree, $this->selectedNodePath, $newNode);
        }

        $this->newNodeName = '';
        $this->addWithStructure = false;
    }

    protected function addChildAtPath(&$nodes, $path, $newNode)
    {
        $index = array_shift($path);

        if (count($path) === 0) {
            // At the target node, add child
            $nodes[$index]['children'][] = $newNode;
        } else {
            // Go deeper
            $this->addChildAtPath($nodes[$index]['children'], $path, $newNode);
        }
    }

    public function removeNode($path)
    {
        $this->removeNodeAtPath($this->tree, $path);
    }

    protected function removeNodeAtPath(&$nodes, $path)
    {
        $index = array_shift($path);

        if (count($path) === 0) {
            array_splice($nodes, $index, 1);
        } else {
            $this->removeNodeAtPath($nodes[$index]['children'], $path);
        }
    }

    public function selectNode($path)
    {
        $this->selectedNodePath = $path;
    }

    public function generateJson()
    {
        $this->generatedJson = json_encode($this->tree, JSON_PRETTY_PRINT);
    }


    public function generateExcel(): void
    {
        $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post('http://localhost:8000/generate-excel', ['tree' => $this->tree]);

        if (!$response->successful()) {
            $this->addError('generate', 'Failed to generate Excel file.');
            return;
        }

        $filename = 'tree-' . time() . '.xlsx';
        $path = 'temp/' . $filename;

        // Save the binary content to storage/app/temp/
        Storage::put($path, $response->body());

        // Store the filename so frontend knows what to download
        $this->downloadFilename = $filename;
        // Emit event to trigger frontend download
        $this->dispatch('excel-ready', filename: $filename);
    }

    public function render()
    {
        return view('livewire.tree-component');
    }
}
