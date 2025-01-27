<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AuthViewController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            // Jeśli użytkownik jest zalogowany, przekieruj go np. do dashboardu
            return redirect()->route('dashboard');
        }

        // W przeciwnym razie wyświetl widok logowania i rejestracji
        return view('auth.guest');
    }
}
