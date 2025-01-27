<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\UserAttempt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Pobieranie wartości z pola wyszukiwania
        $search = $request->input('search');
    
        // Pobieranie quizów stworzonych przez użytkownika
        $quizzesCreatedByUser = Quiz::where('user_id', Auth::id())->get();
    
        // Pobieranie quizów przypisanych do grup, do których użytkownik należy, tylko aktywne quizy, wraz z wersjami
        $userGroups = Auth::user()->groups;
        $groupedQuizzes = $userGroups->mapWithKeys(function ($group) {
            $activeQuizzes = $group->quizzes()->where('is_active', 1)->with('versions')->get();
            return [$group->name => $activeQuizzes];
        });
    
        // Pobieranie quizów publicznych, tylko aktywne, wraz z wersjami
        $publicQuizzes = Quiz::where('is_public', true)
            ->where('user_id', '!=', Auth::id())
            ->when($search, function ($query, $search) {
                return $query->where('title', 'like', '%' . $search . '%');
            })
            ->where('is_active', true)
            ->with('versions')
            ->get();
    
        // Zbieranie wszystkich quizów do sprawdzenia
        $allQuizzes = $groupedQuizzes->flatten(1)->merge($publicQuizzes);
    
        // Tablica do przechowywania liczby podejść użytkownika do najnowszej wersji każdego quizu
        $userAttempts = [];
    
        foreach ($allQuizzes as $quiz) {
            // Pobierz najnowszą wersję quizu
            $latestQuizVersion = $quiz->versions->sortByDesc('version_number')->first();
    
            if ($latestQuizVersion) {
                // Liczba podejść użytkownika do najnowszej wersji quizu
                $attemptCount = UserAttempt::where('user_id', Auth::id())
                    ->where('quiz_id', $quiz->id)
                    ->where('quiz_version_id', $latestQuizVersion->id)
                    ->count();
    
                $userAttempts[$quiz->id] = $attemptCount;
            } else {
                $userAttempts[$quiz->id] = 0;
            }
        }
    
        return view('user.dashboard', [
            'quizzesCreatedByUser' => $quizzesCreatedByUser,
            'groupedQuizzes' => $groupedQuizzes,
            'publicQuizzes' => $publicQuizzes,
            'userAttempts' => $userAttempts, // Zaktualizowana zmienna do widoku
        ]);
    }
}
