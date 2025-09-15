<?php

namespace App\Livewire;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class TreeEditor extends Component
{
    /** Editable tree (raw, no export wrapper) */
    public $tree = [];

    /** Current tree DB id */
    public ?int $treeId = null;

    /** Title of the tree/model */
    public string $title = '';

    /** Inputs for adding a node */
    public $newNodeName = '';
    public $newAppName = '';

    /** Currently selected node path (array of indexes) */
    public $selectedNodePath = null;

    /** When true, add a default sub-structure under the new node */
    public $addWithStructure = false;

    /** Generated JSON (pretty printed) */
    public $generatedJson = '';

    /** Download filename for generated Excel */
    public string $downloadFilename = '';

    /** Inline edit state */
    public $editNodePath = null;   // array<int>|null
    public $editField = null;      // "name" | "appName" | null
    public $editValue = '';

    /** Pending move (confirmation dialog) */
    public array $pendingFromPath = [];
    public array $pendingToPath = [];
    public string $pendingPosition = 'into';
    public string $pendingOldParentName = '';
    public string $pendingNewParentName = '';
    public bool $pendingSameParent = false;
    public string $pendingWithinParentName = '';
    public int $pendingFromIndex = -1;
    public int $pendingToIndex = -1;
    public string $pendingOldParentPathStr = '';
    public string $pendingNewParentPathStr = '';

    /** Names that must never be deletable */
    protected array $fixedNames = ['Ltg', 'Allg', 'AblgOE', 'PoEing', 'SB'];

    /** Default subtree used when "mit Ablagen" is checked */
    protected $predefinedStructure = [
        ['name' => 'Ltg', 'children' => []],
        ['name' => 'Allg', 'children' => []],
        [
            'name' => 'AblgOE',
            'children' => [
                ['name' => 'PoEing', 'children' => []],
                ['name' => 'SB', 'children' => []],
            ],
        ],
    ];

    /** Load model, unwrap, sanitize flags, refresh app names */
    public function mount(TreeModel $tree)
    {
        $this->treeId = $tree->id;
        $this->title = $tree->title;

        $data = $tree->data ?? [];
        $this->tree = $this->unwrapIfWrapped($data);
        $this->sanitizeDeletionFlags($this->tree);
        $this->refreshAppNames($this->tree, null, null);
    }

    /** Persist raw tree and title */
    protected function persist(): void
    {
        if (!$this->treeId) return;
        $model = TreeModel::find($this->treeId);
        if (!$model) return;

        $model->update([
            'title' => $this->title !== '' ? $this->title : $model->title,
            'data' => $this->tree,
        ]);

        $this->dispatch('autosaved');
    }

