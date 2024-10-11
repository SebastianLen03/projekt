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
     * Wyświetla stronę pojedynczego quizu z możliwością filtrowania po nazwie i emailu oraz sortowania po wyniku.
     */
    public function show(Request $request, Quiz $quiz)
    {
        // Pobieranie parametrów wyszukiwania i sortowania z zapytania
        $name = $request->input('name');
        $email = $request->input('email');
        $sort = $request->input('sort');

        // Budowanie zapytania do bazy danych
        $query = DB::table('user_answers')
            ->join('users', 'user_answers.user_id', '=', 'users.id') // Dołączenie tabeli users
            ->select(
                'user_answers.user_id',
                'users.name',
                'users.email',
                'attempt_uuid',
                DB::raw('count(*) as total'),
                DB::raw('sum(is_correct) as correct')
            )
            ->whereIn('question_id', $quiz->questions->pluck('id'))
            ->groupBy('user_answers.user_id', 'attempt_uuid', 'users.name', 'users.email');

        // Filtrowanie po nazwie użytkownika
        if ($name) {
            $query->where('users.name', 'like', '%' . $name . '%');
        }

        // Filtrowanie po adresie email
        if ($email) {
            $query->where('users.email', 'like', '%' . $email . '%');
        }

        // Sortowanie wyników
        if ($sort === 'asc') {
            $query->orderBy('correct', 'asc');
        } elseif ($sort === 'desc') {
            $query->orderBy('correct', 'desc');
        } else {
            $query->orderBy('users.name', 'asc'); // Domyślne sortowanie po nazwie
        }

        // Pobranie wyników zapytania
        $userAttempts = $query->get();

        // Pobieranie szczegółowych odpowiedzi dla każdego podejścia
        $userResults = [];
        foreach ($userAttempts as $attempt) {
            $user = User::find($attempt->user_id);
            $answers = UserAnswer::where('user_id', $attempt->user_id)
                ->where('attempt_uuid', $attempt->attempt_uuid) // Używamy attempt_uuid do identyfikacji podejścia
                ->get();

            $userResults[] = [
                'user' => $user,
                'correct' => $attempt->correct,
                'total' => $attempt->total,
                'answers' => $answers,
                'attempt_uuid' => $attempt->attempt_uuid,
            ];
        }

        return view('quizzes.show', compact('quiz', 'userResults'));
    }
}
