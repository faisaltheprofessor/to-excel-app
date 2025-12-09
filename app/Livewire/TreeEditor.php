<?php

namespace App\Livewire;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Interactive editor for hierarchical organization structures.
 *
 * Features:
 * - Read-only vs edit mode (toggleable)
 * - Add/remove/reorder nodes with drag & drop
 * - Automatic and manual appName handling
 * - Enable/disable flags for specific node types
 * - JSON preview export
 * - Excel generation via external Python backend
 */
class TreeEditor extends Component
{
    /** @var bool Tree is editable when true, read-only otherwise. */
    public bool $editable = false;

    /** @var array The editable tree data structure. */
    public $tree = [];

    /** @var int|null Primary key of the OrganizationStructure record. */
    public ?int $treeId = null;

    /** @var string Display title of the tree. */
    public string $title = '';

    /** @var string Input for new node name. */
    public string $newNodeName = '';

    /** @var string Input for optional appName for new node. */
    public string $newAppName = '';

    /** @var array<int,int>|null Path to the currently selected node. */
    public ?array $selectedNodePath = null;

    /** @var bool Whether to add new node with predefined sub-structure. */
    public bool $addWithStructure = false;

    /** @var string JSON preview of current tree. */
    public string $generatedJson = '';

    /** @var string Base name for Excel download. */
    public string $downloadFilename = '';

    /** @var bool Append timestamp to download filename. */
    public bool $withTimestamp = true;

    /** @var array<int,int>|null Path of node currently in inline edit. */
    public ?array $editNodePath = null;

    /** @var string|null Field currently edited inline ("name" or "appName"). */
    public ?string $editField = null;

    /** @var string Editable value for inline edit. */
    public string $editValue = '';

    /** @var array<int,int> Path of node being moved (drag & drop). */
    public array $pendingFromPath = [];

    /** @var array<int,int> Target path for pending move. */
    public array $pendingToPath = [];

    /** @var string Target position: "into", "before", or "after". */
    public string $pendingPosition = 'into';

    /** @var string Parent name of node before move. */
    public string $pendingOldParentName = '';

    /** @var string Parent name of node after move. */
    public string $pendingNewParentName = '';

    /** @var bool True when move stays within same parent. */
    public bool $pendingSameParent = false;

    /** @var string Display name when reordering within one parent. */
    public string $pendingWithinParentName = '';

    /** @var int Index of node before move (within parent). */
    public int $pendingFromIndex = -1;

    /** @var int Index node will have after move (within parent). */
    public int $pendingToIndex = -1;

    /** @var string Human-readable path of old parent. */
    public string $pendingOldParentPathStr = '';

    /** @var string Human-readable path of new parent. */
    public string $pendingNewParentPathStr = '';

    /** @var array<int,int> Path to node to be deleted. */
    public array $confirmDeleteNodePath = [];

    /** @var string Human-readable path for node deletion confirmation. */
    public string $confirmDeleteNodePathStr = '';

    /** @var string Name of node to be deleted. */
    public string $confirmDeleteNodeName = '';

    /** @var bool Excel: include main structure sheet. */
    public bool $sheetGE = true;

    /** @var bool Excel: include Ablage sheet. */
    public bool $sheetAblage = true;

    /** @var bool Excel: include roles sheet. */
    public bool $sheetRoles = true;

    /** @var int Number of placeholder roles in Excel sheet. */
    public int $rolesPlaceholderCount = 10;

    /** @var bool Used to control Excel options modal open state. */
    public bool $excelOptionsOpen = false;

    /** @var string[] Node names that cannot be deleted. */
    protected array $fixedNames = ['Ltg', 'Allg', 'AblgOE', 'PoEing', 'SB'];

    /**
     * Canonical predefined structure for "mit Ablagen" option.
     *
     * @var array<int,array<string,mixed>>
     */
    protected array $predefinedStructure = [
        ['name' => 'Ltg', 'children' => []],
        ['name' => 'Allg', 'children' => []],
        [
            'name'     => 'AblgOE',
            'children' => [
                ['name' => 'PoEing', 'children' => []],
                ['name' => 'SB',     'children' => []],
            ],
        ],
    ];

    /**
     * Initialize the component with an existing OrganizationStructure record.
     */
    public function mount(TreeModel $tree): void
    {
        $this->treeId = $tree->id;
        $this->title  = $this->normalizeTitle((string) $tree->title);

        $data       = $tree->data ?? [];
        $this->tree = $this->unwrapIfWrapped($data);

        $this->sanitizeDeletionFlags($this->tree);
        $this->sanitizeEnabledFlags($this->tree);
        $this->refreshAppNames($this->tree, null, null);

        $this->editable = false;
    }

    /**
     * Toggle between read-only and edit mode.
     */
    public function toggleEditable(): void
    {
        $this->editable = ! $this->editable;

        if (! $this->editable) {
            $this->cancelInlineEdit();
            $this->pendingFromPath    = [];
            $this->pendingToPath      = [];
            $this->pendingPosition    = 'into';
            $this->pendingOldParentName = '';
            $this->pendingNewParentName = '';
        }
    }

