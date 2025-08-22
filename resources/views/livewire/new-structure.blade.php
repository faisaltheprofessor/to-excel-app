<div>
    {{-- Trigger button you can place anywhere (e.g., in the page header) --}}
    <flux:modal.trigger name="create-structure">
        <flux:button icon="plus" class="cursor-pointer">Neu</flux:button>
    </flux:modal.trigger>

    {{-- The modal itself --}}
    <flux:modal name="create-structure" class="min-w-[28rem]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Neue Struktur anlegen</flux:heading>
                <flux:text class="mt-2">
                    <p>Bitte einen Namen für die Organisation/Struktur eingeben.</p>
                </flux:text>
            </div>

            <form wire:submit.prevent="create" class="space-y-3">
                <flux:input
                    wire:model.live="title"
                    placeholder="z. B. FM IKT"
                    autofocus
                />
                @error('title')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror

                <div class="flex items-center gap-2">
                    <flux:spacer/>
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" color="green" icon="arrow-right">
                        Anlegen &amp; öffnen
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
