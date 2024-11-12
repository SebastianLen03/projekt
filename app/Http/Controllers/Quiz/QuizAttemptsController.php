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
     * Wyświetla szczegóły podejść użytkownika do danego quizu.
     *
     * @param int $quizId ID quizu, którego podejścia chcemy zobaczyć.
     * @return \Illuminate\View\View
     */
    public function showAttempts($quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::with('questions.answers')->findOrFail($quizId);

        // Pobierz podejścia użytkownika dla danego quizu
        $userAttempts = UserAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Pobierz odpowiedzi użytkownika na pytania z tego quizu
        $userAnswers = UserAnswer::where('user_id', $userId)
            ->whereIn('attempt_id', $userAttempts->pluck('id'))
            ->get();

        // Grupowanie odpowiedzi użytkownika według `attempt_id` (będzie to pomocne przy renderowaniu w widoku)
        $groupedUserAnswers = $userAnswers->groupBy('attempt_id');

        // Logowanie, aby upewnić się, że dane są prawidłowe
        Log::info('Quiz Data:', ['quiz' => $quiz]);
        Log::info('User Attempts Data:', $userAttempts->toArray());
        Log::info('Grouped User Answers Data:', $groupedUserAnswers->toArray());

        // Przekaż dane do widoku
        return view('quizzes.attempts', [
            'quiz' => $quiz,
            'userAttempts' => $userAttempts,
            'groupedUserAnswers' => $groupedUserAnswers,
        ]);
    }
}
