{{-- resources/views/livewire/tree-editor.blade.php --}}
<div class="w-full">
    {{-- Main layout: left editor + (optional) right minimap --}}
    <div
        class="mx-auto h-screen overflow-hidden flex gap-4 px-4 relative"
        x-data="{ minimapOpen: true }"
    >
        {{-- LEFT: main editor --}}
        <div class="flex-1 flex flex-col min-h-0">
            {{-- Header accordions --}}
            <div class="p-6 pb-2 flex flex-col gap-3 shrink-0">

                {{-- 1) Aktionen --}}
                <flux:accordion class="rounded-lg border p-2">
                    <flux:accordion.item expanded>
                        <flux:accordion.heading>Aktionen</flux:accordion.heading>
                        <flux:accordion.content>
                            <div class="flex flex-wrap items-center gap-2">
                                {{-- Bearbeiten / Bearbeitung beenden --}}
                                @if(!($editable ?? false))
                                    <flux:button wire:click="toggleEditable" icon="pencil" class="cursor-pointer" size="sm">
                                        Bearbeiten
                                    </flux:button>
                                @else
                                    <flux:button wire:click="toggleEditable" icon="pencil"  class="cursor-pointer" size="sm">
                                        Bearbeitung beenden
                                    </flux:button>
                                @endif

                                {{-- Abschließen --}}
                                <flux:button wire:click="finalizeStructure" variant="primary" color="amber" icon="lock-closed" class="cursor-pointer" size="sm">
                                    Abschließen
                                </flux:button>

                                {{-- Neue Version --}}
                                <flux:button wire:click="createNewVersion" variant="primary" color="indigo" icon="plus" class="cursor-pointer" size="sm">
                                    Neue Version
                                </flux:button>

                                {{-- Excel erzeugen --}}
                                <flux:modal.trigger name="excel-options">
                                    <flux:button variant="primary" color="green" icon="sheet" class="cursor-pointer" size="sm">
                                        Excel erzeugen
                                    </flux:button>
                                </flux:modal.trigger>

                                {{-- Löschen --}}
                                <flux:modal.trigger name="delete-structure">
                                    <flux:button variant="danger" icon="trash" class="cursor-pointer" size="sm">
                                        Löschen
                                    </flux:button>
                                </flux:modal.trigger>
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>

                {{-- 2) Titel --}}
                <flux:accordion class="rounded-lg border p-2">
                    <flux:accordion.item expanded>
                        <flux:accordion.heading>Titel</flux:accordion.heading>
                        <flux:accordion.content>
                            <div class="flex items-center gap-3">
                                <flux:input
                                    id="tree-title-input"
                                    wire:model.blur="title"
                                    placeholder="Titel des Baums"
                                    class="flex-1 text-lg py-2"
                                    readonly
                                    data-title
                                />
                                <div class="text-xs text-zinc-500 select-none">ID: {{ $treeId }}</div>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                @error('title')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                                <span class="text-xs text-zinc-500 ml-auto">Doppelklick auf den Titel zum Bearbeiten</span>
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>

                {{-- 3) Knoten hinzufügen --}}
                <flux:accordion class="rounded-lg border p-2">
                    <flux:accordion.item expanded>
                        <flux:accordion.heading>Knoten Hinzufügen</flux:accordion.heading>
                        <flux:accordion.content>
                            <form class="flex flex-col gap-2" wire:submit.prevent="addNode">
                                <div class="flex flex-wrap items-center gap-4">
                                    <flux:input id="new-node-input"
                                                wire:model.live="newNodeName"
                                                placeholder="Neuer Knotenname"
                                                class="flex-1 min-w-[10rem]"
                                                :disabled="!$editable"
                                    />
                                    <flux:input
                                        wire:model.live="newAppName"
                                        placeholder="Name im Nscale (optional)"
                                        class="flex-1 min-w-[10rem]"
                                        :disabled="!$editable"
                                    />

                                    <label class="flex items-center gap-1 cursor-pointer select-none">
                                        <input type="checkbox" wire:model="addWithStructure" class="form-checkbox" @disabled(!$editable) />
                                        mit Ablagen
                                    </label>

                                    <flux:button type="submit" variant="primary" color="green" class="cursor-pointer" size="sm" :disabled="!$editable">
                                        Hinzufügen
                                    </flux:button>
                                </div>

                                <div class="flex flex-wrap items-start gap-4 text-sm">
                                    <div class="flex-1 text-red-600">@error('newNodeName') {{ $message }} @enderror</div>
                                    <div class="flex-1 text-red-600">@error('newAppName')  {{ $message }} @enderror</div>
                                </div>
                            </form>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>

                {{-- 4) Struktur / Baum --}}
                <flux:accordion class="rounded-lg border p-2">
                    <flux:accordion.item expanded>
                        <flux:accordion.heading>Struktur</flux:accordion.heading>
                        <flux:accordion.content>
                            {{-- Main tree area (controls drag & drop availability) --}}
                            <flux:card
                                class="h-[48vh] overflow-auto"
                                data-tree-root
                                data-editable="{{ ($editable ?? false) ? 1 : 0 }}"
                            >
                                <div class="pr-2">
                                    <ul class="space-y-1 pb-28">
                                        @foreach ($tree as $index => $node)
                                            @include('livewire.partials.tree-node', [
                                                'node'     => $node,
                                                'path'     => [$index],
                                                'editable' => ($editable ?? false),
                                            ])
                                        @endforeach
                                    </ul>
                                </div>
                            </flux:card>
                        </flux:accordion.content>
                    </flux:accordion.item>
                </flux:accordion>
            </div>

            {{-- Errors + JSON preview --}}
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
        </div>

        {{-- RIGHT: Minimap column (whole column collapsible) --}}
        <div
            class="hidden lg:flex flex-col h-screen pt-6 w-72 min-h-0"
            x-show="minimapOpen"
            x-transition
        >
            <flux:card class="flex-1 flex flex-col text-xs min-h-0 overflow-hidden">
                {{-- Minimap header --}}
                <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <span class="font-semibold text-sm">Baum-Übersicht</span>
                    <button
                        type="button"
                        class="text-[11px] text-gray-500 hover:text-gray-800 dark:text-gray-300"
                        @click.stop="minimapOpen = false"
                    >
                        schließen
                    </button>
                </div>

                {{-- Scrollable minimap content (vertical + horizontal) --}}
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto px-3 py-2 space-y-1">
                    @if(!empty($tree))
                        <ul class="space-y-1 min-w-max">
                            @foreach($tree as $index => $node)
                                @include('livewire.partials.tree-minimap-node', [
                                    'node'             => $node,
                                    'path'             => [$index],
                                    'selectedNodePath' => $selectedNodePath ?? null,
                                ])
                            @endforeach
                        </ul>
                    @else
                        <p class="text-[11px] text-gray-500">
                            Keine Knoten vorhanden.
                        </p>
                    @endif
                </div>
            </flux:card>
        </div>

        {{-- Reopen handle when minimap is collapsed --}}
        <button
            type="button"
            class="hidden lg:flex items-center justify-center absolute top-1/2 right-0 -translate-y-1/2 w-6 h-16 rounded-l-full
                   bg-gray-200 dark:bg-gray-700 shadow border border-gray-300 dark:border-gray-600"
            x-show="!minimapOpen"
            x-transition
            @click="minimapOpen = true"
            title="Minimap öffnen"
        >
            <flux:icon.chevron-left class="w-4 h-4 text-gray-700 dark:text-gray-100"/>
        </button>
    </div>

    @script
    <script>
    (function () {
      // Check edit mode for drag & drop
      function isTreeEditable() {
        const root = document.querySelector('[data-tree-root]');
        if (!root) return false;
        return root.dataset.editable === '1';
      }

      // Title inline edit (dblclick / blur / Enter / Esc)
      const titleEl = document.getElementById('tree-title-input');
      if (titleEl) {
        const ACTIVE_RING = 'ring-1 ring-blue-400 dark:ring-blue-500 rounded';
        const addRing = () => titleEl.classList.add(...ACTIVE_RING.split(' '));
        const removeRing = () => titleEl.classList.remove(...ACTIVE_RING.split(' '));

        titleEl.addEventListener('dblclick', () => {
          if (!titleEl.readOnly) return;
          titleEl.readOnly = false;
          addRing();
          titleEl.focus();
          titleEl.select?.();
        });

        const lockTitle = () => {
          removeRing();
          titleEl.readOnly = true;
        };

        titleEl.addEventListener('blur', lockTitle);
        titleEl.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            titleEl.blur();
          } else if (e.key === 'Escape') {
            e.preventDefault();
            lockTitle();
            titleEl.blur();
          }
        });
      }

      // Focus new node input on custom event
      window.addEventListener('focus-newnode', () => {
        const el = document.getElementById('new-node-input');
        if (el) { el.focus(); el.select?.(); }
      });

      // Drag & drop helpers (only when editable)
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
        if (!isTreeEditable()) return;
        const li = e.target.closest('[data-tree-node]');
        if (!li) return;
        if (e.target.tagName === 'INPUT' || e.target.isContentEditable) { e.preventDefault(); return; }
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.dropEffect = 'move';
        e.dataTransfer.setData('text/plain', li.dataset.path || '[]');
      });

      document.addEventListener('dragover', (e) => {
        if (!isTreeEditable()) return;
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
        if (!isTreeEditable()) return;
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
        if (!isTreeEditable()) return;
        const z = e.target.closest('[data-dropzone]');
        const row = e.target.closest('[data-tree-node] [data-dropzone][data-pos="into"]');
        if (z && z.getAttribute('data-pos') !== 'into') {
          z.classList.remove(...tok(RING_BEFORE), ...tok(RING_AFTER));
        } else if (row) {
          row.classList.remove(...tok(RING), ...tok(RING_BEFORE), ...tok(RING_AFTER));
        }
      });

      document.addEventListener('drop', async (e) => {
        if (!isTreeEditable()) return;

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

      // Lock page scroll; only the tree & minimap scroll
      document.documentElement.classList.add('overflow-hidden');
      document.body.classList.add('overflow-hidden');

      // Excel ready -> trigger download
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

      // Open delete node modal from Livewire
      window.addEventListener('open-delete-node', () => {
          const opener = document.getElementById('open-delete-node');
          if (opener) opener.click();
      });

      // Scroll main tree to selected node (from minimap or main tree)
      window.addEventListener('node-selected', event => {
          const path = event.detail.path || [];
          const root = document.querySelector('[data-tree-root]');
          if (!root) return;

          const all = root.querySelectorAll('[data-tree-node]');
          let target = null;

          for (const el of all) {
              try {
                  const p = JSON.parse(el.dataset.path || '[]');
                  if (JSON.stringify(p) === JSON.stringify(path)) {
                      target = el;
                      break;
                  }
              } catch (e) {}
          }

          if (!target) return;

          const rootRect = root.getBoundingClientRect();
          const targetRect = target.getBoundingClientRect();
          const offset = targetRect.top - rootRect.top;

          root.scrollTo({
              top: root.scrollTop + offset - root.clientHeight / 3,
              behavior: 'smooth',
          });
      });
    })();
    </script>
    @endscript
</div>
