<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\UserAnswer;
use Illuminate\Support\Facades\DB;

class UserDashboardController extends Controller
{
    public function index(Request $request)
{
    // Pobieranie wartości wpisanej w pole wyszukiwania
    $search = $request->input('search');

    // Pobieranie quizów utworzonych przez zalogowanego użytkownika
    $quizzesCreatedByUser = Quiz::where('user_id', Auth::id())->get();

    // Pobieranie quizów innych użytkowników z filtrowaniem na podstawie wyszukiwania
    $availableQuizzes = Quiz::where('user_id', '!=', Auth::id())
        ->when($search, function ($query, $search) {
            return $query->where('title', 'like', '%' . $search . '%');
        })
        ->get();

    // Pętla przez dostępne quizy, aby zebrać dane o podejściach użytkownika
    $userAttempts = [];
    foreach ($availableQuizzes as $quiz) {
        $userId = Auth::id();
        $attempts = UserAnswer::where('user_id', $userId)
                            ->whereIn('question_id', $quiz->questions->pluck('id'))
                            ->select('attempt_uuid', 'created_at', DB::raw('count(*) as total'), DB::raw('sum(is_correct) as correct'))
                            ->groupBy('attempt_uuid', 'created_at')
                            ->orderBy('created_at', 'desc')
                            ->get();
        // Przypisanie wyników podejść do klucza odpowiadającego ID quizu
        $userAttempts[$quiz->id] = $attempts;
    }

    // Przekazywanie quizów i podejść do widoku
    return view('user.dashboard', [
        'quizzesCreatedByUser' => $quizzesCreatedByUser,
        'availableQuizzes' => $availableQuizzes,
        'userAttempts' => $userAttempts, // Przekazanie podejść użytkownika
    ]);
}

    public function getUserAttempts(Quiz $quiz)
    {
        $userId = Auth::id();
        
        $attempts = UserAnswer::where('user_id', $userId)
                            ->whereIn('question_id', $quiz->questions->pluck('id'))
                            ->select('attempt_uuid', 'created_at', DB::raw('count(*) as total'), DB::raw('sum(is_correct) as correct'))
                            ->groupBy('attempt_uuid', 'created_at')
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json(['attempts' => $attempts]);
    }

    public function answers($attempt_uuid)
    {
        // Pobierz wszystkie odpowiedzi użytkownika dla podanego attempt_uuid
        $userAnswers = UserAnswer::where('attempt_uuid', $attempt_uuid)->with('question')->get();
    
        // Sformatuj dane w odpowiednim formacie do zwrócenia w AJAX
        $answers = $userAnswers->map(function ($answer) {
            return [
                'question' => $answer->question->question_text,
                'user_answer' => $answer->answer ?? $answer->selected_option, // Odpowiedź użytkownika
                'is_correct' => $answer->is_correct ? 'Poprawna' : 'Niepoprawna', // Czy odpowiedź jest poprawna
            ];
        });
    
        // Zwróć dane w formacie JSON
        return response()->json(['answers' => $answers]);
    }
}
