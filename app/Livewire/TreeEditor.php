<?php

namespace App\Livewire;

use App\Models\OrganizationStructure as TreeModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

class TreeEditor extends Component
{
    // ===== Internal tree state (renamed to avoid collision with route param) =====
    public array $treeData = [];   // was: public array $tree

    // Expose read-only computed property so Blade can keep using `$tree`
    public function getTreeProperty(): array
    {
        return $this->treeData;
    }

    public ?int $treeId = null;
    public string $title = '';

    public string $newNodeName = '';
    public string $newAppName = '';

    public $selectedNodePath = null;
    public bool $addWithStructure = false;

    public string $generatedJson = '';
    public string $downloadFilename = '';
    public bool $withTimestamp = true;

    public $editNodePath = null;
    public $editField = null;
    public string $editValue = '';

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

    public array $confirmDeleteNodePath = [];
    public string $confirmDeleteNodePathStr = '';
    public string $confirmDeleteNodeName = '';

    public bool $sheetGE = true;
    public bool $sheetAblage = true;
    public bool $sheetRoles = true;
    public int $rolesPlaceholderCount = 10;

    public bool $excelOptionsOpen = false;

    protected array $fixedNames = ['Ltg', 'Allg', 'AblgOE', 'PoEing', 'SB'];

