<div class="relative pb-24">
    {{-- Kopfzeile: Titel (Autosave) + Neu --}}
    <div class="mb-4 flex items-center gap-3">
        <flux:input
            wire:model.debounce.600ms="title"
            placeholder="Titel des Baums"
            class="flex-1"
            :disabled="! $treeId"
        />

        {{-- Öffnet das „Neu“-Modal --}}
        <flux:modal.trigger name="new-tree">
            <flux:button variant="primary">Neu</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Eingabe: Knoten hinzufügen --}}
    <div class="mb-4 flex items-center gap-4">
        <flux:input wire:model.defer="newNodeName" placeholder="Neuer Knotenname" class="flex-1" />
        <flux:input wire:model.defer="newAppName"  placeholder="App-Name (optional)" class="flex-1" />

        <label class="flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" wire:model="addWithStructure" class="form-checkbox" />
            mit Ablagen
        </label>

        <flux:button wire:click="addNode" color="primary" :disabled="! $treeId">
            Hinzufügen
        </flux:button>
    </div>

    @unless($treeId)
        <div class="mb-3 text-sm text-amber-600">
            Bitte zuerst „Neu“ klicken, einen Titel vergeben und speichern.
        </div>
    @endunless

    {{-- Scrollbarer Baum --}}
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
        <pre class="mt-4 p-4 bg-gray-100 rounded text-sm overflow-auto" style="max-height: 300px;">
{{ $generatedJson }}
        </pre>
    @endif

    {{-- Feste Aktionsleiste unten (kleiner Bodenabstand) --}}
    <div class="fixed inset-x-0 bottom-4 flex justify-end px-4 pointer-events-none">
        <div class="pointer-events-auto flex gap-2">
            <flux:button wire:click="generateJson" color="secondary" :disabled="! $treeId">
                JSON erzeugen
            </flux:button>

            <flux:button wire:click="generateExcel" variant="primary" color="green" icon="sheet" :disabled="! $treeId">
                Excel erzeugen
            </flux:button>
        </div>
    </div>

    {{-- „Neu“-Modal via Flux --}}
    <flux:modal name="new-tree" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Neuen Baum anlegen</flux:heading>
                <flux:text class="mt-2">
                    Vergib einen Titel. Der Baum wird gespeichert und spätere Änderungen automatisch übernommen.
                </flux:text>
            </div>

            <flux:field>
                <flux:label>Titel</flux:label>
                <flux:input wire:model.defer="title" placeholder="z. B. DigitaleAkte – Abteilung X" />
                @error('title') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button variant="primary" wire:click="createNewTree">Erstellen</flux:button>
            </div>
        </div>
    </flux:modal>

    @script
    <script>
        window.addEventListener('autosaved', () => {
            // Optional: kleines Autosave-Feedback
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
