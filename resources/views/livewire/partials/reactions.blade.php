@php
    use Illuminate\Support\Facades\Auth;

    $uid = Auth::id();
    $rx = $targetFeedback->reactions()
        ->selectRaw('emoji, COUNT(*) as c, comment_id')
        ->where('comment_id', $commentId)
        ->groupBy('emoji','comment_id')
        ->pluck('c','emoji');

    $userHas = [];
    foreach ($quickEmojis as $e) {
        $userHas[$e] = $targetFeedback->reactions()
            ->where('comment_id', $commentId)
            ->where('emoji', $e)
            ->where('user_id', $uid)
            ->exists();
    }
@endphp

<div class="flex items-center gap-2">
    @foreach($quickEmojis as $e)
        @php
            $mine = $userHas[$e] ?? false;
            $btnCls = $mine
                ? 'bg-emerald-50 dark:bg-emerald-900/20 ring-1 ring-emerald-200 dark:ring-emerald-800'
                : 'bg-zinc-50 dark:bg-zinc-800/60 ring-1 ring-zinc-200 dark:ring-zinc-700';
            $key = $e.'|'.($commentId ?? 'null');
        @endphp

        <div class="relative group"
             wire:mouseenter="loadReactionUsers('{{ $e }}', {{ $commentId ? $commentId : 'null' }})">
            <button type="button"
                    class="text-sm px-2 py-1 rounded-md {{ $btnCls }} hover:brightness-95 transition"
                    wire:click="toggleReaction('{{ $e }}', {{ $commentId ? $commentId : 'null' }})">
                <span class="align-middle">{{ $e }}</span>
                <span class="ml-1 text-xs text-zinc-600 dark:text-zinc-300">{{ $rx[$e] ?? 0 }}</span>
            </button>

            <div class="absolute z-50 top-full mt-1 w-56 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg p-2 text-xs text-zinc-700 dark:text-zinc-200 hidden group-hover:block">
                @if(!empty($reactionHover[$key]['names'] ?? []))
                    <div class="font-medium mb-1">Reagiert:</div>
                    <ul class="max-h-40 overflow-auto list-disc list-inside">
                        @foreach(($reactionHover[$key]['names'] ?? []) as $n)
                            <li>{{ $n }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-zinc-500">Noch niemand</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
