<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\UserAnswer;
use App\Models\UserAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class QuizOwnerAttemptsController extends Controller
{
    /**
     * Wyświetla odpowiedzi użytkowników na dany quiz.
     *
     * @param int $quizId ID quizu.
     * @return \Illuminate\View\View
     */
    public function showAttempts($quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::with('questions.answers')->findOrFail($quizId);

        // Logowanie informacji o quizie
        Log::info('Quiz details:', [
            'quiz_id' => $quiz->id,
            'title' => $quiz->title,
            'questions' => $quiz->questions->toArray()
        ]);

        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== $userId) {
            abort(403, 'Nie masz uprawnień do przeglądania tych informacji.');
        }

        // Pobranie wszystkich podejść użytkowników do quizu
        $userAttempts = UserAttempt::where('quiz_id', $quizId)
            ->with('user') // Pobranie powiązanego użytkownika dla wyświetlania w widoku
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        // Logowanie informacji o podejściach użytkowników
        Log::info('User attempts:', $userAttempts->toArray());

        // Pobranie odpowiedzi użytkowników do pytań z tego quizu
        $groupedUserAnswers = UserAnswer::whereIn('attempt_id', $userAttempts->pluck('id'))
            ->get()
            ->groupBy('attempt_id');

        // Logowanie informacji o odpowiedziach użytkowników
        Log::info('Grouped user answers:', $groupedUserAnswers->toArray());

        // Dodanie obliczenia punktów do każdego podejścia
        foreach ($userAttempts as $attempt) {
            $userScore = 0;
            if (isset($groupedUserAnswers[$attempt->id])) {
                foreach ($groupedUserAnswers[$attempt->id] as $userAnswer) {
                    // Pobierz pytanie powiązane z odpowiedzią
                    $question = $quiz->questions->firstWhere('id', $userAnswer->question_id);

                    if ($question) {
                        // Sprawdź, czy odpowiedź jest poprawna
                        if ($question->type === 'open') {
                            // Zakładamy, że ocena odpowiedzi otwartej jest ręczna, więc korzystamy z istniejącej wartości punktacji
                            $userScore += $userAnswer->score ?? 0;
                        } else {
                            $correctAnswer = $question->answers->where('is_correct', true)->pluck('id')->toArray();
                            if (in_array($userAnswer->answer_id, $correctAnswer)) {
                                $userAnswer->score = $question->points; // Ustaw poprawną punktację
                                $userScore += $question->points;
                            } else {
                                $userAnswer->score = $userAnswer->score ?? 0; // Jeśli niepoprawna odpowiedź, ustaw na 0 lub pozostaw istniejącą wartość
                            }
                        }
                    }
                }
            }

            // Przechowaj obliczony wynik w podejściu
            $attempt->calculated_score = $userScore;

            // Logowanie informacji o obliczonym wyniku dla każdego podejścia
            Log::info('Attempt score calculated:', [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'attempt_number' => $attempt->attempt_number,
                'calculated_score' => $userScore
            ]);
        }

        // Przekazanie danych do widoku
        return view('quizzes.owner_attempts', [
            'quiz' => $quiz,
            'userAttempts' => $userAttempts,
            'groupedUserAnswers' => $groupedUserAnswers,
        ]);
    }

    /**
     * Zapisuje zaktualizowane punkty dla odpowiedzi użytkowników.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $quizId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateScores(Request $request, $quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== $userId) {
            abort(403, 'Nie masz uprawnień do przeglądania tych informacji.');
        }

        // Pobranie punktów z formularza
        $scores = $request->input('scores', []);

        foreach ($scores as $answerId => $score) {
            $userAnswer = UserAnswer::find($answerId);
            if ($userAnswer) {
                $userAnswer->score = $score; // Aktualizacja punktów
                $userAnswer->save();
            }
        }

        return redirect()->route('quiz.owner_attempts', ['quiz' => $quizId])->with('success', 'Punkty zostały zaktualizowane.');
    }
}
