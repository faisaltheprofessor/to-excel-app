<?php

namespace App\Livewire;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TreeComponent extends Component
{
    public $tree = [];
    public ?int $treeId = null;
    public string $title = '';

    public $newNodeName = '';
    public $newAppName  = '';
    public $selectedNodePath = null;
    public $addWithStructure = false;
    public $generatedJson = '';
    public string $downloadFilename = '';

    // inline edit state
    public $editNodePath = null;     // e.g. [0,2,1]
    public $editField = null;        // 'name' | 'appName'
    public $editValue = '';

    // Predefined subtree
    protected $predefinedStructure = [
        ['name' => 'Ltg',   'appName' => 'Ltg',   'children' => []],
        ['name' => 'Allg',  'appName' => 'Allg',  'children' => []],
        [
            'name' => 'AblgOE', 'appName' => 'AblgOE',
            'children' => [
                ['name' => 'PoEing', 'appName' => 'PoEing', 'children' => []],
                ['name' => 'SB',     'appName' => 'SB',     'children' => []],
            ],
        ],
    ];

    public function mount(?int $treeId = null)
    {
        if ($treeId) {
            $this->loadTree($treeId);
        } else {
            $this->tree = [];
        }
    }

    // ===== NEW / LOAD / SAVE =====
    public function createNewTree()
    {
        $this->validate(['title' => 'required|string|min:2']);

        $model = TreeModel::create([
            'title' => $this->title,
            'data'  => $this->exportableTree($this->tree), // initially empty
        ]);

        $this->treeId = $model->id;

        // Close modal via Flux helper bound to this component
        $this->modal('new-tree')->close();

        $this->dispatch('toast', type: 'success', message: 'Baum angelegt.');
    }

    public function loadTree(int $id): void
    {
        $model = TreeModel::findOrFail($id);
        $this->treeId = $model->id;
        $this->title  = $model->title;
        $this->tree   = $model->data ?? [];
        $this->hydrateDeletableFlags($this->tree);
    }

    protected function hydrateDeletableFlags(array &$nodes): void
    {
        foreach ($nodes as &$n) {
            if (!array_key_exists('deletable', $n)) $n['deletable'] = true;
            if (!empty($n['children'])) $this->hydrateDeletableFlags($n['children']);
        }
    }

    protected function persist(): void
    {
        if (!$this->treeId) return;
        $model = TreeModel::find($this->treeId);
        if (!$model) return;

        $model->update([
            'title' => $this->title ?: $model->title,
            'data'  => $this->exportableTree($this->tree),
        ]);

        $this->dispatch('autosaved');
    }

    public function updatedTitle(): void
    {
        $this->persist();
    }

    // ===== NODE OPS (all persist) =====
    public function addNode()
    {
        if (trim($this->newNodeName) === '') return;

        $newNode = [
            'name'      => $this->newNodeName,
            'appName'   => ($this->newAppName !== '') ? $this->newAppName : $this->newNodeName,
            'children'  => [],
            'deletable' => true,
        ];
        if ($this->addWithStructure) {
            $newNode['children'] = $this->buildPredefinedChildrenNonDeletable($this->predefinedStructure);
        }

        $targetPath = $this->pathExists($this->tree, $this->selectedNodePath) ? $this->selectedNodePath : null;
        if ($targetPath === null) $this->tree[] = $newNode;
        else $this->addChildAtPathSafely($this->tree, $targetPath, $newNode);

        $this->newNodeName = '';
        $this->newAppName  = '';
        $this->addWithStructure = false;

        $this->persist();
    }

    public function removeNode($path)
    {
        $node = $this->getNodeByPathRef($this->tree, $path);
        if (!$node) return;
        if (!($node['deletable'] ?? false)) return;

        $this->removeNodeAtPath($this->tree, $path);

        $this->selectedNodePath = null;
        $this->editNodePath = null; $this->editField = null; $this->editValue = '';

        $this->persist();
    }

    public function selectNode($path)
    {
        $this->selectedNodePath = $this->pathExists($this->tree, $path) ? $path : null;
    }

    // Inline edit
    public function startInlineEdit($path, $field)
    {
        if (!in_array($field, ['name', 'appName'])) return;
        $node = $this->getNodeByPathRef($this->tree, $path);
        if (!$node) return;

        $this->editNodePath = $path;
        $this->editField    = $field;
        $this->editValue    = $node[$field] ?? '';
    }

    public function saveInlineEdit()
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = trim((string) $this->editValue);
        $fields = [$this->editField => $val];

        $node = $this->getNodeByPathRef($this->tree, $this->editNodePath);
        if ($this->editField === 'name' && $node) {
            if (($node['appName'] ?? '') === ($node['name'] ?? '')) {
                $fields['appName'] = ($val !== '') ? $val : '';
            }
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->persist();
    }

