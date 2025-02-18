<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\QuizVersion;
use App\Models\VersionedQuestion;
use App\Models\UserAttempt;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Traits\LoadsQuizTrait;

class QuizAttemptController extends Controller
{
    use LoadsQuizTrait;
    /**
     * Resetowanie podejść użytkownika – dawniej w QuizManageController
     */
    public function resetAttempts(Request $request, $quizId)
    {
        $userId = $request->input('user_id');
        $quiz = $this->loadUserQuiz($quizId);

        UserAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->delete();

        $quizVersionIds = QuizVersion::where('quiz_id', $quizId)->pluck('id');
        $versionedQuestionIds = VersionedQuestion::whereIn('quiz_version_id', $quizVersionIds)->pluck('id');

        UserAnswer::where('user_id', $userId)
            ->whereIn('versioned_question_id', $versionedQuestionIds)
            ->delete();

        return back()->with('message', 'Podejścia użytkownika zostały zresetowane.');
    }

        /**
     * Reset podejść użytkowników w konkretnej wersji quizu.
     * Jeśli request->user_id jest przekazane, usuwa tylko podejścia danego usera;
     * w przeciwnym wypadku – usuwa podejścia wszystkich użytkowników do tej wersji.
     */
    public function resetVersionAttempts(Request $request, $quizId, $versionId)
    {
        $quiz = $this->loadUserQuiz($quizId);

        $version = QuizVersion::where('quiz_id', $quiz->id)
            ->where('id', $versionId)
            ->where('is_draft', false)
            ->firstOrFail();

        $userId = $request->input('user_id');

        $attemptsQuery = UserAttempt::where('quiz_id', $quiz->id)
            ->where('quiz_version_id', $version->id);

        if ($userId) {
            $attemptsQuery->where('user_id', $userId);
        }

        $attempts = $attemptsQuery->get();
        $attemptIds = $attempts->pluck('id')->toArray();

        $questionIds = VersionedQuestion::where('quiz_version_id', $version->id)->pluck('id');

        if ($userId) {
            UserAnswer::where('user_id', $userId)
                ->whereIn('versioned_question_id', $questionIds)
                ->delete();
        } else {
            UserAnswer::whereIn('versioned_question_id', $questionIds)->delete();
        }

        $attemptsQuery->delete();

        if ($userId) {
            return back()->with('message', 'Zresetowano podejścia wybranego użytkownika w wersji ' . $version->version_name . '.');
        } else {
            return back()->with('message', 'Zresetowano wszystkie podejścia w wersji ' . $version->version_name . '.');
        }
    }

        /**
     * Edycja punktów za konkretne pytanie w danym podejściu.
     */
    public function updateScore(Request $request, $quizId, $attemptId, $questionId)
    {
        $validated = $request->validate([
            'new_score' => 'required|numeric|min:0',
        ]);

        $quiz = $this->loadUserQuiz($quizId);

        $attempt = UserAttempt::where('id', $attemptId)->where('quiz_id', $quizId)->firstOrFail();

        $userAnswer = UserAnswer::where('attempt_id', $attemptId)
            ->where('versioned_question_id', $questionId)
            ->firstOrFail();

        $newScore = $validated['new_score'];
        $userAnswer->score = $newScore;
        $userAnswer->save();

        $sum = UserAnswer::where('attempt_id', $attemptId)->sum('score');
        $attempt->score = $sum;
        $attempt->save();

        return back()->with('message','Punktacja zaktualizowana.');
    }
}
