<div class="w-1/2 md:w-3/4 mx-auto p-6 space-y-6">
    <div class="flex items-center gap-3">
        <flux:heading size="lg" class="flex-1">Feedback</flux:heading>
        <a href="{{ route('importer.index') }}"
           class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">Zurück</a>
    </div>

    <flux:accordion class="p-2 rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <flux:accordion.item open>
            <flux:accordion.heading>Filter</flux:accordion.heading>
            <flux:accordion.content>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    <flux:input class="w-96" placeholder="Suche…" wire:model.debounce.live="q"/>

                    <flux:select class="w-56" wire:model.live="type">
                        <option value="all">Alle Typen</option>
                        <option value="bug">Fehler</option>
                        <option value="suggestion">Vorschlag</option>
                        <option value="question">Feedback</option>
                    </flux:select>

                    <flux:select class="w-56" wire:model.live="status">
                        <option value="all">Alle Status</option>
                        <option value="open">Offen</option>
                        <option value="in_progress">In Arbeit</option>
                        <option value="in_review">Im Review</option>   {{-- NEW --}}
                        <option value="in_test">Im Test</option>       {{-- NEW --}}
                        <option value="resolved">Gelöst</option>
                        <option value="closed">Geschlossen</option>
                        <option value="wontfix">Wird nicht behoben</option>
                    </flux:select>

                    <flux:select class="w-56" wire:model.live="priority">
                        <option value="all">Alle Prioritäten</option>
                        <option value="low">Niedrig</option>
                        <option value="normal">Normal</option>
                        <option value="high">Hoch</option>
                        <option value="urgent">Dringend</option>
                    </flux:select>

                    <flux:select class="w-56" wire:model.live="tag">
                        <option value="all">Alle Tags</option>
                        @foreach($allTags as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </flux:select>
                </div>
            </flux:accordion.content>
        </flux:accordion.item>
    </flux:accordion>

    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
        @forelse($items as $f)
            <a href="{{ route('feedback.show', $f) }}" wire:navigate
               class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                <div class="flex items-start gap-3">
                    <flux:icon.chat-bubble-left-ellipsis class="w-6 h-6 text-blue-600"/>
                    <div class="min-w-0 w-full">
                        {{-- badges --}}
                        <div class="flex items-center flex-wrap gap-2">
                            {{-- Typ --}}
                            <span class="text-xs rounded px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700">
                                @if($f->type==='bug')
                                    Bug
                                @elseif($f->type==='suggestion')
                                    Feature
                                @else
                                    Feedback
                                @endif
                            </span>

                            @php
                                $statusMap = [
                                    'open'        => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                    'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                                    'in_review'   => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200', // NEW
                                    'in_test'     => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200', // NEW
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
                                $prioDe = ['low'=>'Niedrig','normal'=>'Normal','high'=>'Hoch','urgent'=>'Dringend'];
                            @endphp

                            {{-- Status --}}
                            <span class="text-xs rounded px-2 py-0.5 {{ $statusMap[$f->status] ?? 'bg-zinc-100' }}">
                                {{ [
                                    'open'=>'Offen',
                                    'in_progress'=>'In Arbeit',
                                    'in_review'=>'Im Review',   // NEW
                                    'in_test'=>'Im Test',       // NEW
                                    'resolved'=>'Gelöst',
                                    'closed'=>'Geschlossen',
                                    'wontfix'=>'Wird nicht behoben'
                                ][$f->status] ?? $f->status }}
                            </span>

                            {{-- Priorität --}}
                            <span class="text-xs rounded px-2 py-0.5 {{ $prioMap[$f->priority ?? 'normal'] }}">
                                {{ $prioDe[$f->priority ?? 'normal'] }}
                            </span>

                            {{-- Autor + Zeit --}}
                            <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $f->user?->name ?? 'Anonym' }}</span>
                            <span class="text-xs text-zinc-500">{{ $f->created_at->format('d.m.Y H:i') }}</span>
                        </div>

                        {{-- Title --}}
                        <div class="mt-2 font-semibold text-base text-zinc-900 dark:text-zinc-100 line-clamp-1">
                            {{ $f->title }}
                        </div>

                        {{-- Description / message --}}
                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300 line-clamp-2">
                            {{ $f->message }}
                        </div>

                        {{-- Tags --}}
                        @if($f->tags)
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($f->tags as $tg)
                                    <span
                                        class="inline-flex items-center text-[11px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">#{{ $tg }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Meta info --}}
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
