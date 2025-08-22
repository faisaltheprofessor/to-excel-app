<div>
    <flux:dropdown>
    <flux:button icon="chat-bubble-oval-left" icon:variant="micro" icon:class="text-zinc-300">
        Feedback
    </flux:button>

    <flux:popover class="min-w-[30rem] flex flex-col gap-4" x-data>

        {{-- Typ --}}
        <flux:radio.group variant="buttons" class="*:flex-1" wire:model="type">
            <flux:radio icon="bug-ant" value="bug"        :checked="$type==='bug'">Fehler melden</flux:radio>
            <flux:radio icon="light-bulb" value="suggestion" :checked="$type==='suggestion'">Vorschlag</flux:radio>
            <flux:radio icon="question-mark-circle" value="question" :checked="$type==='question'">Frage</flux:radio>
        </flux:radio.group>

        {{-- Text --}}
        <div class="relative">
            <flux:textarea
                rows="8"
                class="dark:bg-transparent!"
                wire:model.defer="message"
                placeholder="Bitte beschreiben Sie Ihr Anliegen. Bei Fehlern gern Schritte zur Reproduktion angeben."
            />
            @error('message')
                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
            @enderror

            {{-- Attachments toolbar --}}
            <div class="absolute bottom-3 left-3 flex items-center gap-2">
                <input x-ref="filepick" type="file" class="hidden"
                       wire:model="uploads" multiple accept="image/*,video/*">
                <flux:button
                    variant="filled" size="xs" icon="paper-clip"
                    icon:class="text-zinc-400 dark:text-zinc-300"
                    x-on:click="$refs.filepick.click()"
                >
                    <span class="sr-only">Dateien anhängen</span>
                </flux:button>
            </div>
        </div>

        {{-- Selected files + errors --}}
        <div class="space-y-2">
            @error('uploads') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            @error('uploads.*') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

            @if ($uploads)
                <div class="text-xs text-zinc-500">Anhänge (max. 5, je 10&nbsp;MB):</div>
                <ul class="text-sm space-y-1">
                    @foreach ($uploads as $i => $f)
                        <li class="flex items-center gap-2">
                            <flux:icon.paper-clip class="w-4 h-4 text-zinc-400"/>
                            <span class="truncate">{{ $f->getClientOriginalName() }}</span>
                            <span class="text-xs text-zinc-500">
                                ({{ number_format($f->getSize() / 1024 / 1024, 2) }} MB)
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex gap-2 justify-end">
             <a href="{{ route('feedback.index') }}" class="text-sm text-zinc-600 dark:text-zinc-300 hover:underline">
        Alle Feedbacks
    </a>

            <flux:button variant="filled" size="sm" kbd="esc" class="w-28"
                         x-on:click="$flux.popover.close()">
                Abbrechen
            </flux:button>

            <flux:button size="sm" kbd="⏎" class="w-28"
                         wire:click="submit"
                         wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Senden</span>
                <span wire:loading wire:target="submit">Senden…</span>
            </flux:button>
        </div>

        {{-- Hidden trigger to open success modal --}}
        <flux:modal.trigger name="feedback-success">
            <button type="button" id="open-feedback-success" class="hidden"></button>
        </flux:modal.trigger>
    </flux:popover>
</flux:dropdown>

{{-- Success modal --}}
<flux:modal name="feedback-success" class="min-w-[26rem]">
    <div class="space-y-4">
        <flux:heading size="lg">Vielen Dank!</flux:heading>
        <flux:text>Ihr Feedback wurde erfolgreich übermittelt.</flux:text>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="primary">Schließen</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>

@script
<script>
document.addEventListener('feedback-sent', () => {
    // Close the popover and open the success modal
    const btn = document.getElementById('open-feedback-success');
    if (btn) btn.click();
    // optional: close any open popover programmatically if needed
    if (window.$flux?.popover?.close) { window.$flux.popover.close(); }
}, { once: false });
</script>
@endscript

</div>
