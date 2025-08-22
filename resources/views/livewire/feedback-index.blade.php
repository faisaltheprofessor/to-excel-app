<div class="p-6 space-y-6">
    <div class="flex items-center gap-3">
        <flux:heading size="lg" class="flex-1">Feedback</flux:heading>
        <a href="{{ route('importer.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">Zurück</a>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:input class="w-72" placeholder="Suche…" wire:model.debounce.300ms="q" />
        <flux:select class="w-56" wire:model.live="type">
            <option value="all">Alle Typen</option>
            <option value="bug">Fehler</option>
            <option value="suggestion">Vorschlag</option>
            <option value="question">Frage</option>
        </flux:select>
    </div>

    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
        @forelse($items as $f)
            <a href="{{ route('feedback.show', $f) }}" wire:navigate
               class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                <div class="flex items-start gap-3">
                    <flux:icon.chat-bubble-left-ellipsis class="w-6 h-6 text-blue-600" />
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs rounded px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700">
                                @if($f->type==='bug') Fehler
                                @elseif($f->type==='suggestion') Vorschlag
                                @else Frage
                                @endif
                            </span>
                            <span class="text-xs text-zinc-500">{{ $f->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="mt-1 font-medium line-clamp-2">{{ Str::limit($f->message, 140) }}</div>
                        <div class="mt-2 text-xs text-zinc-500">
                            {{ $f->comments()->count() }} Kommentare
                            @if(is_array($f->attachments) && count($f->attachments))
                                • {{ count($f->attachments) }} Anhang{{ count($f->attachments)>1?'e':'' }}
                            @endif
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="text-zinc-500">Noch kein Feedback vorhanden.</div>
        @endforelse
    </div>

    <div>
        {{ $items->links() }}
    </div>
</div>
