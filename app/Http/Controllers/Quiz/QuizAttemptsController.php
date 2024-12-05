<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\UserAttempt;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizAttemptsController extends Controller
{
    /**
     * Wyświetla szczegóły podejść użytkownika do danego quizu, pogrupowane według wersji quizu.
     *
     * @param int $quizId ID quizu, którego podejścia chcemy zobaczyć.
     * @return \Illuminate\View\View
     */
    public function showAttempts($quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdzenie, czy użytkownik ma dostęp do quizu
        if (!$quiz->is_public && $quiz->user_id !== $userId && !$this->userHasAttemptedQuiz($quizId, $userId)) {
            abort(403, 'Nie masz uprawnień do przeglądania tego quizu.');
        }

        // Pobranie podejść użytkownika, pogrupowanych według wersji quizu
        $userAttempts = UserAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->with('quizVersion')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('quiz_version_id');

        // Pobranie odpowiedzi użytkownika
        $userAnswers = UserAnswer::where('user_id', $userId)
            ->whereIn('attempt_id', $userAttempts->flatten()->pluck('id'))
            ->get();

        // Grupowanie odpowiedzi po ID podejścia
        $groupedUserAnswers = $userAnswers->groupBy('attempt_id');

        // Przekazanie danych do widoku
        return view('quizzes.attempts', [
            'quiz' => $quiz,
            'userAttempts' => $userAttempts,
            'groupedUserAnswers' => $groupedUserAnswers,
        ]);
    }
    
    /**
     * Sprawdza, czy użytkownik miał co najmniej jedno podejście do quizu.
     *
     * @param int $quizId
     * @param int $userId
     * @return bool
     */
    protected function userHasAttemptedQuiz($quizId, $userId)
    {
        return UserAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->exists();
    }

}
