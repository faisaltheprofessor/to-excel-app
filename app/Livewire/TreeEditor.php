<?php

namespace App\Livewire;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TreeEditor extends Component
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

    public $editNodePath = null;
    public $editField = null;
    public $editValue = '';

    /** Names that must never be deletable */
    protected array $fixedNames = ['Ltg','Allg','AblgOE','PoEing','SB'];

    /** Default subtree used when "mit Ablagen" is checked */
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

    public function mount(TreeModel $tree)
    {
        $this->treeId = $tree->id;
        $this->title  = $tree->title;

        $data = $tree->data ?? [];
        $this->tree = $this->unwrapIfWrapped($data);
        $this->sanitizeDeletionFlags($this->tree);
    }

    /** Persist RAW (no wrapper) */
    protected function persist(): void
    {
        if (!$this->treeId) return;
        $model = TreeModel::find($this->treeId);
        if (!$model) return;

        $model->update([
            'title' => $this->title !== '' ? $this->title : $model->title,
            'data'  => $this->tree,
        ]);

        $this->dispatch('autosaved');
    }

    public function updatedTitle(): void
    {
        $this->persist();
    }

    // ---- Clear errors instantly while typing (ADD + UPDATE) ----
    public function updatedNewNodeName(): void
    {
        $this->resetValidation();
    }

    public function updatedNewAppName(): void
    {
        $this->resetValidation();
    }

    public function updatedEditValue(): void
    {
        $this->resetValidation();
    }

    // ================== NODE OPS ==================
    public function addNode()
    {
        if (trim($this->newNodeName) === '') return;

        // Validate name and appName for ADD
        if ($reason = $this->invalidNameReason($this->newNodeName)) {
            $this->addError('newNodeName', $reason);
            return;
        }
        if ($this->newAppName !== '' && ($reason = $this->invalidNameReason($this->newAppName))) {
            $this->addError('newAppName', $reason);
            return;
        }

        $newNode = [
            'name'      => $this->newNodeName,
            'appName'   => ($this->newAppName !== '') ? $this->newAppName : $this->newNodeName,
            'children'  => [],
            'deletable' => true,
        ];

        if ($this->addWithStructure) {
            $newNode['children'] = $this->buildPredefinedChildren($this->predefinedStructure);
        }

        $targetPath = $this->pathExists($this->tree, $this->selectedNodePath) ? $this->selectedNodePath : null;

        if ($targetPath === null) {
            $this->tree[] = $newNode;
        } else {
            $this->addChildAtPathSafely($this->tree, $targetPath, $newNode);
        }

        $this->newNodeName = '';
        $this->newAppName  = '';
        $this->addWithStructure = false;

        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    public function removeNode($path)
    {
        $node = $this->getNodeAtPath($this->tree, $path);
        if (!$node) return;
        if ($this->isFixedName($node['name'] ?? '')) return;

        $this->removeNodeAtPath($this->tree, $path);

        $this->selectedNodePath = null;
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    public function selectNode($path)
    {
        $this->selectedNodePath = $this->pathExists($this->tree, $path) ? $path : null;
    }

    public function startInlineEdit($path, $field)
    {
        if (!in_array($field, ['name', 'appName'])) return;

        $node = $this->getNodeAtPath($this->tree, $path);
        if (!$node) return;

        $this->editNodePath = $path;
        $this->editField    = $field;
        $this->editValue    = $node[$field] ?? '';
    }

    public function saveInlineEdit()
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = trim((string) $this->editValue);

        // Validate for UPDATE (both name and appName use same rule)
        if ($reason = $this->invalidNameReason($val)) {
            $this->addError('editValue', $reason);
            return;
        }

        $fields = [$this->editField => $val];

        // If renaming and appName mirrored old name, keep them in sync
        $node = $this->getNodeAtPath($this->tree, $this->editNodePath);
        if ($this->editField === 'name' && $node) {
            if (($node['appName'] ?? '') === ($node['name'] ?? '')) {
                $fields['appName'] = ($val !== '') ? $val : '';
            }
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    public function cancelInlineEdit()
    {
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
    }

    // ================== EXPORT ==================
    public function generateJson()
    {
        $wrapped = $this->wrapForExport($this->tree);
        $this->generatedJson = json_encode($wrapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function generateExcel(): void
    {
        $payload = ['tree' => $this->wrapForExport($this->tree)];
        $res = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post('http://localhost:8000/generate-excel', $payload);

        if (!$res->successful()) {
            $this->addError('generate', 'Excel-Erzeugung fehlgeschlagen.');
            return;
        }

        // File name: importer_{title}.xlsx (spaces -> underscores)
        $safeTitle = preg_replace('/\s+/', '_', trim($this->title));
        $filename = 'importer_' . $safeTitle . '.xlsx';

        Storage::put('temp/' . $filename, $res->body());
        $this->downloadFilename = $filename;
        $this->dispatch('excel-ready', filename: $filename);
    }

    protected function wrapForExport(array $nodes): array
    {
        $clean = $this->stripInternal($nodes);

        return [[
            'name'=>'.PANKOW','appName'=>'.PANKOW',
            'children' => [[
                'name'=>'ba','appName'=>'ba',
                'children'=> [[
                    'name'=>'DigitaleAkte-203','appName'=>'DigitaleAkte-203',
                    'children'=>$clean
                ]]
            ]]
        ]];
    }

    protected function stripInternal(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = [
                'name'     => $n['name'] ?? '',
                'appName'  => $n['appName'] ?? ($n['name'] ?? ''),
                'children' => !empty($n['children']) ? $this->stripInternal($n['children']) : [],
            ];
        }
        return $out;
    }

    // ================== HELPERS ==================
    protected function unwrapIfWrapped(array $data): array
    {
        if (
            count($data) === 1 &&
            isset($data[0]['name']) && $data[0]['name'] === '.PANKOW' &&
            !empty($data[0]['children']) &&
            isset($data[0]['children'][0]['name']) && $data[0]['children'][0]['name'] === 'ba' &&
            !empty($data[0]['children'][0]['children']) &&
            isset($data[0]['children'][0]['children'][0]['name']) &&
            $data[0]['children'][0]['children'][0]['name'] === 'DigitaleAkte-203'
        ) {
            return $data[0]['children'][0]['children'][0]['children'] ?? [];
        }
        return $data;
    }

    protected function isFixedName(?string $name): bool
    {
        return in_array((string)$name, $this->fixedNames, true);
    }

    protected function sanitizeDeletionFlags(array &$nodes): void
    {
        foreach ($nodes as &$n) {
            $n['deletable'] = !$this->isFixedName($n['name'] ?? '');
            if (!empty($n['children']) && is_array($n['children'])) {
                $this->sanitizeDeletionFlags($n['children']);
            }
        }
    }

    protected function buildPredefinedChildren(array $items): array
    {
        $res = [];
        foreach ($items as $it) {
            $child = [
                'name'      => $it['name'],
                'appName'   => $it['appName'] ?? $it['name'],
                'children'  => !empty($it['children']) ? $this->buildPredefinedChildren($it['children']) : [],
                'deletable' => !$this->isFixedName($it['name']),
            ];
            $res[] = $child;
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

    protected function getNodeAtPath($nodes, $path): ?array
    {
        if ($path === null || !is_array($path)) return null;
        $ptr = $nodes;
        foreach ($path as $i) {
            if (!isset($ptr[$i]) || !is_array($ptr[$i])) return null;
            $node = $ptr[$i];
            if ($i === end($path)) return $node;
            $ptr = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        }
        return null;
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

    public function delete()
    {
        if (TreeModel::find($this->treeId)->delete()) {
            return $this->redirect('/importer', navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.tree-editor');
    }

    // ================== VALIDATION HELPERS ==================
    /**
     * Same rules for name & appName: no '_' and at most two '-'.
     */
    protected function invalidNameReason(string $name): ?string
    {
        if (substr_count($name, '-') > 3) {
            return 'Name darf höchstens drei Bindestriche (-) enthalten.';
        }
        return null;
    }
}
