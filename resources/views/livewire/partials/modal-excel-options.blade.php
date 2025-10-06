<flux:modal name="excel-options" class="min-w-[28rem]" wire:model="excelOptionsOpen">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Excel-Optionen</flux:heading>
            <flux:text class="mt-2 space-y-1 text-sm">
                <p>Wählen Sie die zu erzeugenden Arbeitsblätter, die Anzahl der Rollen-Platzhalter
                und den gewünschten Dateinamen für den Export.</p>
            </flux:text>

            <div class="mt-4 space-y-5">
                <flux:input
                    type="text"
                    id="excelFilename"
                    name="downloadFilename"
                    wire:model.defer="downloadFilename"
                    label="Dateiname"
                    placeholder="Importer-Datei-{{ $title ?? '' }}"
                    class="w-full"
                />
                <p class="text-xs text-gray-500 mt-1">
                    Wird automatisch auf <strong>Importer-Datei-{{ $title ?? '' }}</strong> gesetzt, falls leer.
                    Die Endung <code>.xlsx</code> wird automatisch hinzugefügt.
                </p>

                <flux:checkbox.group label="Arbeitsblätter auswählen">
                    <flux:checkbox label="GE_Gruppenstruktur" wire:model="sheetGE" />
                    <flux:checkbox label="Strukt. Ablage Behörde" wire:model="sheetAblage" />
                    <flux:checkbox label="Geschäftsrollen" wire:model="sheetRoles" />
                </flux:checkbox.group>

                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Anzahl Geschäftsrollen-Platzhalter:
                    </span>
                    <flux:input
                        type="number"
                        min="1"
                        max="50"
                        wire:model.live="rolesPlaceholderCount"
                        class="w-24 text-center"
                        :disabled="! $sheetRoles"
                    />
                </div>

                @error('generate')
                    <div class="text-sm text-red-600 mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:spacer/>
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>

            <flux:button
                variant="primary"
                color="green"
                icon="sheet"
                wire:click="generateExcel"
                class="cursor-pointer"
            >
                Excel erzeugen
            </flux:button>
        </div>
    </div>
</flux:modal>
