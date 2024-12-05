<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

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

        DB::statement("SET time_zone='+00:00'");
    }
}
