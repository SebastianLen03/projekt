<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use App\Models\Answer;

class QuizEditController extends Controller
{
    /**
     * Wyświetla formularz edycji quizu.
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        // Pobierz quiz wraz z pytaniami i odpowiedziami
        $quiz = Quiz::with('questions.answers')->findOrFail($id);

        // Zwróć widok 'manage', przekazując quiz oraz pytania
        return view('manage', ['quiz' => $quiz, 'questions' => $quiz->questions]);
    }
    
    // Wyświetlanie formularza zarządzania quizem
    public function manageQuiz($quizId)
    {
        $quiz = Quiz::with('questions.answers')->findOrFail($quizId);
        return view('quizzes.edit', compact('quiz'));
    }

    // Tworzenie nowego quizu
    public function createQuiz(Request $request)
    {
        $quiz = new Quiz(['title' => '', 'time_limit' => null, 'user_id' => Auth::id()]);
        $quiz->save();
        return redirect()->route('quizzes.edit', $quiz->id)->with('success', 'Nowy quiz został stworzony.');
    }

    // Zapis lub aktualizacja quizu
    public function saveQuiz(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'time_limit' => 'nullable|integer',
        ]);

        $quizId = $request->input('quiz_id');
        $quiz = Quiz::updateOrCreate(
            ['id' => $quizId],
            [
                'title' => $request->input('title'),
                'user_id' => Auth::id(),
                'time_limit' => $request->input('time_limit')
            ]
        );

        return redirect()->route('quizzes.edit', $quiz->id)->with('success', 'Quiz został zapisany.');
    }

    // Zapis nowego pytania dla określonego quizu
    public function saveQuestion(Request $request, $quizId)
    {
        $request->validate([
            'question_text' => 'required|string|max:255',
            'answers.*.text' => 'nullable|string|max:255',
            'answers.*.is_correct' => 'nullable|boolean',
        ]);

        $question = new Question([
            'quiz_id' => $quizId,
            'question_text' => $request->input('question_text'),
        ]);
        $question->save();

        foreach ($request->input('answers', []) as $answerData) {
            $question->answers()->create([
                'text' => $answerData['text'],
                'is_correct' => !empty($answerData['is_correct']),
            ]);
        }

        return response()->json(['success' => 'Pytanie zostało zapisane.', 'question_id' => $question->id]);
    }

    // Zaktualizuj istniejące pytanie po usunięciu starego
    public function updateAndSaveQuestion(Request $request, $quizId, $questionId)
    {
        $request->validate([
            'question_text' => 'required|string|max:255',
            'answers.*.text' => 'nullable|string|max:255',
            'answers.*.is_correct' => 'nullable|boolean',
        ]);

        $question = Question::findOrFail($questionId);
        $question->question_text = $request->input('question_text');
        $question->save();

        foreach ($request->input('answers', []) as $answerData) {
            $answer = $question->answers()->find($answerData['id']);
            if ($answer) {
                $answer->text = $answerData['text'];
                $answer->is_correct = !empty($answerData['is_correct']);
                $answer->save();
            }
        }

        return redirect()->route('quizzes.edit', $quizId)->with('success', 'Pytanie zostało zaktualizowane.');
    }

    // Usunięcie pytania
    public function deleteQuestion($quizId, $questionId)
    {
        $question = Question::findOrFail($questionId);
        $question->delete();

        return response()->json(['success' => 'Pytanie zostało usunięte.']);
    }

    // Usunięcie quizu
    public function destroy($id)
    {
        Quiz::findOrFail($id)->delete();
        return redirect()->route('user.dashboard')->with('success', 'Quiz został usunięty.');
    }

    // Aktualizacja tytułu quizu
    public function updateTitle(Request $request, $quizId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $quiz->title = $request->input('title');
        $quiz->save();

        return redirect()->route('quizzes.edit', $quizId)->with('success', 'Tytuł quizu został zaktualizowany.');
    }

    // Aktualizacja limitu czasu quizu
    public function updateTimeLimit(Request $request, $quizId)
    {
        $request->validate([
            'time_limit' => 'nullable|integer',
        ]);

        $quiz = Quiz::findOrFail($quizId);
        $quiz->time_limit = $request->input('time_limit');
        $quiz->save();

        return redirect()->route('quizzes.edit', $quizId)->with('success', 'Limit czasu quizu został zaktualizowany.');
    }

    // Przełączanie statusu quizu
    public function toggleStatus($id)
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->is_active = !$quiz->is_active;
        $quiz->save();

        return back()->with('success', 'Status quizu został zaktualizowany.');
    }

    // Dodawanie odpowiedzi
    public function addAnswer(Request $request, $quizId, $questionId)
    {
        $request->validate([
            'text' => 'required|string|max:255',
            'is_correct' => 'boolean',
        ]);
    
        $answer = new Answer([
            'text' => $request->input('text'),
            'is_correct' => $request->input('is_correct', false),
            'question_id' => $questionId,
        ]);
    
        $answer->save();
    
        // Ważne, aby zwrócić odpowiedź w formacie JSON
        return response()->json(['success' => 'Odpowiedź została dodana.', 'answer' => $answer]);
    }

    // Dodawanie pustej odpowiedzi
    public function addEmptyAnswer($quizId, $questionId)
    {
        $answer = new Answer();
        $answer->question_id = $questionId;
        $answer->text = ''; // Pusta treść jako domyślna
        $answer->is_correct = false; // Domyślnie fałszywe
        $answer->save();

        return response()->json(['answer' => $answer]);
    }


}
