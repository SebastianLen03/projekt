<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UserAnswer;
use App\Models\UserAttempt;
use App\Models\Question;
use App\Models\Answer;
use App\Models\QuizVersion;
use App\Models\VersionedQuestion;
use App\Models\VersionedAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizManageController extends Controller
{
    /**
     * Wyświetlenie formularza edycji quizu.
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $quiz = Quiz::with('groups')->findOrFail($id);
    
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do edycji tego quizu.');
        }
    
        // 1. Szukamy wersji roboczej (is_draft=1).
        $draftVersion = $quiz->quizVersions()
            ->where('is_draft', true)
            ->first();
    
        $questions = collect();
    
        if ($draftVersion) {
            // Jeśli jest wersja robocza, to właśnie ją pokażemy w edycji
            $questions = $draftVersion->versionedQuestions()->with('answers')->get();
        } else {
            // Jeśli nie ma roboczej → pokazujemy ostatnią finalną
            $latestVersion = $quiz->quizVersions()
                ->where('is_draft', false)
                ->orderByDesc('version_number')
                ->first();
    
            if ($latestVersion) {
                $questions = $latestVersion->versionedQuestions()->with('answers')->get();
            }
        }
    
        $userGroups = Auth::user()->groups;
        $userAttempts = $quiz->userAttempts()->with('user')->get();
    
        return view('quizzes.manage', [
            'quiz'       => $quiz,
            'questions'  => $questions,
            'userGroups' => $userGroups,
            'userAttempts' => $userAttempts->unique('user_id'),
        ]);
    }
    
    
    /**
     * Zapisuje cały quiz wraz z pytaniami, odpowiedziami i przypisanymi grupami.
     * Po zapisaniu quizu dezaktywuje go, a gdy quiz jest aktywowany, tworzy nową wersję.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $quizId
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAll(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
    
        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do edycji tego quizu.'], 403);
        }
    
        // Walidacja danych wejściowych
        $validatedData = $request->validate([
            'title' => 'required|string',
            'is_public' => 'boolean',
            'multiple_attempts' => 'boolean',
            'has_passing_criteria' => 'boolean',
            'passing_score' => 'nullable|integer|min:1',
            'passing_percentage' => 'nullable|integer|min:1|max:100',
            'has_time_limit' => 'boolean',
            'time_limit' => 'nullable|integer|min:1',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:groups,id',
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'nullable|exists:versioned_questions,id',
            'questions.*.question_text' => 'required|string',
            'questions.*.type' => 'required|in:open,single_choice,multiple_choice',
            'questions.*.expected_code' => 'nullable|string',
            'questions.*.language' => 'nullable|in:php,java',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.points_type' => 'nullable|in:full,partial',
            'questions.*.answers' => 'nullable|array',
            'questions.*.answers.*.text' => 'required|string',
            'questions.*.answers.*.is_correct' => 'required|boolean',
        ]);
    
        // Aktualizacja quizu (podstawowe pola)
        $quiz->title             = $validatedData['title'];
        $quiz->is_public         = $validatedData['is_public'] ?? false;
        $quiz->multiple_attempts = $validatedData['multiple_attempts'] ?? false;
        $quiz->is_active         = false; // Dezaktywuj quiz po zmianach
        $quiz->save();
    
        // Pola passing_* i time_limit – do zmiennych (potem użyjemy przy aktywacji)
        $passingCriteria = [
            'has_passing_criteria' => $validatedData['has_passing_criteria'] ?? false,
            'passing_score'        => $validatedData['passing_score'] ?? null,
            'passing_percentage'   => $validatedData['passing_percentage'] ?? null,
        ];
        $timeLimit = $validatedData['has_time_limit'] ? $validatedData['time_limit'] : null;
    
        // Aktualizacja przypisanych grup
        if (!$quiz->is_public && isset($validatedData['groups'])) {
            $userGroupIds = Auth::user()->groups->pluck('id')->toArray();
            $selectedGroupIds = $validatedData['groups'];
    
            foreach ($selectedGroupIds as $groupId) {
                if (!in_array($groupId, $userGroupIds)) {
                    return response()->json([
                        'message' => 'Nie masz uprawnień do przypisania quizu do wybranych grup.'
                    ], 403);
                }
            }
            $quiz->groups()->sync($selectedGroupIds);
        } else {
            $quiz->groups()->detach();
        }
    
        // Znajdź lub utwórz "wersję roboczą" (draft)
        // 1) Szukamy is_draft=1
        $draftVersion = QuizVersion::where('quiz_id', $quiz->id)
            ->where('is_draft', true)
            ->first();
    
        if (!$draftVersion) {
            // 2) Jeśli nie istnieje → tworzymy nową wersję z numerem = max(finalnych) + 1
            $latestFinal = QuizVersion::where('quiz_id', $quiz->id)
                ->where('is_draft', false)
                ->max('version_number');
            $latestFinal = $latestFinal ?: 0;
    
            $draftVersion = QuizVersion::create([
                'quiz_id'              => $quiz->id,
                'version_number'       => $latestFinal + 1, // nowy numer
                'is_draft'             => true,
                // Ustawiamy parametry testu
                'has_passing_criteria' => $passingCriteria['has_passing_criteria'],
                'passing_score'        => $passingCriteria['passing_score'],
                'passing_percentage'   => $passingCriteria['passing_percentage'],
                'time_limit'           => $timeLimit,
            ]);
        } else {
            // Zaktualizuj passing_* i time_limit w istniejącej wersji roboczej
            $draftVersion->update([
                'has_passing_criteria' => $passingCriteria['has_passing_criteria'],
                'passing_score'        => $passingCriteria['passing_score'],
                'passing_percentage'   => $passingCriteria['passing_percentage'],
                'time_limit'           => $timeLimit,
            ]);
        }
    
        // Przetwarzanie pytań
        $updatedQuestions = [];
    
        foreach ($validatedData['questions'] as $questionData) {
            // Sprawdzenie pytań zamkniętych
            if (in_array($questionData['type'], ['single_choice', 'multiple_choice'])) {
                if (empty($questionData['answers']) || !is_array($questionData['answers'])) {
                    return response()->json([
                        'message' => 'Pytania zamknięte muszą mieć odpowiedzi.'
                    ], 422);
                }
                $correctAnswers = collect($questionData['answers'])->where('is_correct', true);
                if ($correctAnswers->count() < 1) {
                    return response()->json([
                        'message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna w pytaniu: ' 
                                     . strip_tags($questionData['question_text'])
                    ], 422);
                }
            }
    
            // Znajdź lub utwórz VersionedQuestion w tej wersji roboczej
            $versionedQuestion = null;
            if (!empty($questionData['id'])) {
                // Tylko, jeśli to ID faktycznie należy do "draftVersion"
                $versionedQuestion = VersionedQuestion::where('id', $questionData['id'])
                    ->where('quiz_version_id', $draftVersion->id)
                    ->first();
            }
    
            if (!$versionedQuestion) {
                $versionedQuestion = new VersionedQuestion();
                $versionedQuestion->quiz_version_id = $draftVersion->id;
            }
    
            $versionedQuestion->question_text = $questionData['question_text'];
            $versionedQuestion->type          = $questionData['type'];
            $versionedQuestion->points        = $questionData['points'];
            $versionedQuestion->points_type   = ($questionData['type'] === 'multiple_choice')
                ? ($questionData['points_type'] ?? 'full')
                : null;
            $versionedQuestion->save();
    
            // Usuwamy stare odpowiedzi
            $versionedQuestion->answers()->delete();
    
            // Dodajemy nowe
            if ($questionData['type'] === 'open') {
                VersionedAnswer::create([
                    'versioned_question_id' => $versionedQuestion->id,
                    'expected_code' => $questionData['expected_code'] ?? null,
                    'language'      => $questionData['language'] ?? 'php',
                    'text'          => null,
                    'is_correct'    => null,
                ]);
            } else {
                foreach ($questionData['answers'] as $ans) {
                    VersionedAnswer::create([
                        'versioned_question_id' => $versionedQuestion->id,
                        'text'        => $ans['text'],
                        'is_correct'  => $ans['is_correct'],
                        'expected_code' => null,
                        'language'    => null,
                    ]);
                }
            }
    
            $updatedQuestions[] = [
                'id'      => $versionedQuestion->id,
                'temp_id' => $questionData['id'] ?? null,
            ];
        }
    
        // Zapisz w sesji passing_criteria i time_limit (przyda się w togglu)
        session([
            'passing_criteria' => $passingCriteria,
            'time_limit'       => $timeLimit,
        ]);
    
        return response()->json([
            'message' => 'Quiz i pytania zapisane w wersji roboczej. Quiz został dezaktywowany.',
            'updated_questions' => $updatedQuestions,
        ]);
    }
    
    

    /**
     * Aktualizacja podstawowych informacji o quizie.
     * Używane przez AJAX.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Walidacja danych wejściowych
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'time_limit' => 'required|integer|min:1',
            'is_public' => 'boolean',
        ]);

        // Znajdź quiz i aktualizuj dane
        $quiz = Quiz::findOrFail($id);

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do aktualizacji tego quizu.'], 403);
        }

        $quiz->update($validatedData);

        return response()->json(['message' => 'Quiz zaktualizowany pomyślnie!']);
    }

    /**
     * Tworzenie nowego pustego quizu i przekierowanie do jego edycji.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createNewQuiz()
    {
        // Utwórz nowy quiz dla zalogowanego użytkownika
        $quiz = Quiz::create([
            'title' => 'Nowy Quiz',
            'user_id' => Auth::id(),
            'is_public' => false, // Domyślnie nowy quiz nie jest publiczny
        ]);

        // Przekieruj do strony edycji nowo utworzonego quizu
        return redirect()->route('quizzes.edit', ['quiz' => $quiz->id]);
    }

    /**
     * Przełączanie statusu aktywności quizu.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        try {
            $quiz = Quiz::with(['quizVersions'])->findOrFail($id);
    
            if ($quiz->user_id !== Auth::id()) {
                return response()->json(['message' => 'Brak uprawnień.'], 403);
            }
    
            $quiz->is_active = !$quiz->is_active;
            $quiz->save();
    
            if ($quiz->is_active) {
                // Quiz staje się aktywny → zamknij (zafinalizuj) wersję roboczą
                $draftVersion = $quiz->quizVersions
                    ->where('is_draft', true)
                    ->first();
    
                if ($draftVersion) {
                    // Ustaw is_draft=0
                    $draftVersion->is_draft = false;
    
                    // (opcjonalnie) pobierz z session
                    $passingCriteria = session('passing_criteria', [
                        'has_passing_criteria' => false,
                        'passing_score'       => null,
                        'passing_percentage'  => null,
                    ]);
                    $timeLimit = session('time_limit', null);
    
                    $draftVersion->has_passing_criteria = $passingCriteria['has_passing_criteria'];
                    $draftVersion->passing_score        = $passingCriteria['passing_score'];
                    $draftVersion->passing_percentage   = $passingCriteria['passing_percentage'];
                    $draftVersion->time_limit           = $timeLimit;
    
                    $draftVersion->save();
    
                    // Możesz wyczyścić sesję:
                    session()->forget('passing_criteria');
                    session()->forget('time_limit');
                }
            }
    
            return response()->json([
                'message' => 'Status quizu został zmieniony pomyślnie.',
                'is_active' => $quiz->is_active,
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Wystąpił błąd podczas zmiany statusu quizu.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    
    /**
     * Tworzenie nowego pytania.
     * Używane przez AJAX.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
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
    
        // Znajdź quiz
        $quiz = Quiz::findOrFail($validatedData['quiz_id']);
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }
    
        // Znajdź lub stwórz JEDYNĄ wersję roboczą
        $draftVersion = QuizVersion::where('quiz_id', $quiz->id)
            ->where('is_draft', true)
            ->first();
    
        if (!$draftVersion) {
            // Tworzymy nową wersję roboczą
            $latestFinal = QuizVersion::where('quiz_id', $quiz->id)
                ->where('is_draft', false)
                ->max('version_number');
            $latestFinal = $latestFinal ?: 0;
    
            $draftVersion = QuizVersion::create([
                'quiz_id'        => $quiz->id,
                'version_number' => $latestFinal + 1,
                'is_draft'       => true,
            ]);
        }
    
        // Walidacja pytań zamkniętych
        if (in_array($validatedData['type'], ['single_choice','multiple_choice'])) {
            if (empty($validatedData['answers']) || !is_array($validatedData['answers'])) {
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
    
        // Tworzymy versionedQuestion w wersji roboczej
        $versionedQuestion = VersionedQuestion::create([
            'quiz_version_id' => $draftVersion->id,
            'question_text'   => $validatedData['question_text'],
            'type'            => $validatedData['type'],
            'points'          => $validatedData['points'],
            'points_type'     => ($validatedData['type'] === 'multiple_choice')
                ? ($validatedData['points_type'] ?? 'full')
                : null,
        ]);
    
        // Tworzymy versionedAnswers
        if ($validatedData['type'] === 'open') {
            VersionedAnswer::create([
                'versioned_question_id' => $versionedQuestion->id,
                'expected_code' => $validatedData['expected_code'] ?? null,
                'language'      => $validatedData['language'] ?? 'php',
                'text'          => null,
                'is_correct'    => null,
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
     * Aktualizacja pytania.
     * Używane przez AJAX.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateQuestion(Request $request, $id)
    {
        $versionedQuestion = VersionedQuestion::findOrFail($id);
        $quizVersion = $versionedQuestion->quizVersion;
        $quiz = $quizVersion->quiz;
    
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }
    
        // Dezaktywuj quiz
        $quiz->is_active = false;
        $quiz->save();
    
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
            if (empty($validatedData['answers']) || !is_array($validatedData['answers'])) {
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
    
        $versionedQuestion->answers()->delete();
    
        if ($validatedData['type'] === 'open') {
            VersionedAnswer::create([
                'versioned_question_id' => $versionedQuestion->id,
                'expected_code' => $validatedData['expected_code'] ?? null,
                'language'      => $validatedData['language'] ?? 'php',
                'text'          => null,
                'is_correct'    => null,
            ]);
        } else {
            foreach ($validatedData['answers'] as $ans) {
                VersionedAnswer::create([
                    'versioned_question_id' => $versionedQuestion->id,
                    'text'       => $ans['text'],
                    'is_correct' => $ans['is_correct'],
                    'expected_code' => null,
                    'language'   => null,
                ]);
            }
        }
    
        return response()->json(['message' => 'Pytanie zaktualizowane pomyślnie!']);
    }
    
    

    /**
     * Usuwanie pytania.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteQuestion($id)
    {
        $versionedQuestion = VersionedQuestion::findOrFail($id);
        $quizVersion = $versionedQuestion->quizVersion;
        $quiz = $quizVersion->quiz;
    
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Brak uprawnień.'], 403);
        }
    
        // Dezaktywuj quiz
        $quiz->is_active = false;
        $quiz->save();
    
        $versionedQuestion->answers()->delete();
        $versionedQuestion->delete();
    
        return response()->json(['message' => 'Pytanie zostało pomyślnie usunięte']);
    }
    
    

    /**
     * Usuwanie quizu wraz z pytaniami i odpowiedziami.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        // Znajdź quiz i usuń wraz z pytaniami i odpowiedziami
        $quiz = Quiz::findOrFail($id);

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do usunięcia tego quizu.');
        }

        // Usuń powiązania z grupami
        $quiz->groups()->detach();

        // Usuń pytania i odpowiedzi
        $quiz->questions()->each(function ($question) {
            $question->answers()->delete();
            $question->delete();
        });

        // Usuń quiz
        $quiz->delete();

        return back()->with('message', 'Quiz został pomyślnie usunięty');
    }

    /**
     * Usuwanie odpowiedzi.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAnswer($id)
    {
        $answer = Answer::findOrFail($id);

        // Sprawdź, czy użytkownik jest właścicielem quizu powiązanego z odpowiedzią
        if ($answer->question->quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do usunięcia tej odpowiedzi.'], 403);
        }

        $answer->delete();

        return response()->json(['message' => 'Odpowiedź została pomyślnie usunięta.']);
    }

    /**
     * Resetowanie podejść użytkownika do quizu.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $quizId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetAttempts(Request $request, $quizId)
    {
        $userId = $request->input('user_id');
    
        // Usuń podejścia użytkownika do quizu
        UserAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->delete();
    
        // Pobierz identyfikatory wersji quizu powiązane z tym quizem
        $quizVersionIds = QuizVersion::where('quiz_id', $quizId)->pluck('id');
    
        // Pobierz identyfikatory wersjonowanych pytań powiązanych z tymi wersjami quizu
        $versionedQuestionIds = VersionedQuestion::whereIn('quiz_version_id', $quizVersionIds)->pluck('id');
    
        // Usuń odpowiedzi użytkownika na pytania z tego quizu
        UserAnswer::where('user_id', $userId)
            ->whereIn('versioned_question_id', $versionedQuestionIds)
            ->delete();
    
        return back()->with('message', 'Podejścia użytkownika oraz jego odpowiedzi zostały zresetowane.');
    }
    
}
