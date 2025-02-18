<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Quiz;
use App\Models\QuizVersion;
use App\Models\VersionedQuestion;
use App\Models\VersionedAnswer;
use App\Models\Answer;

class QuizQuestionController extends Controller
{
    /**
     * Tworzenie nowego pytania (AJAX) – dawny storeQuestion
     */
    public function storeQuestion(Request $request)
    {
        $validatedData = $request->validate([
            'quiz_id'       => 'required|exists:quizzes,id',
            'question_text' => 'required|string|max:500',
            'type'          => 'required|in:open,single_choice,multiple_choice',
            'expected_code' => 'nullable|string',
            'language'      => 'required_if:type,open|in:php,java',
            'points'        => 'required|integer|min:1',
            'points_type'   => 'nullable|in:full,partial',
            'answers'       => 'nullable|array',
            'answers.*.text'      => 'required|string|max:500',
            'answers.*.is_correct'=> 'required|boolean',
        ]);

        $quiz = Quiz::findOrFail($validatedData['quiz_id']);
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }

        // Znajdź draft
        $draftVersion = QuizVersion::where('quiz_id', $quiz->id)
            ->where('is_draft', true)
            ->first();

        if (!$draftVersion) {
            // Gdyby nie było draftu – stwórz
            $latestFinal = QuizVersion::where('quiz_id', $quiz->id)
                ->where('is_draft', false)
                ->max('version_number');
            $latestFinal = $latestFinal ?: 0;

            $draftVersion = QuizVersion::create([
                'quiz_id'        => $quiz->id,
                'version_number' => $latestFinal + 1,
                'is_draft'       => true,
                'is_active'      => false,
            ]);
        }

        // Walidacja pytań zamkniętych
        if (in_array($validatedData['type'], ['single_choice','multiple_choice'])) {
            if (empty($validatedData['answers'])) {
                return response()->json([
                    'message' => 'Pytania zamknięte muszą mieć odpowiedzi.'
                ], 422);
            }
            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json([
                    'message' => 'Przynajmniej jedna odpowiedź musi być poprawna.'
                ], 422);
            }
        }

        $versionedQuestion = VersionedQuestion::create([
            'quiz_version_id' => $draftVersion->id,
            'question_text'   => $validatedData['question_text'],
            'type'            => $validatedData['type'],
            'points'          => $validatedData['points'],
            'points_type'     => ($validatedData['type'] === 'multiple_choice')
                                 ? ($validatedData['points_type'] ?? 'full')
                                 : null,
        ]);

        if ($validatedData['type'] === 'open') {
            VersionedAnswer::create([
                'versioned_question_id' => $versionedQuestion->id,
                'expected_code' => $validatedData['expected_code'] ?? null,
                'language'      => $validatedData['language'] ?? 'php',
            ]);
        } else {
            foreach ($validatedData['answers'] as $ans) {
                VersionedAnswer::create([
                    'versioned_question_id' => $versionedQuestion->id,
                    'text'        => $ans['text'],
                    'is_correct'  => $ans['is_correct'],
                ]);
            }
        }

        return response()->json([
            'message'     => 'Pytanie utworzone pomyślnie!',
            'question_id' => $versionedQuestion->id,
        ]);
    }

    /**
     * Aktualizacja pytania (updateQuestion)
     */
    public function updateQuestion(Request $request, $id)
    {
        $versionedQuestion = VersionedQuestion::findOrFail($id);
        $quizVersion = $versionedQuestion->quizVersion;
        $quiz = $quizVersion->quiz;

        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }

        $validatedData = $request->validate([
            'question_text' => 'required|string|max:500',
            'type' => 'required|in:open,single_choice,multiple_choice',
            'points' => 'required|integer|min:1',
            'points_type' => 'nullable|in:full,partial',
            'expected_code' => 'nullable|string',
            'language' => 'required_if:type,open|in:php,java',
            'answers' => 'nullable|array',
            'answers.*.text' => 'required|string|max:500',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        if (in_array($validatedData['type'], ['single_choice', 'multiple_choice'])) {
            if (empty($validatedData['answers'])) {
                return response()->json(['message' => 'Pytania zamknięte muszą mieć odpowiedzi.'], 422);
            }
            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json(['message' => 'Przynajmniej jedna odpowiedź poprawna.'], 422);
            }
        }

        $versionedQuestion->update([
            'question_text' => $validatedData['question_text'],
            'type'          => $validatedData['type'],
            'points'        => $validatedData['points'],
            'points_type'   => ($validatedData['type'] === 'multiple_choice')
                                ? ($validatedData['points_type'] ?? 'full')
                                : null,
        ]);

        // Usuwamy stare answer
        $versionedQuestion->answers()->delete();

        if ($validatedData['type'] === 'open') {
            VersionedAnswer::create([
                'versioned_question_id' => $versionedQuestion->id,
                'expected_code' => $validatedData['expected_code'] ?? null,
                'language'      => $validatedData['language'] ?? 'php',
            ]);
        } else {
            foreach ($validatedData['answers'] as $ans) {
                VersionedAnswer::create([
                    'versioned_question_id' => $versionedQuestion->id,
                    'text'       => $ans['text'],
                    'is_correct' => $ans['is_correct'],
                ]);
            }
        }

        return response()->json(['message' => 'Pytanie zaktualizowane pomyślnie!']);
    }

    /**
     * Usunięcie pytania
     */
    public function deleteQuestion($id)
    {
        $versionedQuestion = VersionedQuestion::findOrFail($id);
        $quizVersion = $versionedQuestion->quizVersion;
        $quiz = $quizVersion->quiz;

        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }

        $versionedQuestion->answers()->delete();
        $versionedQuestion->delete();

        return response()->json(['message' => 'Pytanie zostało pomyślnie usunięte']);
    }

    /**
     * Usuwanie odpowiedzi (jeśli chcesz):
     */
    public function deleteAnswer($id)
    {
        $answer = VersionedAnswer::findOrFail($id);
        $question = $answer->Question;        
    
        // Sprawdzanie istnienia pytania i wersji
        if (!$question) {
            return response()->json(['message' => 'Brak pytania.'], 404);
        }
    
        // Pobierz wersję
        $version = $question->quizVersion;
        if (!$version) {
            return response()->json(['message' => 'Brak wersji quizu.'], 404);
        }
    
        // Czy to jest draft?
        if (!$version->is_draft) {
            return response()->json(['message' => 'Nie można usuwać odpowiedzi z ukończonej wersji quizu.'], 403);
        }
    
        // Czy quiz należy do aktualnego użytkownika (jeśli Ci to potrzebne):
        if ($version->quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień do tego quizu.'], 403);
        }
    
        $answer->delete();
    
        return response()->json(['message' => 'Odpowiedź została pomyślnie usunięta.']);
    }
    
    
}
