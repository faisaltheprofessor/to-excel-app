@php $pad = min(3, $level); @endphp

<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 pl-{{ 3 + $pad*2 }}">
    {{-- header --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="font-medium">{{ $comment->user?->name ?? 'Anonym' }}</span>
        <span class="text-zinc-500 text-xs">{{ $comment->created_at->diffForHumans() }}</span>

        {{-- edited badge for comments --}}
@if(($commentEditedMap[$comment->id] ?? false))
<button type="button"
    class="text-[11px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300
           hover:bg-zinc-200 dark:hover:bg-zinc-700 cursor-pointer"
    wire:click="openCommentHistory({{ $comment->id }})"
>(bearbeitet)</button>
@endif
    </div>

    {{-- body OR editor --}}
    @if($editingCommentId === $comment->id)
        <div class="mt-2">
            <textarea rows="3" wire:model.defer="editingCommentBody"
                      class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"></textarea>
            @error('editingCommentBody') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95"
                        wire:click="saveEditComment">Speichern</button>
                <button type="button" class="text-xs rounded-md px-2 py-1 border border-zinc-300 dark:border-zinc-700"
                        wire:click="cancelEditComment">Abbrechen</button>
            </div>
        </div>
    @else
        <div class="mt-1 whitespace-pre-wrap break-words">{{ $comment->body }}</div>
    @endif

    {{-- reactions --}}
    <div class="mt-2">
        @include('livewire.partials.reactions', [
            'targetFeedback' => $comment->feedback,
            'commentId'      => $comment->id,
            'quickEmojis'    => $this->quickEmojis,
            'canInteract'    => $canInteract,
        ])
    </div>

    {{-- actions --}}
    <div class="mt-2 flex items-center gap-2">
        @if($canInteract)
            <button
                type="button"
                class="text-xs rounded-md px-2 py-1 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700"
                wire:click="setReplyTo({{ $comment->id }})"
            >Antworten</button>
        @endif

        @if($comment->user_id === auth()->id())
            @if($canInteract && $editingCommentId !== $comment->id)
                <button
                    type="button"
                    class="text-xs rounded-md px-2 py-1 border border-zinc-300 dark:border-zinc-700"
                    wire:click="startEditComment({{ $comment->id }})"
                >Bearbeiten</button>
            @endif

            @if($canInteract)
                <button
                    type="button"
                    class="text-xs rounded-md px-2 py-1 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 dark:border-rose-800"
                    wire:click="deleteComment({{ $comment->id }})"
                >Löschen</button>
            @endif
        @endif
    </div>

    {{-- children --}}
    @if($comment->children && $comment->children->count())
        <div class="mt-3 space-y-3">
            @foreach($comment->children as $child)
                @include('livewire.partials.feedback-comment', ['comment' => $child, 'level' => $level+1, 'canInteract' => $canInteract, 'commentEditedMap' => $commentEditedMap])
            @endforeach
        </div>
    @endif

    {{-- inline reply --}}
    @if($replyTo === $comment->id && $canInteract)
        <div class="mt-3"
             x-data="jiraShortcuts(
                 () => $wire.get('reply'),
                 (v) => $wire.set('reply', v),
                 'replyInlineTa'
             )">
            <textarea
                x-ref="replyInlineTa"
                rows="2"
                wire:model.defer="reply"
                placeholder="Antwort schreiben … (mit @Name erwähnen)"
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                x-on:keydown="onKeydown($event)"
            ></textarea>
            @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95" wire:click="send">Senden</button>
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-transparent hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setReplyTo(null)">Abbrechen</button>
            </div>
        </div>
    @endif
</div>
