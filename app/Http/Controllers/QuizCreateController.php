<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class QuizCreateController extends Controller
{
    /**
     * Wyświetla stronę tworzenia nowego quizu.
     */
    public function create()
    {
        return view('quizzes.create');
    }

    /**
     * Zapisuje nowo utworzony quiz i jego pytania do bazy danych.
     */
    public function store(Request $request)
    {
        // Walidacja danych wejściowych
        $request->validate([
            'title' => 'required|string|max:255',
            'questions' => 'required|array',
            'questions.*.question_text' => 'required|string|max:255',
            'questions.*.type' => 'required|in:open,closed',
            'questions.*.option_a' => 'nullable|string|max:255',
            'questions.*.option_b' => 'nullable|string|max:255',
            'questions.*.option_c' => 'nullable|string|max:255',
            'questions.*.option_d' => 'nullable|string|max:255',
            'questions.*.correct_option' => 'nullable|in:A,B,C,D',
            'questions.*.expected_code' => 'nullable|string',
        ]);

        // Tworzenie nowego quizu
        $quiz = Quiz::create([
            'title' => $request->input('title'),
            'user_id' => Auth::id(),
        ]);

        // Tworzenie pytań quizu
        foreach ($request->input('questions') as $questionData) {
            if ($questionData['type'] === 'open') {
                $quiz->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'expected_code' => $questionData['expected_code'],
                    'option_a' => null,
                    'option_b' => null,
                    'option_c' => null,
                    'option_d' => null,
                    'correct_option' => null,
                ]);
            } else {
                $quiz->questions()->create([
                    'question_text' => $questionData['question_text'],
                    'option_a' => $questionData['option_a'],
                    'option_b' => $questionData['option_b'],
                    'option_c' => $questionData['option_c'],
                    'option_d' => $questionData['option_d'],
                    'correct_option' => $questionData['correct_option'],
                    'expected_code' => null,
                ]);
            }
        }

        // Przekierowanie do listy quizów
        return redirect()->route('user.dashboard')->with('success', 'Quiz został pomyślnie utworzony!');
    }
}
