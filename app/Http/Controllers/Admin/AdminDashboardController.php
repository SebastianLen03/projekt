<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Wyświetla stronę z listą użytkowników, jeśli zalogowany użytkownik jest adminem.
     *
     * @param Request $request Obiekt żądania HTTP.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View Przekierowanie lub widok dashboardu.
     */
    public function index(Request $request)
    {
        // Sprawdzanie, czy użytkownik jest zalogowany i czy ma uprawnienia administratora
        if (!Auth::check() || Auth::user()->admin !== 1) {
            // Jeśli użytkownik nie jest zalogowany lub nie jest adminem, przekierowanie do strony głównej
            return redirect('/')->with('error', 'Nie masz uprawnień do dostępu do tej strony.');
        }

        // Pobieranie wszystkich użytkowników z bazy danych
        $users = User::all();

        // Zwrócenie widoku dashboardu admina z tabelą wszystkich użytkowników
        return view('admin.dashboard', ['users' => $users]);
    }

    /**
     * Aktualizuje dane wybranego użytkownika.
     *
     * @param Request $request Obiekt żądania HTTP zawierający dane użytkownika do zaktualizowania.
     * @param int $id ID użytkownika, którego dane mają być zaktualizowane.
     * @return \Illuminate\Http\RedirectResponse Przekierowanie z wiadomością o sukcesie.
     */
    public function update(Request $request, $id)
    {
        // Znajdowanie użytkownika w bazie danych na podstawie przekazanego ID
        $user = User::findOrFail($id);

        // Walidacja danych wprowadzonych przez admina w formularzu
        $request->validate([
            'name' => 'required|string|max:255', // Pole 'name' jest wymagane i musi być ciągiem znaków o maksymalnej długości 255
            'email' => 'required|email|max:255|unique:users,email,' . $user->id, // Pole 'email' jest wymagane, musi być poprawnym adresem email, unikalnym w tabeli 'users', z wyjątkiem aktualnego użytkownika
            'admin' => 'required|boolean', // Pole 'admin' jest wymagane i musi być wartością logiczną (1 lub 0)
        ]);

        // Aktualizowanie danych użytkownika na podstawie wprowadzonych wartości
        $user->name = $request->input('name'); // Ustawienie nowej nazwy użytkownika
        $user->email = $request->input('email'); // Ustawienie nowego adresu email użytkownika
        $user->admin = $request->input('admin'); // Ustawienie roli użytkownika (1 = admin, 0 = zwykły użytkownik)
        $user->save(); // Zapisanie zmian w bazie danych

        // Przekierowanie na stronę, z której przyszło żądanie, z wiadomością o sukcesie aktualizacji
        return redirect()->back()->with('success', 'Dane użytkownika zostały zaktualizowane.');
    }
}
