<div class="p-6 space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:heading size="lg" class="flex-1">Organisationseinheit</flux:heading>

        {{-- Neu = open new tab that creates a draft and redirects to editor --}}
        <a href="{{ route('importer.new') }}" wire:navigate>
            <flux:button variant="primary" class="cursor-pointer">Neu</flux:button>
        </a>
    </div>

    {{-- Search --}}
    <div class="max-w-lg">
        <flux:input
            wire:model.debounce.300ms="search"
            placeholder="Suchen nach Titel …"
        />
    </div>

    {{-- Tiles grid --}}
    <div class="grid gap-4"
         style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
        @forelse ($trees as $t)
            <a href="{{ route('importer.edit', $t->id) }}" wire:navigate
               class="block rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                <div class="flex items-start gap-3">
                    <flux:icon.folder class="w-6 h-6 text-blue-600" />
                    <div class="min-w-0">
                        <div class="font-medium truncate" title="{{ $t->title }}">{{ $t->title }}</div>
                        <div class="text-xs text-zinc-500 mt-1">
                            Aktualisiert: {{ $t->updated_at->format('d.m.Y H:i') }}
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-xs text-zinc-500 line-clamp-2">
                    {{ is_array($t->data) ? (count($t->data).' Knoten (Top-Level)') : '—' }}
                </div>
            </a>
        @empty
            <div class="text-zinc-500">Noch keine OEs vorhanden.</div>
        @endforelse
    </div>
</div>
