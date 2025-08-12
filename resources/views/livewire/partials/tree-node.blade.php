<li class="pl-4 border-l-2 border-gray-300 relative">
    <div
        wire:click.prevent="selectNode({{ json_encode($path) }})"
        class="flex items-center gap-2 cursor-pointer
            {{ $selectedNodePath === $path ? 'bg-blue-100 font-semibold' : '' }}
            hover:bg-blue-50 rounded px-2 py-1"
    >
        <flux:icon.folder class="w-5 h-5 text-gray-500" />

        <span>{{ $node['name'] }}</span>

        <flux:button
            wire:click.stop="removeNode({{ json_encode($path) }})"
            color="danger"
            size="sm"
            class="ml-auto"
            title="Remove node"
            aria-label="Remove node"
        >
            <flux:icon.trash class="w-4 h-4" />
        </flux:button>
    </div>

    @if (!empty($node['children']))
        <ul class="pl-6 mt-1 space-y-1">
            @foreach ($node['children'] as $childIndex => $childNode)
                @include('livewire.partials.tree-node', ['node' => $childNode, 'path' => array_merge($path, [$childIndex])])
            @endforeach
        </ul>
    @endif
</li>
