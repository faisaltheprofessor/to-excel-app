@php use Illuminate\Support\Js; @endphp
<div class="p-6 space-y-8">
    <div class="flex items-start gap-4">
        <flux:heading size="lg" class="flex-1">Feedback-Details</flux:heading>
        <a href="{{ route('feedback.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">Alle Feedbacks</a>
    </div>

    <flux:card>
        <div class="p-4 space-y-4">
            {{-- Header --}}
            <div class="flex items-center flex-wrap gap-2">
                {{-- Typ --}}
                <span class="text-xs rounded px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700">
                    @if($feedback->type==='bug') Bug
                    @elseif($feedback->type==='suggestion') Feature
                    @else Frage
                    @endif
                </span>

                @php
                    $statusMap = [
                        'open'        => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                        'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                        'resolved'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                        'closed'      => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
                        'wontfix'     => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                    ];
                    $prioMap = [
                        'low'    => 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200',
                        'normal' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
                        'high'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                        'urgent' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                    ];
                    $prioDe = [
                        'low'    => 'Niedrig',
                        'normal' => 'Normal',
                        'high'   => 'Hoch',
                        'urgent' => 'Dringend',
                    ];
                @endphp

                {{-- Status (bearbeitbar) --}}
                <flux:select wire:model.live="status" class="h-7 px-2 text-xs">
                    @foreach(\App\Models\Feedback::STATUSES as $s)
                        <option value="{{ $s }}">{{ [
                            'open'=>'Offen','in_progress'=>'In Arbeit','resolved'=>'Gelöst','closed'=>'Geschlossen','wontfix'=>'Wird nicht behoben'
                        ][$s] }}</option>
                    @endforeach
                </flux:select>
                <span class="text-xs rounded px-2 py-0.5 {{ $statusMap[$status] ?? 'bg-zinc-100' }}">
                    {{ [
                        'open'=>'Offen','in_progress'=>'In Arbeit','resolved'=>'Gelöst','closed'=>'Geschlossen','wontfix'=>'Wird nicht behoben'
                    ][$status] ?? $status }}
                </span>

                {{-- Priorität (bearbeitbar) --}}
                <flux:select wire:model.live="priority" class="h-7 px-2 text-xs">
                    @foreach(\App\Models\Feedback::PRIORITIES as $p)
                        <option value="{{ $p }}">{{ $prioDe[$p] ?? ucfirst($p) }}</option>
                    @endforeach
                </flux:select>
                <span class="text-xs rounded px-2 py-0.5 {{ $prioMap[$priority] ?? 'bg-zinc-100' }}">
                    {{ $prioDe[$priority] ?? ucfirst($priority) }}
                </span>

                {{-- Autor + Datum --}}
                <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $feedback->user?->name ?? 'Anonym' }}</span>
                <span class="text-xs text-zinc-500">{{ $feedback->created_at->format('d.m.Y H:i') }}</span>

                @if($feedback->url)
                    <a href="{{ $feedback->url }}" target="_blank" class="text-xs text-blue-600 hover:underline">Seite öffnen</a>
                @endif
            </div>

            {{-- Nachricht --}}
            <div class="whitespace-pre-wrap">{{ $feedback->message }}</div>

            {{-- Tags --}}
            <div class="space-y-2">
                <div class="text-xs text-zinc-500">Tags</div>
                <div class="flex flex-wrap gap-1">
                    @foreach($tags as $i => $tg)
                        <span class="inline-flex items-center gap-1 text-[11px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                            #{{ $tg }}
                            <button class="ml-1" wire:click="$set('tags', {{ collect($tags)->except($i)->values()->toJson() }})">×</button>
                        </span>
                    @endforeach

                    {{-- Tag hinzufügen --}}
                    <form wire:submit.prevent="addTag" class="flex items-center gap-2">
                        <flux:input class="w-48 h-7 text-xs" placeholder="Tag hinzufügen…" wire:model.defer="tagInput" name="tagInput" />
                        <flux:button size="xs" type="submit">Hinzufügen</flux:button>
                    </form>
                </div>

                {{-- Tag-Vorschläge --}}
                <div class="flex flex-wrap gap-1">
                    @foreach($tagSuggestions as $sug)
                        <flux:button size="xs" variant="subtle" wire:click="addTag({{ Js::from($sug) }})">#{{ $sug }}</flux:button>
                    @endforeach
                </div>

                <div>
                    <flux:button size="xs" wire:click="saveMeta">Meta speichern</flux:button>
                </div>
            </div>

            {{-- Anhänge --}}
            @php $attachments = $attachments ?? []; @endphp
            @if(is_array($attachments) && count($attachments))
                <div class="pt-2">
                    <div class="text-xs text-zinc-500 mb-1">Anhänge:</div>
                    <ul class="text-sm space-y-1">
                        @foreach($attachments as $i => $path)
                            <li>
                                <a class="text-blue-600 hover:underline" href="{{ route('feedback.file', [$feedback, $i]) }}">
                                    {{ basename($path) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Reaktionen (Top-Level) --}}
            @include('livewire.partials.reactions', [
                'targetFeedback' => $feedback,
                'commentId' => null,
                'quickEmojis' => $this->quickEmojis,
            ])
        </div>
    </flux:card>

    {{-- Unterhaltung --}}
    <div class="space-y-6">
        <flux:heading size="md">Unterhaltung</flux:heading>

        {{-- Antwort-Editor mit Mentions --}}
        <div
            x-data="mentionBox({
                getText:   () => $refs.replyTa.value,
                setText:   (v) => { $refs.replyTa.value = v; $wire.set('reply', v) },
                setQuery:  (q) => $wire.set('mentionQuery', q),
                open:      () => $wire.set('mentionOpen', true),
                close:     () => $wire.call('closeMentions'),
                isOpen:    () => $wire.get('mentionOpen'),
                results:   () => $wire.get('mentionResults'),
            })"
            x-on:keydown.escape.prevent.stop="close()"
            class="space-y-2 relative"
        >
            <flux:textarea
                x-ref="replyTa"
                rows="3"
                wire:model.defer="reply"
                placeholder="Antwort schreiben … (mit &#64;Namen erwähnen)"
                x-on:keyup="detect($event)"
                x-on:click="detect($event)"
                x-on:keydown.enter.prevent="maybePick($event)"
            />
            @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- Mentions-Dropdown --}}
            <div
                class="absolute z-50 mt-1 w-72 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow"
                x-show="$wire.mentionOpen"
                x-transition
                x-on:click.outside="$wire.call('closeMentions')"
            >
                <div class="px-3 py-2 text-xs text-zinc-500 border-b border-zinc-100 dark:border-zinc-700">
                    Personen erwähnen
                </div>
                @if (empty($mentionResults))
                    <div class="px-3 py-2 text-sm text-zinc-500">Keine Treffer</div>
                @else
                    <ul class="max-h-64 overflow-auto">
                        @foreach($mentionResults as $u)
                            <li>
                                <button type="button"
                                    class="w-full text-left px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                    x-on:click='insert(String.fromCharCode(64) + {{ Js::from($u["name"]) }} + " ")'
                                ><span>&#64;{{ $u['name'] }}</span></button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <flux:spacer />
                <flux:button size="sm" wire:click="send">Senden</flux:button>
            </div>
        </div>

        {{-- Thread --}}
        <div class="space-y-4">
            @foreach($rootComments as $c)
                @include('livewire.partials.feedback-comment', ['comment' => $c, 'level' => 0])
            @endforeach
        </div>
    </div>
