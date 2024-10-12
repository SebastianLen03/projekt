<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Obsługuje żądanie - sprawdza, czy użytkownik jest adminem.
     */
    public function handle(Request $request, Closure $next)
    {
        // Sprawdź, czy użytkownik jest zalogowany i jest adminem
        if (Auth::check() && Auth::user()->admin) {
            return $next($request);
        }

        // Jeśli nie jest adminem, przekieruj na stronę główną lub wyświetl błąd
        return redirect('/');
    }
}
