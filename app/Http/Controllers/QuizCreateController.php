<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quiz;
use App\Models\Question;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizCreateController extends Controller
{
    public function create()
    {
        // Utworzenie nowego quizu w bazie danych
        $quiz = Quiz::create([
            'title' => '',
            'user_id' => Auth::id(),
            'time_limit' => null,
            'available_from' => null,
            'available_to' => null,
        ]);

        return view('quizzes.edit', ['quizId' => $quiz->id]);
    }

    public function store(Request $request)
    {
        Log::info('Rozpoczęcie walidacji w QuizCreateController', ['request' => $request->all()]);

        // Walidacja danych wejściowych
        $request->validate([
            'title' => 'required|string|max:255',
            'time_limit' => 'nullable|integer',
            'questions' => 'required|array',
            'questions.*.question_text' => 'required|string|max:255',
            'questions.*.type' => 'required|in:open,single_choice,multiple_choice',
            'questions.*.answers' => 'nullable|array',
            'questions.*.answers.*.text' => 'nullable|string|max:255',
            'questions.*.correct_answer' => 'nullable|array',
            'questions.*.expected_code' => 'nullable|string'
        ]);

        // Usuń istniejący quiz przed zapisaniem nowego
        Quiz::where('id', $request->input('quiz_id'))->delete();

        // Tworzenie quizu
        $quiz = Quiz::create([
            'title' => $request->input('title'),
            'user_id' => Auth::id(),
            'time_limit' => $request->input('time_limit', null),
            'available_from' => $request->input('available_from', null),
            'available_to' => $request->input('available_to', null),
        ]);

        // Tworzenie pytań i odpowiedzi
        foreach ($request->input('questions') as $questionData) {
            $question = $quiz->questions()->create([
                'question_text' => $questionData['question_text'],
                'type' => $questionData['type'],
            ]);

            if ($questionData['type'] === 'open' && isset($questionData['expected_code'])) {
                $question->answers()->create([
                    'text' => null,
                    'is_correct' => null,
                    'expected_code' => $questionData['expected_code']
                ]);
            } elseif (in_array($questionData['type'], ['single_choice', 'multiple_choice']) && isset($questionData['answers'])) {
                foreach ($questionData['answers'] as $index => $answerData) {
                    $isCorrect = in_array($index, $questionData['correct_answer'] ?? []);

                    $question->answers()->create([
                        'text' => $answerData['text'],
                        'is_correct' => $isCorrect,
                        'expected_code' => null
                    ]);
                }
            }
        }

        return redirect()->route('user.dashboard')->with('success', 'Quiz został pomyślnie utworzony!');
    }
    
    public function saveQuestion(Request $request)
    {
        Log::info('Rozpoczęcie zapisu pytania.', ['request' => $request->all()]);

        $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'question_text' => 'required|string|max:255',
            'type' => 'required|in:open,single_choice,multiple_choice',
            'answers' => 'nullable|array',
            'answers.*.text' => 'nullable|string|max:255',
            'correct_answer' => 'nullable|array',
            'expected_code' => 'nullable|string'
        ]);

        // Znajdź quiz
        $quiz = Quiz::findOrFail($request->quiz_id);
        Log::info('Znaleziono quiz.', ['quiz_id' => $quiz->id]);

        // Tworzenie pytania
        $question = $quiz->questions()->create([
            'question_text' => $request->input('question_text'),
            'type' => $request->input('type'),
        ]);

        Log::info('Utworzono pytanie.', ['question_id' => $question->id]);

        if ($request->input('type') === 'open' && $request->filled('expected_code')) {
            $question->answers()->create([
                'text' => null,
                'is_correct' => null,
                'expected_code' => $request->input('expected_code')
            ]);
        } elseif (in_array($request->input('type'), ['single_choice', 'multiple_choice']) && $request->filled('answers')) {
            foreach ($request->input('answers') as $index => $answerData) {
                $isCorrect = in_array($index, $request->input('correct_answer', []));
                Log::info('Tworzenie odpowiedzi.', ['text' => $answerData['text'], 'is_correct' => $isCorrect]);

                $question->answers()->create([
                    'text' => $answerData['text'],
                    'is_correct' => $isCorrect,
                    'expected_code' => null
                ]);
            }
        }

        Log::info('Pytanie i odpowiedzi zostały zapisane.');

        return response()->json(['message' => 'Question saved successfully']);
    }
}
