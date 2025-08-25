@php use Illuminate\Support\Js; @endphp
<div class="p-6 space-y-8">
    <div class="flex items-start justify-between">
        <flux:heading size="lg">Feedback</flux:heading>
        <a href="{{ route('feedback.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">
            Zurück zur Übersicht
        </a>
    </div>

    {{-- Primary card: clean view + quick edit --}}
    <flux:card>
        <div class="p-6 space-y-5">

            {{-- Title --}}
            <h2 class="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                {{ $feedback->title }}
            </h2>

            {{-- Meta badges (compact) --}}
            <div class="flex flex-wrap items-center gap-2 text-sm">
                {{-- Type (with legacy mapping) --}}
                <span class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700">
                    @php $t = $feedback->type; @endphp
                    @if($t==='bug') Fehler
                    @elseif($t==='feature' || $t==='suggestion') Vorschlag
                    @elseif($t==='feedback' || $t==='question') Feedback
                    @else {{ ucfirst($t) }}
                    @endif
                </span>

                {{-- Status --}}
                @php
                    $statusDe = [
                        'open'=>'Offen','in_progress'=>'In Arbeit','resolved'=>'Gelöst',
                        'closed'=>'Geschlossen','wontfix'=>'Wird nicht behoben'
                    ];
                    $prioDe = ['low'=>'Niedrig','normal'=>'Normal','high'=>'Hoch','urgent'=>'Dringend'];
                @endphp
                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                    {{ $statusDe[$status] ?? ucfirst($status) }}
                </span>

                {{-- Priority --}}
                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                    {{ $prioDe[$priority] ?? ucfirst($priority) }}
                </span>

                <span class="text-zinc-400">•</span>
                <span class="text-zinc-500">{{ $feedback->user?->name ?? 'Anonym' }}</span>
                <span class="text-zinc-500">{{ $feedback->created_at->format('d.m.Y H:i') }}</span>
            </div>

            {{-- Quick edit bar --}}
            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-zinc-50 dark:bg-zinc-900/40 p-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Status</span>
                    <flux:select size="xs" wire:model.live="status" class="h-7">
                        @foreach(\App\Models\Feedback::STATUSES as $s)
                            <option value="{{ $s }}">{{ $statusDe[$s] ?? ucfirst($s) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Priorität</span>
                    <flux:select size="xs" wire:model.live="priority" class="h-7">
                        @foreach(\App\Models\Feedback::PRIORITIES as $p)
                            <option value="{{ $p }}">{{ $prioDe[$p] ?? ucfirst($p) }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:spacer />
                <flux:button size="xs" variant="outline" wire:click="saveMeta">Änderungen speichern</flux:button>
            </div>

            {{-- Description --}}
            <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-[15px] leading-6">
                {{ $feedback->message }}
            </div>

            {{-- Attachments --}}
            @php $attachments = $attachments ?? []; @endphp
            @if(is_array($attachments) && count($attachments))
                <div class="space-y-1">
                    <div class="text-xs text-zinc-500">Anhänge</div>
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

            {{-- Tags (view-only + remove; quick suggestions to add) --}}
            @if($tags && count($tags))
                <div class="space-y-2">
                    <div class="text-xs text-zinc-500">Tags</div>

                    <div class="flex flex-wrap items-center gap-1">
                        @foreach($tags as $i => $tg)
                            <span class="inline-flex items-center gap-1 text-[11px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                                #{{ $tg }}
                                <button class="ml-1" wire:click="removeTag({{ $i }})" title="Entfernen">×</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Quick-add tag suggestions (keine freie Eingabe) --}}
            @if(!empty($tagSuggestions))
                <div class="space-y-1">
                    <div class="text-xs text-zinc-500">Vorschläge</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($tagSuggestions as $sug)
                            <flux:button size="xs" variant="subtle" wire:click="addTag({{ Js::from($sug) }})">#{{ $sug }}</flux:button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Reactions (top-level) --}}
            @include('livewire.partials.reactions', [
                'targetFeedback' => $feedback,
                'commentId' => null,
                'quickEmojis' => $this->quickEmojis,
            ])
        </div>
    </flux:card>

    {{-- Comments (clean, with mentions dropdown) --}}
    <flux:card>
        <div class="p-6 space-y-5">
            <flux:heading size="md">Kommentare</flux:heading>

            {{-- new comment with mentions --}}
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

                {{-- Mentions dropdown --}}
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
                                    <button
                                        type="button"
                                        class="w-full text-left px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                        data-name="{{ e($u['name']) }}"
                                        x-on:click="insert(String.fromCharCode(64) + $el.dataset.name + ' ')"
                                    >
                                        <span>&#64;{{ $u['name'] }}</span>
                                    </button>
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

            {{-- thread --}}
            <div class="space-y-4">
                @foreach($rootComments as $c)
                    @include('livewire.partials.feedback-comment', ['comment' => $c, 'level' => 0])
                @endforeach
            </div>
        </div>
    </flux:card>
</div>

{{-- Mentions helper (safe; no @js) --}}
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
            const rx = new RegExp(AT + '([\\p{L}\\p{M}\\.\\- ]{1,50})$', 'u');
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
