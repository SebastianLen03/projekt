<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\Auth;

class QuizResultsController extends Controller
{

    /**
     * Wyświetla wyniki quizu dla użytkownika.
     */
    public function results(Quiz $quiz)
    {
        // Pobranie odpowiedzi użytkownika na pytania z quizu
        $userAnswers = UserAnswer::where('user_id', Auth::id())
            ->whereIn('question_id', $quiz->questions->pluck('id'))
            ->get()
            ->keyBy('question_id');

        // Pobranie danych porównawczych z sesji
        $comparisonsData = session()->get('comparisons', []);

        // Przetwarzanie wyników dla każdego pytania
        $results = $quiz->questions->map(function ($question) use ($userAnswers, $comparisonsData) {
            $userAnswer = $userAnswers->get($question->id);

            // Sprawdzanie, czy istnieją dane porównawcze
            $comparisons = isset($comparisonsData[$question->id]) && is_array($comparisonsData[$question->id])
                ? $comparisonsData[$question->id]
                : [];

            return [
                'question' => $question->question_text,
                'correct_option' => $question->correct_option,
                'user_option' => $userAnswer ? $userAnswer->selected_option : 'Brak odpowiedzi',
                'user_answer' => $userAnswer ? $userAnswer->answer : 'Brak odpowiedzi',
                'is_correct' => $userAnswer ? $userAnswer->is_correct : false,
                'expected_code' => $question->expected_code,
                'comparisons' => $comparisons,
            ];
        });

        // Usunięcie danych porównawczych z sesji
        session()->forget('comparisons');

        return view('quizzes.results', compact('quiz', 'results'));
    }
}