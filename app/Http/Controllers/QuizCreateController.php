<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class QuizCreateController extends Controller
{
    /**
     * Wyświetla stronę tworzenia nowego quizu.
     * 
     * Ta funkcja odpowiada za wyświetlenie widoku, w którym użytkownik może utworzyć nowy quiz. 
     * Widok ten zawiera formularz, w którym można wprowadzić tytuł quizu oraz dodać pytania.
     *
     * @return \Illuminate\View\View Widok formularza tworzenia quizu.
     */
    public function create()
    {
        return view('quizzes.create'); // Zwraca widok dla tworzenia nowego quizu.
    }

    /**
     * Zapisuje nowo utworzony quiz oraz jego pytania w bazie danych.
     * 
     * Ta funkcja obsługuje przesłanie formularza tworzenia quizu. 
     * Waliduje dane wejściowe, tworzy nowy quiz i zapisuje w bazie jego pytania.
     *
     * @param Request $request Żądanie HTTP zawierające dane wprowadzone przez użytkownika.
     * @return \Illuminate\Http\RedirectResponse Przekierowuje użytkownika po zapisaniu quizu.
     */
    public function store(Request $request)
    {
        // Walidacja danych wejściowych
        // Sprawdzamy poprawność danych: tytułu quizu oraz pytań.
        $request->validate([
            'title' => 'required|string|max:255', // Tytuł quizu jest wymagany, musi być stringiem o maks. długości 255 znaków
            'questions' => 'required|array', // Lista pytań musi być tablicą i jest wymagana
            'questions.*.question_text' => 'required|string|max:255', // Każde pytanie musi mieć tekst o maks. długości 255 znaków
            'questions.*.type' => 'required|in:open,closed', // Każde pytanie musi być otwarte (open) lub zamknięte (closed)
            'questions.*.option_a' => 'nullable|string|max:255', // Opcje A-D są opcjonalne (nullable), maks. 255 znaków
            'questions.*.option_b' => 'nullable|string|max:255',
            'questions.*.option_c' => 'nullable|string|max:255',
            'questions.*.option_d' => 'nullable|string|max:255',
            'questions.*.correct_option' => 'nullable|in:A,B,C,D', // Poprawna odpowiedź musi być jedną z opcji (A, B, C, D)
            'questions.*.expected_code' => 'nullable|string', // Dla pytań otwartych może być wymagany kod
        ]);

        // Tworzenie nowego quizu w bazie danych
        $quiz = Quiz::create([
            'title' => $request->input('title'), // Pobranie tytułu quizu z danych wejściowych
            'user_id' => Auth::id(), // Przypisanie quizu zalogowanemu użytkownikowi
        ]);

        // Tworzenie pytań dla tego quizu
        foreach ($request->input('questions') as $questionData) {
            // Jeśli pytanie jest otwarte
            if ($questionData['type'] === 'open') {
                // Tworzenie pytania otwartego z kodem oczekiwanym
                $quiz->questions()->create([
                    'question_text' => $questionData['question_text'], // Treść pytania
                    'expected_code' => $questionData['expected_code'], // Oczekiwany kod (jeśli jest pytaniem otwartym)
                    'option_a' => null, // Opcje odpowiedzi nie są potrzebne dla pytań otwartych
                    'option_b' => null,
                    'option_c' => null,
                    'option_d' => null,
                    'correct_option' => null, // Brak poprawnej opcji dla pytań otwartych
                ]);
            } else {
                // Tworzenie pytania zamkniętego z opcjami A-D i poprawną odpowiedzią
                $quiz->questions()->create([
                    'question_text' => $questionData['question_text'], // Treść pytania
                    'option_a' => $questionData['option_a'], // Opcja A
                    'option_b' => $questionData['option_b'], // Opcja B
                    'option_c' => $questionData['option_c'], // Opcja C
                    'option_d' => $questionData['option_d'], // Opcja D
                    'correct_option' => $questionData['correct_option'], // Poprawna opcja (A-D)
                    'expected_code' => null, // Nie ma oczekiwanego kodu dla pytań zamkniętych
                ]);
            }
        }

        // Po utworzeniu quizu przekierowanie użytkownika do panelu z listą jego quizów
        return redirect()->route('user.dashboard')->with('success', 'Quiz został pomyślnie utworzony!');
    }
}
