<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class QuizDestroyController extends Controller
{
    /**
     * Usuwa quiz, jeśli użytkownik jest jego właścicielem.
     */
    public function destroy(Quiz $quiz)
    {
        if ($quiz->user_id !== Auth::id()) {
            return redirect()->route('user.dashboard')->with('error', 'Nie masz uprawnień do usunięcia tego quizu.');
        }

        // Usunięcie quizu
        $quiz->delete();

        return redirect()->route('user.dashboard')->with('success', 'Quiz został pomyślnie usunięty.');
    }
}