@php
    $pageIds        = $this->rows->pluck('id')->all();
    $pageAllChecked = !empty($pageIds) && count(array_diff($pageIds, $selected)) === 0;

    $hasSelection     = !empty($selected);
    $hasAny           = $this->total > 0;

    $countLabel       = $hasSelection ? ' ('.count($selected).')' : '';
    $restoreLabel     = $hasSelection ? 'Ausgewählte wiederherstellen'.$countLabel : 'Alle wiederherstellen';
    $deleteLabel      = $hasSelection ? 'Ausgewählte endgültig löschen'.$countLabel : 'Alle endgültig löschen';

    $restoreDisabled  = !$hasSelection && !$hasAny;
    $deleteDisabled   = !$hasSelection && !$hasAny;
@endphp

<div class="w-1/2 md:w-3/4 mx-auto py-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold flex items-center gap-2">
            <flux:icon.archive-box class="w-5 h-5 text-zinc-500" />
            Gelöschte Tickets
        </h1>
        <a href="{{ route('feedback.index') }}" wire:navigate>
            <flux:button variant="subtle" size="sm" icon="arrow-left">Zurück zum Board</flux:button>
        </a>
    </div>

    {{-- Filter bar --}}
    <div class="flex items-center gap-3">
        <input type="text"
               wire:model.live.debounce.300ms="q"
               placeholder="Suche Titel/Beschreibung…"
               class="flex-1 px-3 py-2 rounded border border-zinc-300/70 dark:border-zinc-600 bg-transparent text-sm" />

        <flux:select variant="listbox" placeholder="Typ" multiple clearable wire:model.live="type" class="flex-1">
            <flux:select.option value="bug">Bug</flux:select.option>
            <flux:select.option value="suggestion">Feature</flux:select.option>
            <flux:select.option value="feedback">Feedback</flux:select.option>
            <flux:select.option value="question">Question</flux:select.option>
        </flux:select>

        <flux:select variant="listbox" placeholder="Priorität" multiple clearable wire:model.live="priority" class="flex-1">
            <flux:select.option value="low">Niedrig</flux:select.option>
            <flux:select.option value="normal">Normal</flux:select.option>
            <flux:select.option value="high">Hoch</flux:select.option>
            <flux:select.option value="urgent">Dringend</flux:select.option>
        </flux:select>

        <div class="flex-1"></div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr class="text-left">
                    <th class="px-3 py-2 w-10">
                        <input type="checkbox"
                               wire:model.live="selectPage"
                               @if($pageAllChecked) checked @endif
                               class="rounded border-zinc-300 dark:border-zinc-600">
                    </th>
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">Titel</th>
                    <th class="px-3 py-2">Typ</th>
                    <th class="px-3 py-2">Prio</th>
                    <th class="px-3 py-2">Zugewiesen</th>
                    <th class="px-3 py-2">Gelöscht am</th>
                    <th class="px-3 py-2 text-right">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->rows as $f)
                    @php $isChecked = in_array($f->id, $selected, true); @endphp
                    <tr wire:key="row-{{ $f->id }}">
                        <td class="px-3 py-2">
                            <input type="checkbox"
                                   value="{{ $f->id }}"
                                   @if($isChecked) checked @endif
                                   wire:model.live="selected"
                                   class="rounded border-zinc-300 dark:border-zinc-600">
                        </td>
                        <td class="px-3 py-2">{{ $f->id }}</td>
                        <td class="px-3 py-2">
                            <div class="font-medium line-clamp-1">{{ $f->title }}</div>
                            <div class="text-xs text-zinc-500 line-clamp-1">
                                {{ \Illuminate\Support\Str::limit($f->message, 120) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 capitalize">{{ $f->type }}</td>
                        <td class="px-3 py-2 capitalize">{{ $f->priority }}</td>
                        <td class="px-3 py-2">{{ $f->assignee?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ optional($f->deleted_at)->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">
                            <div class="flex justify-end gap-2">
                                <flux:button size="xs" variant="subtle" icon="arrow-uturn-left"
                                             wire:click="restoring({{ $f->id }})"
                                             wire:loading.attr="disabled">
                                    Wiederherstellen
                                </flux:button>

                                <flux:modal.trigger name="delete-ticket-{{ $f->id }}" wire:key="t-{{ $f->id }}">
                                    <flux:button size="xs" variant="danger" icon="trash">
                                        Endgültig löschen
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal name="delete-ticket-{{ $f->id }}" class="min-w-[22rem]" wire:key="m-{{ $f->id }}">
                                    <div class="space-y-6">
                                        <div>
                                            <flux:heading size="lg">Ticket #{{ $f->id }} löschen?</flux:heading>
                                            <flux:text class="mt-2">
                                                <p>Dieses Ticket, Kommentare, Reaktionen und <strong>alle Anhänge</strong> werden dauerhaft entfernt.</p>
                                                <p class="text-red-500 font-medium">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                                            </flux:text>
                                        </div>
                                        <div class="flex gap-2">
                                            <flux:spacer />
                                            <flux:modal.close>
                                                <flux:button variant="ghost">Abbrechen</flux:button>
                                            </flux:modal.close>
                                            <flux:modal.close>
                                                <flux:button variant="danger" icon="trash"
                                                             wire:click="destroyForever({{ $f->id }})"
                                                             wire:loading.attr="disabled">
                                                    Endgültig löschen
                                                </flux:button>
                                            </flux:modal.close>
                                        </div>
                                    </div>
                                </flux:modal>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-zinc-500">
                            Keine Tickets.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div>
        {{ $this->rows->links() }}
    </div>

    {{-- Footer bulk actions --}}
    <div class="flex justify-end gap-2">
        {{-- Restore bulk --}}
        <flux:modal.trigger name="bulk-restore">
            @if($restoreDisabled)
                <flux:button variant="subtle" icon="arrow-uturn-left" disabled>
                    {{ $restoreLabel }}
                </flux:button>
            @else
                <flux:button variant="subtle" icon="arrow-uturn-left">
                    {{ $restoreLabel }}
                </flux:button>
            @endif
        </flux:modal.trigger>

        <flux:modal name="bulk-restore" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $restoreLabel }}?</flux:heading>
                </div>
                <div class="flex gap-2">
                    <flux:spacer/>
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button variant="primary" icon="arrow-uturn-left"
                                     wire:click="restoreBulk"
                                     wire:loading.attr="disabled">
                            Wiederherstellen
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        {{-- Delete bulk --}}
        <flux:modal.trigger name="bulk-delete">
            @if($deleteDisabled)
                <flux:button variant="danger" icon="trash" disabled>
                    {{ $deleteLabel }}
                </flux:button>
            @else
                <flux:button variant="danger" icon="trash">
                    {{ $deleteLabel }}
                </flux:button>
            @endif
        </flux:modal.trigger>

        <flux:modal name="bulk-delete" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $deleteLabel }}?</flux:heading>
                </div>
                <div class="flex gap-2">
                    <flux:spacer/>
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <flux:button variant="danger" icon="trash"
                                     wire:click="deleteBulk"
                                     wire:loading.attr="disabled">
                            Endgültig löschen
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