    /** Title changed → normalize, validate, unique check, save */
    public function updatedTitle(): void
    {
        $candidate = $this->translitUmlauts(
            $this->normalizeTitle((string)$this->title)
        );

        if ($candidate === '') {
            $this->addError('title', 'Name darf nicht leer sein.');
            return;
        }
        if ($reason = $this->invalidNameReason($candidate)) {
            $this->addError('title', $reason);
            return;
        }

        $exists = TreeModel::query()
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])
            ->where('id', '!=', $this->treeId)
            ->exists();

        if ($exists) {
            $this->addError('title', 'Name ist bereits vergeben (Groß-/Kleinschreibung unbeachtet).');
            return;
        }

        $this->resetErrorBag('title');
        $this->title = $candidate;
        $this->persist();
    }

    /** Reset validation on edits */
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

    /** Add a new node at selected path (or root). Optional predefined sub-structure. */
    public function addNode()
    {
        $nameInput = $this->translitUmlauts(trim((string)$this->newNodeName));

        // STRICT: empty & whitespace
        if ($nameInput === '') {
            $this->addError('newNodeName', 'Name darf nicht leer sein.');
            return;
        }
        if (preg_match('/\s/u', $nameInput)) {
            $this->addError('newNodeName', 'Name darf keine Leerzeichen enthalten.');
            return;
        }

        if ($reason = $this->invalidNameReason($nameInput)) {
            $this->addError('newNodeName', $reason);
            return;
        }

        $appInputRaw = $this->translitUmlauts(trim((string)$this->newAppName));
        if ($appInputRaw !== '') {
            if (preg_match('/\s/u', $appInputRaw)) {
                $this->addError('newAppName', 'Name darf keine Leerzeichen enthalten.');
                return;
            }
            if ($reason = $this->invalidNameReason($appInputRaw)) {
                $this->addError('newAppName', $reason);
                return;
            }
        }

        $manual = false;
        if ($appInputRaw !== '') {
            // user provided appName → normalize leading SB, lock manual
            $computedAppName = $this->normalizeSbPrefix($appInputRaw);
            $manual = true;
        } else {
            // no appName provided → only normalize if name starts with SB; if changed, lock manual
            $fromName = $this->normalizeSbPrefix($nameInput);
            if ($fromName !== $nameInput) {
                $computedAppName = $fromName; // e.g., SBKasse -> SbKasse
                $manual = true;               // prevent refresh from turning it into Parent_Sb
            } else {
                $computedAppName = $nameInput; // keep old behavior
            }
        }

        $newNode = [
            'name'           => $nameInput,
            'appName'        => $computedAppName,
            'appNameManual'  => $manual,
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
        $this->newAppName = '';
        $this->addWithStructure = false;

        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();

        $this->dispatch('focus-newnode');
    }

    /** Remove node at path; fixed names are protected. Keep selection on parent if possible. */
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

    /** Select node by path if it exists. */
    public function selectNode($path)
    {
        $this->selectedNodePath = $this->pathExists($this->tree, $path) ? $path : null;
    }

    /** Begin inline edit for a field ("name" or "appName"). */
    public function startInlineEdit($path, $field)
    {
        if (!in_array($field, ['name', 'appName'])) return;

        $node = $this->getNodeAtPath($this->tree, $path);
        if (!$node) return;

        $this->editNodePath = $path;
        $this->editField = $field;
        $this->editValue = $node[$field] ?? '';
    }

    /** Commit inline edit; handles manual flag and cascaded recomputation. */
    public function saveInlineEdit($value = null)
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = $this->translitUmlauts(trim((string)($value ?? $this->editValue)));

        // STRICT: empty & whitespace
        if ($val === '') {
            $this->addError('editValue', 'Name darf nicht leer sein.');
            return; // keep editing state, show error
        }
        if (preg_match('/\s/u', $val)) {
            $this->addError('editValue', 'Name darf keine Leerzeichen enthalten.');
            return; // keep editing state, show error
        }

        if ($reason = $this->invalidNameReason($val)) {
            $this->addError('editValue', $reason);
            return; // keep editing state, show error
        }

        $before = $this->getNodeAtPath($this->tree, $this->editNodePath);
        $oldName = $before['name'] ?? null;
        $oldApp = $before['appName'] ?? null;
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

        // only clear edit state after a successful save
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    /** Cancel inline edit state. */
    public function cancelInlineEdit()
    {
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
    }

    // ================== EXPORT ==================

    /** Generate JSON (pretty) using export wrapper. */
    public function generateJson()
    {
        $wrapped = $this->wrapForExport($this->tree);
        $this->generatedJson = json_encode($wrapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Request Excel from backend, store temp file, and notify UI. */
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

    /** Export wrapper: .PANKOW → ba → DigitaleAkte-203 → {clean tree}. */
    protected function wrapForExport(array $nodes): array
    {
        $clean = $this->stripInternal($nodes);

        return [[
            'name' => '.PANKOW', 'appName' => '.PANKOW',
            'children' => [[
                'name' => 'ba', 'appName' => 'ba',
                'children' => [[
                    'name' => 'DigitaleAkte-203', 'appName' => 'DigitaleAkte-203',
                    'children' => $clean
                ]]
            ]]
        ]];
    }

    /** Strip internal fields recursively for export (keep name, appName, children). */
    protected function stripInternal(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $out[] = [
                'name' => $n['name'] ?? '',
                'appName' => $n['appName'] ?? ($n['name'] ?? ''),
                'children' => !empty($n['children']) ? $this->stripInternal($n['children']) : [],
            ];
        }
        return $out;
    }

    // ================== DnD / CONFIRMATION ==================

    /**
     * Prepare a pending move (for confirmation dialog).
     * $position: 'into' | 'before' | 'after'
     */
    public function preparePendingMove($fromPath, $toPath, $position = 'into'): void
    {
        $this->pendingFromPath = is_array($fromPath) ? $fromPath : [];
        $this->pendingToPath = is_array($toPath) ? $toPath : [];
        $this->pendingPosition = in_array($position, ['into', 'before', 'after'], true) ? $position : 'into';

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
        $this->pendingToIndex = -1;

        if ($this->pendingSameParent && in_array($this->pendingPosition, ['before', 'after'], true)) {
            $fromIndex = end($this->pendingFromPath);
            $targetIndexOriginal = end($this->pendingToPath);
            $shift = ($fromIndex < $targetIndexOriginal) ? 1 : 0;
            $newIndex = ($this->pendingPosition === 'before')
                ? ($targetIndexOriginal - $shift)
                : ($targetIndexOriginal - $shift + 1);

            $this->pendingFromIndex = (int)$fromIndex;
            $this->pendingToIndex = (int)$newIndex;
        }
    }

    /** Apply the pending move and clear dialog state. */
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

        // recompute appNames with our naming rules
        $this->refreshAppNames($this->tree, null, null);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();

        $this->selectedNodePath = $newPath;
    }

    /** True if both paths have the same parent (same depth and same parent prefix). */
    protected function pathsShareParent(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        return $this->isAncestorPath(array_slice($a, 0, -1), $b) && count($a) - 1 === count(array_slice($b, 0, -1));
    }

    /** Return true if A is ancestor of B (paths). */
    protected function isAncestorPath(array $a, array $b): bool
    {
        if (count($a) >= count($b)) return false;
        for ($i = 0; $i < count($a); $i++) {
            if ($a[$i] !== $b[$i]) return false;
        }
        return true;
    }

    // ================== HELPERS ==================

    /** Unwrap if the data is nested under .PANKOW/ba/DigitaleAkte-203. */
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

    /** True if name is protected from deletion. */
    protected function isFixedName(?string $name): bool
    {
        return in_array((string)$name, $this->fixedNames, true);
    }

    /** Ensure each node has correct 'deletable' flag. */
    protected function sanitizeDeletionFlags(array &$nodes): void
    {
        foreach ($nodes as &$n) {
            $n['deletable'] = !$this->isFixedName($n['name'] ?? '');
            if (!empty($n['children']) && is_array($n['children'])) {
                $this->sanitizeDeletionFlags($n['children']);
            }
        }
    }

    /** Build predefined children using a fixed effective parent for appName composition. */
    protected function buildPredefinedChildrenWithParent(array $items, ?string $effectiveParentName): array
    {
        $res = [];
        foreach ($items as $it) {
            $childName = $it['name'];
            $appName = $this->composeAppName($effectiveParentName, $childName);
            $nextEffective = $this->nextEffectiveParent($childName, $effectiveParentName);

            $res[] = [
                'name' => $childName,
                'appName' => $appName,
                'appNameManual' => false,
                'children' => !empty($it['children'])
                    ? $this->buildPredefinedChildrenWithParent($it['children'], $nextEffective)
                    : [],
                'deletable' => !$this->isFixedName($childName),
            ];
        }
        return $res;
    }

    /** Resolve effective parent name for composing appNames when inserting under $path. */
    protected function effectiveParentNameForPath($path): ?string
    {
        if ($path === null || !is_array($path) || empty($path)) {
            return null;
        }

        $parentName = $this->getNameAtPath($this->tree, $path);
        if ($parentName === null) {
            return null;
        }

        // Skip 'AblgOE' as effective parent → use grandparent
        if ($parentName === 'AblgOE') {
            $gpPath = $path;
            array_pop($gpPath);
            $grandparentName = $this->getNameAtPath($this->tree, $gpPath);
            return $grandparentName ?? null;
        }

        return $parentName;
    }

    /** Add child under a given path. Returns success state. */
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

    /** Check if a path exists. */
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

    /** Get node at a path. */
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

    /** Get the 'name' of node at path. */
    protected function getNameAtPath($nodes, $path): ?string
    {
        $n = $this->getNodeAtPath($nodes, $path);
        return $n['name'] ?? null;
    }

    /** Set multiple fields on node at path. Ensures appName/appNameManual defaults. */
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

    /** Remove node at path (recursive). */
    protected function removeNodeAtPath(&$nodes, $path)
    {
        $index = array_shift($path);
        if (!isset($nodes[$index])) return;

        if (count($path) === 0) {
            array_splice($nodes, $index, 1);
        } else {
            if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) return;
            $this->removeNodeAtPath($nodes[$index]['children'], $path);
        }
    }

    /** Remove and return node at path. */
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

    /** Append child under a given path. */
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

    /** Insert sibling at index under parent path. */
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

    /** Last child index at path, or -1. */
    protected function lastChildIndexAtPath($nodes, $path): int
    {
        $n = $this->getNodeAtPath($nodes, $path);
        if (!$n) return -1;
        $kids = $n['children'] ?? [];
        return is_array($kids) ? max(count($kids) - 1, -1) : -1;
    }

    /** Names along a path. */
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

    /** Human-readable path string. */
    protected function pathToString(array $path): string
    {
        $names = $this->getPathNames($path);
        return empty($names) ? '(Wurzel)' : implode(' / ', $names);
    }

    /** Delete the current tree model and redirect. */
    public function delete()
    {
        if (TreeModel::find($this->treeId)->delete()) {
            return $this->redirect('/importer', navigate: true);
        }
    }

    /** Render Livewire view. */
    public function render()
    {
        return view('livewire.tree-editor');
    }

    // ================== VALIDATION HELPERS ==================

    /** Windows-like filename validation; returns message or null. */
    protected function invalidNameReason(string $name): ?string
    {
        if (mb_strlen($name) > 255) {
            return 'Name darf höchstens 255 Zeichen lang sein.';
        }
        if ($name === '.' || $name === '..') {
            return 'Name darf nicht "." oder ".." sein.';
        }
        if (preg_match('/[<>:"\/\\\\|?*]/u', $name) || preg_match('/[\x00-\x1F]/u', $name)) {
            return 'Ungültige Zeichen: < > : " / \\ | ? * oder Steuerzeichen sind nicht erlaubt.';
        }
        if (preg_match('/[ \.]$/u', $name)) {
            return 'Name darf nicht mit einem Punkt oder Leerzeichen enden.';
        }
        $norm = rtrim($name, " .");
        $upper = mb_strtoupper($norm);
        $reserved = [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
        ];
        if (in_array($upper, $reserved, true)) {
            return 'Name ist unter Windows reserviert (z. B. CON, PRN, AUX, NUL, COM1–COM9, LPT1–LPT9).';
        }
        if (substr_count($name, '-') > 3) {
            return 'Name darf höchstens drei Bindestriche (-) enthalten.';
        }
        return null;
    }

    // ================== APPNAME RULES ==================

    /**
     * Abbreviation for child names used in appName.
     * - Ltg => Ltg
     * - Allg => Allg
     * - PoEing => Pe
     * - SB => Sb
     * Fallback: first letter uppercase + second letter lowercase.
     */
    protected function abbr(string $name): string
    {
        $map = [
            'Ltg' => 'Ltg',
            'Allg' => 'Allg',
            'PoEing' => 'Pe',
            'SB' => 'Sb',
        ];
        if (isset($map[$name])) return $map[$name];

        $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $name);
        if ($letters === '') return $name;
        $first = mb_strtoupper(mb_substr($letters, 0, 1));
        $second = mb_strtolower(mb_substr($letters, 1, 1));
        return $first . $second;
    }

    /**
     * Compose default appName under effective parent.
     * - No effective parent  → appName = child name
     * - Ltg                  → "Ltg_<Parent>"
     * - Allg                 → "Allg_<Parent>"
     * - AblgOE               → "<Parent>_Ab"
     * - SB                   → "Sb_<Parent>"
     * - PoEing               → "Pe_<Parent>"
     * - Else                 → "<Parent>_<Abbreviation(child)>"
     */
    protected function composeAppName(?string $effectiveParent, string $childName): string
    {
        if ($effectiveParent === null || $effectiveParent === '') {
            return $childName;
        }

        switch ($childName) {
            case 'Ltg':
                return 'Ltg_' . $effectiveParent;
            case 'Allg':
                return 'Allg_' . $effectiveParent;
            case 'AblgOE':
                return 'Ab_' . $effectiveParent;
            case 'SB':
                return 'Sb_' . $effectiveParent;       // prefix form
            case 'PoEing':
                return 'Pe_' . $effectiveParent;       // prefix form
            default:
                return $effectiveParent . '_' . $this->abbr($childName);
        }
    }

    /** Next effective parent for descendants; 'AblgOE' does not change the effective parent. */
    protected function nextEffectiveParent(string $currentNodeName, ?string $currentEffectiveParent): ?string
    {
        return ($currentNodeName === 'AblgOE') ? $currentEffectiveParent : $currentNodeName;
    }

    /**
     * Refresh descendants' appNames; preserve manual edits.
     * If parent is 'AblgOE', we use the grandparent as effective parent for naming.
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

            $effectiveParent = $parentName;
            if ($parentName === 'AblgOE') {
                $effectiveParent = $grandparentName; // skip AblgOE
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

    /** Replace umlauts with ASCII equivalents. */
    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'ß' => 'ss',
        ];
        return strtr($s, $map);
    }

    /** Collapse whitespace and trim. */
    protected function normalizeTitle(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        return trim($s);
    }

    protected function normalizeSbPrefix(string $s): string
    {
        // Convert only a leading 'SB' → 'Sb' (leave the rest untouched)
        return preg_replace('/^SB/u', 'Sb', $s);
    }
}