</div>

{{-- Script: Mentions-Helper (sicher, kein rohes "@") --}}
<script>
window.mentionBox = function(api) {
    const AT = String.fromCharCode(64); // "@"
    return {
        range: null,
        detect() {
            const ta = this.textarea(); if (!ta) return;
            const val = ta.value ?? '';
            const pos = ta.selectionStart ?? 0;
            const prefix = val.slice(0, pos);
            const rx = new RegExp(AT + '([\\p{L}\\p{M}\\.\\- ]{1,50})$', 'u'); // @Name…
            const match = prefix.match(rx);

            if (match) {
                const q = (match[1] || '').trim();
                const start = pos - match[0].length;
                this.range = { start, end: pos };
                api.setQuery(q);
                if (q.length > 0) api.open();
            } else {
                this.range = null; api.setQuery(''); api.close();
            }
        },
        maybePick(e) {
            if (this.range && api.isOpen && api.isOpen()) {
                e.preventDefault();
                const list = (api.results && api.results()) || [];
                if (list.length > 0) this.insert(AT + list[0].name + ' ');
            }
        },
        insert(text) {
            if (!this.range) return;
            const ta = this.textarea(); const val = ta.value ?? '';
            const newVal = val.slice(0, this.range.start) + text + val.slice(this.range.end);
            api.setText(newVal);
            const newPos = this.range.start + text.length;
            this.setCaret(ta, newPos); api.setQuery(''); api.close(); this.range = null;
        },
        setCaret(el, pos){ el.focus(); el.setSelectionRange(pos,pos); },
        textarea(){ return this.$refs.replyTa; },
        close(){ api.setQuery(''); api.close(); this.range = null; }
    }
}
</script>
