<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\LoadsQuizTrait;

class QuizController extends Controller
{
    use LoadsQuizTrait;
    /**
     * (Opcjonalny) index – lista quizów lub panel
     */
    public function index()
    {
        $user = Auth::user();

        // Quizy stworzone przez użytkownika
        $quizzesCreatedByUser = Quiz::where('user_id', $user->id)->get();

        // Quizy przypisane do grup użytkownika
        $groupedQuizzes = [];
        $userGroups = $user->groups;  // <-- pobieramy $userGroups, aby móc je też przekazać do widoku
        foreach ($userGroups as $group) {
            $groupQuizzes = $group->quizzes()->where('is_active', true)->get();
            if ($groupQuizzes->isNotEmpty()) {
                $groupedQuizzes[$group->name] = $groupQuizzes;
            }
        }

        // Quizy publiczne
        $publicQuizzes = Quiz::where('is_public', true)->where('is_active', true)->get();

        // Możesz pobrać userAttempts, jeśli potrzebne
        // $userAttempts = ...

        // PRZEKAZANIE userGroups do widoku
        return view('quizzes.manage', compact(
            'quizzesCreatedByUser',
            'groupedQuizzes',
            'publicQuizzes',
            'userGroups'      // <-- DODANE
            //, 'userAttempts'
        ));
    }

    /**
     * Formularz tworzenia nowego quizu – ale tak naprawdę
     * od razu tworzymy quiz w bazie i przekierowujemy do /edit.
     */
    public function create()
    {
        // 1) Stwórz nowy quiz w bazie
        $quiz = Quiz::create([
            'user_id'          => Auth::id(),
            'title'            => 'Nowy Quiz', // tytuł domyślny
            'is_public'        => false,
            'multiple_attempts'=> false,
        ]);

        // 2) Stwórz od razu wersję draft
        $quiz->quizVersions()->create([
            'version_number'       => 1,
            'is_draft'             => true,
            'is_active'            => false,
            'has_passing_criteria' => false,
            'passing_score'        => null,
            'passing_percentage'   => null,
            'time_limit'           => null,
        ]);

        // 3) Przekieruj do /quizzes/{quiz->id}/edit – tam już mamy quiz->id w bazie
        return redirect()->route('quizzes.edit', $quiz->id)
                        ->with('message', 'Nowy quiz został utworzony. Możesz go teraz edytować.');
    }

    /**
     * Edycja quizu – tu pokazujesz formularz z draftem itp.
     */
    public function edit($quizId)
    {
        $quiz = $this->loadUserQuiz($quizId);
        $userGroups = Auth::user()->groups;
        
        // Pobieramy wersję roboczą (draft) quizu
        $draftVersion = $quiz->quizVersions()->where('is_draft', true)->first();
        
        // Jeśli istnieje wersja robocza, pobieramy pytania (oraz ładujemy odpowiedzi)
        $questions = $draftVersion 
            ? $draftVersion->versionedQuestions()->with('versionedAnswers')->get()
            : collect();
        
        return view('quizzes.manage', compact('quiz', 'userGroups', 'questions'));
    }
    
    /**
     * Usuwanie quizu – przeniesione z Twojego starego destroy()
     */
    public function destroy($quizId)
    {
        $quiz = Quiz::findOrFail($quizId);
        if ($quiz->user_id !== Auth::id()) {
            abort(403, 'Nie masz uprawnień do usunięcia tego quizu.');
        }
    
        DB::transaction(function() use ($quiz) {
            $quiz->groups()->detach();
            $quiz->delete();
        });
    
        return back()->with('message', 'Quiz został pomyślnie usunięty');
    }
    
}
