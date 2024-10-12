<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\Auth;

class QuizResultsController extends Controller
{
    /**
     * Wyświetla wyniki quizu dla użytkownika.
     * 
     * Ta funkcja wyświetla wyniki quizu po jego zakończeniu, prezentując odpowiedzi użytkownika, 
     * poprawne odpowiedzi i dane porównawcze, jeśli istnieją.
     *
     * @param Quiz $quiz Quiz, dla którego wyniki będą wyświetlane.
     * @return \Illuminate\View\View Zwraca widok strony wyników quizu.
     */
    public function results(Quiz $quiz)
    {
        // Pobranie odpowiedzi użytkownika na pytania w quizie, filtrując po ID użytkownika i ID pytań z quizu
        $userAnswers = UserAnswer::where('user_id', Auth::id()) // Pobieranie odpowiedzi aktualnie zalogowanego użytkownika
            ->whereIn('question_id', $quiz->questions->pluck('id')) // Pobieranie odpowiedzi tylko na pytania z aktualnego quizu
            ->get() // Pobranie wyników z bazy danych
            ->keyBy('question_id'); // Kluczowanie odpowiedzi według ID pytania, aby ułatwić dostęp do nich później

        // Pobranie danych porównawczych (jeśli istnieją) z sesji użytkownika. Dane te mogą być używane do porównania wyników kodu.
        $comparisonsData = session()->get('comparisons', []);

        // Przetwarzanie wyników każdego pytania z quizu
        $results = $quiz->questions->map(function ($question) use ($userAnswers, $comparisonsData) {
            // Pobranie odpowiedzi użytkownika na bieżące pytanie
            $userAnswer = $userAnswers->get($question->id);

            // Sprawdzanie, czy dla pytania istnieją dane porównawcze w sesji
            $comparisons = isset($comparisonsData[$question->id]) && is_array($comparisonsData[$question->id])
                ? $comparisonsData[$question->id]
                : [];

            // Zwraca przetworzone dane dla pytania, w tym: treść pytania, odpowiedź użytkownika, poprawną odpowiedź, status poprawności odpowiedzi itp.
            return [
                'question' => $question->question_text, // Treść pytania
                'correct_option' => $question->correct_option, // Poprawna odpowiedź dla pytania zamkniętego
                'user_option' => $userAnswer ? $userAnswer->selected_option : 'Brak odpowiedzi', // Odpowiedź użytkownika (lub 'Brak odpowiedzi', jeśli brak odpowiedzi)
                'user_answer' => $userAnswer ? $userAnswer->answer : 'Brak odpowiedzi', // Odpowiedź użytkownika dla pytania otwartego (lub 'Brak odpowiedzi')
                'is_correct' => $userAnswer ? $userAnswer->is_correct : false, // Czy odpowiedź była poprawna
                'expected_code' => $question->expected_code, // Oczekiwany kod dla pytania otwartego
                'comparisons' => $comparisons, // Dane porównawcze, jeśli są dostępne (dla pytań otwartych)
            ];
        });

        // Usunięcie danych porównawczych z sesji, ponieważ nie będą już potrzebne
        session()->forget('comparisons');

        // Zwrócenie widoku wyników quizu z przetworzonymi wynikami
        return view('quizzes.results', compact('quiz', 'results'));
    }
}
