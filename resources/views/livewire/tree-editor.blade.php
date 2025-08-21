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
    <form class="flex flex-col gap-2" wire:submit.prevent="addNode">
        <div class="flex items-center gap-4">
            <flux:input wire:model.live="newNodeName" placeholder="Neuer Knotenname" class="flex-1"/>
            <flux:input wire:model.live="newAppName"  placeholder="Name im Nscale (optional)" class="flex-1"/>

            <label class="flex items-center gap-1 cursor-pointer select-none">
                <input type="checkbox" wire:model="addWithStructure" class="form-checkbox"/>
                mit Ablagen
            </label>

            <flux:button type="submit" variant="primary" color="green" class="cursor-pointer">
                Hinzufügen
            </flux:button>
        </div>

        <div class="flex items-start gap-4 text-sm">
            <div class="flex-1 text-red-600">@error('newNodeName') {{ $message }} @enderror</div>
            <div class="flex-1 text-red-600">@error('newAppName')  {{ $message }} @enderror</div>
        </div>
    </form>

    {{-- Baum --}}
    <flux:card class="overflow-auto h-auto max-h-200" data-tree-root>
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
        (function () {
            const OVER_CLASS = 'ring-1 ring-offset-1 dark:ring-offset-0 ring-blue-400 dark:ring-blue-500 rounded';

            // Find the Livewire component instance for a given element by walking up to the nearest [wire:id]
            function findLivewireInstance(el) {
                const host = el.closest('[wire\\:id]');
                if (!host || !window.Livewire) return null;
                const id = host.getAttribute('wire:id');
                return window.Livewire.find ? window.Livewire.find(id) : null;
            }

            // DRAG START
            document.addEventListener('dragstart', (e) => {
                const li = e.target.closest('[data-tree-node]');
                if (!li) return;
                if (e.target.tagName === 'INPUT' || e.target.isContentEditable) { e.preventDefault(); return; }
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.dropEffect = 'move';
                e.dataTransfer.setData('text/plain', li.dataset.path || '[]');
            });

            // Allow drops
            document.addEventListener('dragover', (e) => {
                if (e.target.closest('[data-tree-node]')) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                }
            });

            // Hover ring
            document.addEventListener('dragenter', (e) => {
                const li = e.target.closest('[data-tree-node]');
                if (li) li.classList.add(...OVER_CLASS.split(' '));
            });
            document.addEventListener('dragleave', (e) => {
                const li = e.target.closest('[data-tree-node]');
                if (li) li.classList.remove(...OVER_CLASS.split(' '));
            });

            // DROP → Livewire call
            document.addEventListener('drop', (e) => {
                const li = e.target.closest('[data-tree-node]');
                if (!li) return;
                e.preventDefault();
                li.classList.remove(...OVER_CLASS.split(' '));

                let fromPath = [];
                try { fromPath = JSON.parse(e.dataTransfer.getData('text/plain') || '[]'); } catch {}
                const toPath = JSON.parse(li.dataset.path || '[]');

                const inst = findLivewireInstance(li);
                if (!inst) return; // Livewire not found near this node

                inst.call('moveNode', fromPath, toPath);
            });
        })();
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
