<div>
    <div class="mb-4 flex items-center gap-4">
    <flux:input
        wire:model.defer="newNodeName"
        placeholder="New node name"
        class="flex-1"
    />
    <label class="flex items-center gap-1 cursor-pointer select-none">
        <input
            type="checkbox"
            wire:model="addWithStructure"
            class="form-checkbox"
        />
        mit Ablagen
    </label>
    <flux:button wire:click="addNode" color="primary">
        Add
    </flux:button>



</div>
 <ul>
    @foreach ($tree as $index => $node)
        @include('livewire.partials.tree-node', ['node' => $node, 'path' => [$index]])
    @endforeach
</ul>

    <div class="flex justify-end">
        <flux:button wire:click="generateJson" color="secondary" class="ml-2" hidden>
    Generate JSON
</flux:button>
    <flux:button wire:click="generateExcel" variant="primary" color="green" icon="sheet">
    Generate Excel
</flux:button>
    </div>

    @if($generatedJson)
    <pre class="mt-4 p-4 bg-gray-100 rounded text-sm overflow-auto" style="max-height: 300px;">
        {{ $generatedJson }}
    </pre>


@endif

    @script
<script>
      window.addEventListener('excel-ready', event => {
    const filename = event.detail.filename;
    if (!filename) {
        console.error('Filename missing in event detail');
        return;
    }
    const url = '{{ route("download-excel", ":filename") }}'.replace(':filename', filename);

    const newTab = window.open('', '_blank');

    if (newTab) {
        newTab.location.href = url;

        setTimeout(() => {
            newTab.close();
        }, 500);
    } else {
        console.error('Popup blocked or failed to open new tab');
        window.location.href = url;
    }
});
</script>

    @endscript

</div>






