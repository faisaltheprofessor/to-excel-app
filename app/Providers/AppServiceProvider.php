<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Debugbar', \Barryvdh\Debugbar\Facades\Debugbar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('local-dev') && !app()->runningInConsole()) {
            // Skip API routes if desired
            if (!request()->is('api/*') && !Auth::check()) {
                $user = User::firstOrCreate(
                    ['email' => 'dev@user.com'],
                    [
                        'name' => 'Local Dev User',
                        'email_verified_at' => now(),
                        'password' => bcrypt('password'),
                    ]
                );

                Auth::guard('web')->login($user);
            }
        }
    }
}
