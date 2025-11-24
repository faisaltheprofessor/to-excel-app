<div class="w-3/4 md:w-1/2 mx-auto p-6 space-y-6">
    <div class="flex items-center gap-3">
        <flux:heading size="lg" class="flex-1">Organisationseinheit</flux:heading>
        <livewire:new-structure />
    </div>

    <div class="max-w-lg">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Suchen nach Titel …"
            clearable
        />
    </div>

    <div class="grid gap-4"
         style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
        @forelse ($trees as $t)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                <div class="flex items-start gap-3">
                    <a href="{{ route('importer.edit', $t->id) }}" wire:navigate
                       class="flex items-start gap-3 flex-1 min-w-0">
                        <flux:icon.folder class="w-6 h-6 text-blue-600" />
                        <div class="min-w-0">
                            <div class="font-medium truncate" title="{{ $t->title }}">{{ $t->title }}</div>
                            <div class="text-xs text-zinc-500 mt-1">
                                Aktualisiert: {{ $t->updated_at->format('d.m.Y H:i') }}
                                <br />
                                {{ $t->updated_at->diffForHumans() }}
                            </div>
                        </div>
                    </a>
                </div>

                <div class="mt-3 text-xs text-zinc-500 flex justify-between items-center">
                    <span>
                        {{ $t->node_count > 0 ? $t->node_count.' Knoten' : '—' }}
                    </span>

                    <div
                        x-data="makeExcelExporter(@js(route('importer.export', $t->id)))"
                        class="shrink-0"
                    >
                        <flux:dropdown>
                            <flux:button
                                variant="ghost"
                                size="xs"
                                title="Excel erzeugen"
                                class="p-0"
                            >
                                <div
                                    class="flex items-center gap-1 rounded-md border px-2 py-1 text-[0.7rem] transition"
                                    x-bind:class="busy
                                        ? 'border-zinc-300 dark:border-zinc-600 text-zinc-400 dark:text-zinc-500 cursor-wait opacity-60'
                                        : 'border-zinc-200 dark:border-zinc-600 text-emerald-600 dark:text-emerald-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 cursor-pointer'
                                    "
                                >
                                    <flux:icon.sheet class="w-4 h-4" />

                                    <span class="hidden sm:inline" x-show="!busy">
                                        Excel erzeugen
                                    </span>
                                    <span class="hidden sm:inline" x-show="busy">
                                        Wird erzeugt …
                                    </span>

                                    <svg
                                        x-show="busy"
                                        class="w-4 h-4 animate-spin"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </div>
                            </flux:button>

                            <flux:popover class="min-w-[18rem] space-y-3">
                                <div class="text-xs font-medium text-zinc-500">
                                    Arbeitsblätter
                                </div>

                                <div class="space-y-2 text-xs">
                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            x-model="ge"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        >
                                        <span>GE_Gruppenstruktur</span>
                                    </label>

                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            x-model="ablage"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        >
                                        <span>Strukt. Ablage Behörde</span>
                                    </label>

                                    <label class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            x-model="roles"
                                            class="rounded border-zinc-300 dark:border-zinc-600"
                                        >
                                        <span>Geschäftsrollen</span>
                                    </label>
                                </div>

                                <div class="text-xs space-y-1" x-show="roles">
                                    <div>Anzahl der Rollen-Platzhalter</div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="number"
                                            min="1"
                                            max="50"
                                            x-model.number="rolesCount"
                                            class="w-16 border border-zinc-300 dark:border-zinc-600 rounded px-1 py-0.5 text-xs bg-white dark:bg-zinc-800"
                                        />
                                        <span class="text-zinc-400">
                                            (1–50)
                                        </span>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <flux:button
                                        size="xs"
                                        x-bind:disabled="busy"
                                        @click.prevent="doExport()"
                                    >
                                        Excel erzeugen
                                    </flux:button>
                                </div>
                            </flux:popover>
                        </flux:dropdown>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-zinc-500 flex items-center gap-3">
                    keine OEs vorhanden.
                <livewire:new-structure />
            </div>
        @endforelse
    </div>
</div>

@once
    <script>
        window.makeExcelExporter = function (url) {
            return {
                busy: false,
                ge: true,
                ablage: true,
                roles: true,
                rolesCount: 10,

                async doExport() {
                    if (this.busy) return;

                    if (!this.ge && !this.ablage && !this.roles) {
                        alert('Bitte wählen Sie mindestens ein Arbeitsblatt.');
                        return;
                    }

                    if (isNaN(this.rolesCount)) this.rolesCount = 10;
                    if (this.rolesCount < 1) this.rolesCount = 1;
                    if (this.rolesCount > 50) this.rolesCount = 50;

                    this.busy = true;

                    try {
                        const params = new URLSearchParams();
                        params.set('ge', this.ge ? '1' : '0');
                        params.set('ablage', this.ablage ? '1' : '0');
                        params.set('roles', this.roles ? '1' : '0');
                        params.set('rolesCount', this.roles ? String(this.rolesCount) : '0');

                        const response = await fetch(url + '?' + params.toString());
                        if (!response.ok) throw new Error('Fehler beim Export');

                        const blob = await response.blob();
                        const downloadUrl = window.URL.createObjectURL(blob);

                        const disposition = response.headers.get('Content-Disposition') || '';
                        let filename = 'export.xlsx';
                        const match = disposition.match(/filename="?([^";]+)/);
                        if (match && match[1]) filename = match[1];

                        const a = document.createElement('a');
                        a.href = downloadUrl;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();

                        window.URL.revokeObjectURL(downloadUrl);
                    } catch (e) {
                        console.error(e);
                        alert('Excel-Export fehlgeschlagen.');
                    } finally {
                        this.busy = false;
                    }
                }
            };
        };
    </script>
@endonce
