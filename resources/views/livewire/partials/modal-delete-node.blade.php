<flux:modal name="delete-node" class="min-w-[28rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Knoten löschen?</flux:heading>
            <flux:text class="mt-2">
                <p>Sie sind dabei, den Knoten <span class="font-semibold">{{ $confirmDeleteNodeName }}</span> zu löschen.</p>
                <p class="text-sm mt-1 text-zinc-600">Pfad: {{ $confirmDeleteNodePathStr }}</p>
                <p>Diese Aktion kann nicht rückgängig gemacht werden.</p>
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:spacer/>
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:modal.close>
                <flux:button type="button" variant="danger" icon="trash" wire:click="confirmDeleteNode">
                    Knoten löschen
                </flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
