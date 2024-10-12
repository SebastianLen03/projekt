<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class QuizDestroyController extends Controller
{
    /**
     * Usuwa quiz, jeśli użytkownik jest jego właścicielem.
     * 
     * Ta funkcja sprawdza, czy bieżący użytkownik jest właścicielem quizu, a następnie usuwa quiz z bazy danych.
     * 
     * @param Quiz $quiz Quiz, który ma zostać usunięty.
     * @return \Illuminate\Http\RedirectResponse Przekierowanie użytkownika po usunięciu quizu lub w przypadku braku uprawnień.
     */
    public function destroy(Quiz $quiz)
    {
        // Sprawdzenie, czy bieżący użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            // Jeśli użytkownik nie jest właścicielem quizu, przekierowanie do panelu z komunikatem o błędzie
            return redirect()->route('user.dashboard')->with('error', 'Nie masz uprawnień do usunięcia tego quizu.');
        }

        // Usunięcie quizu z bazy danych
        $quiz->delete();

        // Przekierowanie do panelu użytkownika z komunikatem o pomyślnym usunięciu quizu
        return redirect()->route('user.dashboard')->with('success', 'Quiz został pomyślnie usunięty.');
    }
}
