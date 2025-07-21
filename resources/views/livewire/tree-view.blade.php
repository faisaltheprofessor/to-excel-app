<div class="p-4 max-w-xl mx-auto">

    @if (empty($tree))
        <p class="text-gray-500 mb-4">Der Baum ist leer. Verwenden Sie die Schaltfläche unten, um Ihr erstes Element hinzuzufügen.</p>
    @endif

    <ul class="border-l-2 border-gray-300 pl-4">
        @foreach ($tree as $index => $node)
            @livewire('tree-node', ['node' => $node, 'path' => [$index]], key(json_encode([$index, $node['title']])))
        @endforeach
    </ul>

    <button
        wire:click="addChild"
        class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
    >
       hinzuzufügen
    </button>
</div>

