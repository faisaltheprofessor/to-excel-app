@php
    use Illuminate\Support\Facades\Storage;

    $statusDe = [
        'open'       => 'Offen',
        'in_progress'=> 'In Arbeit',
        'resolved'   => 'Gelöst',
        'in_review'  => 'Im Review',
        'closed'     => 'Geschlossen',
        'wontfix'    => 'Wird nicht behoben'
    ];

    $prioDe = [
        'low'      => 'Niedrig',
        'normal'   => 'Normal',
        'high'     => 'Hoch',
        'urgent'   => 'Dringend'
    ];

    $attachmentsRaw = is_array($attachments ?? null) ? $attachments : [];
    $tags           = is_array($tags ?? null) ? $tags : [];
    $tagSuggestions = is_array($tagSuggestions ?? null) ? $tagSuggestions : [];

    $attachments = [];
    foreach ($attachmentsRaw as $idx => $item) {
        if (is_string($item)) {
            // Old style: only stored path
            $attachments[] = [
                'label' => basename($item),
                'url'   => Storage::disk('public')->url($item), // ✅ build proper public URL
                'mime'  => null,
                'size'  => null,
            ];
        } elseif (is_array($item)) {
            // New style: array with path+url+meta
            $label = $item['name'] ?? basename($item['path'] ?? ('file-'.$idx));
            $url   = $item['url'] ?? (isset($item['path']) ? Storage::disk('public')->url($item['path']) : '#');

            $attachments[] = [
                'label' => $label,
                'url'   => $url, // ✅ always store as "url"
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
                    wire:target="status,priority,assigneeId"
                    class="{{ $metaDirty ? '' : 'hidden' }}"
                >
                    <button type="button"
                            wire:click.prevent="saveMeta"
                            wire:target="status,priority,assigneeId"
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
                <div class="space-y-2">
                    <input type="text" wire:model.defer="editTitle"
                           class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                           placeholder="Titel" />
                    <textarea rows="6" wire:model.defer="editMessage"
                              class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                              placeholder="Beschreibung"></textarea>
                </div>
            @else
                <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-[15px] leading-6">
                    {{ $feedback->message }}
                </div>
            @endif

            {{-- Attachments --}}
@if(count($attachments))
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
                        {{-- ✅ Image preview --}}
                        <a href="{{ $url }}" target="_blank" rel="noopener">
                            <img src="{{ $url }}"
                                 alt="{{ $label }}"
                                 class="w-full h-40 object-cover rounded-md">
                        </a>
                    @elseif($isVid)
                        {{-- ✅ Video player --}}
                        <video src="{{ $url }}" controls playsinline
                               class="w-full max-h-48 rounded-md"></video>
                    @elseif($isPdf)
                        <div class="flex items-center gap-2">
                            <flux:icon.document class="w-5 h-5 text-zinc-400" />
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="text-blue-600 hover:underline">
                                {{ $label }}
                            </a>
                        </div>
                    @elseif($isDoc)
                        <div class="flex items-center gap-2">
                            <flux:icon.document-text class="w-5 h-5 text-zinc-400" />
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="text-blue-600 hover:underline">
                                {{ $label }}
                            </a>
                        </div>
                    @else
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="text-blue-600 hover:underline">
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


            {{-- Tags --}}
            @if(count($tags))
                <div class="space-y-2">
                    <div class="text-xs text-zinc-500">Tags</div>
                    <div class="flex flex-wrap items-center gap-1">
                        @foreach($tags as $i => $tg)
                            <span class="inline-flex items-center gap-1 text-[11px] rounded-full px-2 py-0.5 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                                #{{ $tg }}
                                <button type="button" wire:click="removeTag({{ $i }})"
                                        class="ml-1 text-xs {{ $canModifyFeedback ? 'cursor-pointer' : 'opacity-50 pointer-events-none' }}"
                                        {{ $canModifyFeedback ? '' : 'disabled' }}
                                        title="{{ $canModifyFeedback ? 'Tag entfernen' : 'Nicht erlaubt' }}">×</button>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tag suggestions --}}
            @if(count($tagSuggestions) && $canModifyFeedback)
                <div class="space-y-1">
                    <div class="text-xs text-zinc-500">Vorschläge</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($tagSuggestions as $sug)
                            <button type="button"
                                    class="text-xs rounded-md px-2 py-1 bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 cursor-pointer"
                                    wire:click="addTag('{{ $sug }}')">
                                #{{ $sug }}
                            </button>
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

    {{-- Comments --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white/60 dark:bg-zinc-900/40">
        <div class="p-6 space-y-5">
            <h3 class="text-md font-semibold">Kommentare</h3>

            {{-- Composer --}}
            <div class="{{ $canInteract ? '' : 'opacity-60 pointer-events-none' }}"
                 x-data="composeBox({
                    getText:   () => $refs.replyTa?.value ?? '',
                    setText:   (v) => { if ($refs.replyTa) { $refs.replyTa.value = v; $wire.set('reply', v) } },
                    setQuery:  (q) => $wire.set('mentionQuery', q),
                    open:      () => $wire.set('mentionOpen', true),
                    close:     () => $wire.call('closeMentions'),
                    isOpen:    () => $wire.get('mentionOpen'),
                    results:   () => $wire.get('mentionResults'),
                 })"
                 class="space-y-2 relative">

                <textarea x-ref="replyTa" rows="3" wire:model.defer="reply"
                          placeholder="{{ $canInteract ? 'Antwort schreiben … (mit @Namen erwähnen)' : 'Geschlossen – keine Kommentare möglich' }}"
                          class="w-full rounded-md border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-2"
                          {{ $canInteract ? '' : 'disabled' }}
                          x-on:input="$data.detect()"
                          x-on:click="$data.detect()"
                          x-on:keydown="$data.onKeydown($event)"></textarea>

                @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                {{-- Mentions dropdown --}}
                <div class="absolute z-50 mt-1 w-80 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow"
                     x-show="$wire.mentionOpen" x-transition x-on:click.outside="$wire.call('closeMentions')">
                    <div class="px-3 py-2 text-xs text-zinc-500 border-b border-zinc-100 dark:border-zinc-700">
                        Personen erwähnen
                    </div>

                    @if(empty($mentionResults))
                        <div class="px-3 py-2 text-sm text-zinc-500">Keine Treffer</div>
                    @else
                        <ul class="max-h-64 overflow-auto">
                            @foreach($mentionResults as $u)
                                <li>
                                    <button type="button"
                                            class="w-full text-left px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                                            data-name="{{ $u['name'] }}"
                                            x-on:click="$data.insert('@' + $el.dataset.name + ' ')">
                                        <div class="flex flex-col">
                                            <span>{{ '@'.$u['name'] }}</span>
                                            @if(!empty($u['email'] ?? null))
                                                <small class="text-[11px] text-zinc-500">{{ $u['email'] }}</small>
                                            @endif
                                        </div>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="flex items-center gap-2 justify-end">
                    <button type="button"
                            class="mt-2 text-sm rounded-md px-3 py-1.5 bg-blue-600 text-white hover:bg-blue-700 cursor-pointer"
                            wire:click="send">
                        Senden
                    </button>
                </div>
            </div>

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

<script>
    /* Fallback: if helpers weren’t loaded by the parent (e.g., direct route to feedback-show) */
    if (!window.composeBox) {
        window.composeBox = function(api) {
            const jiraMap = new Map([ ['/', '✅'], ['x', '❌'], ['y', '👍'] ]);
            return {
                range:null,
                textarea(){ return this.$refs.replyTa; },
                setCaret(el,pos){ try{ el?.focus(); el?.setSelectionRange(pos,pos); }catch(e){} },
                detect(){
                    const ta=this.textarea(); if(!ta)return;
                    const v=ta.value??'', p=ta.selectionStart??0, pre=v.slice(0,p);
                    const m=pre.match(new RegExp('@([\\p{L}\\p{M}\\.\\- ]{1,50})$','u'));
                    if(m){ const q=(m[1]||'').trim(); this.range={start:p-m[0].length,end:p}; api.setQuery(q); if(q.length>0) api.open(); }
                    else { this.range=null; api.setQuery(''); api.close(); }
                },
                insert(text){
                    if(!this.range)return; const ta=this.textarea(); if(!ta)return;
                    const v=ta.value??''; const nv=v.slice(0,this.range.start)+text+v.slice(this.range.end);
                    ta.value=nv; api.setText(nv); const np=this.range.start+text.length; this.setCaret(ta,np);
                    api.setQuery(''); api.close(); this.range=null;
                },
                replaceJiraToken(triggerKey){
                    const ta=this.textarea(); if(!ta)return false;
                    const val=ta.value??'', pos=ta.selectionStart??0, leftRaw=val.slice(0,pos), right=val.slice(pos);
                    const ws=leftRaw.match(/[ \t\r\n]+$/); const trailing=ws?ws[0]:''; const left=trailing?leftRaw.slice(0,leftRaw.length-trailing.length):leftRaw;
                    const m=left.match(/\(([^\s()]{1,10})\)$/i); if(!m) return false;
                    const emoji=({'/':'✅','x':'❌','y':'👍'})[(m[1]||'').toLowerCase()]; if(!emoji) return false;
                    const newLeft=left.slice(0,left.length-m[0].length)+emoji;
                    let trigger=''; if(triggerKey===' ')trigger=' '; else if(triggerKey==='Enter')trigger='\n'; else if(triggerKey==='Tab')trigger='\t';
                    const newVal=newLeft+trailing+trigger+right; api.setText(newVal); const newPos=(newLeft+trailing+trigger).length; this.setCaret(ta,newPos);
                    this.range=null; api.setQuery(''); api.close(); return true;
                },
                onKeydown(e){ if([' ','Enter','Tab'].includes(e.key)){ if(this.replaceJiraToken(e.key)){ e.preventDefault(); return; } } queueMicrotask(()=>this.detect()); }
            };
        };
        window.mentionBox = function(api){ return window.composeBox(api); };
    }
</script>
