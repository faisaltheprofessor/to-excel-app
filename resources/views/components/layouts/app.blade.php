<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Excel Generator</title>
    @vite("resources/css/app.css")
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:header container class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:brand href="#" logo="#" name="Digitale Akte" class="max-lg:hidden dark:hidden" />
        <flux:brand href="#" logo="#" name="Digitale Akte" class="max-lg:hidden! hidden dark:flex" />

    <flux:navbar class="-mb-px max-lg:hidden">
        <flux:navbar.item icon="home" href="#">Home</flux:navbar.item>
        <flux:navbar.item icon="inbox" href="/importer">Importer</flux:navbar.item>

        <flux:separator vertical variant="subtle" class="my-2"/>

        </flux:navbar>

    <flux:spacer />

    <flux:navbar class="me-4">
        <flux:navbar.item icon="magnifying-glass" href="#" label="Search" />
        <flux:navbar.item class="max-lg:hidden" icon="cog-6-tooth" href="#" label="Settings" />
        <flux:navbar.item class="max-lg:hidden" icon="information-circle" href="#" label="Help" />
    </flux:navbar>

    <flux:dropdown position="top" align="start">
        <flux:profile avatar="#" />

        <flux:menu>
            <flux:menu.radio.group>
                <flux:menu.radio checked>Habibi</flux:menu.radio>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.item icon="arrow-right-start-on-rectangle">Logout</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</flux:header>

<flux:sidebar stashable sticky class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border rtl:border-r-0 rtl:border-l border-zinc-200 dark:border-zinc-700">
    <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

    <flux:brand href="#" logo="#" name="Digitale Akte" class="px-2 dark:hidden" />
        <flux:brand href="#" logo="#" name="Digitale Akte" class="px-2 hidden dark:flex" />

    <flux:navlist variant="outline">
        <flux:navlist.item icon="home" href="#">Home</flux:navlist.item>
        <flux:navlist.item icon="inbox" wire:navigate>Importer</flux:navlist.item>

    </flux:navlist>

    <flux:spacer />

    <flux:navlist variant="outline">
        <flux:navlist.item icon="cog-6-tooth" href="#">Settings</flux:navlist.item>
        <flux:navlist.item icon="information-circle" href="#">Help</flux:navlist.item>
    </flux:navlist>
</flux:sidebar>

<flux:main container>
    <flux:heading size="xl" level="1">Good afternoon, Habibi</flux:heading>

    <flux:text class="mt-2 mb-6 text-base">Here's what's new today</flux:text>

    {{ $slot }}
    <flux:separator variant="subtle" />
</flux:main>

@fluxScripts
</body>
</html>
