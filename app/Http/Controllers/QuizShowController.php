<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UserAnswer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizShowController extends Controller
{
    /**
     * Wyświetla stronę pojedynczego quizu z wynikami użytkowników.
     *
     * Funkcja wyświetla stronę z wynikami quizu.
     *
     * @param Request $request Obiekt żądania HTTP.
     * @param Quiz $quiz Instancja modelu Quiz, który zawiera pytania i odpowiedzi dla tego quizu.
     * @return \Illuminate\View\View Zwraca widok z przetworzonymi wynikami quizu.
     */
    public function show(Request $request, Quiz $quiz)
    {
        // Budowanie zapytania do bazy danych dla wyników quizu
        $query = DB::table('user_answers')
            ->join('users', 'user_answers.user_id', '=', 'users.id') // Dołączenie tabeli users
            ->select(
                'user_answers.user_id',
                'users.name', // Pobieranie imienia użytkownika
                'users.email', // Pobieranie emaila użytkownika
                'attempt_uuid', // UUID podejścia użytkownika (unikalny identyfikator podejścia do quizu)
                DB::raw('count(*) as total'), // Liczenie liczby pytań (total)
                DB::raw('sum(is_correct) as correct') // Sumowanie poprawnych odpowiedzi
            )
            ->whereIn('question_id', $quiz->questions->pluck('id')) // Filtracja wyników tylko dla pytań z bieżącego quizu
            ->groupBy('user_answers.user_id', 'attempt_uuid', 'users.name', 'users.email'); // Grupowanie wyników na podstawie użytkownika i podejścia (UUID)

        // Pobranie wyników zapytania
        $userAttempts = $query->get();

        // Pobieranie szczegółowych odpowiedzi dla każdego podejścia
        $userResults = [];
        foreach ($userAttempts as $attempt) {
            $user = User::find($attempt->user_id); // Znalezienie użytkownika po jego ID
            $answers = UserAnswer::where('user_id', $attempt->user_id) // Pobieranie odpowiedzi użytkownika
                ->where('attempt_uuid', $attempt->attempt_uuid) // Filtrowanie odpowiedzi na podstawie UUID podejścia
                ->get();

            // Zapis wyników użytkownika, poprawnych odpowiedzi, liczby pytań i jego odpowiedzi
            $userResults[] = [
                'user' => $user, // Dane użytkownika (imię, email itd.)
                'correct' => $attempt->correct, // Liczba poprawnych odpowiedzi
                'total' => $attempt->total, // Liczba wszystkich pytań
                'answers' => $answers, // Szczegóły odpowiedzi na pytania
                'attempt_uuid' => $attempt->attempt_uuid, // UUID tego podejścia
            ];
        }

        // Zwrócenie widoku z wynikami quizu i przetworzonymi wynikami użytkowników
        return view('quizzes.show', compact('quiz', 'userResults'));
    }
}
