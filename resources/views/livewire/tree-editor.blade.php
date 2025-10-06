{{-- resources/views/livewire/tree-editor.blade.php --}}
<div class="w-1/2 m:w-3/4 mx-auto h-screen overflow-hidden flex flex-col">
    {{-- Header: title (left) + actions (right) --}}
    <div class="p-6 pb-2 flex flex-col gap-2 shrink-0">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.blur="title"
                placeholder="Titel des Baums"
                class="flex-1"
            />

            <div class="flex items-center gap-2">
                <flux:modal.trigger name="excel-options">
                    <flux:button variant="primary" color="green" icon="sheet" class="cursor-pointer" size="sm">
                        Excel erzeugen
                    </flux:button>
                </flux:modal.trigger>

                <flux:modal.trigger name="delete-structure">
                    <flux:button variant="danger" icon="trash" class="cursor-pointer" size="sm">
                        Löschen
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="flex items-center justify-between">
            @error('title')
                <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <flux:separator class="mb-3" />
    {{-- Add form --}}
    <div class="px-6 pb-4 shrink-0">
        <form class="flex flex-col gap-2" wire:submit.prevent="addNode">
            <div class="flex items-center gap-4">
                <flux:input id="new-node-input" wire:model.live="newNodeName" placeholder="Neuer Knotenname" class="flex-1"/>
                <flux:input wire:model.live="newAppName"  placeholder="Name im Nscale (optional)" class="flex-1"/>

                <label class="flex items-center gap-1 cursor-pointer select-none">
                    <input type="checkbox" wire:model="addWithStructure" class="form-checkbox"/>
                    mit Ablagen
                </label>

                <flux:button type="submit" variant="primary" color="green" class="cursor-pointer" size="sm">
                    Hinzufügen
                </flux:button>
            </div>

            <div class="flex items-start gap-4 text-sm">
                <div class="flex-1 text-red-600">@error('newNodeName') {{ $message }} @enderror</div>
                <div class="flex-1 text-red-600">@error('newAppName')  {{ $message }} @enderror</div>
            </div>
        </form>
    </div>


    {{-- Tree --}}
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

    {{-- Hidden triggers --}}
    <flux:modal.trigger name="confirm-move">
        <button type="button" id="open-confirm-move" class="hidden"></button>
    </flux:modal.trigger>

    <flux:modal.trigger name="delete-node">
        <button type="button" id="open-delete-node" class="hidden"></button>
    </flux:modal.trigger>

    {{-- Modals --}}
    @include('livewire.partials.modal-confirm-move')
    @include('livewire.partials.modal-delete-node')
    @include('livewire.partials.modal-delete-structure')
    @include('livewire.partials.modal-excel-options')

    @script
    <script>
    (function () {
      window.addEventListener('focus-newnode', () => {
        const el = document.getElementById('new-node-input');
        if (el) { el.focus(); el.select?.(); }
      });

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

      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');

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

      window.addEventListener('open-delete-node', () => {
          const opener = document.getElementById('open-delete-node');
          if (opener) opener.click();
      });
    })();
    </script>
    @endscript
</div>
