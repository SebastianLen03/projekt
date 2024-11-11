<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Logowanie ID użytkownika
        Log::info('ID zalogowanego użytkownika: ' . Auth::id());
    
        // Pobieranie wartości z pola wyszukiwania
        $search = $request->input('search');
        Log::info('Wartość wyszukiwania', ['search' => $search]);
    
        try {
            // Sprawdzenie autoryzacji
            if (Auth::check()) {
                Log::info('Użytkownik jest zalogowany', ['user_id' => Auth::id()]);
            } else {
                Log::warning('Użytkownik nie jest zalogowany');
            }
    
            // Pobieranie quizów stworzonych przez użytkownika
            $quizzesCreatedByUser = Quiz::where('user_id', Auth::id())->get(); // get() zwraca kolekcję
            Log::info('Quizy stworzone przez użytkownika', ['quizzesCreatedByUser' => $quizzesCreatedByUser->toArray()]);
    
            // Pobieranie quizów przypisanych do grup, do których użytkownik należy, ale tylko aktywne quizy
            $userGroups = Auth::user()->groups;
            $groupedQuizzes = $userGroups->mapWithKeys(function ($group) {
                // Filtrujemy quizy tylko z `is_active = 1`
                $activeQuizzes = $group->quizzes()->where('is_active', 1)->get();
                return [$group->name => $activeQuizzes];
            });

            // Pobieranie quizów innych użytkowników (publicznych), tylko aktywne quizy
            $publicQuizzes = Quiz::where('is_public', true)
                ->where('user_id', '!=', Auth::id())
                ->when($search, function ($query, $search) {
                    return $query->where('title', 'like', '%' . $search . '%');
                })
                ->where('is_active', true)
                ->get(); // get() zwraca kolekcję
            Log::info('Dostępne quizy', ['publicQuizzes' => $publicQuizzes->toArray()]);
    
            return view('user.dashboard', [
                'quizzesCreatedByUser' => $quizzesCreatedByUser,
                'groupedQuizzes' => $groupedQuizzes,
                'publicQuizzes' => $publicQuizzes,
            ]);
        } catch (\Exception $e) {
            Log::error('Wystąpił błąd', ['error' => $e->getMessage()]);
            return response()->view('errors.general', ['message' => 'Wystąpił błąd podczas ładowania strony.'], 500);
        }
    }
}
