<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Wyświetla formularz edycji profilu użytkownika.
     * 
     * Ta funkcja zwraca widok formularza edycji profilu użytkownika. Użytkownik jest
     * automatycznie pobierany z aktualnie zalogowanej sesji.
     *
     * @param  Request  $request - obiekt żądania HTTP
     * @return View - zwraca widok edycji profilu użytkownika
     */
    public function edit(Request $request): View
    {
        // Zwraca widok 'profile.edit', z przekazaniem aktualnie zalogowanego użytkownika
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Aktualizuje informacje o profilu użytkownika.
     * 
     * Funkcja służy do aktualizacji danych użytkownika na podstawie poprawnie
     * zweryfikowanych danych z formularza. Jeśli użytkownik zmienia swój email,
     * weryfikacja emaila zostanie usunięta.
     *
     * @param  ProfileUpdateRequest  $request - obiekt żądania, który zawiera zwalidowane dane z formularza
     * @return RedirectResponse - przekierowuje użytkownika z powrotem do formularza edycji profilu z komunikatem o sukcesie
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Wypełnia obiekt użytkownika zweryfikowanymi danymi z formularza
        $request->user()->fill($request->validated());

        // Jeśli zmieniono email użytkownika, anuluj weryfikację emaila
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        // Zapisuje zmiany użytkownika do bazy danych
        $request->user()->save();

        // Przekierowuje użytkownika do strony edycji profilu z komunikatem o sukcesie
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Usuwa konto użytkownika.
     * 
     * Ta funkcja usuwa konto użytkownika po poprawnej weryfikacji hasła.
     * Przed usunięciem użytkownika funkcja wylogowuje go i unieważnia jego sesję.
     *
     * @param  Request  $request - obiekt żądania, który zawiera hasło użytkownika do weryfikacji
     * @return RedirectResponse - przekierowuje użytkownika na stronę główną po usunięciu konta
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Walidacja, aby upewnić się, że podano poprawne hasło przed usunięciem konta
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'], // Sprawdza, czy podane hasło jest poprawne
        ]);

        // Pobiera aktualnie zalogowanego użytkownika
        $user = $request->user();

        // Wylogowanie użytkownika
        Auth::logout();

        // Usunięcie konta użytkownika z bazy danych
        $user->delete();

        // Unieważnia sesję użytkownika
        $request->session()->invalidate();

        // Regeneruje token sesji CSRF, aby zabezpieczyć sesję przed powtórnym użyciem
        $request->session()->regenerateToken();

        // Przekierowuje na stronę główną po usunięciu konta
        return Redirect::to('/');
    }
}
