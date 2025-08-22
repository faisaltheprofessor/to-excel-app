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

    // Pending move (Bestätigungsdialog)
    public array $pendingFromPath = [];
    public array $pendingToPath = [];
    public string $pendingPosition = 'into';
    public string $pendingOldParentName = '';
    public string $pendingNewParentName = '';
    public bool $pendingSameParent = false;
    public string $pendingWithinParentName = '';
    public int $pendingFromIndex = -1;
    public int $pendingToIndex   = -1;
    public string $pendingOldParentPathStr = '';
    public string $pendingNewParentPathStr = '';

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

public function updatedTitle(): void
{
    // Build a candidate to validate/save, but DON'T overwrite the input yet
    $candidate = $this->normalizeTitle((string)$this->title);

    // Empty / Windows-style rules
    if ($candidate === '') {
        $this->addError('title', 'Name darf nicht leer sein.');
        return;
    }
    if ($reason = $this->invalidNameReason($candidate)) {
        $this->addError('title', $reason);
        return;
    }

    // Case-insensitive uniqueness (exclude current record)
    $exists = TreeModel::query()
        ->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])
        ->where('id', '!=', $this->treeId)
        ->exists();

    if ($exists) {
        $this->addError('title', 'Name ist bereits vergeben (Groß-/Kleinschreibung unbeachtet).');
        return;
    }

    // Passed: clear errors, now commit the normalized value
    $this->resetErrorBag('title');
    $this->title = $candidate;
    $this->persist();
}
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

        // Re-focus the "Neuer Knotenname" input
        $this->dispatch('focus-newnode');
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

    /*** accept the value explicitly to avoid snap-back / race ***/
    public function saveInlineEdit($value = null)
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = $this->translitUmlauts(trim((string)($value ?? $this->editValue)));
        if ($reason = $this->invalidNameReason($val)) {
            $this->addError('editValue', $reason);
            return;
        }

        // capture BEFORE change to know if appName mirrored name
        $before  = $this->getNodeAtPath($this->tree, $this->editNodePath);
        $oldName = $before['name'] ?? null;
        $oldApp  = $before['appName'] ?? null;
        $wasManual = (bool)($before['appNameManual'] ?? false);

        $fields = [$this->editField => $val];

        if ($this->editField === 'name') {
            // keep appName in sync only if it was auto (oldApp==oldName and not manual)
            if ($oldName !== null && $oldApp === $oldName && !$wasManual) {
                $fields['appName'] = $val;
                $fields['appNameManual'] = false;
            }
        } elseif ($this->editField === 'appName') {
            // mark as manual so refreshAppNames won't overwrite it
            $fields['appNameManual'] = true;
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        // Recompute descendants’ appNames based on the rule (won’t touch manual ones)
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

    /** Build children using a fixed effective parent name */
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

    /** Add child at path */
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

    /*** set fields using a by-reference pointer down the path ***/
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

    /** Resolve effective parent for naming when inserting under $path (skip AblgOE) */
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

    // ================== DnD / Confirm ==================
    public function preparePendingMove($fromPath, $toPath, $position = 'into'): void
    {
        $this->pendingFromPath = is_array($fromPath) ? $fromPath : [];
        $this->pendingToPath   = is_array($toPath)   ? $toPath   : [];
        $this->pendingPosition = in_array($position, ['into','before','after'], true) ? $position : 'into';

        $oldParentPath = array_slice($this->pendingFromPath, 0, -1);
        $newParentPath = ($this->pendingPosition === 'into')
            ? $this->pendingToPath
            : array_slice($this->pendingToPath, 0, -1);

        $this->pendingOldParentName = $this->getNameAtPath($this->tree, $oldParentPath) ?? '';
        $this->pendingNewParentName = $this->getNameAtPath($this->tree, $newParentPath) ?? '';

        $this->pendingOldParentPathStr = $this->pathToString($oldParentPath);
        $this->pendingNewParentPathStr = $this->pathToString($newParentPath);

        $this->pendingSameParent = ($oldParentPath === $newParentPath);

        $this->pendingWithinParentName = $this->pendingSameParent
            ? ($this->pendingOldParentName ?: '(Wurzel)')
            : '';

        $this->pendingFromIndex = -1;
        $this->pendingToIndex   = -1;

        if ($this->pendingSameParent && in_array($this->pendingPosition, ['before','after'], true)) {
            $fromIndex = end($this->pendingFromPath);
            $targetIndexOriginal = end($this->pendingToPath);
            $shift = ($fromIndex < $targetIndexOriginal) ? 1 : 0;
            $newIndex = ($this->pendingPosition === 'before')
                ? ($targetIndexOriginal - $shift)
                : ($targetIndexOriginal - $shift + 1);

            $this->pendingFromIndex = (int)$fromIndex;
            $this->pendingToIndex   = (int)$newIndex;
        }
    }

    public function confirmPendingMove(): void
    {
        if (empty($this->pendingFromPath) || empty($this->pendingToPath)) {
            return;
        }

        $this->moveNode($this->pendingFromPath, $this->pendingToPath, $this->pendingPosition);

        $this->pendingFromPath = [];
        $this->pendingToPath = [];
        $this->pendingPosition = 'into';
        $this->pendingOldParentName = '';
        $this->pendingNewParentName = '';
        $this->pendingSameParent = false;
        $this->pendingWithinParentName = '';
        $this->pendingFromIndex = -1;
        $this->pendingToIndex = -1;
        $this->pendingOldParentPathStr = '';
        $this->pendingNewParentPathStr = '';
    }

    /**
     * Move a node relative to target.
     * $position: 'into' (as last child), 'before', 'after'
     */
    public function moveNode($fromPath, $toPath, $position = 'into'): void
    {
        if (!$this->pathExists($this->tree, $fromPath) || !$this->pathExists($this->tree, $toPath)) return;

        // disallow moving onto itself or into its own subtree
        if ($fromPath === $toPath || $this->isAncestorPath($fromPath, $toPath)) return;

        $moved = $this->extractNodeAtPath($this->tree, $fromPath);
        if ($moved === null) return;

        $newPath = null;

        if ($position === 'before' || $position === 'after') {
            $parentPath = array_slice($toPath, 0, -1);
            $targetIndex = end($toPath);

            if ($this->pathsShareParent($fromPath, $toPath)) {
                $fromIndex = end($fromPath);
                if ($fromIndex < $targetIndex) $targetIndex -= 1;
            }

            $insertIndex = ($position === 'before') ? $targetIndex : $targetIndex + 1;
            $this->insertSiblingAt($this->tree, $parentPath, $insertIndex, $moved);
            $newPath = array_merge($parentPath, [$insertIndex]);
        } else {
            $this->appendChildAtPath($this->tree, $toPath, $moved);
            $newChildIndex = $this->lastChildIndexAtPath($this->tree, $toPath);
            $newPath = array_merge($toPath, [$newChildIndex]);
        }

        $this->refreshAppNames($this->tree, null, null);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();

        $this->selectedNodePath = $newPath;
    }

    protected function pathsShareParent(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        return $this->isAncestorPath(array_slice($a, 0, -1), $b) && count($a) - 1 === count(array_slice($b, 0, -1));
    }

    /** return true if A is ancestor of B (paths) */
    protected function isAncestorPath(array $a, array $b): bool
    {
        if (count($a) >= count($b)) return false;
        for ($i = 0; $i < count($a); $i++) {
            if ($a[$i] !== $b[$i]) return false;
        }
        return true;
    }

    /** remove and return node at path */
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

    /** append child at path (assumes path exists) */
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

    /** insert as sibling under parent at index */
    protected function insertSiblingAt(&$nodes, $parentPath, int $insertIndex, $newNode): void
    {
        $ptr =& $nodes;
        foreach ($parentPath as $i) {
            if (!isset($ptr[$i])) return;
            if (!isset($ptr[$i]['children']) || !is_array($ptr[$i]['children'])) $ptr[$i]['children'] = [];
            $ptr =& $ptr[$i]['children'];
        }
        $insertIndex = max(0, min($insertIndex, count($ptr)));
        array_splice($ptr, $insertIndex, 0, [$newNode]);
    }

    /** last index of children at path (or -1) */
    protected function lastChildIndexAtPath($nodes, $path): int
    {
        $n = $this->getNodeAtPath($nodes, $path);
        if (!$n) return -1;
        $kids = $n['children'] ?? [];
        return is_array($kids) ? max(count($kids) - 1, -1) : -1;
    }

    /** Pfad -> Namenliste / String */
    protected function getPathNames(array $path): array
    {
        $names = [];
        $ptr = $this->tree;
        foreach ($path as $idx) {
            if (!isset($ptr[$idx])) break;
            $names[] = $ptr[$idx]['name'] ?? '';
            $ptr = isset($ptr[$idx]['children']) && is_array($ptr[$idx]['children']) ? $ptr[$idx]['children'] : [];
        }
        return $names;
    }

    protected function pathToString(array $path): string
    {
        $names = $this->getPathNames($path);
        return empty($names) ? '(Wurzel)' : implode(' / ', $names);
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
        // Windows-like rules for filenames/folders (component)
        // 1) length
        if (mb_strlen($name) > 255) {
            return 'Name darf höchstens 255 Zeichen lang sein.';
        }
        // 2) disallow only "." or ".."
        if ($name === '.' || $name === '..') {
            return 'Name darf nicht "." oder ".." sein.';
        }
        // 3) disallow invalid characters: < > : " / \ | ? * and control chars 0x00-0x1F
        if (preg_match('/[<>:"\/\\\\|?*]/u', $name) || preg_match('/[\x00-\x1F]/u', $name)) {
            return 'Ungültige Zeichen: < > : " / \\ | ? * oder Steuerzeichen sind nicht erlaubt.';
        }
        // 4) no trailing space or dot
        if (preg_match('/[ \.]$/u', $name)) {
            return 'Name darf nicht mit einem Punkt oder Leerzeichen enden.';
        }
        // 5) reserved device names (case-insensitive), optionally with extension
        $base = $name;
        // windows treats trailing dots/spaces as equivalent; we already block them,
        // but normalize anyway for device-name check
        $norm = rtrim($base, " .");
        $upper = mb_strtoupper($norm);
        $reserved = [
            'CON','PRN','AUX','NUL',
            'COM1','COM2','COM3','COM4','COM5','COM6','COM7','COM8','COM9',
            'LPT1','LPT2','LPT3','LPT4','LPT5','LPT6','LPT7','LPT8','LPT9',
        ];
        if (in_array($upper, $reserved, true)) {
            return 'Name ist unter Windows reserviert (z. B. CON, PRN, AUX, NUL, COM1–COM9, LPT1–LPT9).';
        }
        // Existing custom rule: max three hyphens
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

    /**
     * Recompute child appNames; do not overwrite nodes explicitly edited (appNameManual=true).
     * Also allow auto-propagation for nodes that keep appName==name and not manual.
     */
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

            // determine effective parent for THIS node's children
            $effectiveParent = $parentName;
            if ($parentName === 'AblgOE') {
                $effectiveParent = $grandparentName; // skip AblgOE
            }
            $nextEffectiveParent = $this->nextEffectiveParent($name, $effectiveParent);

            // apply scheme to children
            if (!empty($n['children']) && is_array($n['children'])) {
                foreach ($n['children'] as &$child) {
                    $childName = $child['name'] ?? '';
                    $manual = isset($child['appNameManual']) && $child['appNameManual'] === true;

                    // Only auto-compute if NOT manual
                    if (!$manual) {
                        // preserve "keep = name" behavior
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
    /** Replace German umlauts with ASCII equivalents in any user-entered field */
    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss',
        ];
        return strtr($s, $map);
    }

    protected function normalizeTitle(string $s): string
{
    $s = preg_replace('/\s+/u', ' ', $s ?? '');
    return trim($s);
}
}
