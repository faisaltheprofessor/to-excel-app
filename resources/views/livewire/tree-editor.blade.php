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
    <div class="flex flex-col gap-2">
        <div class="flex items-center gap-4">
            {{-- CHANGED: .defer -> .live so updated* hooks fire on each keystroke --}}
            <flux:input wire:model.live="newNodeName" placeholder="Neuer Knotenname" class="flex-1"/>
            <flux:input wire:model.live="newAppName"  placeholder="Name im Nscale (optional)" class="flex-1"/>

            <label class="flex items-center gap-1 cursor-pointer select-none">
                <input type="checkbox" wire:model="addWithStructure" class="form-checkbox"/>
                mit Ablagen
            </label>

            <flux:button wire:click="addNode" variant="primary" color="green" class="cursor-pointer">
                Hinzufügen
            </flux:button>
        </div>

        {{-- (Optional) Inline validation messages --}}
        <div class="flex items-start gap-4 text-sm">
            <div class="flex-1 text-red-600">@error('newNodeName') {{ $message }} @enderror</div>
            <div class="flex-1 text-red-600">@error('newAppName')  {{ $message }} @enderror</div>
        </div>
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
            <flux:button wire:click="generateJson" color="secondary" hidden>JSON erzeugen</flux:button>
            <flux:modal.trigger name="delete-structure">
                <flux:button variant="danger" icon="trash" class="cursor-pointer">Löschen</flux:button>
            </flux:modal.trigger>
            <flux:button wire:click="generateExcel" variant="primary" color="green" icon="sheet" class="cursor-pointer">
                Excel erzeugen
            </flux:button>
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
            if (t) {
                t.location.href = url;
                setTimeout(() => t.close(), 500);
            } else {
                location.href = url;
            }
        });
    </script>
    @endscript

    <flux:modal name="delete-structure" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Struktur löschen?</flux:heading>
                <flux:text class="mt-2">
                    <p>Sie sind dabei, diese Struktur zu löschen.</p>
                    <p>Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer/>
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" wire:click="delete()">Struktur löschen</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
