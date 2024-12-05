<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\UserAnswer;
use App\Models\UserAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\QuizVersion;

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
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== $userId) {
            abort(403, 'Nie masz uprawnień do przeglądania tych informacji.');
        }

        // Pobranie wszystkich podejść użytkowników do quizu wraz z ich wynikami
        $userAttempts = UserAttempt::where('quiz_id', $quizId)
            ->with('user', 'quizVersion')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        // Pobranie odpowiedzi użytkowników do pytań z tego quizu
        $userAnswers = UserAnswer::whereIn('attempt_id', $userAttempts->pluck('id'))
            ->get()
            ->groupBy('attempt_id');

        // Dodanie obliczenia punktów do każdego podejścia
        foreach ($userAttempts as $attempt) {
            $userScore = 0;

            // Pobierz wersję quizu dla tego podejścia
            $quizVersion = $attempt->quizVersion;

            // Pobierz pytania i odpowiedzi z wersji quizu
            $versionedQuestions = $quizVersion->questions()->with('answers')->get()->keyBy('id');

            if (isset($userAnswers[$attempt->id])) {
                foreach ($userAnswers[$attempt->id] as $userAnswer) {
                    // Jeśli punkty zostały ocenione ręcznie, pomijamy automatyczne obliczenie
                    if ($userAnswer->is_manual_score) {
                        $userScore += $userAnswer->score ?? 0;
                        continue;
                    }

                    // Pobierz pytanie powiązane z odpowiedzią
                    $question = $versionedQuestions->get($userAnswer->versioned_question_id);

                    if ($question) {
                        // Sprawdź, czy odpowiedź jest poprawna
                        if ($question->type === 'open') {
                            // Zakładamy, że ocena odpowiedzi otwartej jest ręczna
                            $userScore += $userAnswer->score ?? 0;
                        } else {
                            $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();

                            // Dla pytań wielokrotnego wyboru
                            if ($question->type === 'multiple_choice') {
                                // Pobierz wszystkie wybrane odpowiedzi przez użytkownika
                                $selectedAnswerIds = explode(',', $userAnswer->selected_answers);

                                if ($question->points_type === 'full') {
                                    // Pełne punkty tylko jeśli wszystkie poprawne odpowiedzi zostały wybrane
                                    if (array_diff($correctAnswerIds, $selectedAnswerIds) === [] && array_diff($selectedAnswerIds, $correctAnswerIds) === []) {
                                        $userAnswer->score = $question->points;
                                        $userScore += $userAnswer->score;
                                    } else {
                                        $userAnswer->score = 0;
                                    }
                                } elseif ($question->points_type === 'partial') {
                                    // Częściowe punkty za każdą poprawną odpowiedź
                                    $correctSelected = array_intersect($correctAnswerIds, $selectedAnswerIds);
                                    $pointsPerCorrect = $question->points;
                                    $userAnswer->score = count($correctSelected) * $pointsPerCorrect;
                                    $userScore += $userAnswer->score;
                                }
                            } else {
                                // Pytania jednokrotnego wyboru
                                if (in_array($userAnswer->versioned_answer_id, $correctAnswerIds)) {
                                    $userAnswer->score = $question->points;
                                    $userScore += $userAnswer->score;
                                } else {
                                    $userAnswer->score = 0;
                                }
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
            'groupedUserAnswers' => $userAnswers,
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
            abort(403, 'Nie masz uprawnień do edycji tych informacji.');
        }

        // Pobranie attempt_id z formularza
        $attemptId = $request->input('attempt_id');

        // Pobranie punktów z formularza
        $scores = $request->input('scores', []);

        foreach ($scores as $answerId => $score) {
            $userAnswer = UserAnswer::find($answerId);
            if ($userAnswer && $userAnswer->attempt_id == $attemptId) {
                $userAnswer->score = $score; // Aktualizacja punktów
                $userAnswer->is_manual_score = true; // Oznacz jako ręcznie ocenione
                $userAnswer->save();
            }
        }

        // Zaktualizuj sumę punktów dla tego podejścia
        $attempt = UserAttempt::find($attemptId);
        if ($attempt) {
            // Pobierz sumę punktów z UserAnswer
            $totalScore = UserAnswer::where('attempt_id', $attemptId)->sum('score');

            // Zaktualizuj pole score w UserAttempt
            $attempt->score = $totalScore;
            $attempt->save();
        }

        return redirect()->route('quiz.owner_attempts', ['quiz' => $quizId])->with('success', 'Punkty zostały zaktualizowane.');
    }
}
