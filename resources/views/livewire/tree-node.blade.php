<li class="mb-2">
    <div class="flex items-center justify-between group hover:bg-gray-100 px-2 py-1 rounded">
        <div class="flex items-center gap-2 cursor-pointer" wire:click="toggle">
            <svg
                class="w-4 h-4 text-gray-500 transition-transform duration-200"
                style="transform: rotate({{ $expanded ? 90 : 0 }}deg);"
                fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
                <path d="M9 5l7 7-7 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            @if ($editing)
                <input
                    type="text"
                    wire:model.defer="title"
                    wire:keydown.enter="saveTitle"
                    wire:keydown.escape="$set('editing', false)"
                    class="border px-1 rounded text-sm"
                    autofocus
                />
            @else
                <span wire:click.stop="startEditing" class="truncate">{{ $title }}</span>
            @endif
        </div>

        <div class="opacity-0 group-hover:opacity-100 transition flex gap-1">
            <button wire:click.stop="add" class="text-xs text-green-600 hover:underline">Add</button>
            <button wire:click.stop="remove" class="text-xs text-red-600 hover:underline">Remove</button>
        </div>
    </div>

    @if ($expanded && !empty($node['children']))
        <ul class="border-l-2 border-gray-200 pl-4 mt-1">
            @foreach ($node['children'] as $i => $child)
                @livewire('tree-node', [
                    'node' => $child,
                    'path' => array_merge($path, [$i])
                ], key(json_encode(array_merge($path, [$i]))))
            @endforeach
        </ul>
    @endif
</li>

