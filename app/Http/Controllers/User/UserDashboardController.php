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
    /**
     * Wyświetla panel użytkownika z jego quizami oraz quizami innych użytkowników.
     *
     * @param Request $request Obiekt żądania HTTP zawierający parametry wyszukiwania.
     * @return \Illuminate\View\View Zwraca widok dashboardu użytkownika z quizami i podejściami.
     */
    public function index(Request $request)
    {
        // Pobieranie wartości z pola wyszukiwania, jeśli użytkownik wpisał cokolwiek
        $search = $request->input('search');

        // Pobieranie quizów stworzonych przez zalogowanego użytkownika
        $quizzesCreatedByUser = Quiz::where('user_id', Auth::id())->get();

        // Pobieranie quizów innych użytkowników, z opcjonalnym filtrowaniem według tytułu quizu
        $availableQuizzes = Quiz::where('user_id', '!=', Auth::id())
            ->when($search, function ($query, $search) {
                // Jeśli wprowadzono wyszukiwaną frazę, filtrujemy quizy po tytule
                return $query->where('title', 'like', '%' . $search . '%');
            })
            ->get();

        // Pętla przez dostępne quizy, aby zebrać dane o podejściach użytkownika
        $userAttempts = [];
        foreach ($availableQuizzes as $quiz) {
            $userId = Auth::id(); // ID zalogowanego użytkownika
            // Pobieranie podejść użytkownika dla bieżącego quizu
            $attempts = UserAnswer::where('user_id', $userId)
                                ->whereIn('question_id', $quiz->questions->pluck('id')) // Pobieranie pytań dla quizu
                                ->select('attempt_uuid', 'created_at', DB::raw('count(*) as total'), DB::raw('sum(is_correct) as correct'))
                                ->groupBy('attempt_uuid', 'created_at') // Grupowanie według UUID podejścia
                                ->orderBy('created_at', 'desc') // Sortowanie podejść według daty, od najnowszego
                                ->get();
            // Przypisanie wyników podejść do klucza odpowiadającego ID quizu
            $userAttempts[$quiz->id] = $attempts;
        }

        // Przekazywanie quizów i podejść do widoku dashboardu
        return view('user.dashboard', [
            'quizzesCreatedByUser' => $quizzesCreatedByUser, // Quizy stworzone przez użytkownika
            'availableQuizzes' => $availableQuizzes, // Quizy innych użytkowników dostępne do rozwiązania
            'userAttempts' => $userAttempts, // Dane o podejściach użytkownika do quizów
        ]);
    }

    /**
     * Zwraca wszystkie podejścia użytkownika do danego quizu w formacie JSON.
     *
     * @param Quiz $quiz Obiekt modelu Quiz, dla którego szukamy podejść użytkownika.
     * @return \Illuminate\Http\JsonResponse Zwraca dane JSON z podejściami użytkownika.
     */
    public function getUserAttempts(Quiz $quiz)
    {
        $userId = Auth::id(); // ID zalogowanego użytkownika
        
        // Pobieranie podejść użytkownika dla wybranego quizu
        $attempts = UserAnswer::where('user_id', $userId)
                            ->whereIn('question_id', $quiz->questions->pluck('id')) // Pobieranie pytań dla quizu
                            ->select('attempt_uuid', 'created_at', DB::raw('count(*) as total'), DB::raw('sum(is_correct) as correct'))
                            ->groupBy('attempt_uuid', 'created_at') // Grupowanie według UUID podejścia
                            ->orderBy('created_at', 'desc') // Sortowanie według daty podejścia
                            ->get();

        // Zwracanie podejść w formacie JSON
        return response()->json(['attempts' => $attempts]);
    }

    /**
     * Zwraca odpowiedzi użytkownika dla wybranego podejścia (na podstawie attempt_uuid).
     *
     * @param string $attempt_uuid UUID podejścia użytkownika, na podstawie którego pobieramy odpowiedzi.
     * @return \Illuminate\Http\JsonResponse Zwraca dane JSON z odpowiedziami użytkownika i poprawnością.
     */
    public function answers($attempt_uuid)
    {
        // Pobieranie wszystkich odpowiedzi użytkownika dla danego attempt_uuid
        $userAnswers = UserAnswer::where('attempt_uuid', $attempt_uuid)->with('question')->get();
    
        // Mapowanie odpowiedzi użytkownika na format do zwrócenia
        $answers = $userAnswers->map(function ($answer) {
            return [
                'question' => $answer->question->question_text, // Pytanie quizowe
                'user_answer' => $answer->answer ?? $answer->selected_option, // Odpowiedź użytkownika lub wybrana opcja
                'is_correct' => $answer->is_correct ? 'Poprawna' : 'Niepoprawna', // Informacja o poprawności odpowiedzi
            ];
        });
    
        // Zwracanie odpowiedzi w formacie JSON
        return response()->json(['answers' => $answers]);
    }
}
