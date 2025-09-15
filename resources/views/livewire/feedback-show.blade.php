@php
    $statusDe = [
        'open'=>'Offen','in_progress'=>'In Arbeit','in_review'=>'Im Review','in_test'=>'Im Test',
        'resolved'=>'Gelöst','closed'=>'Geschlossen','wontfix'=>'Wird nicht behoben'
    ];
    $prioDe = ['low'=>'Niedrig','normal'=>'Normal','high'=>'Hoch','urgent'=>'Dringend'];

    $attachments    = is_array($attachments ?? null) ? $attachments : [];
    $tags           = is_array($tags ?? null) ? $tags : [];
    $tagSuggestions = is_array($tagSuggestions ?? null) ? $tagSuggestions : [];
@endphp

<div class="p-6 space-y-8">
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
                    @php $t = $feedback->type; @endphp
                    {{ $t==='bug' ? 'Fehler' : (($t==='feature' || $t==='suggestion') ? 'Vorschlag' : (($t==='feedback' || $t==='question') ? 'Feedback' : ucfirst($t))) }}
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

            {{-- Quick edit bar (NO forms) --}}
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

                {{-- Aktualisieren: appears immediately when any select is dirty --}}
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
                <div class="space-y-1">
                    <div class="text-xs text-zinc-500">Anhänge</div>
                    <ul class="text-sm space-y-1">
                        @foreach($attachments as $i => $path)
                            <li>
                                <a class="text-blue-600 hover:underline" href="{{ route('feedback.file', [$feedback, $i]) }}">
                                    {{ basename($path) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
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

            {{-- Composer (mentions + Jira tokens) --}}
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
                          x-on:input="detectMentions()"
                          x-on:click="detectMentions()"
                          x-on:keydown="onKeydown($event)"></textarea>

                @error('reply') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                {{-- Mentions dropdown --}}
                <div class="absolute z-50 mt-1 w-72 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow"
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
                                            data-name="{{ e($u['name']) }}"
                                            x-on:click="insert('@' + $el.dataset.name + ' ')">
                                        <span>@{{ $u['name'] }}</span>
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

    {{-- ===== Flux Modals (no Blade directives inside) ===== --}}
    <flux:modal wire:model.self="showHistoryModal" class="md:w-96">
        <div class="space-y-4 p-4"
             x-data="{ t: '', html: '' }"
             x-init="
                $watch(() => $wire.historyTitle, v => t = v);
                $watch(() => $wire.historyHtml,  v => html = v);
                t = $wire.historyTitle; html = $wire.historyHtml;
             ">
            <div><h3 class="text-base font-semibold" x-text="t"></h3></div>
            <div class="text-sm space-y-2" x-html="html"></div>
            <div class="flex">
                <div class="flex-1"></div>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="closeHistory">Schließen</button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showCloseModal" class="md:w-96" :dismissible="false">
        <div class="space-y-6 p-4">
            <div>
                <h3 class="text-base font-semibold">Ticket abschließen?</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    Wenn der Status auf <strong>Abgeschlossen</strong> gesetzt wird, sind weitere Änderungen, Kommentare und Reaktionen nicht mehr möglich.
                </p>
            </div>
            <div class="flex">
                <div class="flex-1"></div>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border border-zinc-300 dark:border-zinc-700 mr-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="cancelCloseSelection">Abbrechen</button>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700 cursor-pointer"
                        wire:click="confirmCloseInfo">Verstanden</button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteConfirm" class="md:w-96">
        <div class="space-y-6 p-4">
            <div>
                <h3 class="text-base font-semibold">Feedback wirklich löschen?</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                    Das Feedback wird <strong>archiviert (Soft Delete)</strong>. Du kannst es später wiederherstellen.
                </p>
            </div>
            <div class="flex">
                <div class="flex-1"></div>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md border border-zinc-300 dark:border-zinc-700 mr-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer"
                        wire:click="cancelDelete">Abbrechen</button>
                <button type="button"
                        class="text-xs px-3 py-1.5 rounded-md bg-rose-600 text-white hover:bg-rose-700 cursor-pointer"
                        wire:click="confirmDelete">Löschen</button>
            </div>
        </div>
    </flux:modal>
</div>

<script>
    /**
     * Unified composer: @mentions + Jira tokens ( (y) (/) (x) -> 👍 ✅ ❌ )
     * Usage: x-data="composeBox({...})" + textarea x-ref="replyTa"
     */
    window.composeBox = function(api) {
        const AT = '@';
        const jiraMap = new Map([ ['/', '✅'], ['x', '❌'], ['y', '👍'] ]);

        return {
            range: null,

            textarea(){ return this.$refs.replyTa; },
            setCaret(el, pos){ el.focus(); el.setSelectionRange(pos, pos); },

            // Mentions
            detectMentions(){
                const ta = this.textarea(); if (!ta) return;
                const v = ta.value ?? '';
                const pos = ta.selectionStart ?? 0;
                const pre = v.slice(0, pos);
                const m = pre.match(new RegExp(AT+'([\\p{L}\\p{M}\\.\\- ]{1,50})$','u'));
                if (m) {
                    const q = (m[1] || '').trim();
                    this.range = { start: pos - m[0].length, end: pos };
                    api.setQuery(q);
                    if (q.length > 0) api.open();
                } else {
                    this.range = null; api.setQuery(''); api.close();
                }
            },

            insert(text){
                if (!this.range) return;
                const ta = this.textarea(); const v = ta.value ?? '';
                const nv = v.slice(0, this.range.start) + text + v.slice(this.range.end);
                ta.value = nv; api.setText(nv);
                const np = this.range.start + text.length;
                this.setCaret(ta, np);
                api.setQuery(''); api.close(); this.range = null;
            },

            // Jira tokens before caret: (y) (/) (x)
            replaceJiraToken(triggerKey){
                const ta = this.textarea(); if (!ta) return false;
                const val = ta.value ?? '';
                const pos = ta.selectionStart ?? 0;
                const leftRaw = val.slice(0, pos);
                const right   = val.slice(pos);

                const ws = leftRaw.match(/[ \t\r\n]+$/);
                const trailingWS = ws ? ws[0] : '';
                const left = trailingWS ? leftRaw.slice(0, leftRaw.length - trailingWS.length) : leftRaw;

                const m = left.match(/\(([^\s()]{1,10})\)$/i);
                if (!m) return false;

                const token = (m[1] || '').toLowerCase();
                if (!jiraMap.has(token)) return false;

                const emoji = jiraMap.get(token);
                const newLeft = left.slice(0, left.length - m[0].length) + emoji;

                let triggerChar = '';
                if (triggerKey === ' ') triggerChar = ' ';
                else if (triggerKey === 'Enter') triggerChar = '\n';
                else if (triggerKey === 'Tab') triggerChar = '\t';

                const newVal = newLeft + trailingWS + triggerChar + right;
                api.setText(newVal);
                const newPos = (newLeft + trailingWS + triggerChar).length;
                this.setCaret(ta, newPos);

                // after replacement, also re-check mentions state
                this.range = null; api.setQuery(''); api.close();
                return true;
            },

            onKeydown(e){
                if ([' ', 'Enter', 'Tab'].includes(e.key)) {
                    const replaced = this.replaceJiraToken(e.key);
                    if (replaced) { e.preventDefault(); return; }
                }
                queueMicrotask(() => this.detectMentions());
            }
        };
    };
</script>
