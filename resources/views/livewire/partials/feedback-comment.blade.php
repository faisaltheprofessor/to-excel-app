@php $pad = min(3, $level); @endphp
<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 pl-{{ 3 + $pad*2 }}">
    {{-- header: author + time --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="font-medium">{{ $comment->user?->name ?? 'Anonym' }}</span>
        <span class="text-zinc-500 text-xs">{{ $comment->created_at->diffForHumans() }}</span>
    </div>

    {{-- body --}}
    <div class="mt-1 whitespace-pre-wrap break-words">{{ $comment->body }}</div>

    {{-- reactions (reusable partial; no inline JS with "@") --}}
    <div class="mt-2">
        @include('livewire.partials.reactions', [
            'targetFeedback' => $comment->feedback,
            'commentId' => $comment->id,
            'quickEmojis' => $this->quickEmojis,
        ])
    </div>

    {{-- actions --}}
    <div class="mt-2">
        <flux:button size="xs" variant="subtle" wire:click="setReplyTo({{ $comment->id }})">Antworten</flux:button>
    </div>

    {{-- children --}}
    @if($comment->children && $comment->children->count())
        <div class="mt-3 space-y-3">
            @foreach($comment->children as $child)
                @include('livewire.partials.feedback-comment', ['comment' => $child, 'level' => $level+1])
            @endforeach
        </div>
    @endif

    {{-- inline reply box (Alpine x-data just for Jira shortcuts) --}}
    @if($replyTo === $comment->id)
        <div class="mt-3"
             x-data="jiraShortcuts(
                 () => $wire.get('reply'),
                 (v) => $wire.set('reply', v),
                 'replyInlineTa'
             )">
            <flux:textarea
                x-ref="replyInlineTa"
                rows="2"
                wire:model.defer="reply"
                placeholder="Antwort schreiben … (mit &#64;Name erwähnen"
                x-on:keydown="onKeydown($event)"  {{-- Jira-style (y) (/) (x) --}}
            />
            @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            <div class="mt-2 flex items-center gap-2">
                <flux:button size="xs" wire:click="send">Senden</flux:button>
                <flux:button size="xs" variant="ghost" wire:click="setReplyTo(null)">Abbrechen</flux:button>
            </div>
        </div>
    @endif
</div>
