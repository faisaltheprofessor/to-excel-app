<?php
namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TreeComponent extends Component
{
    public $tree = [];

    public $newNodeName = '';
    public $newAppName  = '';
    public $selectedNodePath = null;
    public $addWithStructure = false;
    public $generatedJson = '';
    public string $downloadFilename = '';

    // inline edit state
    public $editNodePath = null;     // e.g. [0,2,1]
    public $editField = null;        // 'name' | 'appName'
    public $editValue = '';          // temp value while editing

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

    public function mount()
    {
        $this->tree = [];
    }

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

        // Ensure target path is still valid after deletions
        $targetPath = $this->selectedNodePath;
        if (!$this->pathExists($this->tree, $targetPath)) {
            $targetPath = null;
        }

        if ($targetPath === null) {
            $this->tree[] = $newNode;
        } else {
            if (!$this->addChildAtPathSafely($this->tree, $targetPath, $newNode)) {
                // Fallback: push to root and purge broken selection
                $this->tree[] = $newNode;
                $this->selectedNodePath = null;
            }
        }

        // reset create inputs
        $this->newNodeName = '';
        $this->newAppName  = '';
        $this->addWithStructure = false;
    }

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

    // Safe: returns bool
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

    public function removeNode($path)
    {
        $node = $this->getNodeByPathRef($this->tree, $path);
        if (!$node) return;
        if (!($node['deletable'] ?? false)) return;

        $this->removeNodeAtPath($this->tree, $path);

        // Clear selection & edit state after structural change
        $this->selectedNodePath = null;
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
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

    public function selectNode($path)
    {
        // Only save if valid
        $this->selectedNodePath = $this->pathExists($this->tree, $path) ? $path : null;
    }

    // -------- Inline edit ----------
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
        $fields = [];
        $fields[$this->editField] = $val;

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
    }

    public function cancelInlineEdit()
    {
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
    }

    // ---------- helpers ----------
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
            // Normalize: if appName is empty, mirror name
            if (($nodes[$index]['appName'] ?? '') === '') {
                $nodes[$index]['appName'] = $nodes[$index]['name'] ?? '';
            }
        } else {
            $this->setNodeFieldsByPath($nodes[$index]['children'], $path, $fields);
        }
    }

    protected function modalNameFromPath($path)
    {
        return $path === null ? '' : 'edit-' . implode('-', $path);
    }

    // -------- Export / JSON --------
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
            $this->addError('generate', 'Failed to generate Excel file.');
            return;
        }

        $filename = 'tree-' . time() . '.xlsx';
        Storage::put('temp/' . $filename, $response->body());
        $this->downloadFilename = $filename;
        $this->dispatch('excel-ready', filename: $filename);
    }

    /**
     * Recursively strip internal fields for export, wrapped once.
     */
    protected function exportableTree(array $nodes, bool $wrap = true): array
    {
        $out = [];
        foreach ($nodes as $n) {
            // Only export well-formed nodes
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
