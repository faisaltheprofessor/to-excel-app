{{-- resources/views/livewire/partials/reactions.blade.php --}}

@if($targetFeedback)
@php
    /** @var \App\Models\Feedback $targetFeedback */
    $uid = auth()->id();

    // Counts per emoji for this target (feedback or a specific comment)
    $reactionCounts = $targetFeedback->reactions()
        ->selectRaw('emoji, COUNT(*) as c')
        ->where('comment_id', $commentId)
        ->groupBy('emoji')
        ->pluck('c', 'emoji')
        ->all();

    // Whether current user has reacted with each emoji
    $userHas = [];
    foreach ($quickEmojis as $emoji) {
        $userHas[$emoji] = $targetFeedback->reactions()
            ->where('comment_id', $commentId)
            ->where('emoji', $emoji)
            ->where('user_id', $uid)
            ->exists();
    }

    $commentParam = $commentId === null ? 'null' : (string)$commentId;
@endphp

<div class="flex items-center gap-2 {{ ($canInteract ?? true) ? '' : 'opacity-60 pointer-events-none' }}">
    @foreach($quickEmojis as $emoji)
        @php
            $mine     = $userHas[$emoji] ?? false;
            $btnCls   = $mine
                ? 'bg-emerald-50 dark:bg-emerald-900/20 ring-1 ring-emerald-200 dark:ring-emerald-800'
                : 'bg-zinc-50 dark:bg-zinc-800/60 ring-1 ring-zinc-200 dark:ring-zinc-700';
            $count    = $reactionCounts[$emoji] ?? 0;
            $hoverKey = $emoji.'|'.($commentId ?? 'null');
            $namesArr = (isset($reactionHover[$hoverKey]['names']) && is_array($reactionHover[$hoverKey]['names']))
                ? $reactionHover[$hoverKey]['names'] : [];
            $hasNames = count($namesArr) > 0;
        @endphp

        <div class="relative group"
             @if(($canInteract ?? true))
                 wire:mouseenter="loadReactionUsers('{{ $emoji }}', {{ $commentParam }})"
             @endif
        >
            <button type="button"
                    class="text-sm px-2 py-1 rounded-md {{ $btnCls }} hover:brightness-95 transition"
                    @if(($canInteract ?? true))
                        wire:click="toggleReaction('{{ $emoji }}', {{ $commentParam }})"
                    @else
                        disabled
                    @endif
            >
                <span class="align-middle">{{ $emoji }}</span>
                <span class="ml-1 text-xs text-zinc-600 dark:text-zinc-300">{{ $count }}</span>
            </button>

            <div class="absolute z-50 top-full mt-1 w-56 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg p-2 text-xs text-zinc-700 dark:text-zinc-200 hidden group-hover:block">
                @if($hasNames)
                    <div class="font-medium mb-1">Reagiert:</div>
                    <ul class="max-h-40 overflow-auto list-disc list-inside">
                        @foreach($namesArr as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-zinc-500">Noch niemand</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@else
@endif
