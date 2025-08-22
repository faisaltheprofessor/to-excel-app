{{-- Full-height, page scroll locked; only tree scrolls --}}
<div class="h-screen overflow-hidden flex flex-col">
    {{-- Header (title + info) --}}
    <div class="p-6 pb-2 flex flex-col gap-1 shrink-0">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.blur="title"
                placeholder="Titel des Baums"
                class="flex-1"
            />
            <div class="text-xs text-zinc-500">ID: {{ $treeId }}</div>
        </div>
        {{-- Title error (Windows rules / CI-unique) --}}
        @error('title')
            <div class="text-sm text-red-600">{{ $message }}</div>
        @enderror
    </div>

    {{-- Add form (frozen at top) --}}
    <div class="px-6 pb-4 shrink-0">
        <form class="flex flex-col gap-2" wire:submit.prevent="addNode">
            <div class="flex items-center gap-4">
                {{-- focus target --}}
                <flux:input id="new-node-input" wire:model.live="newNodeName" placeholder="Neuer Knotenname" class="flex-1"/>
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
    </div>

    {{-- Tree area (only this scrolls) --}}
    <div class="px-6 pb-24 grow min-h-0">
        <flux:card class="h-full overflow-auto" data-tree-root>
            <div class="pr-2">
                <ul class="space-y-1 pb-28">
                    @foreach ($tree as $index => $node)
                        @include('livewire.partials.tree-node', ['node' => $node, 'path' => [$index]])
                    @endforeach
                </ul>
            </div>
        </flux:card>
    </div>

    @error('generate')
    <div class="px-6 text-sm text-red-600">{{ $message }}</div>
    @enderror

    @if($generatedJson)
        <pre class="mx-6 mt-2 p-4 bg-gray-100 rounded text-sm overflow-auto max-h-72">
{{ $generatedJson }}
        </pre>
    @endif

    {{-- fixed action bar --}}
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

    {{-- Hidden trigger for the confirm-move modal --}}
    <flux:modal.trigger name="confirm-move">
        <button type="button" id="open-confirm-move" class="hidden"></button>
    </flux:modal.trigger>

    {{-- CONFIRMATION MODAL --}}
    <flux:modal name="confirm-move" class="min-w-[32rem]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Verschiebung bestätigen</flux:heading>

                @if ($pendingSameParent && in_array($pendingPosition, ['before','after']))
                    <flux:text class="mt-2 space-y-2">
                        <p>
                            Soll der Knoten <span class="font-semibold">innerhalb</span> von
                            <span class="font-semibold">{{ $pendingWithinParentName }}</span>
                            von <span class="font-semibold">Position {{ $pendingFromIndex + 1 }}</span>
                            nach <span class="font-semibold">Position {{ $pendingToIndex + 1 }}</span> verschoben werden?
                        </p>
                    </flux:text>
                @else
                    <flux:text class="mt-2 space-y-2">
                        <p>Sie sind dabei, diesen Knoten zu verschieben.</p>
                        <div class="text-sm space-y-1">
                            <div><span class="font-medium">Alter Pfad (Elternknoten):</span> {{ $pendingOldParentPathStr }}</div>
                            <div><span class="font-medium">Neuer Pfad (Elternknoten):</span> {{ $pendingNewParentPathStr }}</div>
                            @if ($pendingPosition === 'into')
                                <div class="text-xs text-zinc-500">Wird als <em>letztes Kind</em> des neuen Elternknotens eingefügt.</div>
                            @endif
                        </div>
                    </flux:text>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <flux:spacer/>
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="primary" color="green" icon="arrow-right" wire:click="confirmPendingMove">
                        Verschieben
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    @script
    <script>
    (function () {
      // re-focus new node field after add
      window.addEventListener('focus-newnode', () => {
        const el = document.getElementById('new-node-input');
        if (el) { el.focus(); el.select?.(); }
      });

      // --- Drag & Drop helpers ---
      const RING        = 'ring-1 ring-offset-1 dark:ring-offset-0 ring-blue-400 dark:ring-blue-500 rounded';
      const RING_BEFORE = 'ring-1 ring-offset-1 dark:ring-offset-0 ring-emerald-400 dark:ring-emerald-500 rounded';
      const RING_AFTER  = 'ring-1 ring-offset-1 dark:ring-offset-0 ring-amber-400  dark:ring-amber-500  rounded';
      const tok = (s) => (s || '').trim().split(/\s+/);

      function lwInst(el){
        const host = el.closest('[wire\\:id]');
        if (!host || !window.Livewire) return null;
        return Livewire.find(host.getAttribute('wire:id'));
      }
      function clearHighlights() {
        document.querySelectorAll('[data-tree-node]').forEach(x => x.classList.remove(...tok(RING), ...tok(RING_BEFORE), ...tok(RING_AFTER)));
        document.querySelectorAll('[data-dropzone]').forEach(z => z.classList.remove(...tok(RING_BEFORE), ...tok(RING_AFTER), ...tok(RING)));
      }
      function posFromRow(event, rowEl) {
        const rect = rowEl.getBoundingClientRect();
        const y = event.clientY - rect.top;
        const t = rect.height;
        if (y < t * 0.33) return 'before';
        if (y > t * 0.66) return 'after';
        return 'into';
      }

      document.addEventListener('dragstart', (e) => {
        const li = e.target.closest('[data-tree-node]');
        if (!li) return;
        if (e.target.tagName === 'INPUT' || e.target.isContentEditable) { e.preventDefault(); return; }
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.dropEffect = 'move';
        e.dataTransfer.setData('text/plain', li.dataset.path || '[]');
      });

      document.addEventListener('dragover', (e) => {
        const row = e.target.closest('[data-tree-node] [data-dropzone][data-pos="into"]');
        const zone = e.target.closest('[data-dropzone]');
        if (!row && !zone) return;

        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        if (row) {
          const pos = posFromRow(e, row);
          row.classList.remove(...tok(RING), ...tok(RING_BEFORE), ...tok(RING_AFTER));
          if (pos === 'before') row.classList.add(...tok(RING_BEFORE));
          else if (pos === 'after') row.classList.add(...tok(RING_AFTER));
          else row.classList.add(...tok(RING));
        }
      });

      document.addEventListener('dragenter', (e) => {
        const z = e.target.closest('[data-dropzone]');
        const row = e.target.closest('[data-tree-node] [data-dropzone][data-pos="into"]');
        if (z && z.getAttribute('data-pos') !== 'into') {
          const pos = z.getAttribute('data-pos');
          z.classList.add(...tok(pos==='before' ? RING_BEFORE : RING_AFTER));
        } else if (row) {
          row.classList.add(...tok(RING));
        }
      });
      document.addEventListener('dragleave', (e) => {
        const z = e.target.closest('[data-dropzone]');
        const row = e.target.closest('[data-tree-node] [data-dropzone][data-pos="into"]');
        if (z && z.getAttribute('data-pos') !== 'into') {
          z.classList.remove(...tok(RING_BEFORE), ...tok(RING_AFTER));
        } else if (row) {
          row.classList.remove(...tok(RING), ...tok(RING_BEFORE), ...tok(RING_AFTER));
        }
      });

      document.addEventListener('drop', async (e) => {
        let pos = 'into';
        let hostNode = null;

        const explicitZone = e.target.closest('[data-dropzone]');
        const row = e.target.closest('[data-tree-node] [data-dropzone][data-pos="into"]');

        if (explicitZone && explicitZone.getAttribute('data-pos') !== 'into') {
          pos = explicitZone.getAttribute('data-pos') || 'into';
          hostNode = explicitZone.closest('[data-tree-node]');
        } else if (row) {
          pos = posFromRow(e, row);
          hostNode = row.closest('[data-tree-node]');
        } else {
          return;
        }

        e.preventDefault();
        clearHighlights();

        const inst = lwInst(hostNode);
        if (!inst) return;

        let fromPath = [];
        try { fromPath = JSON.parse(e.dataTransfer.getData('text/plain') || '[]'); } catch {}
        const toPath = JSON.parse(hostNode.dataset.path || '[]');

        await inst.call('preparePendingMove', fromPath, toPath, pos);

        const opener = document.getElementById('open-confirm-move');
        if (opener) opener.click();
      });

      // page scroll lock (only tree scrolls)
      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');

      // excel download
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
