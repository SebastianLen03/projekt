<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;


class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Dodaj walidację bezpośrednio tutaj
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Próba logowania użytkownika
        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Sprawdź czy użytkownik jest adminem
            if ($user->admin) {
                // Jeśli admin, przekieruj do widoku admina
                return redirect()->route('admin.dashboard');
            }

            // Jeśli nie admin, przekieruj do domyślnego widoku
            return redirect()->route('user.dashboard');
        }

        // W przypadku nieudanego logowania rzucamy wyjątek
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
