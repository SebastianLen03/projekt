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
    public function showAttempts($quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::findOrFail($quizId);
        
        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== $userId) {
            abort(403, 'Nie masz uprawnień do przeglądania tych informacji.');
        }

        // Pobranie wszystkich podejść użytkowników do quizu wraz z wersją quizu
        $userAttempts = UserAttempt::where('quiz_id', $quizId)
            ->with('user', 'quizVersion')
            ->orderBy('user_id')
            ->orderBy('attempt_number')
            ->get();

        // Pogrupowanie podejść według wersji quizu
        $groupedAttemptsByVersion = $userAttempts->groupBy('quiz_version_id');

        // Pobranie odpowiedzi użytkowników do pytań z tego quizu
        $userAnswers = UserAnswer::whereIn('attempt_id', $userAttempts->pluck('id'))
            ->get()
            ->groupBy('attempt_id');

        // Pobranie wszystkich wersji tego quizu
        $quizVersions = QuizVersion::where('quiz_id', $quizId)->orderBy('version_number')->get();

        // Dodanie obliczenia punktów do każdego podejścia
        foreach ($userAttempts as $attempt) {
            $userScore = 0;
            $attemptQuizVersion = $attempt->quizVersion;
            if (!$attemptQuizVersion) {
                continue;
            }

            $versionedQuestions = $attemptQuizVersion->versionedQuestions()->with('answers')->get()->keyBy('id');

            if (isset($userAnswers[$attempt->id])) {
                foreach ($userAnswers[$attempt->id] as $userAnswer) {
                    // Pomijamy obliczenia dla ręcznie ocenionych odpowiedzi
                    if ($userAnswer->is_manual_score) {
                        $userScore += $userAnswer->score ?? 0;
                        continue;
                    }

                    $question = $versionedQuestions->get($userAnswer->versioned_question_id);
                    if ($question) {
                        $pointsType = $question->points_type ?? 'full';

                        if ($question->type === 'open') {
                            $userScore += $userAnswer->score ?? 0;
                        } else {
                            $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();

                            if ($question->type === 'multiple_choice') {
                                $selectedAnswerIds = $userAnswer->selected_answers ? explode(',', $userAnswer->selected_answers) : [];

                                if ($pointsType === 'full') {
                                    if (array_diff($correctAnswerIds, $selectedAnswerIds) === [] && array_diff($selectedAnswerIds, $correctAnswerIds) === []) {
                                        $userAnswer->score = $question->points;
                                    } else {
                                        $userAnswer->score = 0;
                                    }
                                } elseif ($pointsType === 'partial') {
                                    $correctSelected = array_intersect($correctAnswerIds, $selectedAnswerIds);
                                    $pointsPerCorrect = $question->points;
                                    $userAnswer->score = count($correctSelected) * $pointsPerCorrect;
                                } else {
                                    $userAnswer->score = in_array($selectedAnswerIds, $correctAnswerIds) ? $question->points : 0;
                                }

                                $userScore += $userAnswer->score;
                            } else {
                                // Pytanie jednokrotnego wyboru
                                if (in_array($userAnswer->versioned_answer_id, $correctAnswerIds)) {
                                    $userAnswer->score = $question->points;
                                } else {
                                    $userAnswer->score = 0;
                                }
                                $userScore += $userAnswer->score;
                            }
                        }
                    }
                }
            }

            $attempt->calculated_score = $userScore;

            Log::info('Attempt score calculated:', [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'attempt_number' => $attempt->attempt_number,
                'calculated_score' => $userScore
            ]);
        }

        // Obliczanie średniego wyniku na pytanie dla każdej wersji
        $averageScorePerQuestionDataByVersion = [];
        $passingDataByVersion = [];

        foreach ($quizVersions as $version) {
            $questions = $version->versionedQuestions()->with('answers')->get();
            $averageScorePerQuestionData = [];

            foreach ($questions as $question) {
                $totalScore = 0;
                $attemptCount = 0;

                // Iterujemy po podejściach dla tej konkretnej wersji
                $versionAttempts = $groupedAttemptsByVersion[$version->id] ?? collect();
                foreach ($versionAttempts as $attempt) {
                    $userAnswer = $userAnswers->get($attempt->id, collect())->firstWhere('versioned_question_id', $question->id);
                    if ($userAnswer) {
                        $totalScore += $userAnswer->score;
                        $attemptCount++;
                    }
                }

                $averageScorePerQuestionData[] = [
                    'question_text' => $question->question_text,
                    'average_score' => $attemptCount > 0 ? round($totalScore / $attemptCount, 2) : 0,
                ];
            }

            $averageScorePerQuestionDataByVersion[$version->id] = $averageScorePerQuestionData;

            // Obliczenie liczby zdanych i niezdanych podejść dla tej wersji
            $passingData = ['passed' => 0, 'failed' => 0];
            $versionAttempts = $groupedAttemptsByVersion[$version->id] ?? collect();

            if ($version->has_passing_criteria && $questions->count() > 0) {
                $totalPossiblePoints = $questions->sum('points');

                foreach ($versionAttempts as $attempt) {
                    $scorePercentage = ($totalPossiblePoints > 0) ? ($attempt->calculated_score / $totalPossiblePoints) * 100 : 0;
                    
                    if ($version->passing_score && $attempt->calculated_score >= $version->passing_score) {
                        $passingData['passed']++;
                    } elseif ($version->passing_percentage && $scorePercentage >= $version->passing_percentage) {
                        $passingData['passed']++;
                    } else {
                        $passingData['failed']++;
                    }
                }
            }

            $passingDataByVersion[$version->id] = $passingData;
        }

        return view('quizzes.owner_attempts', [
            'quiz' => $quiz,
            'quizVersions' => $quizVersions,
            'groupedAttemptsByVersion' => $groupedAttemptsByVersion,
            'groupedUserAnswers' => $userAnswers,
            'averageScorePerQuestionDataByVersion' => $averageScorePerQuestionDataByVersion,
            'passingDataByVersion' => $passingDataByVersion,
        ]);
    }

    public function updateScores(Request $request, $quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::findOrFail($quizId);

        if ($quiz->user_id !== $userId) {
            abort(403, 'Nie masz uprawnień do edycji tych informacji.');
        }

        $attemptId = $request->input('attempt_id');
        $scores = $request->input('scores', []);

        foreach ($scores as $answerId => $score) {
            $userAnswer = UserAnswer::find($answerId);
            if ($userAnswer && $userAnswer->attempt_id == $attemptId) {
                $userAnswer->score = $score;
                $userAnswer->is_manual_score = true;
                $userAnswer->save();
            }
        }

        $attempt = UserAttempt::find($attemptId);
        if ($attempt) {
            $totalScore = UserAnswer::where('attempt_id', $attemptId)->sum('score');
            $attempt->score = $totalScore;
            $attempt->save();
        }

        return redirect()->route('quiz.owner_attempts', ['quiz' => $quizId])->with('success', 'Punkty zostały zaktualizowane.');
    }
}
