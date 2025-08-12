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

    // Predefined subtree (includes appName and deletable=false via builder)
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
            'deletable' => true, // the node the user creates is deletable
        ];

        if ($this->addWithStructure) {
            $newNode['children'] = $this->buildPredefinedChildrenNonDeletable($this->predefinedStructure);
        }

        if ($this->selectedNodePath === null) {
            $this->tree[] = $newNode;
        } else {
            $this->addChildAtPath($this->tree, $this->selectedNodePath, $newNode);
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

    protected function addChildAtPath(&$nodes, $path, $newNode)
    {
        $index = array_shift($path);
        if (count($path) === 0) {
            $nodes[$index]['children'][] = $newNode;
        } else {
            $this->addChildAtPath($nodes[$index]['children'], $path, $newNode);
        }
    }

    public function removeNode($path)
    {
        // Safety: only remove if node is deletable
        $node = $this->getNodeByPathRef($this->tree, $path);
        if (!$node) return;
        if (!($node['deletable'] ?? false)) return;

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

    // -------- Inline edit (double-click) ----------
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
        // empty check: allow empty, but normalize appName fallback later
        $fields = [];
        $fields[$this->editField] = $val;

        // If editing 'name' and appName was identical before, keep them in sync
        $node = $this->getNodeByPathRef($this->tree, $this->editNodePath);
        if ($this->editField === 'name' && $node) {
            if (($node['appName'] ?? '') === ($node['name'] ?? '')) {
                $fields['appName'] = ($val !== '') ? $val : '';
            }
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        // clear edit state
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
    /**
     * Return a reference to the node at $path (or null if not found)
     */
    protected function &getNodeByPathRef(&$nodes, $path)
    {
        $null = null;
        $ptr =& $nodes;

        foreach ($path as $i) {
            if (!isset($ptr[$i])) return $null;
            $node =& $ptr[$i];
            if (!is_array($node)) return $null;
            if (end($path) === $i) {
                return $node; // reference to the final node
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
     * Recursively strip internal fields (like 'deletable') for export.
     */
protected function exportableTree(array $nodes, bool $wrap = true): array
{
    $out = [];
    foreach ($nodes as $n) {
        $out[] = [
            'name'     => $n['name'] ?? '',
            'appName'  => $n['appName'] ?? ($n['name'] ?? ''),
            // pass $wrap = false so children don't get wrapped again
            'children' => !empty($n['children']) ? $this->exportableTree($n['children'], false) : [],
        ];
    }

    // Only wrap once at the top level
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