    public function cancelInlineEdit()
    {
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
    }

    // ===== Helpers =====
    protected function buildPredefinedChildrenNonDeletable(array $items): array
    {
        $res = [];
        foreach ($items as $it) {
            $res[] = [
                'name'      => $it['name'],
                'appName'   => $it['appName'] ?? $it['name'],
                'deletable' => false,
                'children'  => !empty($it['children'])
                    ? $this->buildPredefinedChildrenNonDeletable($it['children'])
                    : [],
            ];
        }
        return $res;
    }

    protected function addChildAtPathSafely(&$nodes, $path, $newNode): bool
    {
        if ($path === null || !is_array($path) || empty($path)) return false;

        $index = array_shift($path);
        if (!isset($nodes[$index]) || !is_array($nodes[$index])) return false;

        if (count($path) === 0) {
            if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) {
                $nodes[$index]['children'] = [];
            }
            $nodes[$index]['children'][] = $newNode;
            return true;
        }
        return $this->addChildAtPathSafely($nodes[$index]['children'], $path, $newNode);
    }

    protected function pathExists($nodes, $path): bool
    {
        if ($path === null) return true;
        if (!is_array($path)) return false;

        $ptr = $nodes;
        foreach ($path as $i) {
            if (!isset($ptr[$i]) || !is_array($ptr[$i])) return false;
            $ptr = $ptr[$i]['children'] ?? [];
            if (!is_array($ptr)) $ptr = [];
        }
        return true;
    }

    protected function &getNodeByPathRef(&$nodes, $path)
    {
        $null = null;
        if (!is_array($nodes)) return $null;
        if ($path === null) return $null;
        $ptr =& $nodes;

        $last = is_array($path) ? (count($path) ? $path[count($path)-1] : null) : null;

        foreach ((array)$path as $i) {
            if (!isset($ptr[$i]) || !is_array($ptr[$i])) return $null;
            $node =& $ptr[$i];
            if ($i === $last) {
                return $node; // reference
            }
            if (!isset($node['children']) || !is_array($node['children'])) {
                $node['children'] = [];
            }
            $ptr =& $node['children'];
        }
        return $null;
    }

    protected function setNodeFieldsByPath(&$nodes, $path, $fields)
    {
        $index = array_shift($path);
        if (!isset($nodes[$index])) return;

        if (count($path) === 0) {
            foreach ($fields as $k => $v) {
                $nodes[$index][$k] = $v;
            }
            if (($nodes[$index]['appName'] ?? '') === '') {
                $nodes[$index]['appName'] = $nodes[$index]['name'] ?? '';
            }
        } else {
            $this->setNodeFieldsByPath($nodes[$index]['children'], $path, $fields);
        }
    }

    protected function removeNodeAtPath(&$nodes, $path)
    {
        $index = array_shift($path);
        if (!isset($nodes[$index])) return;

        if (count($path) === 0) {
            array_splice($nodes, $index, 1);
        } else {
            $this->removeNodeAtPath($nodes[$index]['children'], $path);
        }
    }

    protected function modalNameFromPath($path)
    {
        return $path === null ? '' : 'edit-' . implode('-', $path);
    }

    // ===== Export / Excel =====
    public function generateJson()
    {
        $clean = $this->exportableTree($this->tree);
        $this->generatedJson = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function generateExcel(): void
    {
        $payload = ['tree' => $this->exportableTree($this->tree)];

        $response = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post('http://localhost:8000/generate-excel', $payload);

        if (!$response->successful()) {
            $this->addError('generate', 'Excel-Erzeugung fehlgeschlagen.');
            return;
        }

        $filename = 'tree-' . time() . '.xlsx';
        Storage::put('temp/' . $filename, $response->body());
        $this->downloadFilename = $filename;
        $this->dispatch('excel-ready', filename: $filename);
    }

    protected function exportableTree(array $nodes, bool $wrap = true): array
    {
        $out = [];
        foreach ($nodes as $n) {
            if (!is_array($n)) continue;
            $out[] = [
                'name'     => $n['name'] ?? '',
                'appName'  => $n['appName'] ?? ($n['name'] ?? ''),
                'children' => !empty($n['children']) ? $this->exportableTree($n['children'], false) : [],
            ];
        }

        if ($wrap) {
            return [
                [
                    'name'     => '.PANKOW',
                    'appName'  => '.PANKOW',
                    'children' => [
                        [
                            'name'     => 'ba',
                            'appName'  => 'ba',
                            'children' => [
                                [
                                    'name'     => 'DigitaleAkte-203',
                                    'appName'  => 'DigitaleAkte-203',
                                    'children' => $out,
                                ]
                            ],
                        ],
                    ],
                ],
            ];
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.tree-component');
    }
}
