@php $pad = min(3, $level); @endphp

<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 pl-{{ 3 + $pad*2 }}">
    {{-- header --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="font-medium">{{ $comment->user?->name ?? 'Anonym' }}</span>
        <span class="text-zinc-500 text-xs">{{ $comment->created_at->diffForHumans() }}</span>

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
        <div class="mt-2 space-y-2">
            <textarea rows="3" wire:model.defer="editingCommentBody"
                      class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"></textarea>
            @error('editingCommentBody') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- Existing attachments with remove --}}
            @php $exAtts = $editingCommentExisting ?? []; @endphp
            @if(count($exAtts))
                <div class="mt-2">
                    <div class="text-xs text-zinc-500 mb-1">Vorhandene Anhänge</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($exAtts as $i => $att)
                            @php
                                $label = $att['name'] ?? basename($att['path'] ?? 'datei');
                                $url   = $att['url'] ?? '#';
                                $mime  = $att['mime'] ?? '';
                                $isImg = $mime && str_starts_with($mime, 'image/');
                                $isVid = $mime && str_starts_with($mime, 'video/');
                                $isPdf = $mime === 'application/pdf';
                                $ext   = strtolower(pathinfo($label, PATHINFO_EXTENSION));
                                $isDoc = in_array($ext, ['doc','docx','xls','xlsx']);
                            @endphp
                            <div class="relative border border-zinc-200 dark:border-zinc-700 rounded p-2">
                                <button type="button"
                                        class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                        wire:click="removeEditingExisting({{ $i }})">×</button>
                                @if($isImg)
                                    <a href="{{ $url }}" target="_blank" rel="noopener">
                                        <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-28 object-cover rounded">
                                    </a>
                                @elseif($isVid)
                                    <video src="{{ $url }}" controls playsinline class="w-full max-h-28 rounded"></video>
                                @elseif($isPdf)
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon.document class="w-5 h-5 text-zinc-400" />
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $label }}</a>
                                    </div>
                                @elseif($isDoc)
                                    <div class="flex items-center gap-2 text-sm">
                                        <flux:icon.document-text class="w-5 h-5 text-zinc-400" />
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $label }}</a>
                                    </div>
                                @else
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline text-sm">{{ $label }}</a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Add new files while editing --}}
            <div class="mt-2">
                <div class="flex items-center gap-2">
                    <input x-ref="editCommentFile{{ $comment->id }}"
                           type="file"
                           class="hidden"
                           multiple
                           accept="image/*,video/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                           @change="$wire.uploadMultiple('editingCommentNewUploads', Array.from($event.target.files), ()=>{}, ()=>{}); $event.target.value = '';">
                    <button type="button"
                            class="text-xs rounded-md px-2 py-1 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700"
                            x-on:click="$refs.editCommentFile{{ $comment->id }}.click()">
                        Weitere Dateien anhängen
                    </button>
                    <div class="text-[11px] text-zinc-500">Bilder ≤ 10 MB · PDF/Office ≤ 20 MB · Videos ≤ 100 MB</div>
                </div>

                @error('editingCommentNewUploads') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
                @foreach ($errors->getMessages() as $k => $msgs)
                    @if (str_starts_with($k, 'editingCommentNewUploads.'))
                        <div class="text-sm text-red-600">{{ implode(' ', $msgs) }}</div>
                    @endif
                @endforeach

                @if ($editingCommentNewUploads)
                    <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach ($editingCommentNewUploads as $i => $f)
                            @php
                                $mime   = $f->getMimeType();
                                $name   = $f->getClientOriginalName();
                                $sizeMb = number_format(($f->getSize() ?? 0) / 1024 / 1024, 2);
                                $isImg  = str_starts_with($mime, 'image/');
                                $isVid  = str_starts_with($mime, 'video/');
                                $isPdf  = $mime === 'application/pdf';
                                $ext    = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                $isDoc  = in_array($ext, ['doc','docx','xls','xlsx'], true);
                                $keySig = md5(($name ?? '').'|'.($f->getSize() ?? 0).'|'.($mime ?? ''));
                            @endphp

                            <div class="relative border border-zinc-200/60 dark:border-zinc-700 rounded-lg p-2"
                                 wire:key="edit-new-{{ $comment->id }}-{{ $i }}-{{ $keySig }}">
                                <button type="button"
                                        class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                        wire:click="removeEditingNewUpload({{ $i }})">×</button>

                                @if($isImg)
                                    <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-28 object-cover rounded">
                                @elseif($isVid)
                                    <video src="{{ $f->temporaryUrl() }}" class="w-full max-h-28 rounded" controls muted playsinline></video>
                                @elseif($isPdf)
                                    <div class="text-sm flex items-center gap-2">
                                        <flux:icon.document class="w-5 h-5" />
                                        <span>{{ $name }}</span>
                                    </div>
                                @elseif($isDoc)
                                    <div class="text-sm flex items-center gap-2">
                                        <flux:icon.document-text class="w-5 h-5" />
                                        <span>{{ strtoupper($ext) }}: {{ $name }}</span>
                                    </div>
                                @else
                                    <div class="text-sm truncate">{{ $name }}</div>
                                @endif

                                <div class="mt-1 text-xs text-zinc-500">{{ $sizeMb }} MB</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95"
                        wire:click="saveEditComment">Speichern</button>
                <button type="button" class="text-xs rounded-md px-2 py-1 border border-zinc-300 dark:border-zinc-700"
                        wire:click="cancelEditComment">Abbrechen</button>
            </div>
        </div>
    @else
        <div class="mt-1 whitespace-pre-wrap break-words">{{ $comment->body }}</div>

        {{-- comment attachments (thumbnails/links) --}}
        @php
            $cAtts = is_array($comment->attachments ?? null) ? $comment->attachments : [];
        @endphp
        @if(count($cAtts))
            <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($cAtts as $att)
                    @php
                        $label = $att['name'] ?? basename($att['path'] ?? 'datei');
                        $url   = $att['url'] ?? '#';
                        $mime  = $att['mime'] ?? '';
                        $isImg = $mime && str_starts_with($mime, 'image/');
                        $isVid = $mime && str_starts_with($mime, 'video/');
                        $isPdf = $mime === 'application/pdf';
                        $ext   = strtolower(pathinfo($label, PATHINFO_EXTENSION));
                        $isDoc = in_array($ext, ['doc','docx','xls','xlsx']);
                    @endphp

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded p-2 text-sm">
                        @if($isImg)
                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-32 object-cover rounded">
                            </a>
                        @elseif($isVid)
                            <video src="{{ $url }}" controls playsinline class="w-full max-h-32 rounded"></video>
                        @elseif($isPdf)
                            <div class="flex items-center gap-2">
                                <flux:icon.document class="w-5 h-5 text-zinc-400" />
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $label }}</a>
                            </div>
                        @elseif($isDoc)
                            <div class="flex items-center gap-2">
                                <flux:icon.document-text class="w-5 h-5 text-zinc-400" />
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $label }}</a>
                            </div>
                        @else
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">{{ $label }}</a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
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

    {{-- inline reply kept as-is (you already have attachments on main composer) --}}
    @if($replyTo === $comment->id && $canInteract)
        {{-- (optional) you could reuse the same composer-with-attachments UI here too --}}
        <div class="mt-3">
            <textarea
                rows="2"
                wire:model.defer="reply"
                placeholder="Antwort schreiben … (mit @Name erwähnen)"
                class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
            ></textarea>
            @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95" wire:click="send">Senden</button>
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-transparent hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setReplyTo(null)">Abbrechen</button>
            </div>
        </div>
    @endif
</div>
