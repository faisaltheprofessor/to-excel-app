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
        ['name' => 'Ltg',  'children' => []],
        ['name' => 'Allg', 'children' => []],
        [
            'name' => 'AblgOE',
            'children' => [
                ['name' => 'PoEing', 'children' => []],
                ['name' => 'SB',     'children' => []],
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

        $this->refreshAppNames($this->tree, null, null);
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

    public function updatedTitle(): void       { $this->persist(); }
    public function updatedNewNodeName(): void { $this->resetValidation(); }
    public function updatedNewAppName(): void  { $this->resetValidation(); }
    public function updatedEditValue(): void   { $this->resetValidation(); }

    // ================== NODE OPS ==================
    public function addNode()
    {
        $nameInput = $this->translitUmlauts(trim((string)$this->newNodeName));
        if ($nameInput === '') return;

        if ($reason = $this->invalidNameReason($nameInput)) {
            $this->addError('newNodeName', $reason);
            return;
        }

        $appInput = $this->translitUmlauts(trim((string)$this->newAppName));
        if ($appInput !== '' && ($reason = $this->invalidNameReason($appInput))) {
            $this->addError('newAppName', $reason);
            return;
        }

        $newNode = [
            'name'           => $nameInput,
            'appName'        => ($appInput !== '') ? $appInput : $nameInput,
            'appNameManual'  => ($appInput !== ''),
            'children'       => [],
            'deletable'      => true,
        ];

        if ($this->addWithStructure) {
            $parentAtPath = $this->effectiveParentNameForPath($this->selectedNodePath);
            $effectiveForChildren = $this->nextEffectiveParent($nameInput, $parentAtPath);

            $newNode['children'] = $this->buildPredefinedChildrenWithParent(
                $this->predefinedStructure,
                $effectiveForChildren
            );
        }

        $targetPath = $this->pathExists($this->tree, $this->selectedNodePath) ? $this->selectedNodePath : null;

        if ($targetPath === null) {
            $this->tree[] = $newNode;
        } else {
            $this->addChildAtPathSafely($this->tree, $targetPath, $newNode);
        }

        $this->refreshAppNames($this->tree, null, null);

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

        $parentPath = (is_array($path) && count($path) > 0) ? array_slice($path, 0, -1) : null;

        $this->removeNodeAtPath($this->tree, $path);

        $this->refreshAppNames($this->tree, null, null);

        if (is_array($parentPath) && count($parentPath) > 0 && $this->pathExists($this->tree, $parentPath)) {
            $this->selectedNodePath = $parentPath;
        } elseif (!empty($this->tree)) {
            $this->selectedNodePath = [0];
        } else {
            $this->selectedNodePath = null;
        }

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

    public function saveInlineEdit($value = null)
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = $this->translitUmlauts(trim((string)($value ?? $this->editValue)));

        if ($reason = $this->invalidNameReason($val)) {
            $this->addError('editValue', $reason);
            return;
        }

        $before  = $this->getNodeAtPath($this->tree, $this->editNodePath);
        $oldName = $before['name'] ?? null;
        $oldApp  = $before['appName'] ?? null;
        $wasManual = (bool)($before['appNameManual'] ?? false);

        $fields = [$this->editField => $val];

        if ($this->editField === 'name') {
            if ($oldName !== null && $oldApp === $oldName && !$wasManual) {
                $fields['appName'] = $val;
                $fields['appNameManual'] = false;
            }
        } elseif ($this->editField === 'appName') {
            $fields['appNameManual'] = true;
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        $this->refreshAppNames($this->tree, null, null);

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

    protected function buildPredefinedChildrenWithParent(array $items, ?string $effectiveParentName): array
    {
        $res = [];
        foreach ($items as $it) {
            $childName = $it['name'];
            $appName = $this->composeAppName($effectiveParentName, $childName);
            $nextEffective = $this->nextEffectiveParent($childName, $effectiveParentName);

            $res[] = [
                'name'           => $childName,
                'appName'        => $appName,
                'appNameManual'  => false,
                'children'       => !empty($it['children'])
                    ? $this->buildPredefinedChildrenWithParent($it['children'], $nextEffective)
                    : [],
                'deletable'      => !$this->isFixedName($childName),
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

    protected function getNodeAtPath($nodes, $path): ?array
    {
        if ($path === null || !is_array($path)) return null;
        $ptr = $nodes;
        $last = count($path) - 1;
        foreach ($path as $depth => $idx) {
            if (!isset($ptr[$idx]) || !is_array($ptr[$idx])) return null;
            $node = $ptr[$idx];
            if ($depth === $last) return $node;
            $ptr = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
        }
        return null;
    }

    protected function getNameAtPath($nodes, $path): ?string
    {
        $n = $this->getNodeAtPath($nodes, $path);
        return $n['name'] ?? null;
    }

    protected function setNodeFieldsByPath(&$nodes, $path, $fields)
    {
        if ($path === null || !is_array($path)) return;

        $ptr =& $nodes;
        $last = count($path) - 1;

        foreach ($path as $depth => $idx) {
            if (!isset($ptr[$idx]) || !is_array($ptr[$idx])) return;

            if ($depth === $last) {
                foreach ($fields as $k => $v) {
                    $ptr[$idx][$k] = $v;
                }
                if (($ptr[$idx]['appName'] ?? '') === '') {
                    $ptr[$idx]['appName'] = $ptr[$idx]['name'] ?? '';
                }
                if (!isset($ptr[$idx]['appNameManual'])) {
                    $ptr[$idx]['appNameManual'] = false;
                }
                return;
            }

            if (!isset($ptr[$idx]['children']) || !is_array($ptr[$idx]['children'])) {
                $ptr[$idx]['children'] = [];
            }
            $ptr =& $ptr[$idx]['children'];
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

    protected function effectiveParentNameForPath($path): ?string
    {
        if ($path === null || !is_array($path) || empty($path)) {
            return null;
        }

        $parentName = $this->getNameAtPath($this->tree, $path);
        if ($parentName === null) {
            return null;
        }

        if ($parentName === 'AblgOE') {
            $gpPath = $path;
            array_pop($gpPath);
            $grandparentName = $this->getNameAtPath($this->tree, $gpPath);
            return $grandparentName ?? null;
        }

        return $parentName;
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
    protected function invalidNameReason(string $name): ?string
    {
        if (substr_count($name, '-') > 3) {
            return 'Name darf höchstens drei Bindestriche (-) enthalten.';
        }
        return null;
    }

    // ================== APPNAME RULES ==================
    protected function abbr(string $name): string
    {
        $map = [
            'Ltg'    => 'Ltg',
            'Allg'   => 'Allg',
            'PoEing' => 'PE',
            'SB'     => 'SB',
        ];
        if (isset($map[$name])) return $map[$name];

        $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $name);
        if ($letters === '') return $name;
        $first  = mb_strtoupper(mb_substr($letters, 0, 1));
        $second = mb_strtolower(mb_substr($letters, 1, 1));
        return $first . $second;
    }

    protected function composeAppName(?string $effectiveParent, string $childName): string
    {
        if ($effectiveParent === null || $effectiveParent === '') {
            return $childName;
        }
        if ($childName === 'AblgOE') {
            return 'Ab_' . $effectiveParent;
        }
        return $effectiveParent . '_' . $this->abbr($childName);
    }

    protected function nextEffectiveParent(string $currentNodeName, ?string $currentEffectiveParent): ?string
    {
        return ($currentNodeName === 'AblgOE') ? $currentEffectiveParent : $currentNodeName;
    }

    protected function refreshAppNames(array &$nodes, ?string $parentName, ?string $grandparentName): void
    {
        foreach ($nodes as &$n) {
            $name = $n['name'] ?? '';

            if (!isset($n['appName']) || $n['appName'] === '') {
                $n['appName'] = $name;
            }
            if (!isset($n['appNameManual'])) {
                $n['appNameManual'] = false;
            }

            $effectiveParent = $parentName;
            if ($parentName === 'AblgOE') {
                $effectiveParent = $grandparentName;
            }
            $nextEffectiveParent = $this->nextEffectiveParent($name, $effectiveParent);

            if (!empty($n['children']) && is_array($n['children'])) {
                foreach ($n['children'] as &$child) {
                    $childName = $child['name'] ?? '';
                    $manual = isset($child['appNameManual']) && $child['appNameManual'] === true;

                    if (!$manual) {
                        $keep = isset($child['appName']) && $child['appName'] === $childName;
                        if (!$keep) {
                            $child['appName'] = $this->composeAppName($nextEffectiveParent, $childName);
                        }
                    }
                    if (!isset($child['appNameManual'])) {
                        $child['appNameManual'] = $manual;
                    }
                }
                $this->refreshAppNames($n['children'], $name, $effectiveParent);
            }
        }
    }

    // ================== INPUT NORMALIZATION ==================
    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss',
        ];
        return strtr($s, $map);
    }

    // ================== DRAG & DROP ==================
    /** Move a node (by path) under a target node (by path). Drop ONTO target = append as last child */
    public function moveNode($fromPath, $toPath): void
    {
        if (!$this->pathExists($this->tree, $fromPath) || !$this->pathExists($this->tree, $toPath)) return;

        if ($fromPath === $toPath || $this->isAncestorPath($fromPath, $toPath)) return;

        $moved = $this->extractNodeAtPath($this->tree, $fromPath);
        if ($moved === null) return;

        $this->appendChildAtPath($this->tree, $toPath, $moved);

        $this->refreshAppNames($this->tree, null, null);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();

        $newChildIndex = $this->lastChildIndexAtPath($this->tree, $toPath);
        $this->selectedNodePath = array_merge($toPath, [$newChildIndex]);
    }

    protected function isAncestorPath(array $a, array $b): bool
    {
        if (count($a) >= count($b)) return false;
        for ($i = 0; $i < count($a); $i++) {
            if ($a[$i] !== $b[$i]) return false;
        }
        return true;
    }

    protected function extractNodeAtPath(&$nodes, $path)
    {
        $index = array_shift($path);
        if (!isset($nodes[$index])) return null;

        if (count($path) === 0) {
            $node = $nodes[$index];
            array_splice($nodes, $index, 1);
            return $node;
        }
        if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) return null;
        return $this->extractNodeAtPath($nodes[$index]['children'], $path);
    }

    protected function appendChildAtPath(&$nodes, $path, $newNode): void
    {
        $index = array_shift($path);
        if (!isset($nodes[$index])) return;

        if (count($path) === 0) {
            if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) {
                $nodes[$index]['children'] = [];
            }
            $nodes[$index]['children'][] = $newNode;
            return;
        }
        if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) {
            $nodes[$index]['children'] = [];
        }
        $this->appendChildAtPath($nodes[$index]['children'], $path, $newNode);
    }

    protected function lastChildIndexAtPath($nodes, $path): int
    {
        $n = $this->getNodeAtPath($nodes, $path);
        if (!$n) return -1;
        $kids = $n['children'] ?? [];
        return is_array($kids) ? max(count($kids) - 1, -1) : -1;
    }
}
