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
        $quiz = Quiz::with(['questions.answers', 'groups'])->findOrFail($id);
    
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do edycji tego quizu.');
        }

        $userGroups = Auth::user()->groups;
        $userAttempts = $quiz->userAttempts()->with('user')->get();
    
        return view('quizzes.manage', [
            'quiz' => $quiz,
            'questions' => $quiz->questions,
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
        $quiz = Quiz::with('questions.answers')->findOrFail($quizId);

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
            'questions.*.id' => 'nullable|exists:questions,id',
            'questions.*.question_text' => 'required|string',
            'questions.*.type' => 'required|in:open,single_choice,multiple_choice',
            'questions.*.expected_code' => 'nullable|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.points_type' => 'nullable|in:full,partial',
            'questions.*.answers' => 'nullable|array',
            'questions.*.answers.*.text' => 'required|string',
            'questions.*.answers.*.is_correct' => 'required|boolean',
        ]);

        // Aktualizacja quizu
        $quiz->title = $validatedData['title'];
        $quiz->is_public = $validatedData['is_public'] ?? false;
        $quiz->multiple_attempts = $validatedData['multiple_attempts'] ?? false;

        // Ponieważ pola passing_* i time_limit są teraz w quiz_versions, przechowujemy je tymczasowo w zmiennych
        $passingCriteria = [
            'has_passing_criteria' => $validatedData['has_passing_criteria'] ?? false,
            'passing_score' => $validatedData['passing_score'] ?? null,
            'passing_percentage' => $validatedData['passing_percentage'] ?? null,
        ];

        $timeLimit = $validatedData['has_time_limit'] ? $validatedData['time_limit'] : null;

        $quiz->is_active = false; // Dezaktywuj quiz po zmianach
        $quiz->save();

        // Aktualizacja przypisanych grup (jeśli quiz nie jest publiczny)
        if (!$quiz->is_public && isset($validatedData['groups'])) {
            // Sprawdź, czy wszystkie wybrane grupy należą do użytkownika
            $userGroupIds = Auth::user()->groups->pluck('id')->toArray();
            $selectedGroupIds = $validatedData['groups'];

            foreach ($selectedGroupIds as $groupId) {
                if (!in_array($groupId, $userGroupIds)) {
                    return response()->json(['message' => 'Nie masz uprawnień do przypisania quizu do wybranych grup.'], 403);
                }
            }

            // Synchronizacja grup
            $quiz->groups()->sync($selectedGroupIds);
        } else {
            // Odłącz wszystkie grupy, jeśli quiz jest publiczny lub nie wybrano żadnych grup
            $quiz->groups()->detach();
        }

        $updatedQuestions = [];

        foreach ($validatedData['questions'] as $questionData) {
            // Dodatkowa walidacja dla pytań zamkniętych
            if (in_array($questionData['type'], ['single_choice', 'multiple_choice'])) {
                if (empty($questionData['answers']) || !is_array($questionData['answers'])) {
                    return response()->json(['message' => 'Pytania zamknięte muszą mieć odpowiedzi.'], 422);
                }

                $correctAnswers = collect($questionData['answers'])->where('is_correct', true);
                if ($correctAnswers->count() < 1) {
                    return response()->json(['message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna w pytaniu: ' . strip_tags($questionData['question_text'])], 422);
                }
            }

            // Aktualizacja lub tworzenie nowego pytania
            if (!empty($questionData['id'])) {
                $question = Question::findOrFail($questionData['id']);
            } else {
                $question = new Question();
                $question->quiz_id = $quiz->id;
            }

            $question->question_text = $questionData['question_text'];
            $question->type = $questionData['type'];
            $question->points = $questionData['points'];

            // Ustawienie points_type dla pytań wielokrotnego wyboru
            if ($questionData['type'] === 'multiple_choice') {
                $question->points_type = $questionData['points_type'] ?? 'full';
            } else {
                $question->points_type = null;
            }

            $question->save();

            // Usuń istniejące odpowiedzi
            $question->answers()->delete();

            if ($questionData['type'] === 'open') {
                $answer = new Answer();
                $answer->question_id = $question->id;
                $answer->expected_code = $questionData['expected_code'] ?? null;
                $answer->text = null;
                $answer->is_correct = null;
                $answer->save();
            } else {
                foreach ($questionData['answers'] as $answerData) {
                    $answer = new Answer();
                    $answer->question_id = $question->id;
                    $answer->text = $answerData['text'];
                    $answer->is_correct = $answerData['is_correct'];
                    $answer->expected_code = null;
                    $answer->save();
                }
            }

            $updatedQuestions[] = [
                'id' => $question->id,
                'temp_id' => $questionData['id'] ?? null,
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'text' => $answer->text,
                    ];
                }),
            ];
        }

        // Dezaktywacja quizu po wprowadzeniu zmian
        $quiz->is_active = false;
        $quiz->save();

        // Przechowaj kryteria zdawalności i time_limit w sesji lub w innym miejscu, jeśli potrzebujesz ich podczas aktywacji
        session([
            'passing_criteria' => $passingCriteria,
            'time_limit' => $timeLimit,
        ]);

        return response()->json([
            'message' => 'Quiz i pytania zostały zapisane pomyślnie. Quiz został dezaktywowany ze względu na wprowadzone zmiany.',
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
        $quiz = Quiz::with('questions.answers')->findOrFail($id);

        // Sprawdzenie, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do zmiany statusu tego quizu.'], 403);
        }

        $quiz->is_active = !$quiz->is_active;
        $quiz->save();

        if ($quiz->is_active) {
            // Pobierz kryteria zdawalności i time_limit z sesji lub z innego miejsca
            $passingCriteria = session('passing_criteria', [
                'has_passing_criteria' => false,
                'passing_score' => null,
                'passing_percentage' => null,
            ]);

            $timeLimit = session('time_limit', null);

            // Tworzenie nowej wersji quizu
            $latestVersionNumber = QuizVersion::where('quiz_id', $quiz->id)->max('version_number');
            $newVersionNumber = $latestVersionNumber ? $latestVersionNumber + 1 : 1;

            $quizVersion = QuizVersion::create([
                'quiz_id' => $quiz->id,
                'version_number' => $newVersionNumber,
                'has_passing_criteria' => $passingCriteria['has_passing_criteria'],
                'passing_score' => $passingCriteria['passing_score'],
                'passing_percentage' => $passingCriteria['passing_percentage'],
                'time_limit' => $timeLimit,
            ]);

            // Kopiowanie pytań i odpowiedzi do tabel wersjonowanych
            foreach ($quiz->questions as $question) {
                $versionedQuestion = VersionedQuestion::create([
                    'quiz_version_id' => $quizVersion->id,
                    'question_text' => $question->question_text,
                    'type' => $question->type,
                    'points' => $question->points,
                    'points_type' => $question->points_type,
                ]);

                if ($question->type === 'open') {
                    $originalAnswer = $question->answers->first();
                    VersionedAnswer::create([
                        'versioned_question_id' => $versionedQuestion->id,
                        'text' => null,
                        'is_correct' => null,
                        'expected_code' => $originalAnswer ? $originalAnswer->expected_code : null,
                    ]);
                } else {
                    foreach ($question->answers as $answer) {
                        VersionedAnswer::create([
                            'versioned_question_id' => $versionedQuestion->id,
                            'text' => $answer->text,
                            'is_correct' => $answer->is_correct,
                            'expected_code' => null,
                        ]);
                    }
                }
            }

            // Usuń kryteria zdawalności i time_limit z sesji po ich wykorzystaniu
            session()->forget('passing_criteria');
            session()->forget('time_limit');
        }

        return response()->json([
            'message' => 'Status quizu został zmieniony pomyślnie.',
            'is_active' => $quiz->is_active
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
            'quiz_id' => 'required|exists:quizzes,id',
            'question_text' => 'required|string|max:500',
            'type' => 'required|in:open,single_choice,multiple_choice',
            'expected_code' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'points_type' => 'nullable|in:full,partial', // Dodane pole do walidacji
            'answers' => 'nullable|array',
            'answers.*.text' => 'required|string|max:500',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // Dodatkowa walidacja dla pytań zamkniętych
        if (in_array($validatedData['type'], ['single_choice', 'multiple_choice'])) {
            if (empty($validatedData['answers']) || !is_array($validatedData['answers'])) {
                return response()->json(['message' => 'Pytania zamknięte muszą mieć odpowiedzi.'], 422);
            }

            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json(['message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna.'], 422);
            }
        }

        $question = Question::create([
            'quiz_id' => $validatedData['quiz_id'],
            'question_text' => $validatedData['question_text'],
            'type' => $validatedData['type'],
            'points' => $validatedData['points'],
            'points_type' => $validatedData['type'] === 'multiple_choice' ? ($validatedData['points_type'] ?? 'full') : null, // Dodane pole
        ]);

        if ($validatedData['type'] === 'open') {
            $answer = new Answer();
            $answer->question_id = $question->id;
            $answer->expected_code = $validatedData['expected_code'] ?? null;
            $answer->text = null;
            $answer->is_correct = null;
            $answer->save();
        } else {
            foreach ($validatedData['answers'] as $answerData) {
                Answer::create([
                    'question_id' => $question->id,
                    'text' => $answerData['text'],
                    'is_correct' => $answerData['is_correct'],
                    'expected_code' => null,
                ]);
            }
        }

        return response()->json(['message' => 'Pytanie utworzone pomyślnie!', 'question_id' => $question->id]);
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
        $question = Question::findOrFail($id);

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($question->quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do aktualizacji tego pytania.'], 403);
        }

        $validatedData = $request->validate([
            'question_text' => 'required|string',
            'type' => 'required|in:open,single_choice,multiple_choice',
            'points' => 'required|integer|min:1', // Nowe pole dla punktów
            'points_type' => 'nullable|in:full,partial', // Dodane pole do walidacji
            'expected_code' => 'nullable|string',
            'answers' => 'nullable|array',
            'answers.*.text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // Dodatkowa walidacja dla pytań zamkniętych
        if (in_array($validatedData['type'], ['single_choice', 'multiple_choice'])) {
            if (empty($validatedData['answers']) || !is_array($validatedData['answers'])) {
                return response()->json(['message' => 'Pytania zamknięte muszą mieć odpowiedzi.'], 422);
            }

            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json(['message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna.'], 422);
            }
        }

        $question->update([
            'question_text' => $validatedData['question_text'],
            'type' => $validatedData['type'],
            'points' => $validatedData['points'], // Aktualizacja liczby punktów
            'points_type' => $validatedData['type'] === 'multiple_choice' ? ($validatedData['points_type'] ?? 'full') : null, // Aktualizacja points_type
        ]);

        // Usuń istniejące odpowiedzi
        $question->answers()->delete();

        if ($validatedData['type'] === 'open') {
            $answer = new Answer();
            $answer->question_id = $question->id;
            $answer->expected_code = $validatedData['expected_code'] ?? null;
            $answer->text = null;
            $answer->is_correct = null;
            $answer->save();
        } else {
            foreach ($validatedData['answers'] as $answerData) {
                Answer::create([
                    'question_id' => $question->id,
                    'text' => $answerData['text'],
                    'is_correct' => $answerData['is_correct'],
                    'expected_code' => null,
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
        $question = Question::findOrFail($id);

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($question->quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do usunięcia tego pytania.'], 403);
        }

        // Usuń powiązane odpowiedzi
        $question->answers()->delete();

        // Usuń pytanie
        $question->delete();

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
