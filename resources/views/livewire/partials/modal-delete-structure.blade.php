<flux:modal name="delete-structure" class="min-w-[22rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Struktur löschen?</flux:heading>
            <flux:text class="mt-2">
                <p>Sie sind dabei, diese Struktur zu löschen.</p>
                <p>Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer/>
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger" wire:click="delete()">Struktur löschen</flux:button>
        </div>
    </div>
</flux:modal>
