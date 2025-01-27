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

class QuizAttemptController extends Controller
{
    /**
     * Resetowanie podejść użytkownika – dawniej w QuizManageController
     */
    public function resetAttempts(Request $request, $quizId)
    {
        $userId = $request->input('user_id');

        $quiz = Quiz::findOrFail($quizId);
        $user = Auth::user();
        if ($quiz->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Brak uprawnień.']);
        }

        // Usuwamy attempty
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
        // Znajdź quiz
        $quiz = Quiz::findOrFail($quizId);
        // Sprawdź właściciela
        $user = Auth::user();
        if ($quiz->user_id !== $user->id) {
            return back()->withErrors(['error' => 'Brak uprawnień do edycji quizu.']);
        }

        // Znajdź wersję
        $version = QuizVersion::where('quiz_id', $quiz->id)
            ->where('id', $versionId)
            ->where('is_draft', false)
            ->firstOrFail();

        // Odczyt user_id (opcjonalny)
        $userId = $request->input('user_id'); 
        // user_id => reset tylko tego usera
        // brak user_id => reset wszystkich userów

        // Wylistuj attempty w tej wersji
        $attemptsQuery = UserAttempt::where('quiz_id', $quiz->id)
            ->where('quiz_version_id', $version->id);

        if ($userId) {
            $attemptsQuery->where('user_id', $userId);
        }

        $attempts = $attemptsQuery->get();
        $attemptIds = $attempts->pluck('id')->toArray();

        // Skasuj userAnswers
        $questionIds = VersionedQuestion::where('quiz_version_id', $version->id)->pluck('id');
        
        // Gdy masz w user_answers klucz 'user_attempt_id' lub (user_id + versioned_question_id),
        // dostosuj do własnej migracji:
        if ($userId) {
            // Usuwamy userAnswers TYLKO tego usera w danej wersji
            UserAnswer::where('user_id', $userId)
                ->whereIn('versioned_question_id', $questionIds)
                ->delete();
        } else {
            // Usuwamy userAnswers wszystkich w danej wersji
            UserAnswer::whereIn('versioned_question_id', $questionIds)->delete();
        }

        // Na koniec usuwamy attempty
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
        // Walidacja
        $validated = $request->validate([
            'new_score'=>'required|numeric|min:0',
        ]);

        // Znajdź quiz i sprawdź, czy należy do aktualnego usera
        $quiz = Quiz::where('id',$quizId)->where('user_id',Auth::id())->firstOrFail();

        // Znajdź to podejście:
        $attempt = UserAttempt::where('id',$attemptId)->where('quiz_id',$quizId)->firstOrFail();

        // Znajdź userAnswer do danego pytania
        $userAnswer = UserAnswer::where('attempt_id',$attemptId)
            ->where('versioned_question_id',$questionId)
            ->firstOrFail();

        // Ustaw nową punktację
        $newScore = $validated['new_score'];
        $userAnswer->score = $newScore;
        $userAnswer->save();

        // Przelicz łączny wynik attemptu (sumowanie score ze wszystkich userAnswers)
        $sum = UserAnswer::where('attempt_id',$attemptId)->sum('score');
        $attempt->score = $sum;
        $attempt->save();

        return back()->with('message','Punktacja zaktualizowana.');
    }

    
}
