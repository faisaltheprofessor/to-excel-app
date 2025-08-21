@php
    $node = is_array($node) ? $node : [];
    $nodeKey = implode('-', $path);
    $isEditingName = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'name';
    $isEditingApp  = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'appName';
    $canDelete     = $node['deletable'] ?? false;
    $isSelected    = isset($selectedNodePath) && $selectedNodePath === $path;

    $level = count($path);

    /**
     * Per-level color set:
     * [0] border color
     * [1] icon color (light + dark in one string)
     * [2] selected bg (light)
     * [3] selected bg (dark)
     * [4] selected left bar bg (light + dark in one string)
     */
    $colorPalette = [
        ['border-red-300',    'text-red-500 dark:text-red-300',       'bg-red-100',    'dark:bg-red-900',    'bg-red-400 dark:bg-red-500'],
        ['border-orange-300', 'text-orange-500 dark:text-orange-300', 'bg-orange-100', 'dark:bg-orange-900', 'bg-orange-400 dark:bg-orange-500'],
        ['border-amber-300',  'text-amber-500 dark:text-amber-300',   'bg-amber-100',  'dark:bg-amber-900',  'bg-amber-400 dark:bg-amber-500'],
        ['border-lime-300',   'text-lime-600 dark:text-lime-300',     'bg-lime-100',   'dark:bg-lime-900',   'bg-lime-400 dark:bg-lime-500'],
        ['border-emerald-300','text-emerald-500 dark:text-emerald-300','bg-emerald-100','dark:bg-emerald-900','bg-emerald-400 dark:bg-emerald-500'],
        ['border-cyan-300',   'text-cyan-500 dark:text-cyan-300',     'bg-cyan-100',   'dark:bg-cyan-900',   'bg-cyan-400 dark:bg-cyan-500'],
        ['border-blue-300',   'text-blue-500 dark:text-blue-300',     'bg-blue-100',   'dark:bg-blue-900',   'bg-blue-400 dark:bg-blue-500'],
        ['border-indigo-300', 'text-indigo-500 dark:text-indigo-300', 'bg-indigo-100', 'dark:bg-indigo-900', 'bg-indigo-400 dark:bg-indigo-500'],
        ['border-violet-300', 'text-violet-500 dark:text-violet-300', 'bg-violet-100', 'dark:bg-violet-900', 'bg-violet-400 dark:bg-violet-500'],
        ['border-pink-300',   'text-pink-500 dark:text-pink-300',     'bg-pink-100',   'dark:bg-pink-900',   'bg-pink-400 dark:bg-pink-500'],
        ['border-slate-300',  'text-slate-500 dark:text-slate-300',   'bg-slate-100',  'dark:bg-slate-800',  'bg-slate-400 dark:bg-slate-500'],
    ];

    $colors       = $colorPalette[$level % count($colorPalette)];
    $borderClass  = $colors[0];
    $iconClass    = $colors[1];
    $bgSelected   = $colors[2] . ' ' . $colors[3];
    $leftBarClass = $colors[4];
@endphp

<li class="relative pl-4 border-l-2 {{ $borderClass }}" wire:key="node-{{ $nodeKey }}">
    {{-- subtle left bar highlight on selection (on top of the per-level border) --}}
    @if ($isSelected)
        <span class="pointer-events-none absolute -left-[1px] top-0 bottom-0 w-1 rounded-full {{ $leftBarClass }}"></span>
    @endif

    <div
        wire:click.prevent="selectNode({{ json_encode($path) }})"
        class="flex items-center gap-2 cursor-pointer
               {{ $isSelected ? $bgSelected.' font-semibold' : 'hover:bg-gray-200 dark:hover:bg-gray-600' }}
               rounded px-2 py-1"
    >
        {{-- folder icon color matches the line/level color --}}
        <flux:icon.folder class="w-5 h-5 {{ $iconClass }}" />

        {{-- NAME --}}
        <div class="flex items-center gap-1">
            @if ($isEditingName)
                <div class="relative">
                    <input
                        type="text"
                        class="pl-1 pr-10 py-0.5 border rounded text-sm w-56"
                        wire:key="edit-name-{{ $nodeKey }}"
                        wire:model.live="editValue"
                        wire:keydown.enter.stop.prevent="saveInlineEdit($event.target.value)"
                        wire:keydown.escape.stop.prevent="cancelInlineEdit"
                        autofocus
                    />
                    <div class="absolute inset-y-0 right-1 flex items-center gap-1">
                        <button type="button" wire:click.stop="saveInlineEdit" class="p-0.5">
                            <flux:icon.check class="w-5 h-5 text-green-600 dark:text-green-400 cursor-pointer stroke-[2.5]" />
                        </button>
                        <button type="button" wire:click.stop="cancelInlineEdit" class="p-0.5">
                            <flux:icon.x-mark class="w-5 h-5 text-red-600 dark:text-red-400 cursor-pointer stroke-[2.5]" />
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

        {{-- APP-NAME --}}
        <div class="flex items-center gap-1">
            <span class="text-xs text-gray-500 dark:text-gray-100">Nscale:</span>
            @if ($isEditingApp)
                <div class="relative">
                    <input
                        type="text"
                        class="pl-1 pr-10 py-0.5 border rounded text-xs w-48"
                        wire:key="edit-app-{{ $nodeKey }}"
                        wire:model.live="editValue"
                        wire:keydown.enter.stop.prevent="saveInlineEdit($event.target.value)"
                        wire:keydown.escape.stop.prevent="cancelInlineEdit"
                        autofocus
                    />
                    <div class="absolute inset-y-0 right-1 flex items-center gap-1">
                        <button type="button" wire:click.stop="saveInlineEdit" class="p-0.5">
                            <flux:icon.check class="w-5 h-5 text-green-600 dark:text-green-400 cursor-pointer stroke-[2.5]" />
                        </button>
                        <button type="button" wire:click.stop="cancelInlineEdit" class="p-0.5">
                            <flux:icon.x-mark class="w-5 h-5 text-red-600 dark:text-red-400 cursor-pointer stroke-[2.5]" />
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

        {{-- DELETE --}}
        @if ($canDelete)
            <flux:button
                wire:click.stop="removeNode({{ json_encode($path) }})"
                color="danger"
                size="sm"
                class="ml-auto"
            >
                <flux:icon.trash class="w-4 h-4" />
            </flux:button>
        @endif
    </div>

    @if (!empty($node['children']) && is_array($node['children']))
        <ul class="pl-6 mt-1 space-y-1">
            @foreach ($node['children'] as $childIndex => $childNode)
                @include('livewire.partials.tree-node', [
                    'node' => $childNode,
                    'path' => array_merge($path, [$childIndex])
                ])
            @endforeach
        </ul>
    @endif
</li>
