<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\User\UserDashboardController;
use Illuminate\Support\Facades\Auth;

// Nowe importy:
use App\Http\Controllers\Quiz\QuizController;
use App\Http\Controllers\Quiz\QuizDraftController;
use App\Http\Controllers\Quiz\QuizVersionController;
use App\Http\Controllers\Quiz\QuizQuestionController;
use App\Http\Controllers\Quiz\QuizAttemptController;

use App\Http\Controllers\GroupController;
use App\Http\Controllers\Quiz\QuizSolveController;
use App\Http\Controllers\Quiz\QuizResultsController;
use App\Http\Controllers\Quiz\QuizAttemptsController;
use App\Http\Controllers\Quiz\QuizOwnerAttemptsController;

/*
|--------------------------------------------------------------------------
| Profile routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Home page
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
     return view('layouts.welcome');
 })->name('welcome');

/*
|--------------------------------------------------------------------------
| User dashboard
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
     if (Auth::check()) {
         // Jeśli użytkownik jest zalogowany, przekieruj na dashboard
         return redirect()->route('user.dashboard');
     }
     // Jeśli użytkownik nie jest zalogowany, wyświetl widok "welcome"
     return view('layouts/welcome');
 })->name('welcome');


Route::get('/user/dashboard', [UserDashboardController::class, 'index'])
    ->name('user.dashboard')
    ->middleware('auth');


/*
|--------------------------------------------------------------------------
| Quiz management
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

     Route::put('/quiz/{quiz}/{attempt}/{question}/updateScore', [QuizAttemptController::class, 'updateScore'])
     ->name('quiz.updateScore');


    Route::get('/quizzes/{quiz}/compareVersions', [QuizVersionController::class, 'compareVersions'])
          ->name('quizzes.compareVersions');

     Route::post('/quiz/{quiz}/{attempt}/{question}/updateScore', [QuizAttemptController::class, 'updateScore'])
          ->name('quiz.updateScore');

          Route::patch('/quizzes/{quiz}/updateAccess', [QuizDraftController::class, 'updateAccess'])
     ->name('quizzes.updateAccess');

    // *** Quiz CRUD ***
    Route::get('/quizzes', [QuizController::class, 'index'])->name('quizzes.index');
    Route::get('/quizzes/create', [QuizController::class, 'create'])->name('quizzes.create');
    //Route::post('/quizzes', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('/quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
    Route::put('/quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
    Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');

    // *** Draft (zapisywanie quizu i finalizacja) ***
    Route::post('/quizzes/{quiz}/saveAll', [QuizDraftController::class, 'saveAll'])->name('quizzes.saveAll');
    Route::post('/quizzes/{quiz}/finalizeDraftVersion', [QuizDraftController::class, 'finalizeDraftVersion'])
         ->name('quizzes.finalizeDraftVersion');

    // *** Version (podgląd, aktywacja, dezaktywacja, usuwanie itd.) ***
    Route::get('/quizzes/{quiz}/{version}/showVersion', [QuizVersionController::class, 'showVersion'])
         ->name('quizzes.showVersion');
    Route::post('/quizzes/{quiz}/{version}/activateVersion', [QuizVersionController::class, 'activateVersion'])
         ->name('quizzes.activateVersion');
    Route::post('/quizzes/{quiz}/{version}/deactivateVersion', [QuizVersionController::class, 'deactivateVersion'])
         ->name('quizzes.deactivateVersion');
    Route::delete('/quizzes/{quiz}/{version}/deleteVersion', [QuizVersionController::class, 'deleteVersion'])
         ->name('quizzes.deleteVersion');
    Route::post('/quizzes/{quiz}/{version}/renameVersion', [QuizVersionController::class, 'renameVersion'])
         ->name('quizzes.renameVersion');

    // *** Reset attempts (do konkretnej wersji) – NOWA TRASA ***
    Route::post('/quizzes/{quiz}/{version}/resetVersionAttempts', [QuizAttemptController::class, 'resetVersionAttempts'])
         ->name('quizzes.resetVersionAttempts');

    // *** Questions (dodawanie, edycja, usuwanie) ***
    Route::post('/questions', [QuizQuestionController::class, 'storeQuestion'])->name('questions.store');
    Route::put('/questions/{question}', [QuizQuestionController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{question}', [QuizQuestionController::class, 'deleteQuestion'])->name('questions.destroy');
    Route::delete('/answers/{answer}',   [QuizQuestionController::class, 'deleteAnswer'])->name('answers.destroy');

    // *** (Pozostała) stara trasa – reset-attempts CAŁEGO quizu (jeśli jeszcze używasz) ***
    // Route::post('/quizzes/{quiz}/reset-attempts', [QuizAttemptController::class, 'resetAttempts'])
    //      ->name('quizzes.resetAttempts');

    // *** Solve quiz ***
    Route::get('/quizzes/{quiz}/solve', [QuizSolveController::class, 'solve'])->name('quizzes.solve');
    Route::post('/quizzes/{quiz}/submit', [QuizSolveController::class, 'submitAnswers'])->name('quizzes.submit');

    // *** Results ***
    Route::get('/quizzes/{quiz}/results', [QuizResultsController::class, 'results'])->name('quizzes.results');

    // *** Quiz owner attempts ***
    Route::get('/quiz/{quiz}/owner-attempts', [QuizOwnerAttemptsController::class, 'showAttempts'])
         ->name('quiz.owner_attempts');
    Route::post('/quiz/{quiz}/update-scores', [QuizOwnerAttemptsController::class, 'updateScores'])
         ->name('quiz.update_scores');

    // *** User attempts ***
    Route::get('/quizzes/{quizId}/user-attempts', [QuizAttemptsController::class, 'showAttempts'])
         ->name('quizzes.user_attempts');
});

/*
|--------------------------------------------------------------------------
| Group management
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::resource('groups', GroupController::class);
    
    // Wyszukiwanie użytkownika po e-mail (checkUser)
    Route::post('/groups/checkUser', [GroupController::class, 'checkUser'])
         ->name('groups.checkUser');

    // Wysyłanie zaproszenia do grupy (sendInvitation)
    Route::post('/groups/sendInvitation', [GroupController::class, 'sendInvitation'])
         ->name('groups.sendInvitation');

    // Akceptacja zaproszenia
    Route::post('/groups/invitations/{invitationId}/accept', [GroupController::class, 'acceptInvitation'])
         ->name('groups.invitations.accept');

    // Odrzucenie zaproszenia
    Route::post('/groups/invitations/{invitationId}/reject', [GroupController::class, 'rejectInvitation'])
         ->name('groups.invitations.reject');

    // Usunięcie konkretnego użytkownika z grupy
    Route::delete('/groups/{group}/{user}', [GroupController::class, 'removeUserFromGroup'])
         ->name('groups.removeUser');

    // Nadanie/odebranie roli administratora
    Route::patch('/groups/{group}/{user}/toggleAdmin', [GroupController::class, 'toggleAdminRole'])
         ->name('groups.toggleAdmin');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{id}', [AdminDashboardController::class, 'update'])->name('admin.users.update');
});

Route::get('/login', function () {
     return redirect('/');
 });
/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';
