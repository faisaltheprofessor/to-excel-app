@php
    use Illuminate\Support\Facades\Storage;

    $statusDe = [
        'open'        => 'Offen',
        'in_progress' => 'In Arbeit',
        'resolved'    => 'Gelöst',
        'in_review'   => 'Im Review',
        'closed'      => 'Geschlossen',
        'wontfix'     => 'Wird nicht behoben'
    ];

    $prioDe = [
        'low'    => 'Niedrig',
        'normal' => 'Normal',
        'high'   => 'Hoch',
        'urgent' => 'Dringend'
    ];

    $attachmentsRaw = is_array($attachments ?? null) ? $attachments : [];
    $tags           = is_array($tags ?? null) ? $tags : [];
    $tagSuggestions = is_array($tagSuggestions ?? null) ? $tagSuggestions : [];

    $attachments = [];
    foreach ($attachmentsRaw as $idx => $item) {
        if (is_string($item)) {
            $attachments[] = [
                'label' => basename($item),
                'url'   => Storage::disk('public')->url($item),
                'mime'  => null,
                'size'  => null,
            ];
        } elseif (is_array($item)) {
            $label = $item['name'] ?? basename($item['path'] ?? ('file-'.$idx));
            $url   = $item['url'] ?? (isset($item['path']) ? Storage::disk('public')->url($item['path']) : '#');

            $attachments[] = [
                'label' => $label,
                'url'   => $url,
                'mime'  => $item['mime'] ?? null,
                'size'  => $item['size'] ?? null,
            ];
        }
    }

    $humanSize = function ($b) {
        if (!is_numeric($b)) return null;
        $u = ['B','KB','MB','GB'];
        $i = 0;
        while ($b >= 1024 && $i < count($u)-1) { $b /= 1024; $i++; }
        return number_format($b, $i ? 2 : 0).' '.$u[$i];
    };
@endphp

