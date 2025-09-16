@php
    use Illuminate\Support\Facades\Storage;
    /** @var \App\Models\FeedbackComment $comment */
    $pad  = min(3, $level);
    $atts = is_array($comment->attachments ?? null) ? $comment->attachments : [];
@endphp

<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 pl-{{ 3 + $pad*2 }}">
    {{-- header --}}
    <div class="flex items-center gap-2 text-sm">
        <span class="font-medium">{{ $comment->user?->name ?? 'Anonym' }}</span>
        <span class="text-zinc-500 text-xs">{{ $comment->created_at->diffForHumans() }}</span>

        {{-- edited badge --}}
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
        <div class="mt-2 space-y-2" x-data="filePicker('editingCommentUploads')">
            {{-- EDIT TEXTAREA + MENTIONS --}}
            <div class="relative"
                 x-data="textAssist({ fetchMentions: (q) => $wire.call('searchMentions', q) })">
                <textarea
                    rows="3"
                    wire:model.defer="editingCommentBody"
                    x-ref="field"
                    x-on:keydown="onKeydown($event)"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                ></textarea>

                {{-- mentions dropdown (per-field, works in nested DOM) --}}
                <template x-if="open">
                    <div
                        class="absolute z-[9999] mt-1 w-80 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow"
                        x-on:click.outside="close"
                        style="left:0; top:100%;"
                    >
                        <template x-for="(u,i) in results" :key="u.id ?? i">
                            <div
                                class="px-3 py-2 cursor-pointer"
                                :class="{'bg-blue-50 dark:bg-zinc-800/60': i===highlight}"
                                x-on:mouseenter="highlight=i"
                                x-on:click="pick(u)"
                            >
                                <div class="font-medium" x-text="u.name || u.email || 'User'"></div>
                                <div class="text-xs text-gray-500" x-text="u.email || ''"></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            @error('editingCommentBody') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- existing attachments with remove --}}
            @if(count($atts))
                <div>
                    <div class="text-xs text-zinc-500 mb-1">Bestehende Anhänge</div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($atts as $i => $att)
                            @php
                                $url  = $att['url'] ?? (isset($att['path']) ? Storage::disk('public')->url($att['path']) : '#');
                                $mime = $att['mime'] ?? '';
                                $label= $att['name'] ?? basename($att['path'] ?? 'Datei');
                                $isImg = $mime && str_starts_with($mime,'image/');
                            @endphp
                            <div class="relative border rounded-md p-2">
                                <button type="button"
                                        class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                        wire:click="removeExistingCommentAttachment({{ $i }})">×</button>
                                @if($isImg)
                                    <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-28 object-cover rounded">
                                @else
                                    <a href="{{ $url }}" target="_blank" class="text-blue-600 hover:underline">{{ $label }}</a>
                                @endif
                                @if(isset($removeCommentAttachmentIdx[$i]))
                                    <div class="mt-1 text-[11px] text-rose-600">Zum Entfernen markiert</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- add new files (uniform button) --}}
            <div class="flex items-center gap-2">
                <input x-ref="file" type="file" class="hidden" multiple
                       accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx"
                       @change="add($event)">
                <button type="button"
                        class="text-sm rounded-md px-3 py-1.5 border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="$refs.file.click()">
                    📎 Datei(en) anhängen
                </button>
                <div class="text-[11px] text-zinc-500">Bilder ≤ 10MB, PDF/Office ≤ 20MB, Videos ≤ 100MB</div>
            </div>

            {{-- previews of new uploads --}}
            @if($editingCommentUploads)
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($editingCommentUploads as $i => $f)
                        @php
                            $mime=$f->getMimeType(); $isImg=str_starts_with($mime,'image/'); $name=$f->getClientOriginalName();
                        @endphp
                        <div class="relative border rounded-md p-2" wire:key="editCommentUp-{{ $i }}-{{ md5($name.($f->getSize()??0).$mime) }}">
                            <button type="button" class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                    wire:click="removeEditingCommentUpload({{ $i }})">×</button>
                            @if($isImg)
                                <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-28 object-cover rounded">
                            @else
                                <div class="text-sm truncate">{{ $name }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-2 flex items-center gap-2">
                <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95"
                        wire:click="saveEditComment">Speichern</button>
                <button type="button" class="text-xs rounded-md px-2 py-1 border border-zinc-300 dark:border-zinc-700"
                        wire:click="cancelEditComment">Abbrechen</button>
            </div>
        </div>
    @else
        {{-- read-only body --}}
        <div class="mt-1 whitespace-pre-wrap break-words">{{ $comment->body }}</div>

        {{-- attachments (read-only) --}}
        @if(count($atts))
            <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($atts as $att)
                    @php
                        $url  = $att['url'] ?? (isset($att['path']) ? Storage::disk('public')->url($att['path']) : '#');
                        $mime = $att['mime'] ?? '';
                        $label= $att['name'] ?? basename($att['path'] ?? 'Datei');
                        $isImg = $mime && str_starts_with($mime,'image/');
                        $isVid = $mime && str_starts_with($mime,'video/');
                        $isPdf = $mime === 'application/pdf';
                        $ext   = strtolower(pathinfo($label, PATHINFO_EXTENSION));
                        $isDoc = in_array($ext, ['doc','docx','xls','xlsx']);
                    @endphp
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-md p-2 text-sm">
                        @if($isImg)
                            <a href="{{ $url }}" target="_blank" rel="noopener">
                                <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-28 object-cover rounded">
                            </a>
                        @elseif($isVid)
                            <video src="{{ $url }}" controls playsinline class="w-full max-h-36 rounded"></video>
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
                @include('livewire.partials.feedback-comment', [
                    'comment'          => $child,
                    'level'            => $level+1,
                    'canInteract'      => $canInteract,
                    'commentEditedMap' => $commentEditedMap
                ])
            @endforeach
        </div>
    @endif

    {{-- inline reply (with matching upload button) --}}
    @if($replyTo === $comment->id && $canInteract)
        <div class="mt-3 space-y-2" x-data="filePicker('replyUploads')">
            <div class="relative"
                 x-data="textAssist({ fetchMentions: (q) => $wire.call('searchMentions', q) })">
                <textarea
                    rows="2"
                    wire:model.defer="reply"
                    x-ref="field"
                    x-on:keydown="onKeydown($event)"
                    placeholder="Antwort schreiben … (mit @Name erwähnen)"
                    class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                ></textarea>

                {{-- mentions dropdown for inline reply --}}
                <template x-if="open">
                    <div
                        class="absolute z-[9999] mt-1 w-80 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow"
                        x-on:click.outside="close"
                        style="left:0; top:100%;"
                    >
                        <template x-for="(u,i) in results" :key="u.id ?? i">
                            <div
                                class="px-3 py-2 cursor-pointer"
                                :class="{'bg-blue-50 dark:bg-zinc-800/60': i===highlight}"
                                x-on:mouseenter="highlight=i"
                                x-on:click="pick(u)"
                            >
                                <div class="font-medium" x-text="u.name || u.email || 'User'"></div>
                                <div class="text-xs text-gray-500" x-text="u.email || ''"></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <input x-ref="file" type="file" class="hidden" multiple
                           accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx"
                           @change="add($event)">
                    <button type="button"
                            class="text-sm rounded-md px-3 py-1.5 border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="$refs.file.click()">
                        📎 Datei(en) anhängen
                    </button>
                    <div class="text-[11px] text-zinc-500">Bilder ≤ 10MB, PDF/Office ≤ 20MB, Videos ≤ 100MB</div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="text-xs rounded-md px-2 py-1 bg-blue-600 text-white hover:brightness-95" wire:click="send">Senden</button>
                    <button type="button" class="text-xs rounded-md px-2 py-1 bg-transparent hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setReplyTo(null)">Abbrechen</button>
                </div>
            </div>

            @if($replyUploads)
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($replyUploads as $i => $f)
                        @php
                            $mime=$f->getMimeType(); $isImg=str_starts_with($mime,'image/'); $isVid=str_starts_with($mime,'video/');
                            $name=$f->getClientOriginalName();
                        @endphp
                        <div class="relative border rounded-md p-2" wire:key="replyInline-{{ $comment->id }}-{{ $i }}-{{ md5($name.($f->getSize()??0).$mime) }}">
                            <button type="button" class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                    wire:click="removeReplyUpload({{ $i }})">×</button>
                            @if($isImg)
                                <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-28 object-cover rounded">
                            @elseif($isVid)
                                <video src="{{ $f->temporaryUrl() }}" class="w-full max-h-36 rounded" controls playsinline muted></video>
                            @else
                                <div class="text-sm truncate">{{ $name }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
