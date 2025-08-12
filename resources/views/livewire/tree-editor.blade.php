<div class="relative pb-24 p-6 space-y-4">
    {{-- Titel (autosave) --}}
    <div class="flex items-center gap-3">
        <flux:input
            wire:model.debounce.600ms="title"
            placeholder="Titel des Baums"
            class="flex-1"
        />
        <div class="text-xs text-zinc-500">ID: {{ $treeId }}</div>
    </div>

    {{-- Knoten hinzufügen --}}
    <div class="flex items-center gap-4">
        <flux:input wire:model.defer="newNodeName" placeholder="Neuer Knotenname" class="flex-1" />
        <flux:input wire:model.defer="newAppName"  placeholder="Name im Nscale (optional)" class="flex-1" />
        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" wire:model="addWithStructure" class="form-checkbox" />
            mit Ablagen
        </label>
        <flux:button wire:click="addNode" variant="primary" color="green" class="cursor-pointer">Hinzufügen</flux:button>
    </div>

    {{-- Baum (scrollbar) --}}
    <flux:card class="overflow-auto h-auto max-h-200">
        <div class="overflow-auto pr-2">
        <ul class="space-y-1">
            @foreach ($tree as $index => $node)
                @include('livewire.partials.tree-node', ['node' => $node, 'path' => [$index]])
            @endforeach
        </ul>
    </div>
    </flux:card>

    @error('generate')
        <div class="text-sm text-red-600">{{ $message }}</div>
    @enderror

    @if($generatedJson)
        <pre class="mt-2 p-4 bg-gray-100 rounded text-sm overflow-auto" style="max-height: 300px;">
{{ $generatedJson }}
        </pre>
    @endif

    {{-- feste Aktionsleiste unten --}}
    <div class="fixed inset-x-0 bottom-4 flex justify-end px-6 pointer-events-none">
        <div class="pointer-events-auto flex gap-2">
            <flux:button wire:click="generateJson" color="secondary">JSON erzeugen</flux:button>
            <flux:button wire:click="generateExcel" variant="primary" color="green" icon="sheet">Excel erzeugen</flux:button>
        </div>
    </div>

    @script
    <script>
        window.addEventListener('autosaved', () => {
            // Optional: visueller Ping
        });
        window.addEventListener('excel-ready', event => {
            const filename = event.detail.filename;
            if (!filename) return;
            const url = '{{ route("download-excel", ":filename") }}'.replace(':filename', filename);
            const t = window.open('', '_blank');
            if (t) { t.location.href = url; setTimeout(() => t.close(), 500); } else { location.href = url; }
        });
    </script>
    @endscript
</div>
