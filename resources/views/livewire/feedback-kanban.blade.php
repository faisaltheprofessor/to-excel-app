<div class="flex h-[calc(100vh-4rem)] overflow-hidden w-3/4 mx-auto" x-data="{ openPanel: @entangle('selectedId') }">
    {{-- LEFT: Kanban --}}
    <div class="transition-all duration-200 h-[90%] flex flex-col"
         :class="openPanel ? 'w-1/2' : 'w-full'">

        {{-- FILTER BAR  --}}
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex flex-wrap items-center gap-3">
                {{-- Assignee --}}
                <div class="min-w-[240px]">
                    <flux:select
                        variant="listbox"
                        placeholder="Zugewiesen an …"
                        searchable
                        multiple
                        clearable
                        :filter="false"
                        wire:model.live="assigneeFilter"
                    >
                        <x-slot name="input">
                            <flux:select.input
                                wire:model.live="assigneeSearch"
                                placeholder="Nutzer suchen …"
                            />
                        </x-slot>

                        <flux:select.option value="none">Unzugewiesen</flux:select.option>

                        @foreach ($this->users as $user)
                            <flux:select.option value="{{ $user->id }}" wire:key="assignee-{{ $user->id }}">
                                {{ $user->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                {{-- Priority --}}
                <div class="min-w-[200px]">
                    <flux:select
                        variant="listbox"
                        placeholder="Priorität …"
                        multiple
                        clearable
                        wire:model.live="priorityFilter"
                    >
                        <flux:select.option value="low">Niedrig</flux:select.option>
                        <flux:select.option value="normal">Normal</flux:select.option>
                        <flux:select.option value="high">Hoch</flux:select.option>
                        <flux:select.option value="urgent">Dringend</flux:select.option>
                    </flux:select>
                </div>

                {{-- Type --}}
                <div class="min-w-[200px]">
                    <flux:select
                        variant="listbox"
                        placeholder="Typ …"
                        multiple
                        clearable
                        wire:model.live="typeFilter"
                    >
                        <flux:select.option value="bug">Bug</flux:select.option>
                        <flux:select.option value="suggestion">Feature</flux:select.option>
                        <flux:select.option value="feedback">Feedback</flux:select.option>
                        <flux:select.option value="question">Question</flux:select.option>
                    </flux:select>
                </div>

                {{-- Tags --}}
                <div class="min-w-[220px]">
                    <flux:select
                        variant="listbox"
                        placeholder="Tags …"
                        searchable
                        multiple
                        clearable
                        wire:model.live="tagFilter"
                    >
                        @foreach ($allTags as $tag)
                            <flux:select.option value="{{ $tag }}">{{ $tag }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

            </div>
        </div>

        {{-- BOARD (takes rest of height, horizontally scrollable) --}}
        <div class="flex-1 overflow-x-auto"
     x-data="{ isDown:false, startX:0, scrollLeft:0 }"
     x-ref="scrollBoard"
     @mousedown="isDown=true; startX=$event.pageX - $refs.scrollBoard.offsetLeft; scrollLeft=$refs.scrollBoard.scrollLeft"
     @mouseleave="isDown=false"
     @mouseup="isDown=false"
     @mousemove="if(isDown){ $event.preventDefault(); const x=$event.pageX - $refs.scrollBoard.offsetLeft; const walk=(x-startX); $refs.scrollBoard.scrollLeft=scrollLeft-walk }"
>
    <div class="flex gap-4 min-w-max h-full p-4 select-none">
        @foreach ($this->columns as $col)
            <div class="w-80 flex-shrink-0 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700
                        flex flex-col h-full"
                 x-data="{ status: '{{ $col['key'] }}', over: false }"
                 @dragover.prevent="over = true"
                 @dragleave="over = false"
                 @drop.prevent="
                    over = false;
                    $wire.moveTicket(
                        parseInt($event.dataTransfer.getData('ticket-id')),
                        status
                    )
                 ">
                {{-- Header --}}
                <div class="px-4 py-3 flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700 rounded-t-lg"
                     :class="over ? 'bg-blue-50/60 dark:bg-blue-900/20' : ''">
                    <div class="text-sm font-semibold">{{ $col['title'] }} ({{ count($col['cards']) }})</div>
                    <flux:button variant="subtle" size="xs" icon="ellipsis-horizontal" />
                </div>

                {{-- Cards --}}
                <div class="flex-1 overflow-y-auto p-2">
                    <div class="flex flex-col gap-2">
                        @foreach ($col['cards'] as $card)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer hover:shadow transition"
                                 draggable="true"
                                 @dragstart="$event.dataTransfer.setData('ticket-id', '{{ $card['id'] }}')"
                                 wire:click="selectTicket({{ $card['id'] }})">
                                <div class="p-3 space-y-1">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-1">
                                        {{ $card['title'] }}
                                    </div>
                                    <div class="text-xs text-zinc-500 line-clamp-2">
                                        {{ $card['message'] }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                        {{-- chips (type, priority, assignee) --}}
                                        @php
                                            $typeColor = $card['type']==='bug'
                                                ? ['bg'=>'bg-rose-100', 'text'=>'text-rose-800', 'darkbg'=>'dark:bg-rose-900/40', 'darktext'=>'dark:text-rose-200']
                                                : ($card['type']==='suggestion'
                                                    ? ['bg'=>'bg-blue-100', 'text'=>'text-blue-800', 'darkbg'=>'dark:bg-blue-900/40', 'darktext'=>'dark:text-blue-200']
                                                    : ['bg'=>'bg-zinc-100', 'text'=>'text-zinc-800', 'darkbg'=>'dark:bg-zinc-800', 'darktext'=>'dark:text-zinc-200']);
                                            $typeLabel = $card['type']==='bug' ? 'Bug'
                                                        : ($card['type']==='suggestion' ? 'Feature'
                                                        : ucfirst($card['type']));
                                        @endphp
                                        <span class="px-1.5 py-0.5 rounded {{ $typeColor['bg'] }} {{ $typeColor['text'] }} {{ $typeColor['darkbg'] }} {{ $typeColor['darktext'] }}">
                                            {{ $typeLabel }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                            {{ ucfirst($card['priority']) }}
                                        </span>
                                        @if(!empty($card['assignee']))
                                            <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-200">
                                                {{ $card['assignee'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
    </div>

    {{-- RIGHT: Detail --}}
    <div class="w-1/2 border-l border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-y-auto"
         x-cloak
         x-show="openPanel"
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
     * Global Alpine helpers so nested Livewire children (feedback-show) can use them.
     * Defined once, guarded.
     */
    if (!window.__composeHelpersInit) {
        window.__composeHelpersInit = true;

        window.composeBox = function(api) {
            const jiraMap = new Map([ ['/', '✅'], ['x', '❌'], ['y', '👍'] ]);

            return {
                range: null,

                textarea(){ return this.$refs.replyTa; },
                setCaret(el, pos){ try { el?.focus(); el?.setSelectionRange(pos, pos); } catch(e) {} },

                detect(){
                    const ta = this.textarea(); if (!ta) return;
                    const v  = ta.value ?? '';
                    const p  = ta.selectionStart ?? 0;
                    const pre = v.slice(0, p);

                    const m = pre.match(new RegExp('@([\\p{L}\\p{M}\\.\\- ]{1,50})$','u'));
                    if (m) {
                        const q = (m[1] || '').trim();
                        this.range = { start: p - m[0].length, end: p };
                        api.setQuery(q);
                        if (q.length > 0) api.open();
                    } else {
                        this.range = null; api.setQuery(''); api.close();
                    }
                },

                insert(text){
                    if (!this.range) return;
                    const ta = this.textarea(); if (!ta) return;
                    const v = ta.value ?? '';
                    const nv = v.slice(0, this.range.start) + text + v.slice(this.range.end);
                    ta.value = nv; api.setText(nv);
                    const np = this.range.start + text.length;
                    this.setCaret(ta, np);
                    api.setQuery(''); api.close(); this.range = null;
                },

                replaceJiraToken(triggerKey){
                    const ta = this.textarea(); if (!ta) return false;
                    const val = ta.value ?? '';
                    const pos = ta.selectionStart ?? 0;
                    const leftRaw = val.slice(0, pos);
                    const right   = val.slice(pos);

                    const ws = leftRaw.match(/[ \t\r\n]+$/);
                    const trailing = ws ? ws[0] : '';
                    const left = trailing ? leftRaw.slice(0, leftRaw.length - trailing.length) : leftRaw;

                    const m = left.match(/\(([^\s()]{1,10})\)$/i);
                    if (!m) return false;

                    const token = (m[1] || '').toLowerCase();
                    const emoji = ({'/':'✅','x':'❌','y':'👍'})[token];
                    if (!emoji) return false;

                    const newLeft = left.slice(0, left.length - m[0].length) + emoji;

                    let trigger = '';
                    if (triggerKey === ' ') trigger = ' ';
                    else if (triggerKey === 'Enter') trigger = '\n';
                    else if (triggerKey === 'Tab') trigger = '\t';

                    const newVal = newLeft + trailing + trigger + right;
                    api.setText(newVal);
                    const newPos = (newLeft + trailing + trigger).length;
                    this.setCaret(ta, newPos);

                    this.range = null; api.setQuery(''); api.close();
                    return true;
                },

                onKeydown(e){
                    if ([' ', 'Enter', 'Tab'].includes(e.key)) {
                        if (this.replaceJiraToken(e.key)) { e.preventDefault(); return; }
                    }
                    queueMicrotask(() => this.detect());
                }
            };
        };

        // alias for any older x-data="mentionBox(...)"
        window.mentionBox = function(api){ return window.composeBox(api); };
    }
</script>