    protected array $predefinedStructure = [
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

    // ===== Locking / versioning / meta =====
    public bool $editable = false;        // single-editor mode
    public ?string $versionGroupId = null;
    public int $version = 1;
    public string $status = 'draft';      // draft | in_progress | abgeschlossen
    public ?int $lockedBy = null;
    public ?string $lockedAt = null;
    public array $versions = [];

    // Route-model binding still uses $tree (the route param), no collision now
    public function mount(TreeModel $tree, ?bool $edit = null)
    {
        $this->treeId = $tree->id;
        $this->title = $this->normalizeTitle((string)$tree->title);

        // Read from tree_json (array cast in model)
        $data = $tree->tree_json ?? [];
        $this->treeData = $this->unwrapIfWrapped(is_array($data) ? $data : (json_decode((string)$data, true) ?? []));

        // Normalize/refresh your flags & derived fields
        $this->sanitizeDeletionFlags($this->treeData);
        $this->sanitizeEnabledFlags($this->treeData);
        $this->refreshAppNames($this->treeData, null, null);

        // Load meta
        $this->versionGroupId = $tree->version_group_id;
        $this->version        = (int) $tree->version;
        $this->status         = (string) $tree->status;
        $this->lockedBy       = $tree->locked_by;
        $this->lockedAt       = optional($tree->locked_at)?->toDateTimeString();

        // Versions list
        if ($this->versionGroupId) {
            $this->versions = TreeModel::group($this->versionGroupId)
                ->select('id','version','status','closed_at','deleted_at','title')
                ->withTrashed()
                ->orderBy('version')
                ->get()
                ->map(fn($t) => [
                    'id'         => $t->id,
                    'title'      => $t->title,
                    'version'    => (int)$t->version,
                    'status'     => (string)$t->status,
                    'closed_at'  => optional($t->closed_at)?->toDateTimeString(),
                    'deleted_at' => optional($t->deleted_at)?->toDateTimeString(),
                ])->toArray();
        }

        if ($edit) {
            $this->startEdit();
        }
    }

    public function render()
    {
        return view('livewire.tree-editor');
    }

    // ===== Locking =====
    public function startEdit(): void
    {
        $model = $this->model();

        if ($model->status === 'abgeschlossen') {
            $this->editable = false;
            $this->dispatch('toast', type:'error', message:'Diese Version ist abgeschlossen.');
            return;
        }

        $ok = $model->acquireLock(Auth::id());
        $model->refresh();

        $this->lockedBy = $model->locked_by;
        $this->lockedAt = optional($model->locked_at)?->toDateTimeString();
        $this->status   = (string) $model->status;

        $this->editable = $ok && (int)$model->locked_by === (int)Auth::id();

        $this->dispatch('toast',
            type: $this->editable ? 'success' : 'warning',
            message: $this->editable
                ? 'Bearbeitung gestartet. Sperre gesetzt.'
                : 'Der Baum wird gerade von einem anderen Benutzer bearbeitet.'
        );
    }

    public function releaseLock(): void
    {
        $model = $this->model();
        if ($model->isLocked() && (int)$model->locked_by === (int)Auth::id()) {
            $model->releaseLock(Auth::id());
        }
        $this->editable = false;
        $this->lockedBy = null;
        $this->lockedAt = null;
        $this->status   = (string) $model->status;
        $this->dispatch('toast', type:'info', message:'Bearbeitung beendet. Sperre freigegeben.');
    }

    public function finalizeTree(): void
    {
        $model = $this->model();

        if (!($model->isLocked() && (int)$model->locked_by === (int)Auth::id())) {
            $this->dispatch('toast', type:'warning', message:'Nur der aktive Bearbeiter kann abschließen.');
            return;
        }

        $model->finalize();
        $model->audits()->create([
            'user_id' => Auth::id(),
            'action'  => 'finalized',
        ]);

        $this->editable = false;
        $this->status   = 'abgeschlossen';
        $this->lockedBy = null;
        $this->lockedAt = null;

        $this->refreshVersions();
        $this->dispatch('toast', type:'success', message:'Version abgeschlossen.');
    }

    public function createNewVersionFrom(int $versionId): void
    {
        $base = TreeModel::findOrFail($versionId);
        if ($base->status !== 'abgeschlossen') {
            $this->dispatch('toast', type:'warning', message:'Nur abgeschlossene Versionen können verzweigt werden.');
            return;
        }

        $new = $base->cloneToNewVersion(Auth::id());

        $base->audits()->create([
            'user_id' => Auth::id(),
            'action'  => 'version_created',
            'before'  => ['from_version_id' => $base->id],
            'after'   => ['to_version_id' => $new->id],
        ]);

        $this->redirect(route('trees.edit', ['id' => $new->id, 'edit' => 1]), navigate: true);
    }

    public function delete()
    {
        $model = $this->model();
        if ($model->delete()) {
            $model->audits()->create([
                'user_id' => Auth::id(),
                'action'  => 'deleted',
            ]);
            return $this->redirect('/importer', navigate: true);
        }
    }

    protected function refreshVersions(): void
    {
        $group = $this->versionGroupId;
        if (!$group) { $this->versions = []; return; }

        $this->versions = TreeModel::group($group)
            ->select('id','version','status','closed_at','deleted_at','title')
            ->withTrashed()
            ->orderBy('version')
            ->get()
            ->map(fn($t) => [
                'id'         => $t->id,
                'title'      => $t->title,
                'version'    => (int)$t->version,
                'status'     => (string)$t->status,
                'closed_at'  => optional($t->closed_at)?->toDateTimeString(),
                'deleted_at' => optional($t->deleted_at)?->toDateTimeString(),
            ])->toArray();
    }

    #[On('browser-unload')]
    public function onBrowserUnload(): void
    {
        $model = $this->model();
        if ($model->isLocked() && (int)$model->locked_by === (int)Auth::id()) {
            $model->releaseLock(Auth::id());
        }
    }

    // ===== Persist (writes to tree_json) =====
    protected function persist(): void
    {
        if (!$this->treeId) return;

        $model = TreeModel::find($this->treeId);
        if (!$model) return;

        if ($model->status !== 'abgeschlossen' && !($model->isLocked() && (int)$model->locked_by === (int)Auth::id())) {
            $this->dispatch('toast', type:'warning', message:'Sperre erforderlich, um zu speichern.');
            return;
        }

        $before = [
            'title' => $model->title,
            'tree_json' => $model->tree_json,
        ];

        $model->update([
            'title'     => $this->title !== '' ? $this->title : $model->title,
            'tree_json' => $this->treeData,
        ]);

        if ($model->status !== 'abgeschlossen') {
            $model->locked_at = now();
            $model->save();
        }

        $model->audits()->create([
            'user_id' => Auth::id(),
            'action'  => 'updated',
            'before'  => $before,
            'after'   => [
                'title'     => $model->title,
                'tree_json' => $model->tree_json,
            ],
        ]);

        $this->dispatch('autosaved');
    }

    // ===== Title etc. (unchanged) =====
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

    public function updatedNewNodeName(): void { $this->resetValidation(); }
    public function updatedNewAppName(): void  { $this->resetValidation(); }
    public function updatedEditValue(): void   { $this->resetValidation(); }

    public function updatedSheetGE()      { $this->resetErrorBag('generate'); }
    public function updatedSheetAblage()  { $this->resetErrorBag('generate'); }
    public function updatedSheetRoles($value)
    {
        $this->resetErrorBag('generate');
        if (!$value) {
            $this->rolesPlaceholderCount = 10;
        }
    }
    public function updatedRolesPlaceholderCount() { $this->resetErrorBag('generate'); }

    // ===== Node ops (same as your original, but use $this->treeData) =====
    public function addNode()
    {
        $nameInput = $this->translitUmlauts(trim((string)$this->newNodeName));

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
            $computedAppName = $this->normalizeSbPrefix($appInputRaw);
            $manual = true;
        } else {
            $fromName = $this->normalizeSbPrefix($nameInput);
            if ($fromName !== $nameInput) {
                $computedAppName = $fromName;
                $manual = true;
            } else {
                $computedAppName = $nameInput;
            }
        }

        if (!$this->addWithStructure) {
            $effectiveParent = $this->effectiveParentNameForPath($this->selectedNodePath);
            if ($effectiveParent !== null && $effectiveParent !== '') {
                if (!preg_match('/_' . preg_quote($effectiveParent, '/') . '$/u', $computedAppName)) {
                    $computedAppName .= '_' . $effectiveParent;
                }
                $manual = true;
            }
        }

        $newNode = [
            'name' => $nameInput,
            'appName' => $computedAppName,
            'appNameManual' => $manual,
            'children' => [],
            'deletable' => true,
        ];

        if ($this->addWithStructure) {
            $parentAtPath = $this->effectiveParentNameForPath($this->selectedNodePath);
            $effectiveForChildren = $this->nextEffectiveParent($nameInput, $parentAtPath);
            $newNode['children'] = $this->buildPredefinedChildrenWithParent(
                $this->predefinedStructure,
                $effectiveForChildren
            );
        }

        $targetPath = $this->pathExists($this->treeData, $this->selectedNodePath) ? $this->selectedNodePath : null;

        if ($targetPath === null) {
            $this->treeData[] = $newNode;
        } else {
            $this->addChildAtPathSafely($this->treeData, $targetPath, $newNode);
        }

        $this->refreshAppNames($this->treeData, null, null);
        $this->sanitizeEnabledFlags($this->treeData);
        $this->sanitizeDeletionFlags($this->treeData);
        $this->persist();

        $this->newNodeName = '';
        $this->newAppName = '';
        $this->addWithStructure = false;

        $this->dispatch('focus-newnode');
    }

