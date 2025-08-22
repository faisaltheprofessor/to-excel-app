<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Excel Generator</title>
    @vite("resources/css/app.css")
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left"/>

    <flux:brand href="#" logo="#" name="Digitale Akte" class="max-lg:hidden dark:hidden"/>
    <flux:brand href="#" logo="#" name="Digitale Akte" class="max-lg:hidden! hidden dark:flex"/>

    <flux:navbar class="-mb-px max-lg:hidden">
        <flux:navbar.item icon="home" href="/importer" wire:navigate>Home</flux:navbar.item>

        <flux:separator vertical variant="subtle" class="my-2"/>

    </flux:navbar>

    <flux:spacer/>

    <flux:navbar class="me-4">
        <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle"
                     aria-label="Toggle dark mode"/>

        <livewire:feedback-widget />
    </flux:navbar>

   <flux:dropdown>
    <flux:navbar.item icon:trailing="chevron-down">
        {{ Str::title(Auth::user()->name) }}
    </flux:navbar.item>

    <flux:navmenu>
        <flux:menu.item
            x-data
            icon="arrow-right-start-on-rectangle"
            class="cursor-pointer"
            x-on:click.prevent="document.getElementById('logout-form').submit()"
        >
            Logout
        </flux:menu.item>
    </flux:navmenu>
</flux:dropdown>
</flux:header>

<flux:sidebar stashable sticky
              class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border rtl:border-r-0 rtl:border-l border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark"/>

    <flux:brand href="#" logo="#" name="Digitale Akte" class="px-2 dark:hidden"/>
    <flux:brand href="#" logo="#" name="Digitale Akte" class="px-2 hidden dark:flex"/>

    <flux:navlist variant="outline">
        <flux:navlist.item icon="home" href="#">Home</flux:navlist.item>
        <flux:navlist.item icon="inbox" wire:navigate>Importer</flux:navlist.item>

    </flux:navlist>

    <flux:spacer/>

    <flux:navlist variant="outline">
        <flux:navlist.item icon="cog-6-tooth" href="#">Settings</flux:navlist.item>
        <flux:navlist.item icon="information-circle" href="#">Help</flux:navlist.item>
    </flux:navlist>
</flux:sidebar>

<flux:main container>
    <flux:heading size="xl" level="1">Hallo, {{ Str::title(Auth::user()->name) }}</flux:heading>

    <flux:text class="mt-2 mb-6 text-base">Importer-Datei leicht gemacht</flux:text>

    {{ $slot }}
    <flux:separator variant="subtle"/>
</flux:main>


<!-- Logout Form -->
<form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
    @csrf
</form>
@fluxScripts
</body>
</html>
