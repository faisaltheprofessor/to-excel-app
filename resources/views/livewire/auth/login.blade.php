<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $pkennung = ''; // maps to LDAP `cn`

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $credentials = ['cn' => $this->pkennung, 'password' => $this->password];
        if (!Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'pkennung' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('importer.index', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'pkennung' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->pkennung) . '|' . request()->ip());
    }
};
?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('👮‍♂️✋ Stopp! 🛑 Sicherheitskontrolle')"
                   :description="__('Zeig deine P-Kennung und dein Passwort!')"/>

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')"/>

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- P-Kennung -->
        <flux:input
            wire:model="pkennung"
            :label="__('P-Kennung')"
            type="text"
            required
            autofocus
            autocomplete="username"
            placeholder="p123456"
        />

        <!-- Passwort -->
        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Passwort')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Passwort')"
                viewable
            />

            @if (Route::has('password.request'))
                <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Passwort vergessen?') }}
                </flux:link>
            @endif
        </div>

        <!-- Angemeldet bleiben -->
        <flux:checkbox wire:model="remember" :label="__('Angemeldet bleiben')"/>

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">
                {{ __('Anmelden') }}
            </flux:button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Sie haben noch kein Konto?') }}
            <flux:link :href="route('register')" wire:navigate>{{ __('Registrieren') }}</flux:link>
        </div>
    @endif
</div>
