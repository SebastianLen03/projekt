<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL; // Import dla HTTPS
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Rejestracja innych usług, jeśli wymagane.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Jeśli aplikacja jest uruchamiana na serwerze produkcyjnym, wymuś HTTPS
        if ($this->app->environment('production')) {
            URL::forceScheme('https'); // Wymuszenie HTTPS
        }

        // Opcjonalne ustawienia dla MySQL (długość indeksów)
        Schema::defaultStringLength(191);
    }
}
