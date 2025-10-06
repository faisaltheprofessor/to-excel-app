<flux:modal name="confirm-move" class="min-w-[32rem]">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">Verschiebung bestätigen</flux:heading>

            @if ($pendingSameParent && in_array($pendingPosition, ['before','after']))
                <flux:text class="mt-2 space-y-2">
                    <p>
                        Soll der Knoten <span class="font-semibold">innerhalb</span> von
                        <span class="font-semibold">{{ $pendingWithinParentName }}</span>
                        von <span class="font-semibold">Position {{ $pendingFromIndex + 1 }}</span>
                        nach <span class="font-semibold">Position {{ $pendingToIndex + 1 }}</span> verschoben werden?
                    </p>
                </flux:text>
            @else
                <flux:text class="mt-2 space-y-2">
                    <p>Sie sind dabei, diesen Knoten zu verschieben.</p>
                    <div class="text-sm space-y-1">
                        <div><span class="font-medium">Alter Pfad (Elternknoten):</span> {{ $pendingOldParentPathStr }}</div>
                        <div><span class="font-medium">Neuer Pfad (Elternknoten):</span> {{ $pendingNewParentPathStr }}</div>
                        @if ($pendingPosition === 'into')
                            <div class="text-xs text-zinc-500">Wird als <em>letztes Kind</em> des neuen Elternknotens eingefügt.</div>
                        @endif
                    </div>
                </flux:text>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <flux:spacer/>
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>
            <flux:modal.close>
                <flux:button variant="primary" color="green" icon="arrow-right" wire:click="confirmPendingMove">
                    Verschieben
                </flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
