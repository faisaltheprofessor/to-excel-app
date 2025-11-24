{{-- resources/views/livewire/partials/tree-minimap-node.blade.php --}}
@php
    $node             = is_array($node) ? $node : [];
    $path             = $path ?? [];
    $selectedNodePath = $selectedNodePath ?? null;

    $level = count($path);

    // Same palette as main tree (border + icon color)
    $palette = [
        ['border-red-300',    'text-red-500 dark:text-red-300'],
        ['border-orange-300', 'text-orange-500 dark:text-orange-300'],
        ['border-amber-300',  'text-amber-500 dark:text-amber-300'],
        ['border-lime-300',   'text-lime-600 dark:text-lime-300'],
        ['border-emerald-300','text-emerald-500 dark:text-emerald-300'],
        ['border-cyan-300',   'text-cyan-500 dark:text-cyan-300'],
        ['border-blue-300',   'text-blue-500 dark:text-blue-300'],
        ['border-indigo-300', 'text-indigo-500 dark:text-indigo-300'],
        ['border-violet-300', 'text-violet-500 dark:text-violet-300'],
        ['border-pink-300',   'text-pink-500 dark:text-pink-300'],
        ['border-slate-300',  'text-slate-500 dark:text-slate-300'],
    ];
    $c           = $palette[$level % count($palette)];
    $borderClass = $c[0];
    $iconClass   = $c[1];

    $isSelected  = isset($selectedNodePath) && $selectedNodePath === $path;

    // Mirror ausgeblendet state (enabled flag)
    $enabled     = array_key_exists('enabled', $node) ? (bool) $node['enabled'] : true;
    $isDisabled  = array_key_exists('enabled', $node) ? !$node['enabled'] : false;

    $name    = $node['name'] ?? '(ohne Name)';
    $appName = $node['appName'] ?? $name;

    $textClass = $isSelected
        ? 'font-semibold text-gray-900 dark:text-gray-50'
        : 'text-gray-600 dark:text-gray-200';
@endphp


<li
    class="truncate"
    data-minimap-node
    data-path='@json($path)'
>
    {{-- Row with icon + name:appName; clear disabled visual --}}
    <div class="flex items-center gap-2 px-1 rounded
                {{ $isSelected ? 'bg-gray-100 dark:bg-gray-700' : '' }}
                {{ $isDisabled ? 'bg-red-50 dark:bg-red-950/40' : '' }}">

        {{-- Folder icon in palette color --}}
        <flux:icon.folder class="w-3 h-3 {{ $iconClass }} flex-shrink-0"/>

        <button
            type="button"
            class="flex items-center gap-1 text-left w-full truncate text-[11px] cursor-pointer
                   {{ $textClass }}
                   hover:text-blue-600 dark:hover:text-blue-300"
            wire:click="selectNode({{ json_encode($path) }})"
            title="{{ $name }} : {{ $appName }}{{ $isDisabled ? ' (ausgeblendet)' : '' }}"
        >
            {{-- Name : appName (clean, compact, both struck through if disabled) --}}
            <span class="truncate {{ $isDisabled ? 'line-through' : '' }}">
                {{ $name }}
            </span>

            <span class="text-gray-400 dark:text-gray-300">:</span>

            <span class="truncate text-[10px] text-gray-500 dark:text-gray-300 italic {{ $isDisabled ? 'line-through' : '' }}">
                {{ $appName }}
            </span>
        </button>
    </div>

    {{-- Children --}}
    @if(!empty($node['children']) && is_array($node['children']))
        <ul class="pl-3 mt-0.5 space-y-0.5 border-l border-dashed {{ $borderClass }}">
            @foreach($node['children'] as $childIndex => $childNode)
                @include('livewire.partials.tree-minimap-node', [
                    'node'             => $childNode,
                    'path'             => array_merge($path, [$childIndex]),
                    'selectedNodePath' => $selectedNodePath,
                ])
            @endforeach
        </ul>
    @endif
</li>
