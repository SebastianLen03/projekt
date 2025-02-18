<?php

namespace App\Http\Controllers\Traits;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

trait LoadsQuizTrait
{
    /**
     * Ładuje quiz wraz z powiązaniami potrzebnymi w edycji.
     *
     * @param int $quizId
     * @return \App\Models\Quiz
     */
    public function loadQuizWithRelations($quizId)
    {
        return Quiz::with([
            // 'questions.answers', // usunięto, bo tabela 'questions' nie istnieje
            'quizVersions.versionedQuestions.versionedAnswers',
            'groups'
        ])->findOrFail($quizId);
    }

    /**
     * Ładuje quiz i sprawdza, czy użytkownik jest jego właścicielem.
     *
     * @param int $quizId
     * @return \App\Models\Quiz
     */
    public function loadUserQuiz($quizId)
    {
        $quiz = $this->loadQuizWithRelations($quizId);
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do tego quizu.');
        }
        return $quiz;
    }
}
