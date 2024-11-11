<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Group;
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

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do edycji tego quizu.');
        }

        // Pobierz grupy, do których użytkownik należy
        $userGroups = Auth::user()->groups;

        return view('quizzes.manage', [
            'quiz' => $quiz,
            'questions' => $quiz->questions,
            'userGroups' => $userGroups, // Przekazanie grup użytkownika do widoku
        ]);
    }

    /**
     * Zapisz cały quiz wraz z pytaniami, odpowiedziami i przypisanymi grupami.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $quizId
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveAll(Request $request, $quizId)
    {
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdź, czy użytkownik jest właścicielem quizu
        if ($quiz->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nie masz uprawnień do edycji tego quizu.'], 403);
        }

        // Walidacja danych wejściowych, w tym grup i opcji publicznej
        $validatedData = $request->validate([
            'title' => 'required|string',
            'time_limit' => 'required|integer|min:1',
            'is_public' => 'boolean', // Dodanie walidacji dla pola is_public
            'groups' => 'nullable|array',
            'groups.*' => 'exists:groups,id',
            'questions' => 'required|array|min:1',
            'questions.*.id' => 'nullable|exists:questions,id',
            'questions.*.question_text' => 'required|string',
            'questions.*.type' => 'required|in:open,single_choice,multiple_choice',
            'questions.*.expected_code' => 'nullable|string',
            'questions.*.answers' => 'nullable|array',
            // Usunięto walidację pola 'questions.*.answers.*.id'
            'questions.*.answers.*.text' => 'required|string',
            'questions.*.answers.*.is_correct' => 'required|boolean',
        ]);

        // Aktualizuj quiz
        $quiz->title = $validatedData['title'];
        $quiz->time_limit = $validatedData['time_limit'];
        $quiz->is_public = $validatedData['is_public'] ?? false; // Ustawienie is_public
        $quiz->save();

        // Aktualizacja grup przypisanych do quizu (tylko jeśli quiz nie jest publiczny)
        if (!$quiz->is_public && isset($validatedData['groups'])) {
            // Sprawdź, czy wszystkie wybrane grupy należą do użytkownika
            $userGroupIds = Auth::user()->groups->pluck('id')->toArray();
            $selectedGroupIds = $validatedData['groups'];

            foreach ($selectedGroupIds as $groupId) {
                if (!in_array($groupId, $userGroupIds)) {
                    return response()->json(['message' => 'Nie masz uprawnień do przypisania quizu do wybranych grup.'], 403);
                }
            }

            // Synchronizuj grupy przypisane do quizu
            $quiz->groups()->sync($selectedGroupIds);
        } else {
            // Jeśli quiz jest publiczny lub nie wybrano żadnych grup, odłącz wszystkie
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

            // Jeśli pytanie ma ID, aktualizuj je; w przeciwnym razie utwórz nowe
            if (!empty($questionData['id'])) {
                $question = Question::findOrFail($questionData['id']);
            } else {
                $question = new Question();
                $question->quiz_id = $quiz->id;
            }

            $question->question_text = $questionData['question_text'];
            $question->type = $questionData['type'];
            $question->save();

            // Usuń istniejące odpowiedzi
            $question->answers()->delete();

            if ($questionData['type'] === 'open') {
                $answer = new Answer();
                $answer->question_id = $question->id;
                $answer->expected_code = $questionData['expected_code'];
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

            $updatedQuestion = [
                'id' => $question->id,
                'temp_id' => $questionData['id'] ?? null,
                'answers' => $question->answers->map(function ($answer) {
                    return [
                        'id' => $answer->id,
                        'text' => $answer->text,
                    ];
                }),
            ];

            $updatedQuestions[] = $updatedQuestion;
        }

        return response()->json([
            'message' => 'Quiz i pytania zostały zapisane pomyślnie.',
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
            'time_limit' => 30,
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
            $quiz = Quiz::findOrFail($id);

            // Sprawdź, czy użytkownik jest właścicielem quizu
            if ($quiz->user_id !== Auth::id()) {
                return response()->json(['message' => 'Nie masz uprawnień do zmiany statusu tego quizu.'], 403);
            }

            $quiz->is_active = !$quiz->is_active; // Zmienia wartość is_active na przeciwną
            $quiz->save();

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
            'answers' => 'nullable|array',
            'answers.*.text' => 'required|string|max:500',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // Dodatkowa walidacja
        if (in_array($validatedData['type'], ['single_choice', 'multiple_choice'])) {
            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json(['message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna.'], 422);
            }
        }

        $question = Question::create([
            'quiz_id' => $validatedData['quiz_id'],
            'question_text' => $validatedData['question_text'],
            'type' => $validatedData['type']
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
            'expected_code' => 'nullable|string',
            'answers' => 'nullable|array',
            'answers.*.text' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
        ]);

        // Dodatkowa walidacja dla pytań zamkniętych
        if (in_array($validatedData['type'], ['single_choice', 'multiple_choice'])) {
            $correctAnswers = collect($validatedData['answers'])->where('is_correct', true);
            if ($correctAnswers->count() < 1) {
                return response()->json(['message' => 'Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna.'], 422);
            }
        }

        $question->update([
            'question_text' => $validatedData['question_text'],
            'type' => $validatedData['type']
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
}
