@php
    $author = $comment->user?->name ?? 'Unbekannt';
    // Render @mentions with a simple blue style
    $body = e($comment->body);
    $body = preg_replace('/@([\p{L}\p{M}\.\- ]{2,50})/u', '<span class="text-blue-600">@${1}</span>', $body);
@endphp

<flux:card class="{{ $level ? 'ml-6' : '' }}">
    <div class="p-3 space-y-2">
        <div class="text-sm text-zinc-600 dark:text-zinc-300">
            <span class="font-medium">{{ $author }}</span>
            <span class="text-xs text-zinc-500">• {{ $comment->created_at->diffForHumans() }}</span>
        </div>

        <div class="text-sm prose dark:prose-invert max-w-none">{!! nl2br($body) !!}</div>

        <div class="flex items-center gap-2">
            {{-- Existing reactions for this comment --}}
            @php
                $rx = $comment->reactions()
                    ->selectRaw('emoji, COUNT(*) as c')
                    ->groupBy('emoji')->pluck('c','emoji');
            @endphp
            @foreach($rx as $emoji => $count)
                <button type="button" class="text-sm px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700"
                        wire:click="toggleReaction('{{ $emoji }}', {{ $comment->id }})">
                    {{ $emoji }} {{ $count }}
                </button>
            @endforeach

            @foreach($this->quickEmojis as $e)
                <flux:button size="xs" variant="subtle" wire:click="toggleReaction('{{ $e }}', {{ $comment->id }})">{{ $e }}</flux:button>
            @endforeach

            <flux:spacer/>
            <flux:button size="xs" variant="ghost" wire:click="setReplyTo({{ $comment->id }})">Antworten</flux:button>
        </div>

        {{-- Nested reply editor with @mention popover --}}
        @if($replyTo === $comment->id)
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
                class="mt-2 space-y-2 relative"
            >
                <flux:textarea
                    x-ref="replyTa"
                    rows="3"
                    wire:model.defer="reply"
                    placeholder="Antworten … (mit @Namen erwähnen)"
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
                    <flux:button size="xs" variant="ghost" wire:click="setReplyTo(null)">Abbrechen</flux:button>
                    <flux:spacer/>
                    <flux:button size="sm" wire:click="send">Senden</flux:button>
                </div>
            </div>
        @endif
    </div>
</flux:card>

@if($comment->children && $comment->children->count())
    <div class="mt-2 space-y-3">
        @foreach($comment->children as $child)
            @include('livewire.partials.feedback-comment', ['comment' => $child, 'level' => $level + 1])
        @endforeach
    </div>
@endif