<div class="w-1/2 m:w-3/4 mx-auto p-6 space-y-8">
    {{-- Header --}}
    <div class="flex items-start justify-between">
        <h1 class="text-lg font-semibold">Feedback</h1>
        <a href="{{ route('feedback.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">
            Zurück zur Übersicht
        </a>
    </div>

    {{-- Main card --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white/60 dark:bg-zinc-900/40">
        <div class="p-6 space-y-5">
            {{-- Title row + “(bearbeitet)” + edit controls --}}
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl md:text-2xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $feedback->title }}
                    </h2>
                    @if($feedbackEdited)
                        <button type="button"
                                class="text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300
                                       hover:bg-zinc-200 dark:hover:bg-zinc-700 cursor-pointer"
                                wire:click="openFeedbackHistory">
                            (bearbeitet)
                        </button>
                    @endif
                </div>

                @if($canModifyFeedback)
                    @if(!$editingFeedback)
                        <button type="button"
                                class="text-xs px-2 py-1 rounded-md border border-zinc-300 dark:border-zinc-700
                                       hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                wire:click="startEditFeedback">Bearbeiten</button>
                    @else
                        <div class="flex gap-2">
                            <button type="button"
                                    class="text-xs px-2 py-1 rounded-md bg-blue-600 text-white hover:bg-blue-700 cursor-pointer"
                                    wire:click="saveEditFeedback">Speichern</button>
                            <button type="button"
                                    class="text-xs px-2 py-1 rounded-md border border-zinc-300 dark:border-zinc-700
                                           hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                    wire:click="cancelEditFeedback">Abbrechen</button>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Meta badges --}}
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700">
                    {{ $feedback->type==='bug' ? 'Fehler' : 'Vorschlag' }}
                </span>
                <span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">
                    {{ $statusDe[$status] ?? ucfirst($status) }}
                </span>
                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                    {{ $prioDe[$priority] ?? ucfirst($priority) }}
                </span>
                @if($feedback->assignee)
                    <span class="px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800">
                        Zugewiesen: {{ $feedback->assignee->name }}
                    </span>
                @endif
                <span class="text-zinc-400">•</span>
                <span class="text-zinc-500">{{ $feedback->user?->name ?? 'Anonym' }}</span>
                <span class="text-zinc-500">{{ $feedback->created_at->format('d.m.Y H:i') }}</span>
            </div>

            {{-- Quick edit bar --}}
            <div class="flex flex-wrap items-center gap-3 rounded-xl bg-zinc-50 dark:bg-zinc-900/40 p-3">
                {{-- Typ --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Typ</span>
                    <select
                        wire:model.defer="type"
                        class="h-7 text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2
                               hover:bg-zinc-50 dark:hover:bg-zinc-800
                               {{ $canEditType ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}"
                        {{ $canEditType ? '' : 'disabled' }}
                        title="{{ $canEditType ? 'Typ ändern' : 'Typ gesperrt' }}"
                    >
                        <option value="bug">Fehler</option>
                        <option value="suggestion">Vorschlag</option>
                    </select>
                </div>

                {{-- Status --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Status</span>
                    <select
                        wire:model.defer="status"
                        class="h-7 text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2
                               hover:bg-zinc-50 dark:hover:bg-zinc-800
                               {{ $canEditStatus ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}"
                        {{ $canEditStatus ? '' : 'disabled' }}
                        title="{{ $canEditStatus ? 'Status ändern' : 'Status gesperrt' }}"
                    >
                        @foreach(\App\Models\Feedback::STATUSES as $s)
                            <option value="{{ $s }}">{{ $statusDe[$s] ?? ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Priorität --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Priorität</span>
                    <select
                        wire:model.defer="priority"
                        class="h-7 text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2
                               hover:bg-zinc-50 dark:hover:bg-zinc-800
                               {{ $canEditPriority ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}"
                        {{ $canEditPriority ? '' : 'disabled' }}
                        title="{{ $canEditPriority ? 'Priorität ändern' : 'Priorität gesperrt' }}"
                    >
                        @foreach(\App\Models\Feedback::PRIORITIES as $p)
                            <option value="{{ $p }}">{{ $prioDe[$p] ?? ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Zugewiesen an --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-500">Zugewiesen an</span>
                    <select
                        wire:model.defer="assigneeId"
                        class="h-7 text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2
                               hover:bg-zinc-50 dark:hover:bg-zinc-800
                               {{ $canEditAssignee ? 'cursor-pointer' : 'opacity-50 cursor-not-allowed' }}"
                        {{ $canEditAssignee ? '' : 'disabled' }}
                        title="{{ $canEditAssignee ? 'Zuweisen' : 'Zuweisung gesperrt' }}"
                    >
                        <option value="">— Niemand —</option>
                        @foreach($assignableUsers as $u)
                            <option value="{{ $u['id'] }}">{{ $u['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1"></div>

                {{-- Aktualisieren --}}
                <div
                    wire:dirty.class.remove="hidden"
                    wire:target="type,status,priority,assigneeId"
                    class="{{ $metaDirty ? '' : 'hidden' }}"
                >
                    <button type="button"
                            wire:click.prevent="saveMeta"
                            wire:target="type,status,priority,assigneeId"
                            wire:dirty.remove.attr="disabled"
                            wire:dirty.class.remove="opacity-50 cursor-not-allowed"
                            class="text-xs px-3 py-1.5 rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900
                                   hover:bg-zinc-100 dark:hover:bg-zinc-800 transition
                                   opacity-50 cursor-not-allowed"
                            disabled
                            title="Aktualisieren">
                        Aktualisieren
                    </button>
                </div>

                {{-- Löschen / Wiederherstellen --}}
                @if(is_null($feedback->deleted_at))
                    @if($canModifyFeedback)
                        <button type="button"
                                class="text-xs px-2 py-1 rounded-md bg-rose-600 text-white hover:bg-rose-700 cursor-pointer"
                                wire:click="askDelete">
                            Löschen
                        </button>
                    @endif
                @else
                    @if($feedback->user_id === auth()->id())
                        <button type="button"
                                class="text-xs px-2 py-1 rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900
                                       hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                                wire:click="restoreFeedback">
                            Wiederherstellen
                        </button>
                    @endif
                @endif
            </div>

            {{-- Title/Message view or editor --}}
            @if($editingFeedback)
                <div class="space-y-3">
                    <input type="text" wire:model.defer="editTitle"
                           class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                           placeholder="Titel" />
                    <textarea rows="6" wire:model.defer="editMessage" x-data="jiraBox('reply')"
  x-on:keydown="onKeydown($event)"
                              class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                              placeholder="Beschreibung"></textarea>

                    {{-- Existing ticket attachments with remove --}}
                    @php $existing = is_array($feedback->attachments ?? null) ? $feedback->attachments : []; @endphp
                    @if(count($existing))
                        <div>
                            <div class="text-xs text-zinc-500 mb-1">Bestehende Anhänge</div>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach($existing as $i => $att)
                                    @php
                                        $url  = $att['url'] ?? (isset($att['path']) ? Storage::disk('public')->url($att['path']) : '#');
                                        $mime = $att['mime'] ?? '';
                                        $label= $att['name'] ?? basename($att['path'] ?? 'Datei');
                                        $isImg = $mime && str_starts_with($mime,'image/');
                                    @endphp
                                    <div class="relative border rounded-md p-2">
                                        <button type="button"
                                                class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                                wire:click="removeExistingFeedbackAttachment({{ $i }})">×</button>
                                        @if($isImg)
                                            <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-32 object-cover rounded">
                                        @else
                                            <a href="{{ $url }}" target="_blank" class="text-blue-600 hover:underline">{{ $label }}</a>
                                        @endif
                                        @if(isset($removeFeedbackAttachmentIdx[$i]))
                                            <div class="mt-1 text-[11px] text-rose-600">Zum Entfernen markiert</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Add new ticket files (uniform button) --}}
                    <div class="space-y-2" x-data="filePicker('editUploads')">
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

                        @if($editUploads)
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach($editUploads as $i => $f)
                                    @php
                                        $mime=$f->getMimeType(); $isImg=str_starts_with($mime,'image/'); $name=$f->getClientOriginalName();
                                    @endphp
                                    <div class="relative border rounded-md p-2" wire:key="editUp-{{ $i }}-{{ md5($name.($f->getSize()??0).$mime) }}">
                                        <button type="button" class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                                wire:click="removeEditUpload({{ $i }})">×</button>
                                        @if($isImg)
                                            <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-32 object-cover rounded">
                                        @else
                                            <div class="text-sm truncate">{{ $name }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-[15px] leading-6">
                    {{ $feedback->message }}
                </div>
            @endif

            {{-- Ticket attachments (read-only when not editing) --}}
            @if(!$editingFeedback && count($attachments))
                <div class="space-y-2">
                    <div class="text-xs text-zinc-500">Anhänge</div>

                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($attachments as $att)
                            @php
                                $mime   = $att['mime'] ?? '';
                                $label  = $att['label'] ?? 'Datei';
                                $url    = $att['url'] ?? '#';

                                $isImg  = $mime && str_starts_with($mime, 'image/');
                                $isVid  = $mime && str_starts_with($mime, 'video/');
                                $isPdf  = $mime === 'application/pdf';
                                $ext    = strtolower(pathinfo($label, PATHINFO_EXTENSION));
                                $isDoc  = in_array($ext, ['doc','docx','xls','xlsx']);
                            @endphp

                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 text-sm">
                                @if($isImg)
                                    <a href="{{ $url }}" target="_blank" rel="noopener">
                                        <img src="{{ $url }}" alt="{{ $label }}" class="w-full h-40 object-cover rounded-md">
                                    </a>
                                @elseif($isVid)
                                    <video src="{{ $url }}" controls playsinline class="w-full max-h-48 rounded-md"></video>
                                @elseif($isPdf)
                                    <div class="flex items-center gap-2">
                                        <flux:icon.document class="w-5 h-5 text-zinc-400" />
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                                            {{ $label }}
                                        </a>
                                    </div>
                                @elseif($isDoc)
                                    <div class="flex items-center gap-2">
                                        <flux:icon.document-text class="w-5 h-5 text-zinc-400" />
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                                            {{ $label }}
                                        </a>
                                    </div>
                                @else
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue-600 hover:underline">
                                        {{ $label }}
                                    </a>
                                @endif

                                @if(!empty($att['size']))
                                    <div class="text-xs text-zinc-500 mt-1">
                                        {{ $humanSize($att['size']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Reactions (top-level) --}}
            @include('livewire.partials.reactions', [
                'targetFeedback' => $feedback,
                'commentId'      => null,
                'quickEmojis'    => $this->quickEmojis,
                'canInteract'    => $canInteract,
            ])
        </div>
    </div>

    {{-- Comments (sticky composer + scrollable list) --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white/60 dark:bg-zinc-900/40">
        <div class="p-6 flex flex-col gap-5 max-h-[70vh] overflow-hidden">
            {{-- Header + Sort --}}
            <div class="flex items-center justify-between">
                <h3 class="text-md font-semibold">Kommentare</h3>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-zinc-500">Sortieren:</span>
                    <select
                        wire:model.live="commentSort"
                        class="h-7 text-sm rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2"
                        title="Kommentare sortieren"
                    >
                        <option value="newest">Neueste zuerst</option>
                        <option value="oldest">Älteste zuerst</option>
                    </select>
                </div>
            </div>

            {{-- Composer (sticky) --}}
            <div class="{{ $canInteract ? '' : 'opacity-60 pointer-events-none' }}
                        sticky top-0 z-10 bg-white/90 dark:bg-zinc-900/90 backdrop-blur supports-[backdrop-filter]:bg-white/60
                        -mx-6 px-6 pt-2 pb-3 border-b border-zinc-200/60 dark:border-zinc-700/50">
                <div class="space-y-2" x-data="filePicker('replyUploads')">
                    <textarea rows="3" wire:model.defer="reply"
                              x-data="jiraBox('reply')"
  x-on:keydown="onKeydown($event)"
                              placeholder="{{ $canInteract ? 'Antwort schreiben … (mit @Namen erwähnen)' : 'Geschlossen – keine Kommentare möglich' }}"
                              class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                              {{ $canInteract ? '' : 'disabled' }}></textarea>
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
                            <div class="text-[11px] text-zinc-500">
                                Bilder ≤ 10MB, PDF/Office ≤ 20MB, Videos ≤ 100MB
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    class="text-sm rounded-md px-3 py-1.5 bg-blue-600 text-white hover:bg-blue-700 cursor-pointer"
                                    wire:click="send">
                                Senden
                            </button>
                        </div>
                    </div>

                    @if($replyUploads)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-3">
                            @foreach($replyUploads as $i => $f)
                                @php
                                    $mime=$f->getMimeType(); $isImg=str_starts_with($mime,'image/'); $isVid=str_starts_with($mime,'video/');
                                    $name=$f->getClientOriginalName();
                                @endphp
                                <div class="relative border rounded-md p-2" wire:key="replyUp-{{ $i }}-{{ md5($name.($f->getSize()??0).$mime) }}">
                                    <button type="button"
                                            class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                            wire:click="removeReplyUpload({{ $i }})">×</button>
                                    @if($isImg)
                                        <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-32 object-cover rounded">
                                    @elseif($isVid)
                                        <video src="{{ $f->temporaryUrl() }}" class="w-full max-h-40 rounded" controls playsinline muted></video>
                                    @else
                                        <div class="text-sm truncate">{{ $name }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- List (scrollable) --}}
            <div class="overflow-y-auto pr-1" wire:key="comments-{{ $commentSort }}-{{ $feedback->id }}">
                <div class="space-y-4">
                    @foreach($rootComments as $c)
                        @include('livewire.partials.feedback-comment', [
                            'comment'           => $c,
                            'level'             => 0,
                            'canInteract'       => $canInteract,
                            'commentEditedMap'  => $commentEditedMap,
                        ])
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Flux Modals ===== --}}
    <flux:modal wire:model.self="showHistoryModal" class="md:w-96">
        <div class="space-y-4 p-4"
             x-data="{ t: $wire.historyTitle, html: $wire.historyHtml }"
             x-init="$watch(() => $wire.historyTitle, v => t = v); $watch(() => $wire.historyHtml, v => html = v);">
            <h3 class="text-base font-semibold" x-text="t"></h3>
            <div class="text-sm space-y-2" x-html="html"></div>
            <div class="flex justify-end">
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="closeHistory">Schließen</button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showCloseModal" class="md:w-96" :dismissible="false">
        <div class="space-y-6 p-4">
            <h3 class="text-base font-semibold">Ticket abschließen?</h3>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                Wenn der Status auf <strong>Abgeschlossen</strong> gesetzt wird, sind weitere Änderungen, Kommentare und Reaktionen nicht mehr möglich.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="cancelCloseSelection">Abbrechen</button>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700 cursor-pointer"
                        wire:click="confirmCloseInfo">Verstanden</button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteConfirm" class="md:w-96">
        <div class="space-y-6 p-4">
            <h3 class="text-base font-semibold">Feedback wirklich löschen?</h3>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                Das Feedback wird <strong>archiviert (Soft Delete)</strong>. Du kannst es später wiederherstellen.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="cancelDelete">Abbrechen</button>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md bg-rose-600 text-white hover:bg-rose-700 cursor-pointer"
                        wire:click="confirmDelete">Löschen</button>
            </div>
        </div>
    </flux:modal>
</div>

{{-- File picker helper used for reply, comment edit, and ticket edit --}}
<script>
function filePicker(model) {
    return {
        files: [],
        add(e) {
            const selected = Array.from(e.target.files || []);
            for (const f of selected) this.files.push(f);
            this.sync(); e.target.value = '';
        },
        remove(i) { this.files.splice(i, 1); this.sync(); },
        sync() {
            // merge locally picked files into Livewire's array instead of replacing it
            this.$wire.uploadMultiple(model, this.files, () => {}, () => {});
        }
    };
}
</script>
