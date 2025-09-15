<div class="flex h-[calc(100vh-4rem)] overflow-hidden" x-data="{ openPanel: @entangle('selectedId') }">
    {{-- LEFT: Kanban --}}
    <div class="transition-all duration-200 overflow-x-auto p-4"
         :class="openPanel ? 'w-1/2' : 'w-full'">

        {{-- FILTER BAR --}}
        <div class="mb-4 flex flex-wrap items-center gap-3">
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

                    <flux:select.option value="me">Mir (ich)</flux:select.option>
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

            {{-- Tags (OR) --}}
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

        {{-- BOARD --}}
        <div class="flex gap-4 min-w-max">
            @foreach ($this->columns as $col)
                <div class="w-80 flex-shrink-0 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700"
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
                    <div class="px-4 py-3 flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700"
                         :class="over ? 'bg-blue-50/60 dark:bg-blue-900/20' : ''">
                        <div class="text-sm font-semibold">{{ $col['title'] }}</div>
                        <flux:button variant="subtle" size="xs" icon="ellipsis-horizontal" />
                    </div>

                    <div class="flex flex-col gap-2 p-2 min-h-[100px]">
                        @foreach ($col['cards'] as $card)
                            {{-- CARD: old layout (title + snippet + chips row) --}}
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 cursor-pointer hover:shadow transition"
                                 draggable="true"
                                 @dragstart="$event.dataTransfer.setData('ticket-id', '{{ $card['id'] }}')"
                                 wire:click="selectTicket({{ $card['id'] }})">

                                <div class="p-3 space-y-1">
                                    {{-- Title --}}
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-1">
                                        {{ $card['title'] }}
                                    </div>

                                    {{-- Snippet --}}
                                    <div class="text-xs text-zinc-500 line-clamp-2">
                                        {{ $card['message'] }}
                                    </div>

                                    {{-- Chips --}}
                                    <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                        {{-- Type chip --}}
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

                                        {{-- Priority chip --}}
                                        <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                            {{ ucfirst($card['priority']) }}
                                        </span>

                                        {{-- Assignee chip --}}
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

                    <div class="px-2 py-2">
                        <flux:button variant="subtle" icon="plus" size="sm" class="w-full justify-start!">Neues Ticket</flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- RIGHT: Detail panel (opens when a ticket is selected) --}}
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
                {{-- Pass the actual model to FeedbackShow to avoid null assignment --}}
                @livewire('feedback-show', ['feedback' => $selectedFeedback], key('ticket-'.$selectedFeedback->id))
            @else
                <div class="text-sm text-zinc-500">Kein Ticket ausgewählt.</div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
    });
</script>
