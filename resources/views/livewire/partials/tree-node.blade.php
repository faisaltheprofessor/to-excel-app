{{-- resources/views/livewire/partials/tree-node.blade.php --}}
@php
    $node = is_array($node) ? $node : [];
    $nodeKey = implode('-', $path);
    $isEditingName = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'name';
    $isEditingApp  = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'appName';
    $canDelete     = $node['deletable'] ?? false;
    $isSelected    = isset($selectedNodePath) && $selectedNodePath === $path;

    $level = count($path);

    // Palette shared with minimap
    $palette = [
        ['border-red-300',    'text-red-500 dark:text-red-300',       'bg-red-100',    'dark:bg-red-900'],
        ['border-orange-300', 'text-orange-500 dark:text-orange-300', 'bg-orange-100', 'dark:bg-orange-900'],
        ['border-amber-300',  'text-amber-500 dark:text-amber-300',   'bg-amber-100',  'dark:bg-amber-900'],
        ['border-lime-300',   'text-lime-600 dark:text-lime-300',     'bg-lime-100',   'dark:bg-lime-900'],
        ['border-emerald-300','text-emerald-500 dark:text-emerald-300','bg-emerald-100','dark:bg-emerald-900'],
        ['border-cyan-300',   'text-cyan-500 dark:text-cyan-300',     'bg-cyan-100',   'dark:bg-cyan-900'],
        ['border-blue-300',   'text-blue-500 dark:text-blue-300',     'bg-blue-100',   'dark:bg-blue-900'],
        ['border-indigo-300', 'text-indigo-500 dark:text-indigo-300', 'bg-indigo-100', 'dark:bg-indigo-900'],
        ['border-violet-300', 'text-violet-500 dark:text-violet-300', 'bg-violet-100', 'dark:bg-violet-900'],
        ['border-pink-300',   'text-pink-500 dark:text-pink-300',     'bg-pink-100',   'dark:bg-pink-900'],
        ['border-slate-300',  'text-slate-500 dark:text-slate-300',   'bg-slate-100',  'dark:bg-slate-800'],
    ];
    $c           = $palette[$level % count($palette)];
    $borderClass = $c[0];
    $iconClass   = $c[1];
    $bgSelected  = $c[2] . ' ' . $c[3];

    $enabled     = array_key_exists('enabled', $node) ? (bool) $node['enabled'] : true;
    $isDisabled  = array_key_exists('enabled', $node) ? !$node['enabled'] : false;

    $editable    = $editable ?? false;
@endphp

<li
    class="relative pl-4 {{ $borderClass }} {{ $isSelected ? 'border-l-4' : 'border-l-2' }}"
    wire:key="node-{{ $nodeKey }}"
    data-tree-node
    data-path='@json($path)'
    data-name='{{ $node['name'] ?? '' }}'
    draggable="{{ $editable ? 'true' : 'false' }}"
