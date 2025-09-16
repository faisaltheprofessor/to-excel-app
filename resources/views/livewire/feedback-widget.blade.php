<div>
    <flux:dropdown>
        <flux:button icon="chat-bubble-oval-left" icon:variant="micro" icon:class="text-zinc-300">
            Ticket
        </flux:button>

        <flux:popover class="min-w-[30rem] flex flex-col gap-4"
                      x-data="filePicker()">

            <div class="flex items-center justify-between">
                <flux:heading size="sm">Neues Anliegen</flux:heading>
                <flux:button
                    variant="ghost" size="xs"
                    icon="list-bullet"
                    href="{{ route('feedback.index') }}"
                    wire:navigate
                >
                    Alle Anliegen
                </flux:button>
            </div>

            {{-- Typ --}}
            <flux:radio.group variant="buttons" class="*:flex-1" wire:model="type" label="Typ">
                <flux:radio icon="bug-ant" value="bug">Fehler</flux:radio>
                <flux:radio icon="light-bulb" value="suggestion">Vorschlag</flux:radio>
            </flux:radio.group>

            {{-- Priorität --}}
            <flux:radio.group wire:model="priority" label="Priorität" variant="pills" class="flex flex-wrap gap-2">
                <flux:radio value="low" label="Niedrig" />
                <flux:radio value="normal" label="Normal" />
                <flux:radio value="high" label="Hoch" />
                <flux:radio value="urgent" label="Dringend" />
            </flux:radio.group>
            @error('priority') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- Titel --}}
            <flux:input type="text" class="w-full" wire:model.defer="title" placeholder="Kurzer Titel" x-data="jiraBox('reply')" x-on:keydown="onKeydown($event)"/>
            @error('title') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- Beschreibung --}}
            <flux:textarea rows="6" class="w-full" wire:model.defer="message" placeholder="Beschreibung" x-data="jiraBox('reply')" x-on:keydown="onKeydown($event)"></flux:textarea>
            @error('message') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror

            {{-- Datei hinzufügen --}}
            <div>
                <input x-ref="filepick" type="file" class="hidden"
                       multiple @change="add($event)"
                       accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx" />

                <flux:button variant="filled" size="xs" icon="paper-clip"
                             x-on:click="$refs.filepick.click()">Dateien anhängen</flux:button>

                @error('uploads') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                @foreach ($errors->getMessages() as $k => $msgs)
                    @if (str_starts_with($k, 'uploads.'))
                        <div class="text-sm text-red-600">{{ implode(' ', $msgs) }}</div>
                    @endif
                @endforeach

                @if ($uploads)
                    <div class="text-xs text-zinc-500 mt-2">
                        Anhänge (max. 5): Bilder ≤ 10 MB, PDF/Office ≤ 20 MB, Videos ≤ 100 MB
                    </div>

                    <div class="grid grid-cols-2 gap-3 mt-2">
                        @foreach ($uploads as $i => $f)
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

                            <div class="relative border border-zinc-200 rounded-lg p-2 flex flex-col gap-2"
                                 wire:key="upload-{{ $i }}-{{ $keySig }}">
                                <button type="button"
                                        class="absolute -top-2 -right-2 bg-zinc-800 text-white rounded-full w-6 h-6 text-xs"
                                        @click.prevent="remove({{ $loop->index }})">×</button>

                                @if ($isImg)
                                    <img src="{{ $f->temporaryUrl() }}" alt="{{ $name }}" class="w-full h-36 object-cover rounded-lg">
                                @elseif ($isVid)
                                    <video src="{{ $f->temporaryUrl() }}" class="w-full max-h-40 rounded-lg" controls muted></video>
                                @elseif ($isPdf)
                                    <div class="text-sm flex items-center gap-2">
                                        <flux:icon.document class="w-5 h-5" /> PDF: {{ $name }}
                                    </div>
                                @elseif ($isDoc)
                                    <div class="text-sm flex items-center gap-2">
                                        <flux:icon.document-text class="w-5 h-5" /> Office: {{ $name }}
                                    </div>
                                @else
                                    <div class="text-sm truncate">{{ $name }}</div>
                                @endif

                                <div class="text-xs text-zinc-500 truncate">
                                    {{ $name }} • {{ $sizeMb }} MB
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Aktionen --}}
            <div class="flex justify-end gap-2">
                <flux:button size="sm" class="w-28" variant="primary" color="green"
                             wire:click="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Senden</span>
                    <span wire:loading wire:target="submit">Senden…</span>
                </flux:button>
            </div>

            <flux:modal.trigger name="feedback-success">
                <button type="button" id="open-feedback-success" class="hidden"></button>
            </flux:modal.trigger>
        </flux:popover>
    </flux:dropdown>

    {{-- Erfolg-Modal --}}
    <flux:modal name="feedback-success" class="min-w-[26rem]">
        <div class="space-y-4">
            <flux:heading size="lg">Vielen Dank!</flux:heading>
            <flux:text>Ihr Feedback wurde erfolgreich übermittelt.</flux:text>
            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">Schließen</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <script>
    function filePicker() {
        return {
            files: [],
            add(e) {
                const selected = Array.from(e.target.files || []);
                for (const f of selected) if (this.files.length < 5) this.files.push(f);
                this.sync();
                e.target.value = '';
            },
            remove(index) {
                this.files.splice(index, 1);
                this.sync();
            },
            sync() {
                this.$wire.uploadMultiple('uploads', this.files, () => {}, () => {});
            }
        }
    }

    document.addEventListener('feedback-sent', () => {
        const btn = document.getElementById('open-feedback-success');
        if (btn) btn.click();
        if (window.$flux?.popover?.close) window.$flux.popover.close();
    });
    </script>
</div>
