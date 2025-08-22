<div class="p-6 space-y-8">
    <div class="flex items-start gap-4">
        <flux:heading size="lg" class="flex-1">Feedback-Details</flux:heading>
        <a href="{{ route('feedback.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">Alle Feedbacks</a>
    </div>

    {{-- Header card --}}
    <flux:card>
        <div class="p-4 space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-xs rounded px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700">
                    @if($feedback->type==='bug') Fehler
                    @elseif($feedback->type==='suggestion') Vorschlag
                    @else Frage
                    @endif
                </span>
                <span class="text-xs text-zinc-500">{{ $feedback->created_at->format('d.m.Y H:i') }}</span>
                @if($feedback->url)
                    <a href="{{ $feedback->url }}" target="_blank" class="text-xs text-blue-600 hover:underline">Seite öffnen</a>
                @endif
            </div>

            <div class="whitespace-pre-wrap">{{ $feedback->message }}</div>

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

            {{-- Top-level reactions --}}
            <div class="flex items-center gap-2 pt-2">
                @foreach($this->quickEmojis as $e)
                    <button type="button" class="text-xl" wire:click="toggleReaction('{{ $e }}', null)">{{ $e }}</button>
                @endforeach

                @php
                    $rx = $feedback->reactions()
                        ->selectRaw('emoji, COUNT(*) as c')
                        ->whereNull('comment_id')
                        ->groupBy('emoji')
                        ->pluck('c','emoji');
                @endphp
                <div class="text-sm text-zinc-600 dark:text-zinc-300">
                    @foreach($rx as $emoji => $count)
                        <span class="inline-flex items-center gap-1 ml-2">{{ $emoji }} {{ $count }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Conversation --}}
    <div class="space-y-6">
        <flux:heading size="md">Unterhaltung</flux:heading>

        {{-- Reply editor (top-level) with @mention popover --}}
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
                placeholder="Antwort schreiben … (mit @Namen erwähnen)"
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
                                <button type="button"
                                    class="w-full text-left px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                    x-on:click='insert(@json("@" . $u["name"] . " "))'
                                >{{ '@' . $u['name'] }}</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @foreach($this->quickEmojis as $e)
                    <flux:button size="xs" variant="subtle" wire:click="toggleReaction('{{ $e }}', null)">{{ $e }}</flux:button>
                @endforeach
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

{{-- Global helper (define once here OR move to your main layout) --}}
@script
<script>
window.mentionBox = function(api) {
    return {
        range: null,

        detect() {
            const ta = this.textarea();
            if (!ta) return;
            const val = ta.value ?? '';
            const pos = ta.selectionStart ?? 0;

            const prefix = val.slice(0, pos);
            const match = prefix.match(/@([\p{L}\p{M}\.\- ]{1,50})$/u);

            if (match) {
                const q = (match[1] || '').trim();
                const start = pos - match[0].length;
                this.range = { start, end: pos };
                api.setQuery(q);
                if (q.length > 0) api.open();
            } else {
                this.range = null;
                api.setQuery('');
                api.close();
            }
        },

        maybePick(e) {
            if (this.range && api.isOpen && api.isOpen()) {
                e.preventDefault();
                const list = (api.results && api.results()) || [];
                if (list.length > 0) {
                    const name = list[0].name;
                    this.insert(`@${name} `);
                }
            }
        },

        insert(text) {
            if (!this.range) return;
            const ta = this.textarea();
            const val = ta.value ?? '';
            const newVal = val.slice(0, this.range.start) + text + val.slice(this.range.end);
            api.setText(newVal);
            const newPos = this.range.start + text.length;
            this.setCaret(ta, newPos);
            api.setQuery('');
            api.close();
            this.range = null;
        },

        setCaret(el, pos) {
            el.focus();
            el.setSelectionRange(pos, pos);
        },

        textarea() { return this.$refs.replyTa; },

        close() {
            api.setQuery('');
            api.close();
            this.range = null;
        }
    }
}
</script>
@endscript