>
    {{-- Dropzone before node --}}
    <div data-dropzone data-pos="before" class="h-2 -mt-1"></div>

    {{-- Wide invisible button on left border to select node --}}
    <button
        type="button"
        class="absolute left-0 top-0 bottom-0 w-4 cursor-pointer opacity-0 hover:opacity-20 focus:opacity-20 z-40"
        aria-label="Knoten über Linie auswählen"
        title="Diesen Knoten auswählen"
        wire:click.stop="selectNode({{ json_encode($path) }})"
    ></button>

    <div
        x-data="{ localDisabled: @js($isDisabled), path: @js($path) }"
        :class="localDisabled ? 'opacity-80 bg-gray-200 dark:bg-gray-700' : ''"
        class="relative flex items-center gap-3
               {{ $editable ? 'cursor-move' : 'cursor-default' }}
               {{ $isSelected ? $bgSelected.' font-semibold' : 'hover:bg-gray-200 dark:hover:bg-gray-600' }}
               rounded px-2 py-1"
        data-dropzone
        data-pos="into"
        wire:click.prevent="selectNode({{ json_encode($path) }})"
        :aria-disabled="localDisabled ? 'true' : 'false'"
    >
        {{-- Folder icon, colored by level --}}
        <flux:icon.folder class="w-5 h-5 {{ $iconClass }}"/>

        {{-- Name (inline editable) --}}
        <div class="flex items-center gap-1">
            @if ($isEditingName)
                <div class="relative">
                    <input
                        type="text"
                        class="pl-2 pr-12 py-1 border rounded text-sm w-96"
                        wire:key="edit-name-{{ $nodeKey }}"
                        wire:model.live="editValue"
                        wire:keydown.enter.stop.prevent="saveInlineEdit($event.target.value)"
                        wire:keydown.escape.stop.prevent="cancelInlineEdit"
                        autofocus
                    />
                    <div class="absolute inset-y-0 right-1 flex items-center gap-1">
                        <button type="button" wire:click.stop="saveInlineEdit" class="p-0.5" title="Speichern">
                            <flux:icon.check class="w-5 h-5 text-green-600 dark:text-green-400 cursor-pointer stroke-[2.5]"/>
                        </button>
                        <button type="button" wire:click.stop="cancelInlineEdit" class="p-0.5" title="Abbrechen">
                            <flux:icon.x-mark class="w-5 h-5 text-red-600 dark:text-red-400 cursor-pointer stroke-[2.5]"/>
                        </button>
                    </div>
                </div>
            @else
                <span
                    class="cursor-text"
                    title="Doppelklick zum Bearbeiten"
                    wire:dblclick.stop="startInlineEdit({{ json_encode($path) }}, 'name')"
                >
                    {{ $node['name'] ?? '(ohne Name)' }}
                </span>
            @endif
        </div>

        {{-- Nscale / appName (inline editable) --}}
        <div class="flex items-center gap-1">
            <span class="text-xs text-gray-500 dark:text-gray-100">Nscale:</span>
            @if ($isEditingApp)
                <div class="relative">
                    <input
                        type="text"
                        class="pl-2 pr-12 py-1 border rounded text-xs w-80"
                        wire:key="edit-app-{{ $nodeKey }}"
                        wire:model.live="editValue"
                        wire:keydown.enter.stop.prevent="saveInlineEdit($event.target.value)"
                        wire:keydown.escape.stop.prevent="cancelInlineEdit"
                        autofocus
                    />
                    <div class="absolute inset-y-0 right-1 flex items-center gap-1">
                        <button type="button" wire:click.stop="saveInlineEdit" class="p-0.5" title="Speichern">
                            <flux:icon.check class="w-5 h-5 text-green-600 dark:text-green-400 cursor-pointer stroke-[2.5]"/>
                        </button>
                        <button type="button" wire:click.stop="cancelInlineEdit" class="p-0.5" title="Abbrechen">
                            <flux:icon.x-mark class="w-5 h-5 text-red-600 dark:text-red-400 cursor-pointer stroke-[2.5]"/>
                        </button>
                    </div>
                </div>
            @else
                <span
                    class="text-xs text-gray-700 dark:text-gray-300 italic cursor-text"
                    title="Doppelklick zum Bearbeiten"
                    wire:dblclick.stop="startInlineEdit({{ json_encode($path) }}, 'appName')"
                >
                    {{ $node['appName'] ?? ($node['name'] ?? '') }}
                </span>
            @endif
        </div>

        {{-- Right-side controls (enabled toggle + delete) --}}
 <div class="ml-auto flex items-center gap-3">
            @if (array_key_exists('enabled', $node))
                <div class="flex items-center gap-1 {{ $editable ? '' : 'opacity-60 cursor-not-allowed' }}">
                    <input
                        type="checkbox"
                        class="form-checkbox {{ $editable ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                        {{-- Always reflect disabled state visually --}}
                        :checked="localDisabled"
                        @if($editable)
                            {{-- Only allow interaction in edit mode --}}
                            x-model="localDisabled"
                            @click.stop
                            @change.stop="$nextTick(() => $wire.toggleEnabled(path, localDisabled ? false : true))"
                        @else
                            @click.stop
                        @endif
                        @disabled(!$editable)
                    />
                    <button
                        type="button"
                        class="text-xs text-gray-600 dark:text-gray-300 select-none {{ $editable ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                        title="Ausblenden / Einblenden"
                        @if($editable)
                            @click.stop="localDisabled = !localDisabled; $nextTick(() => $wire.toggleEnabled(path, localDisabled ? false : true))"
                        @endif
                    >
                        ausgeblendet
                    </button>
                </div>
            @endif

            @if ($canDelete)
                <flux:button
                    wire:click.stop="promptDeleteNode({{ json_encode($path) }})"
                    color="danger"
                    size="sm"
                    title="Knoten löschen"
                    :disabled="!$editable"
                >
                    <flux:icon.trash class="w-4 h-4 cursor-pointer"/>
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Children --}}
    @if (!empty($node['children']) && is_array($node['children']))
        <ul class="pl-6 mt-1 space-y-1">
            @foreach ($node['children'] as $childIndex => $childNode)
                @include('livewire.partials.tree-node', [
                    'node'     => $childNode,
                    'path'     => array_merge($path, [$childIndex]),
                    'editable' => $editable,
                ])
            @endforeach
        </ul>
    @endif

    {{-- Dropzone after node --}}
    <div data-dropzone data-pos="after" class="h-2 mb-3"></div>
</li>
