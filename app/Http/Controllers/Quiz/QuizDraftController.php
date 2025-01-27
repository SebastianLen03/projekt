<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizVersion;
use App\Models\VersionedQuestion;
use App\Models\VersionedAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizDraftController extends Controller
{
    /**
     * Zapisuje cały quiz wraz z pytaniami i przypisanymi grupami do wersji roboczej (draft).
     * Dawna metoda "saveAll".
     */
    public function saveAll(Request $request, $quizId)
    {
        $user = Auth::user();

        // (Walidacja przeniesiona z poprzedniego kodu)
        $validated = $request->validate([
            'title'                => 'required|string|max:255',
            'has_passing_criteria' => 'required|boolean',
            'passing_type'         => 'nullable|string|in:points,percentage',
            'has_time_limit'       => 'required|boolean',
            'passing_score'        => 'nullable|integer|min:1',
            'passing_percentage'   => 'nullable|integer|min:0|max:100',
            'time_limit'           => 'nullable|integer|min:1',

            'questions'                     => 'required|array',
            'questions.*.id'               => 'nullable|exists:versioned_questions,id',
            'questions.*.question_text'     => 'required|string',
            'questions.*.type'             => 'required|string|in:multiple_choice,single_choice,open',
            'questions.*.points'           => 'required|integer|min:1',
            'questions.*.points_type'      => 'nullable|string|in:full,partial',

            'questions.*.expected_code'     => 'nullable|string',
            'questions.*.language'          => 'nullable|string|in:php,java',

            'questions.*.answers'                  => 'nullable|array',
            'questions.*.answers.*.id'            => 'nullable|exists:versioned_answers,id',
            'questions.*.answers.*.text'          => 'required|string',
            'questions.*.answers.*.is_correct'    => 'required|boolean',
        ]);

        // Znajdź quiz
        $quiz = Quiz::find($quizId);
        if (!$quiz) {
            return response()->json(['message' => 'Quiz nie został znaleziony.'], 404);
        }

        // Własność
        if ($quiz->user_id !== $user->id) {
            return response()->json(['message' => 'Nie masz uprawnień.'], 403);
        }

        // Aktualizacja pól quizu
        $quiz->title             = $validated['title'];
        // $quiz->is_active       – nie ustawiamy tutaj

        $quiz->save();

        // Znalezienie lub stworzenie draftu
        $draftVersion = $quiz->quizVersions()->where('is_draft', true)->first();
        if (!$draftVersion) {
            // Tworzymy nowy draft
            $draftVersion = QuizVersion::create([
                'quiz_id'              => $quiz->id,
                'version_number'       => 1,
                'is_draft'             => true,
                'is_active'            => false,
                'has_passing_criteria' => $validated['has_passing_criteria'],
                'passing_score'        => $validated['passing_score'],
                'passing_percentage'   => $validated['passing_percentage'],
                'time_limit'           => $validated['time_limit'],
            ]);
        } else {
            // Aktualizacja draftu
            $draftVersion->has_passing_criteria = $validated['has_passing_criteria'];

            if ($draftVersion->has_passing_criteria) {
                if ($request->input('passing_type') === 'points') {
                    $draftVersion->passing_score      = $validated['passing_score'];
                    $draftVersion->passing_percentage = null;
                } elseif ($request->input('passing_type') === 'percentage') {
                    $draftVersion->passing_percentage = $validated['passing_percentage'];
                    $draftVersion->passing_score      = null;
                }
            } else {
                $draftVersion->passing_score      = null;
                $draftVersion->passing_percentage = null;
            }

            if ($validated['has_time_limit']) {
                $draftVersion->time_limit = $validated['time_limit'];
            } else {
                $draftVersion->time_limit = null;
            }

            $draftVersion->save();
        }

        // Obsługa pytań powiązanych z wersją roboczą
        foreach ($validated['questions'] as $questionData) {
            if (isset($questionData['id'])) {
                // Aktualizacja
                $versionedQuestion = VersionedQuestion::find($questionData['id']);
                if ($versionedQuestion) {
                    $versionedQuestion->question_text = $questionData['question_text'];
                    $versionedQuestion->type          = $questionData['type'];
                    $versionedQuestion->points        = $questionData['points'];

                    if ($questionData['type'] === 'multiple_choice') {
                        $versionedQuestion->points_type = $questionData['points_type'] ?? 'full';
                    } else {
                        $versionedQuestion->points_type = null;
                    }
                    $versionedQuestion->save();

                    // Usuń stare odpowiedzi
                    $versionedQuestion->versionedAnswers()->delete();

                    // Nowe odpowiedzi
                    if ($questionData['type'] === 'open') {
                        $versionedQuestion->versionedAnswers()->create([
                            'expected_code' => $questionData['expected_code'] ?? null,
                            'language'      => $questionData['language'] ?? 'php',
                        ]);
                    } else {
                        if (!empty($questionData['answers'])) {
                            foreach ($questionData['answers'] as $ans) {
                                $versionedQuestion->versionedAnswers()->create([
                                    'text'       => $ans['text'],
                                    'is_correct' => $ans['is_correct'],
                                ]);
                            }
                        }
                    }
                }
            } else {
                // Tworzenie nowego pytania w drafcie
                $versionedQuestion = VersionedQuestion::create([
                    'quiz_version_id' => $draftVersion->id,
                    'question_text' => $questionData['question_text'],
                    'type'          => $questionData['type'],
                    'points'        => $questionData['points'],
                    'points_type'   => ($questionData['type'] === 'multiple_choice')
                                       ? ($questionData['points_type'] ?? 'full')
                                       : null,
                ]);

                // Odpowiedzi
                if ($questionData['type'] === 'open') {
                    $versionedQuestion->versionedAnswers()->create([
                        'expected_code' => $questionData['expected_code'] ?? null,
                        'language'      => $questionData['language'] ?? 'php',
                    ]);
                } else {
                    if (!empty($questionData['answers'])) {
                        foreach ($questionData['answers'] as $ans) {
                            $versionedQuestion->versionedAnswers()->create([
                                'text'       => $ans['text'],
                                'is_correct' => $ans['is_correct'],
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'Quiz (draft) zapisany pomyślnie.']);
    }

    /**
     * Finalizacja draftu → pełna wersja (is_draft=0, is_active=0) + utworzenie nowego draftu.
     */
    public function finalizeDraftVersion(Request $request, $quizId)
    {
        $user = Auth::user();
        $quiz = Quiz::where('id', $quizId)->where('user_id', $user->id)->firstOrFail();

        // Znajdź draft
        $draftVersion = QuizVersion::where('quiz_id', $quiz->id)
            ->where('is_draft', true)
            ->firstOrFail();

        // Walidacja nazwy wersji
        $request->validate([
            'version_name' => 'nullable|string|max:255'
        ]);

        // Zmiana draftu na pełną wersję
        $draftVersion->is_draft = false;
        $draftVersion->is_active = false;
        $draftVersion->version_name = $request->input('version_name') ?: ('Wersja ' . $draftVersion->version_number);
        $draftVersion->save();

        // Tworzymy nowy draft
        $newDraft = $draftVersion->replicate([
            'created_at','updated_at','is_draft','is_active'
        ]);
        $newDraft->is_draft = true;
        $newDraft->is_active = false;
        $newDraft->version_number = $draftVersion->version_number + 1;
        $newDraft->version_name = null;
        $newDraft->save();

        // Skopiuj pytania/odpowiedzi do nowego draftu
        foreach ($draftVersion->versionedQuestions as $oldQ) {
            $newQ = $oldQ->replicate(['created_at','updated_at']);
            $newQ->quiz_version_id = $newDraft->id;
            $newQ->save();

            foreach ($oldQ->versionedAnswers as $oldA) {
                $newA = $oldA->replicate(['created_at','updated_at']);
                $newA->versioned_question_id = $newQ->id;
                $newA->save();
            }
        }

        return back()->with('message', 'Zapisano pełną wersję (nr '. $draftVersion->version_number .'). Utworzono nowy draft.');
    }

        public function updateAccess(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdź właściciela
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }

        // Sprawdź, czy któraś wersja nie jest aktywna:
        // (Założenie: nie można zmieniać, gdy jest wersja aktywna)
        if ($quiz->quizVersions()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'Nie można zmieniać ustawień — istnieje aktywna wersja.'], 403);
        }

        // Walidacja: w zależności, co przesyłamy w body
        $data = $request->validate([
            'multiple_attempts' => 'sometimes|boolean',
            'is_public'         => 'sometimes|boolean',
            'group_id'          => 'sometimes|integer|exists:groups,id'
        ]);

        // 1) Zmiana multiple_attempts
        if (array_key_exists('multiple_attempts', $data)) {
            $quiz->multiple_attempts = $data['multiple_attempts'];
            $quiz->save();
            return response()->json(['message' => 'Ustawienie wielokrotnych podejść zostało zmienione.']);
        }

        // 2) Zmiana is_public
        if (array_key_exists('is_public', $data)) {
            $quiz->is_public = $data['is_public'];
            $quiz->save();

            // Jeśli quiz staje się publiczny -> odpinamy wszystkie grupy
            if ($quiz->is_public) {
                $quiz->groups()->detach();
            }

            return response()->json(['message' => 'Ustawienie publiczności quizu zostało zmienione.']);
        }

        // 3) Zmiana przynależności do grupy (toggle)
        if (array_key_exists('group_id', $data)) {
            $groupId = $data['group_id'];
            // Sprawdź, czy quiz jest już przypięty do danej grupy
            if ($quiz->groups->contains($groupId)) {
                $quiz->groups()->detach($groupId);
                return response()->json(['message' => 'Usunięto grupę z quizu.']);
            } else {
                $quiz->groups()->attach($groupId);
                return response()->json(['message' => 'Dodano grupę do quizu.']);
            }
        }

        return response()->json(['message' => 'Nie podano żadnych pól do zmiany.'], 400);
    }
}