    public function promptDeleteNode($path): void
    {
        $this->confirmDeleteNodePath = is_array($path) ? $path : [];
        $this->confirmDeleteNodeName = $this->getNameAtPath($this->treeData, $this->confirmDeleteNodePath) ?? '(ohne Name)';
        $this->confirmDeleteNodePathStr = $this->pathToString($this->confirmDeleteNodePath);
        $this->dispatch('open-delete-node');
    }

    public function confirmDeleteNode(): void
    {
        if (!empty($this->confirmDeleteNodePath)) {
            $this->removeNode($this->confirmDeleteNodePath);
        }
        $this->confirmDeleteNodePath = [];
        $this->confirmDeleteNodeName = '';
        $this->confirmDeleteNodePathStr = '';
    }

    public function removeNode($path)
    {
        $node = $this->getNodeAtPath($this->treeData, $path);
        if (!$node) return;
        if ($this->isFixedName($node['name'] ?? '')) return;

        $parentPath = (is_array($path) && count($path) > 0) ? array_slice($path, 0, -1) : null;

        $this->removeNodeAtPath($this->treeData, $path);
        $this->refreshAppNames($this->treeData, null, null);

        if (is_array($parentPath) && count($parentPath) > 0 && $this->pathExists($this->treeData, $parentPath)) {
            $this->selectedNodePath = $parentPath;
        } elseif (!empty($this->treeData)) {
            $this->selectedNodePath = [0];
        } else {
            $this->selectedNodePath = null;
        }

        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->sanitizeEnabledFlags($this->treeData);
        $this->sanitizeDeletionFlags($this->treeData);
        $this->persist();
    }

