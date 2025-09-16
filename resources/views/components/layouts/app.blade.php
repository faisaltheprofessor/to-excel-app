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
        <flux:navbar.item icon="kanban" href="/feedback/kanban" wire:navigate>Kanban</flux:navbar.item>

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

<flux:main class="w-full" >
    <div class="w-1/2 mx-auto">
        <flux:heading size="xl" level="1">Hallo, {{ Str::title(Auth::user()->name) }}</flux:heading>

    <flux:text class="mt-2 mb-6 text-base">Importer-Datei leicht gemacht</flux:text>
    </div>

    {{ $slot }}
    <flux:separator variant="subtle"/>
</flux:main>


<!-- Logout Form -->
<form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
    @csrf
</form>
@fluxScripts

<script>
document.addEventListener('alpine:init', () => {
  window.JIRA_SHORTCUTS = window.JIRA_SHORTCUTS || {
    '/': '✅',
    'x': '❌',
    'y': '👍',
    'tick': '✅',
    'check': '✅',
    'yes': '✅',
    'no': '❌',
  };

  // Universal, model-agnostic helper
  Alpine.data('jiraBox', () => ({
    _target: null,
    init() {
      // use the element itself if it's a text input/textarea,
      // otherwise the first descendant textarea/input
      this._target = this.$el.matches('textarea,input')
        ? this.$el
        : this.$el.querySelector('textarea, input');
    },
    onKeydown(e) {
      if (![' ', 'Enter', 'Tab'].includes(e.key)) return;

      const el = this._target || e.target;
      if (!el) return;

      const tag = el.tagName;
      const type = (el.type || 'text').toLowerCase();
      const isTextual = tag === 'TEXTAREA' || (tag === 'INPUT' && ['text','search','url','tel'].includes(type));
      if (!isTextual) return;

      const pos = el.selectionStart ?? 0;
      const val = el.value ?? '';
      const leftRaw = val.slice(0, pos);
      const right   = val.slice(pos);

      const ws = leftRaw.match(/[ \t\r\n]+$/);
      const trailing = ws ? ws[0] : '';
      const left = trailing ? leftRaw.slice(0, leftRaw.length - trailing.length) : leftRaw;

      const m = left.match(/\(([^\s()]{1,10})\)$/i);
      if (!m) return;

      const key = (m[1] || '').toLowerCase();
      const emoji = (window.JIRA_SHORTCUTS || {})[key];
      if (!emoji) return;

      e.preventDefault();

      const trigger = e.key === ' ' ? ' ' : (e.key === 'Enter' ? '\n' : '\t');
      const newLeft = left.slice(0, left.length - m[0].length) + emoji;
      const newVal  = newLeft + trailing + trigger + right;

      el.value = newVal;

      // Let Livewire (or vanilla forms) react naturally
      el.dispatchEvent(new Event('input',  { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));

      const newPos = (newLeft + trailing + trigger).length;
      try { el.setSelectionRange(newPos, newPos); } catch (_) {}
    }
  }));
});
</script>



</body>
</html>
