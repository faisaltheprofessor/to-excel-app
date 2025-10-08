<div class="w-1/2 m:w-3/4 mx-auto h-screen overflow-hidden flex flex-col">
    <div class="p-6 pb-2 flex items-center gap-3 shrink-0">
        <flux:input value="{{ $title }}" disabled class="flex-1" />
        <span class="px-2 py-0.5 rounded bg-zinc-200 text-zinc-800 text-xs">
            Version v{{ $version->version_number }} · {{ strtoupper($version->status) }}
        </span>
        <a href="{{ route('importer.edit', $model->id) }}" class="text-sm underline">Zum aktuellen Entwurf</a>
    </div>
    <flux:separator class="mb-3" />
    <div class="px-6 pb-24 grow min-h-0">
        <flux:card class="h-full overflow-auto" data-tree-root>
            <div class="pr-2">
                <ul class="space-y-1 pb-28">
                    @foreach ($tree as $index => $node)
                        @include('livewire.partials.tree-node', [
                            'node' => $node,
                            'path' => [$index],
                            'editable' => false,
                        ])
                    @endforeach
                </ul>
            </div>
        </flux:card>
    </div>
</div>