    public function selectNode($path) { $this->selectedNodePath = $this->pathExists($this->treeData, $path) ? $path : null; }

    public function startInlineEdit($path, $field)
    {
        if (!in_array($field, ['name', 'appName'])) return;

        $node = $this->getNodeAtPath($this->treeData, $path);
        if (!$node) return;

        $this->editNodePath = $path;
        $this->editField = $field;
        $this->editValue = $node[$field] ?? '';
    }

    public function saveInlineEdit($value = null)
    {
        if ($this->editNodePath === null || $this->editField === null) return;

        $val = $this->translitUmlauts(trim((string)($value ?? $this->editValue)));

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

        $before = $this->getNodeAtPath($this->treeData, $this->editNodePath);
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

        $this->setNodeFieldsByPath($this->treeData, $this->editNodePath, $fields);

        $this->refreshAppNames($this->treeData, null, null);

        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';

        $this->sanitizeEnabledFlags($this->treeData);
        $this->sanitizeDeletionFlags($this->treeData);
        $this->persist();
    }

    public function cancelInlineEdit()
    {
        $this->editNodePath = null;
        $this->editField = null;
        $this->editValue = '';
    }

    public function generateJson()
    {
        $wrapped = $this->wrapForExport($this->treeData);
        $this->generatedJson = json_encode($wrapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function generateExcel(): void
    {
        $this->resetErrorBag('generate');

        $selectedSheets = [];
        if ($this->sheetGE)     $selectedSheets[] = 'GE';
        if ($this->sheetAblage) $selectedSheets[] = 'Ablage';
        if ($this->sheetRoles)  $selectedSheets[] = 'Roles';

        if (count($selectedSheets) === 0) {
            $this->addError('generate', 'Bitte wählen Sie mindestens ein Arbeitsblatt aus.');
            return;
        }

        if (!is_int($this->rolesPlaceholderCount)) {
            $this->rolesPlaceholderCount = (int) $this->rolesPlaceholderCount;
        }
        if ($this->sheetRoles && ($this->rolesPlaceholderCount < 1 || $this->rolesPlaceholderCount > 50)) {
            $this->addError('generate', 'Die Anzahl der Rollen muss zwischen 1 und 50 liegen.');
            return;
        }

        $payload = [
            'tree' => $this->wrapForExport($this->treeData),
            'sheets' => $selectedSheets,
            'rolesCount' => $this->sheetRoles ? $this->rolesPlaceholderCount : 0,
        ];

        $port = (string) config('services.python.backend', '8000');
        $url = 'http://localhost:' . $port . '/generate-excel';

        $res = Http::accept('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->post($url, $payload);

        if (!$res->successful()) {
            $this->addError('generate', 'Excel-Erzeugung fehlgeschlagen.');
            return;
        }

        $basename = $this->computeDownloadBasename();
        $finalName = $basename . '.xlsx';

        Storage::put('temp/' . $finalName, $res->body());
        $this->downloadFilename = $basename;

        $this->excelOptionsOpen = false;
        $this->dispatch('excel-ready', filename: $finalName);
    }

    protected function computeDownloadBasename(): string
    {
        $raw = trim((string)$this->downloadFilename);
        $base = $raw !== '' ? $raw : ('Importer-Datei-' . ($this->title ?? ''));
        $base = $this->translitUmlauts($base);
        $base = preg_replace('/\.xlsx$/ui', '', $base);
        $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '-', $base);
        $base = preg_replace('/\s+/u', ' ', $base);
        $base = trim($base, " .-");
        if ($base === '') $base = 'Importer-Datei';
        if (mb_strlen($base) > 120) $base = mb_substr($base, 0, 120);
        $base = str_replace(' ', '_', $base);

        if ($this->withTimestamp) {
            $timestamp = date("Y-m-d_H-i");
            $base = $timestamp . '_' . $base;
        }

        return $base;
    }

    protected function wrapForExport(array $nodes): array
    {
        $clean = $this->stripInternal($nodes);

        return [[
            'name' => '.PANKOW', 'appName' => '.PANKOW',
            'children' => [[
                'name' => 'ba', 'appName' => 'ba',
                'children' => [[
                    'name' => 'DigitaleAkte-203', 'appName' => 'DigitaleAkte-203',
                    'children' => $clean,
                ]],
            ]],
        ]];
    }

    protected function stripInternal(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            $row = [
                'name' => $n['name'] ?? '',
                'appName' => $n['appName'] ?? ($n['name'] ?? ''),
                'children' => !empty($n['children']) ? $this->stripInternal($n['children']) : [],
            ];
            if (array_key_exists('enabled', $n)) {
                $row['enabled'] = (bool)$n['enabled'];
            }
            if (isset($n['description'])) {
                $row['description'] = (string)$n['description'];
            }
            $out[] = $row;
        }
        return $out;
    }

    // ===== Move workflow =====
    public function preparePendingMove($fromPath, $toPath, $position = 'into'): void
    {
        $this->pendingFromPath = is_array($fromPath) ? $fromPath : [];
        $this->pendingToPath = is_array($toPath) ? $toPath : [];
        $this->pendingPosition = in_array($position, ['into', 'before', 'after'], true) ? $position : 'into';

        $oldParentPath = array_slice($this->pendingFromPath, 0, -1);
        $newParentPath = ($this->pendingPosition === 'into')
            ? $this->pendingToPath
            : array_slice($this->pendingToPath, 0, -1);

        $this->pendingOldParentName = $this->getNameAtPath($this->treeData, $oldParentPath) ?? '';
        $this->pendingNewParentName = $this->getNameAtPath($this->treeData, $newParentPath) ?? '';

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

    public function confirmPendingMove(): void
    {
        if (empty($this->pendingFromPath) || empty($this->pendingToPath)) return;

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

    public function moveNode($fromPath, $toPath, $position = 'into'): void
    {
        if (!$this->pathExists($this->treeData, $fromPath) || !$this->pathExists($this->treeData, $toPath)) return;
        if ($fromPath === $toPath || $this->isAncestorPath($fromPath, $toPath)) return;

        $moved = $this->extractNodeAtPath($this->treeData, $fromPath);
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
            $this->insertSiblingAt($this->treeData, $parentPath, $insertIndex, $moved);
            $newPath = array_merge($parentPath, [$insertIndex]);
        } else {
            $this->appendChildAtPath($this->treeData, $toPath, $moved);
            $newChildIndex = $this->lastChildIndexAtPath($this->treeData, $toPath);
            $newPath = array_merge($toPath, [$newChildIndex]);
        }

        $this->refreshAppNames($this->treeData, null, null);
        $this->sanitizeEnabledFlags($this->treeData);
        $this->sanitizeDeletionFlags($this->treeData);
        $this->persist();

        $this->selectedNodePath = $newPath;
    }

    protected function pathsShareParent(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        return $this->isAncestorPath(array_slice($a, 0, -1), $b) && count($a) - 1 === count(array_slice($b, 0, -1));
    }

    protected function isAncestorPath(array $a, array $b): bool
    {
        if (count($a) >= count($b)) return false;
        for ($i = 0; $i < count($a); $i++) if ($a[$i] !== $b[$i]) return false;
        return true;
    }

    // ===== Helpers =====
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

    protected function sanitizeEnabledFlags(array &$nodes, bool $underAblgOE = false): void
    {
        foreach ($nodes as &$n) {
            $name = (string)($n['name'] ?? '');
            $hasChildren = !empty($n['children']) && is_array($n['children']);
            $isAblg = ($name === 'AblgOE');

            $eligible = $underAblgOE || in_array($name, ['Ltg', 'Allg'], true);

            if ($eligible) {
                if (!array_key_exists('enabled', $n)) {
                    $n['enabled'] = true;
                } else {
                    $n['enabled'] = (bool)$n['enabled'];
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

    public function toggleEnabled($path, $checked): void
    {
        $val = is_bool($checked)
            ? $checked
            : (in_array($checked, [1, '1', 'true', 'TRUE', 'on'], true));

        $this->setEnabledAtPath($this->treeData, $path, (bool)$val, true);

        $this->sanitizeEnabledFlags($this->treeData);
        $this->persist();
    }

    protected function setEnabledAtPath(&$nodes, $path, bool $val, bool $cascade): void
    {
        if (!is_array($path) || empty($path)) return;
        $index = array_shift($path);
        if (!isset($nodes[$index]) || !is_array($nodes[$index])) return;

        if (count($path) === 0) {
            $nodes[$index]['enabled'] = $val;
            if ($cascade && !empty($nodes[$index]['children']) && is_array($nodes[$index]['children'])) {
                $this->setEnabledRecursive($nodes[$index]['children'], $val);
            }
            return;
        }

        if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) return;
        $this->setEnabledAtPath($nodes[$index]['children'], $path, $val, $cascade);
    }

    protected function setEnabledRecursive(&$nodes, bool $val): void
    {
        foreach ($nodes as &$n) {
            $n['enabled'] = $val;
            if (!empty($n['children']) && is_array($n['children'])) {
                $this->setEnabledRecursive($n['children'], $val);
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

    protected function effectiveParentNameForPath($path): ?string
    {
        if ($path === null || !is_array($path) || empty($path)) return null;

        $parentName = $this->getNameAtPath($this->treeData, $path);
        if ($parentName === null) return null;

        if ($parentName === 'AblgOE') {
            $gpPath = $path;
            array_pop($gpPath);
            $grandparentName = $this->getNameAtPath($this->treeData, $gpPath);
            return $grandparentName ?? null;
        }

        return $parentName;
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
            if (!isset($nodes[$index]['children']) || !is_array($nodes[$index]['children'])) return;
            $this->removeNodeAtPath($nodes[$index]['children'], $path);
        }
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

    protected function lastChildIndexAtPath($nodes, $path): int
    {
        $n = $this->getNodeAtPath($nodes, $path);
        if (!$n) return -1;
        $kids = $n['children'] ?? [];
        return is_array($kids) ? max(count($kids) - 1, -1) : -1;
    }

    protected function getPathNames(array $path): array
    {
        $names = [];
        $ptr = $this->treeData;
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

    // ===== Validation helpers =====
    protected function invalidNameReason(string $name): ?string
    {
        if (mb_strlen($name) > 255) return 'Name darf höchstens 255 Zeichen lang sein.';
        if ($name === '.' || $name === '..') return 'Name darf nicht "." oder ".." sein.';
        if (preg_match('/[<>:"\/\\\\|?*]/u', $name) || preg_match('/[\x00-\x1F]/u', $name)) {
            return 'Ungültige Zeichen: < > : \" / \\ | ? * oder Steuerzeichen sind nicht erlaubt.';
        }
        if (preg_match('/[ \.]$/u', $name)) return 'Name darf nicht mit einem Punkt oder Leerzeichen enden.';
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

    protected function abbr(string $name): string
    {
        $map = ['Ltg' => 'Ltg', 'Allg' => 'Allg', 'PoEing' => 'Pe', 'SB' => 'Sb'];
        if (isset($map[$name])) return $map[$name];

        $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $name);
        if ($letters === '') return $name;
        $first = mb_strtoupper(mb_substr($letters, 0, 1));
        $second = mb_strtolower(mb_substr($letters, 1, 1));
        return $first . $second;
    }

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
                return 'Sb_' . $effectiveParent;
            case 'PoEing':
                return 'Pe_' . $effectiveParent;
            default:
                return $effectiveParent . '_' . $this->abbr($childName);
        }
    }

    protected function nextEffectiveParent(string $currentNodeName, ?string $currentEffectiveParent): ?string
    {
        return ($currentNodeName === 'AblgOE') ? $currentEffectiveParent : $currentNodeName;
    }

    protected function refreshAppNames(array &$nodes, ?string $parentName, ?string $grandparentName): void
    {
        foreach ($nodes as &$n) {
            $name = $n['name'] ?? '';

            if (!isset($n['appName']) || $n['appName'] === '') $n['appName'] = $name;
            if (!isset($n['appNameManual'])) $n['appNameManual'] = false;

            $effectiveParent = ($parentName === 'AblgOE') ? $grandparentName : $parentName;
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

    protected function normalizeSbPrefix(string $s): string
    {
        return preg_replace('/^SB/u', 'Sb', $s);
    }

    protected function model(): TreeModel
    {
        return TreeModel::findOrFail($this->treeId);
    }
}
