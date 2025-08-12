<div class="relative pb-24"> {{-- Abstand nach unten, damit Buttons nicht überlappen --}}
    <div class="mb-4 flex items-center gap-4">
        <flux:input wire:model.defer="newNodeName" placeholder="Neuer Knotenname" class="flex-1" />
        <flux:input wire:model.defer="newAppName"  placeholder="Name im Nscale (optional)" class="flex-1" />
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" wire:model="addWithStructure" class="form-checkbox" />
            mit Ablagen
        </label>
        <flux:button wire:click="addNode" color="primary">Hinzufügen</flux:button>
    </div>

    {{-- SCROLLBEREICH FÜR BAUM --}}
    <div class="overflow-auto pr-2" style="max-height: calc(100vh - 210px);">
        <ul class="space-y-1">
            @foreach ($tree as $index => $node)
                @include('livewire.partials.tree-node', ['node' => $node, 'path' => [$index]])
            @endforeach
        </ul>
    </div>

    @error('generate')
        <div class="mt-3 text-sm text-red-600">{{ $message }}</div>
    @enderror

    @if($generatedJson)
        <pre class="mt-4 p-4 bg-gray-100 rounded text-sm overflow-auto" style="max-height: 300px;">{{ $generatedJson }}</pre>
    @endif

    {{-- FESTE BUTTON-LEISTE UNTEN --}}
    <div class="fixed inset-x-0 bottom-4 flex justify-end px-4 pointer-events-none">
        <div class="pointer-events-auto flex gap-2">
            <flux:button wire:click="generateJson" color="secondary">JSON erzeugen</flux:button>
            <flux:button wire:click="generateExcel" variant="primary" color="green" icon="sheet">Excel erzeugen</flux:button>
        </div>
    </div>

    @script
    <script>
        window.addEventListener('excel-ready', event => {
            const filename = event.detail.filename;
            if (!filename) return;
            const url = '{{ route("download-excel", ":filename") }}'.replace(':filename', filename);
            const newTab = window.open('', '_blank');
            if (newTab) { newTab.location.href = url; setTimeout(() => { newTab.close(); }, 500); }
            else { window.location.href = url; }
        });
    </script>
    @endscript
</div>
