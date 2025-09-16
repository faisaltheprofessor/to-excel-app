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
/**
 * Global helper: Jira shortcuts + Mentions with ID-based de-duplication,
 * formatted insert (@First Last), and client-side filtering as you type.
 *
 * Usage on any input/textarea:
 *   x-data="textAssist({ fetchMentions: (q) => $wire.call('searchMentions', q) })"
 *   x-on:keydown="onKeydown($event)"
 *   x-on:input.debounce.120ms="detectMentions"
 */
window.textAssist = function (opts = {}) {
  const fetchMentions  = typeof opts.fetchMentions === 'function' ? opts.fetchMentions : async () => [];
  const uniqueMentions = opts.uniqueMentions !== false; // default true
  const mentionKey     = typeof opts.mentionKey === 'function'
                         ? opts.mentionKey
                         : (u) => (u?.name || u?.email || 'user');

  // Track which user IDs we already inserted in THIS field instance
  const mentionedIds = new Set();

  // Jira quick tokens
  const TOKEN_MAP = { '/': '✅', 'x': '❌', 'y': '👍' };

  // Mentions regex (unicode letters + marks + dot/hyphen/space)
  const MENTION_ACTIVE_RE = /@([\p{L}\p{M}.\- ]{0,50})$/u;

  // Normalize: lowercase, strip diacritics, collapse spaces
  const norm = (s) =>
    String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();

  const toTitle = (s) =>
    String(s || '')
      .trim()
      .replace(/\s+/g, ' ')
      .toLowerCase()
      .replace(/\b\p{L}/gu, ch => ch.toUpperCase());

  const handleFor = (u) => toTitle(mentionKey(u));

  // Client-side filter against query across name & email
  const filterByQuery = (items, q) => {
    const nq = norm(q);
    if (!nq) return items;
    return items.filter(u => {
      const nName  = norm(u?.name);
      const nEmail = norm(u?.email);
      const nKey   = norm(mentionKey(u));
      return (nName && nName.includes(nq)) ||
             (nEmail && nEmail.includes(nq)) ||
             (nKey && nKey.includes(nq));
    });
  };

  return {
    // Mentions state
    open: false,
    results: [],
    highlight: 0,
    query: '',
    range: null,
    lastSig: '',

    // --- Field helpers ---
    field()  { return this.$refs.field || this.$el; },
    getVal() { const el = this.field(); return (el && el.value != null) ? el.value : ''; },
    setVal(v){
      const el = this.field(); if (!el) return;
      el.value = v;
      // best-effort Livewire sync if wire:model / wire:model.defer present
      try {
        const k = this.$el.getAttribute('wire:model') || this.$el.getAttribute('wire:model.defer');
        if (k && this.$wire?.set) this.$wire.set(k, v);
      } catch {}
    },
    caret(){
      const el = this.field();
      try { return el?.selectionStart ?? 0; } catch { return (this.getVal() || '').length; }
    },
    setCaret(pos){ const el = this.field(); try { el?.focus(); el?.setSelectionRange(pos,pos); } catch {} },

    // --- Jira token replacement ---
    replaceJiraToken(triggerKey){
      const val = this.getVal();
      const pos = this.caret();
      const leftRaw = val.slice(0, pos);
      const ws = leftRaw.match(/[ \t\r\n]+$/);
      const trailing = ws ? ws[0] : '';
      const left = trailing ? leftRaw.slice(0, leftRaw.length - trailing.length) : leftRaw;

      const m = left.match(/\(([^\s()]{1,10})\)$/i);
      if (!m) return false;

      const key = (m[1] || '').toLowerCase();
      const emoji = TOKEN_MAP[key];
      if (!emoji) return false;

      const newLeft = left.slice(0, left.length - m[0].length) + emoji;
      let trigger = '';
      if (triggerKey === ' ') trigger = ' ';
      else if (triggerKey === 'Enter') trigger = '\n';
      else if (triggerKey === 'Tab') trigger = '\t';

      const newVal = newLeft + trailing + trigger + val.slice(pos);
      this.setVal(newVal);
      this.setCaret((newLeft + trailing + trigger).length);
      return true;
    },

    // --- Mentions detection ---
    async detectMentions(){
      const v   = this.getVal();
      const sig = v + '|' + this.caret();
      if (sig === this.lastSig) return;
      this.lastSig = sig;

      // find active @ token left of caret
      const left = v.slice(0, this.caret());
      const m = left.match(MENTION_ACTIVE_RE);
      if (!m) { this.close(); return; }

      this.query = (m[1] || '').trim();
      this.range = { start: left.length - m[0].length, end: this.caret() };
      this.highlight = 0;

      // fetch suggestions (server), then filter (client), then dedupe
      try {
        const res = await fetchMentions(this.query);
        let arr = Array.isArray(res) ? res : [];

        // Client-side filtering as you type (name/email/handle)
        arr = filterByQuery(arr, this.query);

        // De-duplicate per ID (or fallback to handle) against already mentioned
        if (uniqueMentions) {
          arr = arr.filter(u => {
            const id = (u && (u.id ?? u.ID ?? u.user_id));
            if (id != null) return !mentionedIds.has(String(id));
            // fallback: prevent exact same handle if item truly has no id
            const h = norm(handleFor(u));
            // If some users with the same name DO have IDs and are already mentioned,
            // we still want to allow other distinct IDs. This check only blocks id-less exact handle duplicates.
            const alreadyByHandle = [...mentionedIds].some(() => false); // no-op when we only track IDs
            return !alreadyByHandle && !!h;
          });
        }

        // Limit list (optional): arr = arr.slice(0, 8);
        this.results = arr;
        this.open = this.results.length > 0;
      } catch {
        this.results = [];
        this.open = false;
      }
    },

    close(){ this.open = false; this.results = []; this.query = ''; this.range = null; },

    // --- Insert a mention (ID-based unique, formatted) ---
    pick(user){
      if (!this.range) return;

      const id = (user && (user.id ?? user.ID ?? user.user_id));
      const handle = handleFor(user);
      if (!handle) { this.close(); return; }

      if (uniqueMentions) {
        if (id != null && mentionedIds.has(String(id))) { this.close(); return; }
        if (id == null) {
          // fallback: if no id, prevent exact same handle twice
          const val = this.getVal();
          const escaped = handle.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
          const re = new RegExp(`(^|\\s)@${escaped}(\\s|$)`, 'i');
          if (re.test(val)) { this.close(); return; }
        }
      }

      const at = '@' + handle;
      const v  = this.getVal();
      const newVal = v.slice(0, this.range.start) + at + ' ' + v.slice(this.range.end);
      this.setVal(newVal);
      this.setCaret(this.range.start + at.length + 1);

      if (id != null) mentionedIds.add(String(id));
      this.close();
    },

    // --- Keyboard handling for both Jira + mentions ---
    onKeydown(e){
      // Jira shortcuts
      if ([' ', 'Enter', 'Tab'].includes(e.key)) {
        if (this.replaceJiraToken(e.key)) { e.preventDefault(); return; }
      }

      // Mentions navigation
      if (this.open) {
        if (e.key === 'ArrowDown') { this.highlight = Math.min(this.highlight + 1, Math.max(this.results.length - 1, 0)); e.preventDefault(); return; }
        if (e.key === 'ArrowUp')   { this.highlight = Math.max(this.highlight - 1, 0); e.preventDefault(); return; }
        if (e.key === 'Enter')     { e.preventDefault(); const pick = this.results[this.highlight] || this.results[0]; if (pick) this.pick(pick); return; }
        if (e.key === 'Escape')    { this.close(); e.preventDefault(); return; }
      }

      queueMicrotask(() => this.detectMentions());
    },

    init(){ this.detectMentions(); }
  };
};
</script>

</body>
</html>