    /**
     * Persist the current tree and title to the database.
     */
    protected function persist(): void
    {
        if (! $this->treeId) {
            return;
        }

        /** @var TreeModel|null $model */
        $model = TreeModel::find($this->treeId);
        if (! $model) {
            return;
        }

        $title = $this->title !== '' ? $this->title : (string) $model->title;

        $model->forceFill([
            'title' => $title,
            'data'  => $this->tree,
        ]);

        $model->save();

        $this->dispatch('autosaved');
    }

    /**
     * Handle title updates: normalize, validate, ensure uniqueness, then persist.
     */
    public function updatedTitle(): void
    {
        $candidate = $this->translitUmlauts(
            $this->normalizeTitle((string) $this->title)
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

    /**
     * Clear validation when newNodeName changes.
     */
    public function updatedNewNodeName(): void
    {
        $this->resetValidation();
    }

    /**
     * Clear validation when newAppName changes.
     */
    public function updatedNewAppName(): void
    {
        $this->resetValidation();
    }

    /**
     * Clear validation for inline edit value.
     */
    public function updatedEditValue(): void
    {
        $this->resetValidation();
    }

    public function updatedSheetGE(): void
    {
        $this->resetErrorBag('generate');
    }

    public function updatedSheetAblage(): void
    {
        $this->resetErrorBag('generate');
    }

    /**
     * Reset roles count when roles sheet is disabled.
     */
    public function updatedSheetRoles($value): void
    {
        $this->resetErrorBag('generate');
        if (! $value) {
            $this->rolesPlaceholderCount = 10;
        }
    }

    public function updatedRolesPlaceholderCount(): void
    {
        $this->resetErrorBag('generate');
    }

    /**
     * Add a new node either at root or under selected node.
     */
    public function addNode(): void
{
    if (! $this->editable) {
        return;
    }

    // Validate and normalize node name
    $nameInput = $this->translitUmlauts(trim((string) $this->newNodeName));
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

    // Validate user-defined appName
    $appInputRaw = $this->translitUmlauts(trim((string) $this->newAppName));
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

    // Determine appName
    $manual = false;
    $computedAppName = '';

    if ($appInputRaw !== '') {
        // User-defined appName: use exactly as provided
        $computedAppName = $appInputRaw;
        $manual = true;
    } else {
        // Auto-generate base appName
        $fromName = $this->normalizeSbPrefix($nameInput);
        if ($fromName !== $nameInput) {
            $computedAppName = $fromName;
            $manual = true;
        } else {
            $computedAppName = $nameInput;
        }
    }

    // Auto suffix only for system-generated appNames
    if (! $this->addWithStructure && ! $manual) {
        $parent = $this->effectiveParentNameForPath($this->selectedNodePath);
        if ($parent !== null && $parent !== '') {
            if (! preg_match('/_' . preg_quote($parent, '/') . '$/u', $computedAppName)) {
                $computedAppName .= '_' . $parent;
            }
            $manual = true;
        }
    }

    // Construct node structure
    $newNode = [
        'name'          => $nameInput,
        'appName'       => $computedAppName,
        'appNameManual' => $manual,
        'children'      => [],
        'deletable'     => true,
    ];

    // Optional predefined subtree
    if ($this->addWithStructure) {
        $parentForPath = $this->effectiveParentNameForPath($this->selectedNodePath);
        $effective     = $this->nextEffectiveParent($nameInput, $parentForPath);
        $newNode['children'] = $this->buildPredefinedChildrenWithParent(
            $this->predefinedStructure,
            $effective
        );
    }

    // Insert node at correct location
    $targetPath = $this->pathExists($this->tree, $this->selectedNodePath)
        ? $this->selectedNodePath
        : null;

    if ($targetPath === null) {
        $this->tree[] = $newNode;
    } else {
        $this->addChildAtPathSafely($this->tree, $targetPath, $newNode);
    }

    // Post-processing: refresh names, flags, persist
    $this->refreshAppNames($this->tree, null, null);
    $this->sanitizeEnabledFlags($this->tree);
    $this->sanitizeDeletionFlags($this->tree);
    $this->persist();

    // Reset inputs
    $this->newNodeName      = '';
    $this->newAppName       = '';
    $this->addWithStructure = false;

    $this->dispatch('focus-newnode');
}


    /**
     * Open confirmation modal for node deletion.
     *
     * @param array<int,int> $path
     */
    public function promptDeleteNode($path): void
    {
        if (! $this->editable) {
            return;
        }

        $this->confirmDeleteNodePath    = is_array($path) ? $path : [];
        $this->confirmDeleteNodeName    = $this->getNameAtPath($this->tree, $this->confirmDeleteNodePath) ?? '(ohne Name)';
        $this->confirmDeleteNodePathStr = $this->pathToString($this->confirmDeleteNodePath);

        $this->dispatch('open-delete-node');
    }

    /**
     * Perform the node deletion after confirmation.
     */
    public function confirmDeleteNode(): void
    {
        if (! $this->editable) {
            return;
        }

        if (! empty($this->confirmDeleteNodePath)) {
            $this->removeNode($this->confirmDeleteNodePath);
        }

        $this->confirmDeleteNodePath    = [];
        $this->confirmDeleteNodeName    = '';
        $this->confirmDeleteNodePathStr = '';
    }

    /**
     * Delete a node from the tree at the given path.
     *
     * @param array<int,int> $path
     */
    public function removeNode($path): void
    {
        if (! $this->editable) {
            return;
        }

        $node = $this->getNodeAtPath($this->tree, $path);
        if (! $node) {
            return;
        }

        if ($this->isFixedName($node['name'] ?? '')) {
            return;
        }

        $parentPath = (is_array($path) && count($path) > 0)
            ? array_slice($path, 0, -1)
            : null;

        $this->removeNodeAtPath($this->tree, $path);
        $this->refreshAppNames($this->tree, null, null);

        if (is_array($parentPath) && count($parentPath) > 0 && $this->pathExists($this->tree, $parentPath)) {
            $this->selectedNodePath = $parentPath;
        } elseif (! empty($this->tree)) {
            $this->selectedNodePath = [0];
        } else {
            $this->selectedNodePath = null;
        }

        $this->editNodePath = null;
        $this->editField    = null;
        $this->editValue    = '';

        $this->sanitizeEnabledFlags($this->tree);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    /**
     * Set the currently selected node.
     *
     * @param array<int,int> $path
     */
    public function selectNode($path): void
    {
        $this->selectedNodePath = $this->pathExists($this->tree, $path) ? $path : null;

        if ($this->selectedNodePath !== null) {
            $this->dispatch('node-selected', path: $this->selectedNodePath);
        }
    }

    /**
     * Start inline editing of a node field.
     *
     * @param array<int,int> $path
     * @param string         $field
     */
    public function startInlineEdit($path, string $field): void
    {
        if (! $this->editable) {
            return;
        }

        if (! in_array($field, ['name', 'appName'], true)) {
            return;
        }

        $node = $this->getNodeAtPath($this->tree, $path);
        if (! $node) {
            return;
        }

        $this->editNodePath = $path;
        $this->editField    = $field;
        $this->editValue    = $node[$field] ?? '';
    }

    /**
     * Save inline edit changes and update dependent appNames.
     *
     * @param string|null $value
     */
    public function saveInlineEdit($value = null): void
    {
        if (! $this->editable) {
            return;
        }

        if ($this->editNodePath === null || $this->editField === null) {
            return;
        }

        $val = $this->translitUmlauts(trim((string) ($value ?? $this->editValue)));

        if ($val === '') {
            $this->addError('editValue', 'Name darf nicht leer sein.');
            return;
        }
        if (preg_match('/\s/u', $val)) {
            $this->addError('editValue', 'Name darf keine Leerzeichen enthalten.');
            return;
        }
        if ($reason = $this->invalidNameReason($val)) {
            $this->addError('editValue', $reason);
            return;
        }

        $before    = $this->getNodeAtPath($this->tree, $this->editNodePath);
        $oldName   = $before['name'] ?? null;
        $oldApp    = $before['appName'] ?? null;
        $wasManual = (bool) ($before['appNameManual'] ?? false);

        $fields = [$this->editField => $val];

        if ($this->editField === 'name') {
            if ($oldName !== null && $oldApp === $oldName && ! $wasManual) {
                $fields['appName']       = $val;
                $fields['appNameManual'] = false;
            }
        } elseif ($this->editField === 'appName') {
            $fields['appNameManual'] = true;
        }

        $this->setNodeFieldsByPath($this->tree, $this->editNodePath, $fields);

        $this->refreshAppNames($this->tree, null, null);

        $this->editNodePath = null;
        $this->editField    = null;
        $this->editValue    = '';

        $this->sanitizeEnabledFlags($this->tree);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();
    }

    /**
     * Abort inline edit without saving.
     */
    public function cancelInlineEdit(): void
    {
        $this->editNodePath = null;
        $this->editField    = null;
        $this->editValue    = '';
    }

    /**
     * Generate a formatted JSON preview of the export structure.
     */
    public function generateJson(): void
    {
        $wrapped            = $this->wrapForExport($this->tree);
        $this->generatedJson = json_encode($wrapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate Excel file via Python backend and store it temporarily.
     */
    public function generateExcel(): void
    {
        $this->resetErrorBag('generate');

        $selectedSheets = [];
        if ($this->sheetGE) {
            $selectedSheets[] = 'GE';
        }
        if ($this->sheetAblage) {
            $selectedSheets[] = 'Ablage';
        }
        if ($this->sheetRoles) {
            $selectedSheets[] = 'Roles';
        }

        if (count($selectedSheets) === 0) {
            $this->addError('generate', 'Bitte wählen Sie mindestens ein Arbeitsblatt aus.');
            return;
        }

        if (! is_int($this->rolesPlaceholderCount)) {
            $this->rolesPlaceholderCount = (int) $this->rolesPlaceholderCount;
        }

        if ($this->sheetRoles && ($this->rolesPlaceholderCount < 1 || $this->rolesPlaceholderCount > 50)) {
            $this->addError('generate', 'Die Anzahl der Rollen muss zwischen 1 und 50 liegen.');
            return;
        }

        $payload = [
            'tree'       => $this->wrapForExport($this->tree),
            'sheets'     => $selectedSheets,
            'rolesCount' => $this->sheetRoles ? $this->rolesPlaceholderCount : 0,
        ];

        $port = (string) config('services.python.backend', '8000');
        $url  = 'http://localhost:' . $port . '/generate-excel';

        $res = Http::accept(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        )->post($url, $payload);

        if (! $res->successful()) {
            $this->addError('generate', 'Excel-Erzeugung fehlgeschlagen.');
            return;
        }

        $basename  = $this->computeDownloadBasename();
        $finalName = $basename . '.xlsx';

        Storage::put('temp/' . $finalName, $res->body());

        $this->downloadFilename = $basename;
        $this->excelOptionsOpen = false;

        $this->dispatch('excel-ready', filename: $finalName);
    }

    /**
     * Compute a safe filesystem basename (without extension) for Excel downloads.
     */
    protected function computeDownloadBasename(): string
    {
        $raw  = trim((string) $this->downloadFilename);
        $base = $raw !== '' ? $raw : ('Importer-Datei-' . ($this->title ?? ''));

        $base = $this->translitUmlauts($base);
        $base = preg_replace('/\.xlsx$/ui', '', $base);
        $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $base);
        $base = preg_replace('/\s+/u', ' ', $base);
        $base = trim($base, " .-");

        if ($base === '') {
            $base = 'Importer-Datei';
        }

        if (mb_strlen($base) > 120) {
            $base = mb_substr($base, 0, 120);
        }

        $base = str_replace(' ', '_', $base);

        if ($this->withTimestamp) {
            $timestamp = date('Y-m-d_H-i');
            $base      = $timestamp . '_' . $base;
        }

        return $base;
    }

    /**
     * Wrap the tree with .PANKOW / ba / DigitaleAkte-203 hierarchy for export.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @return array<int,array<string,mixed>>
     */
    protected function wrapForExport(array $nodes): array
    {
        $clean = $this->stripInternal($nodes);

        return [[
            'name'     => '.PANKOW',
            'appName'  => '.PANKOW',
            'children' => [[
                'name'     => 'ba',
                'appName'  => 'ba',
                'children' => [[
                    'name'     => 'DigitaleAkte-203',
                    'appName'  => 'DigitaleAkte-203',
                    'children' => $clean,
                ]],
            ]],
        ]];
    }

    /**
     * Remove internal editor fields before export.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @return array<int,array<string,mixed>>
     */
    protected function stripInternal(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $n) {
            $row = [
                'name'     => $n['name']    ?? '',
                'appName'  => $n['appName'] ?? ($n['name'] ?? ''),
                'children' => ! empty($n['children'])
                    ? $this->stripInternal($n['children'])
                    : [],
            ];

            if (array_key_exists('enabled', $n)) {
                $row['enabled'] = (bool) $n['enabled'];
            }
            if (isset($n['description'])) {
                $row['description'] = (string) $n['description'];
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * Precompute fields for a pending move and prepare confirmation modal.
     *
     * @param array<int,int> $fromPath
     * @param array<int,int> $toPath
     */
    public function preparePendingMove($fromPath, $toPath, string $position = 'into'): void
    {
        if (! $this->editable) {
            return;
        }

        $this->pendingFromPath = is_array($fromPath) ? $fromPath : [];
        $this->pendingToPath   = is_array($toPath)   ? $toPath   : [];
        $this->pendingPosition = in_array($position, ['into', 'before', 'after'], true)
            ? $position
            : 'into';

        $oldParentPath = array_slice($this->pendingFromPath, 0, -1);
        $newParentPath = ($this->pendingPosition === 'into')
            ? $this->pendingToPath
            : array_slice($this->pendingToPath, 0, -1);

        $this->pendingOldParentName   = $this->getNameAtPath($this->tree, $oldParentPath) ?? '';
        $this->pendingNewParentName   = $this->getNameAtPath($this->tree, $newParentPath) ?? '';
        $this->pendingOldParentPathStr = $this->pathToString($oldParentPath);
        $this->pendingNewParentPathStr = $this->pathToString($newParentPath);

        $this->pendingSameParent      = ($oldParentPath === $newParentPath);
        $this->pendingWithinParentName = $this->pendingSameParent
            ? ($this->pendingOldParentName ?: '(Wurzel)')
            : '';

        $this->pendingFromIndex = -1;
        $this->pendingToIndex   = -1;

        if ($this->pendingSameParent && in_array($this->pendingPosition, ['before', 'after'], true)) {
            $fromIndex          = end($this->pendingFromPath);
            $targetIndexOriginal = end($this->pendingToPath);
            $shift              = ($fromIndex < $targetIndexOriginal) ? 1 : 0;

            $newIndex = ($this->pendingPosition === 'before')
                ? ($targetIndexOriginal - $shift)
                : ($targetIndexOriginal - $shift + 1);

            $this->pendingFromIndex = (int) $fromIndex;
            $this->pendingToIndex   = (int) $newIndex;
        }
    }

    /**
     * Apply a confirmed pending move and reset move state.
     */
    public function confirmPendingMove(): void
    {
        if (! $this->editable) {
            return;
        }
        if (empty($this->pendingFromPath) || empty($this->pendingToPath)) {
            return;
        }

        $this->moveNode($this->pendingFromPath, $this->pendingToPath, $this->pendingPosition);

        $this->pendingFromPath        = [];
        $this->pendingToPath          = [];
        $this->pendingPosition        = 'into';
        $this->pendingOldParentName   = '';
        $this->pendingNewParentName   = '';
        $this->pendingSameParent      = false;
        $this->pendingWithinParentName = '';
        $this->pendingFromIndex       = -1;
        $this->pendingToIndex         = -1;
        $this->pendingOldParentPathStr = '';
        $this->pendingNewParentPathStr = '';
    }

    /**
     * Move a node within the tree, either as child or sibling.
     *
     * @param array<int,int> $fromPath
     * @param array<int,int> $toPath
     */
    public function moveNode($fromPath, $toPath, string $position = 'into'): void
    {
        if (! $this->editable) {
            return;
        }

        if (! $this->pathExists($this->tree, $fromPath) || ! $this->pathExists($this->tree, $toPath)) {
            return;
        }

        if ($fromPath === $toPath || $this->isAncestorPath($fromPath, $toPath)) {
            return;
        }

        $moved = $this->extractNodeAtPath($this->tree, $fromPath);
        if ($moved === null) {
            return;
        }

        $newPath = null;

        if ($position === 'before' || $position === 'after') {
            $parentPath  = array_slice($toPath, 0, -1);
            $targetIndex = end($toPath);

            if ($this->pathsShareParent($fromPath, $toPath)) {
                $fromIndex = end($fromPath);
                if ($fromIndex < $targetIndex) {
                    $targetIndex -= 1;
                }
            }

            $insertIndex = ($position === 'before') ? $targetIndex : $targetIndex + 1;
            $this->insertSiblingAt($this->tree, $parentPath, $insertIndex, $moved);
            $newPath = array_merge($parentPath, [$insertIndex]);
        } else {
            $this->appendChildAtPath($this->tree, $toPath, $moved);
            $newChildIndex = $this->lastChildIndexAtPath($this->tree, $toPath);
            $newPath       = array_merge($toPath, [$newChildIndex]);
        }

        $this->refreshAppNames($this->tree, null, null);
        $this->sanitizeEnabledFlags($this->tree);
        $this->sanitizeDeletionFlags($this->tree);
        $this->persist();

        $this->selectedNodePath = $newPath;
    }

    /**
     * Toggle enabled flag for node and optionally cascade to children.
     *
     * @param array<int,int> $path
     * @param mixed          $checked
     */
    public function toggleEnabled($path, $checked): void
    {
        if (! $this->editable) {
            return;
        }

        $val = is_bool($checked)
            ? $checked
            : in_array($checked, [1, '1', 'true', 'TRUE', 'on'], true);

        $this->setEnabledAtPath($this->tree, $path, (bool) $val, false);
        $this->sanitizeEnabledFlags($this->tree);
        $this->persist();
    }

    /**
     * Recursively set enabled flag at a path and (optionally) all children.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $path
     */
    protected function setEnabledAtPath(&$nodes, $path, bool $val, bool $cascade): void
    {
        if (! is_array($path) || empty($path)) {
            return;
        }

        $index = array_shift($path);
        if (! isset($nodes[$index]) || ! is_array($nodes[$index])) {
            return;
        }

        if (count($path) === 0) {
            $nodes[$index]['enabled'] = $val;

            if ($cascade && ! empty($nodes[$index]['children']) && is_array($nodes[$index]['children'])) {
                $this->setEnabledRecursive($nodes[$index]['children'], $val);
            }
            return;
        }

        if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
            return;
        }

        $this->setEnabledAtPath($nodes[$index]['children'], $path, $val, $cascade);
    }

    /**
     * Cascading helper to set enabled flag on each node in a sub-tree.
     *
     * @param array<int,array<string,mixed>> $nodes
     */
    protected function setEnabledRecursive(&$nodes, bool $val): void
    {
        foreach ($nodes as &$n) {
            $n['enabled'] = $val;

            if (! empty($n['children']) && is_array($n['children'])) {
                $this->setEnabledRecursive($n['children'], $val);
            }
        }
    }

    /**
     * Build predefined children with composed appNames for a given parent.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    protected function buildPredefinedChildrenWithParent(array $items, ?string $effectiveParentName): array
    {
        $res = [];

        foreach ($items as $it) {
            $childName      = $it['name'];
            $appName        = $this->composeAppName($effectiveParentName, $childName);
            $nextEffective  = $this->nextEffectiveParent($childName, $effectiveParentName);

            $res[] = [
                'name'          => $childName,
                'appName'       => $appName,
                'appNameManual' => false,
                'children'      => ! empty($it['children'])
                    ? $this->buildPredefinedChildrenWithParent($it['children'], $nextEffective)
                    : [],
                'deletable'     => ! $this->isFixedName($childName),
            ];
        }

        return $res;
    }

    /**
     * Determine effective parent name based on path (AblgOE special handling).
     *
     * @param array<int,int>|null $path
     */
    protected function effectiveParentNameForPath($path): ?string
    {
        if ($path === null || ! is_array($path) || empty($path)) {
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

    /**
     * Add a child node at a path, if path is valid.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>|null            $path
     */
    protected function addChildAtPathSafely(&$nodes, $path, $newNode): bool
    {
        if ($path === null || ! is_array($path) || empty($path)) {
            return false;
        }

        $index = array_shift($path);
        if (! isset($nodes[$index]) || ! is_array($nodes[$index])) {
            return false;
        }

        if (count($path) === 0) {
            if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
                $nodes[$index]['children'] = [];
            }
            $nodes[$index]['children'][] = $newNode;
            return true;
        }

        return $this->addChildAtPathSafely($nodes[$index]['children'], $path, $newNode);
    }

    /**
     * Check if the given path exists in the tree.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>|null            $path
     */
    protected function pathExists($nodes, $path): bool
    {
        if ($path === null) {
            return true;
        }
        if (! is_array($path)) {
            return false;
        }

        $ptr = $nodes;
        foreach ($path as $i) {
            if (! isset($ptr[$i]) || ! is_array($ptr[$i])) {
                return false;
            }
            $ptr = $ptr[$i]['children'] ?? [];
            if (! is_array($ptr)) {
                $ptr = [];
            }
        }

        return true;
    }

    /**
     * Retrieve a node at the given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>|null            $path
     */
    protected function getNodeAtPath($nodes, $path): ?array
    {
        if ($path === null || ! is_array($path)) {
            return null;
        }

        $ptr   = $nodes;
        $last  = count($path) - 1;

        foreach ($path as $depth => $idx) {
            if (! isset($ptr[$idx]) || ! is_array($ptr[$idx])) {
                return null;
            }

            $node = $ptr[$idx];

            if ($depth === $last) {
                return $node;
            }

            $ptr = isset($node['children']) && is_array($node['children'])
                ? $node['children']
                : [];
        }

        return null;
    }

    /**
     * Get the "name" field at a path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>|null            $path
     */
    protected function getNameAtPath($nodes, $path): ?string
    {
        $n = $this->getNodeAtPath($nodes, $path);
        return $n['name'] ?? null;
    }

    /**
     * Update multiple fields of a node at a given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>|null            $path
     * @param array<string,mixed>            $fields
     */
    protected function setNodeFieldsByPath(&$nodes, $path, $fields): void
    {
        if ($path === null || ! is_array($path)) {
            return;
        }

        $ptr  =& $nodes;
        $last  = count($path) - 1;

        foreach ($path as $depth => $idx) {
            if (! isset($ptr[$idx]) || ! is_array($ptr[$idx])) {
                return;
            }

            if ($depth === $last) {
                foreach ($fields as $k => $v) {
                    $ptr[$idx][$k] = $v;
                }

                if (($ptr[$idx]['appName'] ?? '') === '') {
                    $ptr[$idx]['appName'] = $ptr[$idx]['name'] ?? '';
                }
                if (! isset($ptr[$idx]['appNameManual'])) {
                    $ptr[$idx]['appNameManual'] = false;
                }

                return;
            }

            if (! isset($ptr[$idx]['children']) || ! is_array($ptr[$idx]['children'])) {
                $ptr[$idx]['children'] = [];
            }

            $ptr =& $ptr[$idx]['children'];
        }
    }

    /**
     * Remove a node from the tree at a given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $path
     */
    protected function removeNodeAtPath(&$nodes, $path): void
    {
        $index = array_shift($path);
        if (! isset($nodes[$index])) {
            return;
        }

        if (count($path) === 0) {
            array_splice($nodes, $index, 1);
        } else {
            if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
                return;
            }
            $this->removeNodeAtPath($nodes[$index]['children'], $path);
        }
    }

    /**
     * Extract (and remove) a node at a given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $path
     * @return array<string,mixed>|null
     */
    protected function extractNodeAtPath(&$nodes, $path): ?array
    {
        $index = array_shift($path);
        if (! isset($nodes[$index])) {
            return null;
        }

        if (count($path) === 0) {
            $node = $nodes[$index];
            array_splice($nodes, $index, 1);
            return $node;
        }

        if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
            return null;
        }

        return $this->extractNodeAtPath($nodes[$index]['children'], $path);
    }

    /**
     * Append a child node under the parent at a given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $path
     */
    protected function appendChildAtPath(&$nodes, $path, $newNode): void
    {
        $index = array_shift($path);
        if (! isset($nodes[$index])) {
            return;
        }

        if (count($path) === 0) {
            if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
                $nodes[$index]['children'] = [];
            }
            $nodes[$index]['children'][] = $newNode;
            return;
        }

        if (! isset($nodes[$index]['children']) || ! is_array($nodes[$index]['children'])) {
            $nodes[$index]['children'] = [];
        }

        $this->appendChildAtPath($nodes[$index]['children'], $path, $newNode);
    }

    /**
     * Insert a sibling node at the given index under parentPath.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $parentPath
     */
    protected function insertSiblingAt(&$nodes, $parentPath, int $insertIndex, $newNode): void
    {
        $ptr =& $nodes;

        foreach ($parentPath as $i) {
            if (! isset($ptr[$i])) {
                return;
            }

            if (! isset($ptr[$i]['children']) || ! is_array($ptr[$i]['children'])) {
                $ptr[$i]['children'] = [];
            }

            $ptr =& $ptr[$i]['children'];
        }

        $insertIndex = max(0, min($insertIndex, count($ptr)));

        array_splice($ptr, $insertIndex, 0, [$newNode]);
    }

    /**
     * Get the index of the last child beneath the node at the given path.
     *
     * @param array<int,array<string,mixed>> $nodes
     * @param array<int,int>                 $path
     */
    protected function lastChildIndexAtPath($nodes, $path): int
    {
        $n = $this->getNodeAtPath($nodes, $path);
        if (! $n) {
            return -1;
        }

        $kids = $n['children'] ?? [];
        return is_array($kids) ? max(count($kids) - 1, -1) : -1;
    }

    /**
     * Collect the sequence of node names along a path.
     *
     * @param array<int,int> $path
     * @return array<int,string>
     */
    protected function getPathNames(array $path): array
    {
        $names = [];
        $ptr   = $this->tree;

        foreach ($path as $idx) {
            if (! isset($ptr[$idx])) {
                break;
            }

            $names[] = $ptr[$idx]['name'] ?? '';
            $ptr     = isset($ptr[$idx]['children']) && is_array($ptr[$idx]['children'])
                ? $ptr[$idx]['children']
                : [];
        }

        return $names;
    }

    /**
     * Render a human-readable "breadcrumb" path string.
     *
     * @param array<int,int> $path
     */
    protected function pathToString(array $path): string
    {
        $names = $this->getPathNames($path);
        return empty($names) ? '(Wurzel)' : implode(' / ', $names);
    }

    /**
     * Delete the entire structure and redirect back to importer.
     */
    public function delete()
    {
        if (TreeModel::find($this->treeId)?->delete()) {
            return $this->redirect('/importer', navigate: true);
        }
    }

    /**
     * Render the main Livewire view.
     */
    public function render()
    {
        return view('livewire.tree-editor');
    }

    /**
     * Validate node or title names against several rules.
     */
    protected function invalidNameReason(string $name): ?string
    {
        if (mb_strlen($name) > 255) {
            return 'Name darf höchstens 255 Zeichen lang sein.';
        }

        if ($name === '.' || $name === '..') {
            return 'Name darf nicht "." oder ".." sein.';
        }

        if (preg_match('/[<>:"\/\\\\|?*]/u', $name) || preg_match('/[\x00-\x1F]/u', $name)) {
            return 'Ungültige Zeichen: < > : \" / \\ | ? * oder Steuerzeichen sind nicht erlaubt.';
        }

        if (preg_match('/[ \.]$/u', $name)) {
            return 'Name darf nicht mit einem Punkt oder Leerzeichen enden.';
        }

        $norm   = rtrim($name, " .");
        $upper  = mb_strtoupper($norm);
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

    /**
     * Compute short abbreviation for a node name used in appName composition.
     */
    protected function abbr(string $name): string
    {
        $map = [
            'Ltg'    => 'Ltg',
            'Allg'   => 'Allg',
            'PoEing' => 'Pe',
            'SB'     => 'Sb',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $name);
        if ($letters === '') {
            return $name;
        }

        $first  = mb_strtoupper(mb_substr($letters, 0, 1));
        $second = mb_strtolower(mb_substr($letters, 1, 1));

        return $first . $second;
    }

    /**
     * Compose appName from effective parent and child name.
     */
    protected function composeAppName(?string $effectiveParent, string $childName): string
    {
        if ($effectiveParent === null || $effectiveParent === '') {
            return $childName;
        }

        return match ($childName) {
            'Ltg'    => 'Ltg_' . $effectiveParent,
            'Allg'   => 'Allg_' . $effectiveParent,
            'AblgOE' => 'Ab_'  . $effectiveParent,
            'SB'     => 'Sb_'  . $effectiveParent,
            'PoEing' => 'Pe_'  . $effectiveParent,
            default  => $effectiveParent . '_' . $this->abbr($childName),
        };
    }

    /**
     * Determine the next effective parent name given the current node.
     */
    protected function nextEffectiveParent(string $currentNodeName, ?string $currentEffectiveParent): ?string
    {
        return ($currentNodeName === 'AblgOE') ? $currentEffectiveParent : $currentNodeName;
    }

    /**
     * Refresh all appNames bottom-up, respecting "manual" flags.
     *
     * @param array<int,array<string,mixed>> $nodes
     */
    protected function refreshAppNames(array &$nodes, ?string $parentName, ?string $grandparentName): void
    {
        foreach ($nodes as &$n) {
            $name = $n['name'] ?? '';

            if (! isset($n['appName']) || $n['appName'] === '') {
                $n['appName'] = $name;
            }
            if (! isset($n['appNameManual'])) {
                $n['appNameManual'] = false;
            }

            $effectiveParent     = ($parentName === 'AblgOE') ? $grandparentName : $parentName;
            $nextEffectiveParent = $this->nextEffectiveParent($name, $effectiveParent);

            if (! empty($n['children']) && is_array($n['children'])) {
                foreach ($n['children'] as &$child) {
                    $childName = $child['name'] ?? '';
                    $manual    = isset($child['appNameManual']) && $child['appNameManual'] === true;

                    if (! $manual) {
                        $keep = isset($child['appName']) && $child['appName'] === $childName;
                        if (! $keep) {
                            $child['appName'] = $this->composeAppName($nextEffectiveParent, $childName);
                        }
                    }

                    if (! isset($child['appNameManual'])) {
                        $child['appNameManual'] = $manual;
                    }
                }

                $this->refreshAppNames($n['children'], $name, $effectiveParent);
            }
        }
    }

    /**
     * Replace German umlauts and ß with ASCII-friendly equivalents.
     */
    protected function translitUmlauts(string $s): string
    {
        $map = [
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ];

        return strtr($s, $map);
    }

    /**
     * Normalize whitespace in titles.
     */
    protected function normalizeTitle(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s ?? '');
        return trim($s);
    }

    /**
     * Normalize "SB" prefix to "Sb" for appNames.
     */
    protected function normalizeSbPrefix(string $s): string
    {
        return preg_replace('/^SB/u', 'Sb', $s);
    }

    /**
     * If tree data is wrapped in .PANKOW/ba/DigitaleAkte-203, unwrap it.
     *
     * @param array<int,array<string,mixed>> $data
     * @return array<int,array<string,mixed>>
     */
    protected function unwrapIfWrapped(array $data): array
    {
        if (
            count($data) === 1 &&
            isset($data[0]['name']) &&
            $data[0]['name'] === '.PANKOW' &&
            ! empty($data[0]['children']) &&
            isset($data[0]['children'][0]['name']) &&
            $data[0]['children'][0]['name'] === 'ba' &&
            ! empty($data[0]['children'][0]['children']) &&
            isset($data[0]['children'][0]['children'][0]['name']) &&
            $data[0]['children'][0]['children'][0]['name'] === 'DigitaleAkte-203'
        ) {
            return $data[0]['children'][0]['children'][0]['children'] ?? [];
        }

        return $data;
    }

    /**
     * Check whether two paths share the same parent.
     *
     * @param array<int,int> $a
     * @param array<int,int> $b
     */
    protected function pathsShareParent(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        return $this->isAncestorPath(array_slice($a, 0, -1), $b)
            && count($a) - 1 === count(array_slice($b, 0, -1));
    }

    /**
     * Check if path $a is a strict ancestor of path $b.
     *
     * @param array<int,int> $a
     * @param array<int,int> $b
     */
    protected function isAncestorPath(array $a, array $b): bool
    {
        if (count($a) >= count($b)) {
            return false;
        }

        for ($i = 0; $i < count($a); $i++) {
            if ($a[$i] !== $b[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure deletable flags follow fixedNames for all nodes.
     *
     * @param array<int,array<string,mixed>> $nodes
     */
    protected function sanitizeDeletionFlags(array &$nodes): void
    {
        foreach ($nodes as &$n) {
            $n['deletable'] = ! $this->isFixedName($n['name'] ?? '');

            if (! empty($n['children']) && is_array($n['children'])) {
                $this->sanitizeDeletionFlags($n['children']);
            }
        }
    }

    /**
     * Ensure enabled flags only exist where allowed (Ltg, Allg, under AblgOE).
     *
     * @param array<int,array<string,mixed>> $nodes
     */
    protected function sanitizeEnabledFlags(array &$nodes, bool $underAblgOE = false): void
    {
        foreach ($nodes as &$n) {
            $name        = (string) ($n['name'] ?? '');
            $hasChildren = ! empty($n['children']) && is_array($n['children']);
            $isAblg      = ($name === 'AblgOE');

            $eligible = $underAblgOE || in_array($name, ['Ltg', 'Allg'], true);

            if ($eligible) {
                if (! array_key_exists('enabled', $n)) {
                    $n['enabled'] = true;
                } else {
                    $n['enabled'] = (bool) $n['enabled'];
                }
            } else {
                if (array_key_exists('enabled', $n)) {
                    unset($n['enabled']);
                }
            }

            if ($hasChildren) {
                $this->sanitizeEnabledFlags($n['children'], $underAblgOE || $isAblg);
            }
        }
    }

    /**
     * Check whether a node name is protected from deletion.
     */
    protected function isFixedName(?string $name): bool
    {
        return in_array((string) $name, $this->fixedNames, true);
    }
}
