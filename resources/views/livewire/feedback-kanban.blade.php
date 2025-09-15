<div class="flex h-[calc(100vh-4rem)] overflow-hidden"
     x-data="kanbanDnd($wire)"
     x-init="init()">

    {{-- LEFT: Kanban (drag sources & drop targets) --}}
    <div class="transition-all duration-200 overflow-x-auto p-4"
         :class="selectedId ? 'w-1/2' : 'w-full'">

        <div class="flex gap-4 min-w-max">
            @foreach ($this->columns as $col)
                <div class="w-80 flex-shrink-0 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700
                            transition ring-0"
                     :class="over === '{{ $col['key'] }}' ? 'ring-2 ring-blue-400/60' : ''"
                     @dragover.prevent="over='{{ $col['key'] }}'"
                     @dragleave="over=null"
                     @drop.prevent="onDrop('{{ $col['key'] }}')">

                    <div class="px-4 py-3 flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700">
                        <div class="text-sm font-semibold">{{ $col['title'] }}</div>
                        <flux:button variant="subtle" size="xs" icon="ellipsis-horizontal" />
                    </div>

                    <div class="flex flex-col gap-2 p-2">
                        @foreach ($col['cards'] as $card)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800
                                        cursor-pointer hover:shadow transition"
                                 draggable="true"
                                 @dragstart="dragStart({ id: {{ $card['id'] }}, from: '{{ $col['key'] }}' })"
                                 @dragend="dragEnd()"
                                 wire:click="selectTicket({{ $card['id'] }})">

                                {{-- Type-colored header strip --}}
                                <div class="h-7 rounded-t-lg border-b {{ $card['typeClass'] }} px-2 flex items-center justify-between">
                                    <div class="text-[11px] uppercase tracking-wide opacity-80">
                                        {{ $card['typeLabel'] }}
                                    </div>

                                    @php
                                        $map = [
                                            'red' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                                            'yellow' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                                            'zinc' => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
                                        ];
                                        $label = [
                                            'urgent' => 'Dringend', 'high' => 'Hoch', 'normal' => 'Normal', 'low' => 'Niedrig'
                                        ][$card['priority']] ?? ucfirst($card['priority']);
                                    @endphp
                                    <span class="text-[10px] px-1.5 py-0.5 rounded {{ $map[$card['prioColor']] ?? $map['zinc'] }}">{{ $label }}</span>
                                </div>

                                {{-- Body --}}
                                <div class="p-3 space-y-1">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 line-clamp-2">
                                        {{ $card['title'] }}
                                    </div>
                                    @if(!empty($card['assignee']))
                                        <div class="text-xs text-zinc-600 dark:text-zinc-300">
                                            Zugewiesen: <span class="font-medium">{{ $card['assignee'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="px-2 py-2">
                        <flux:button variant="subtle" icon="plus" size="sm" class="w-full justify-start!">Neues Ticket (wip...)</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- RIGHT: Detail panel --}}
    <div class="w-1/2 border-l border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-y-auto"
         x-show="selectedId"
         x-transition>
        <div class="flex justify-between items-center p-3 border-b border-zinc-200 dark:border-zinc-700">
            <div class="text-sm font-semibold">
                @if($selectedFeedback)
                    Ticket #{{ $selectedFeedback->id }}
                @else
                    Ticket
                @endif
            </div>
            <flux:button size="xs" icon="x-mark" variant="subtle" x-on:click="$wire.closePanel()" />
        </div>

        <div class="p-4">
            @if($selectedFeedback)
                @livewire('feedback-show', ['feedback' => $selectedFeedback], key('ticket-'.$selectedFeedback->id))
            @else
                <div class="text-sm text-zinc-500">Kein Ticket ausgewählt.</div>
            @endif
        </div>
    </div>
</div>

<script>
    /**
     * Simple HTML5 Drag & Drop w/ Alpine, calling Livewire to move cards across columns.
     * - dragStart({id, from})
     * - onDrop(toStatus) -> $wire.moveCard(id, toStatus)
     */
    function kanbanDnd($wire) {
        return {
            draggingId: null,
            draggingFrom: null,
            over: null,
            get selectedId(){ return $wire.get('selectedId'); },

            init(){ /* nothing yet */ },

            dragStart(payload){
                this.draggingId = payload.id;
                this.draggingFrom = payload.from;
                document.body.classList.add('select-none');
            },

            dragEnd(){
                this.draggingId = null;
                this.draggingFrom = null;
                this.over = null;
                document.body.classList.remove('select-none');
            },

            onDrop(targetStatus){
                if (!this.draggingId) return;
                this.over = null;
                // Call Livewire to persist the move
                $wire.moveCard(this.draggingId, targetStatus);
                this.dragEnd();
            },
        }
    }
</script>
