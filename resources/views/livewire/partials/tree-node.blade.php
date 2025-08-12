@php
    $node = is_array($node) ? $node : [];
    $nodeKey = implode('-', $path);
    $isEditingName = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'name';
    $isEditingApp  = isset($editNodePath, $editField) && $editNodePath === $path && $editField === 'appName';
    $canDelete     = $node['deletable'] ?? false;
    $isSelected    = isset($selectedNodePath) && $selectedNodePath === $path;
@endphp

<li class="pl-4 border-l-2 border-gray-300 relative" wire:key="node-{{ $nodeKey }}">
    <div
        wire:click.prevent="selectNode({{ json_encode($path) }})"
        class="flex items-center gap-2 cursor-pointer {{ $isSelected ? 'bg-blue-100 font-semibold' : '' }} hover:bg-blue-50 rounded px-2 py-1"
    >
        <flux:icon.folder class="w-5 h-5 text-gray-500" />

        {{-- NAME (Doppelklick zum Bearbeiten) --}}
        <div class="flex items-center gap-1">
            @if ($isEditingName)
                <input
                    type="text"
                    class="px-1 py-0.5 border rounded text-sm"
                    wire:key="edit-name-{{ $nodeKey }}"
                    wire:model.defer="editValue"
                    wire:keydown.enter="saveInlineEdit"
                    wire:keydown.escape="cancelInlineEdit"
                    wire:blur="saveInlineEdit"
                    autofocus
                />
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

        {{-- APP-NAME (Doppelklick zum Bearbeiten) --}}
        <div class="flex items-center gap-1">
            <span class="text-xs text-gray-500">Nscale:</span>
            @if ($isEditingApp)
                <input
                    type="text"
                    class="px-1 py-0.5 border rounded text-xs"
                    wire:key="edit-app-{{ $nodeKey }}"
                    wire:model.defer="editValue"
                    wire:keydown.enter="saveInlineEdit"
                    wire:keydown.escape="cancelInlineEdit"
                    wire:blur="saveInlineEdit"
                    autofocus
                />
            @else
                <span
                    class="text-xs text-gray-700 italic cursor-text"
                    title="Doppelklick zum Bearbeiten"
                    wire:dblclick.stop="startInlineEdit({{ json_encode($path) }}, 'appName')"
                >
                    {{ $node['appName'] ?? ($node['name'] ?? '') }}
                </span>
            @endif
        </div>

        {{-- LÖSCHEN (nur wenn löschbar) --}}
        @if ($canDelete)
            <flux:button
                wire:click.stop="removeNode({{ json_encode($path) }})"
                color="danger"
                size="sm"
                class="ml-auto"
                title="Knoten entfernen"
                aria-label="Knoten entfernen"
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
