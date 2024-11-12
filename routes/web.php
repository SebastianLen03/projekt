<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\QuizManageController;
use App\Http\Controllers\GroupController; // Poprawny import GroupController
use App\Http\Controllers\Quiz\QuizSolveController;
use App\Http\Controllers\Quiz\QuizResultsController; // Dodany kontroler do obsługi wyników quizu
use App\Http\Controllers\Quiz\QuizAttemptsController;

// Trasy profilu użytkownika
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Strona główna
Route::get('/', function () {
    return view('welcome');
});

// Dashboard użytkownika
Route::get('/user/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard')->middleware('auth');

// Trasy zarządzania quizami
Route::middleware('auth')->group(function () {
    // Tworzenie, edycja i usuwanie quizów
    Route::get('/quizzes/create', [QuizManageController::class, 'createNewQuiz'])->name('quizzes.create');
    Route::get('/quizzes/{quiz}/edit', [QuizManageController::class, 'edit'])->name('quizzes.edit');
    Route::post('/quizzes', [QuizManageController::class, 'store'])->name('quizzes.store');
    Route::put('/quizzes/{quiz}', [QuizManageController::class, 'update'])->name('quizzes.update');
    Route::delete('/quizzes/{quiz}', [QuizManageController::class, 'destroy'])->name('quizzes.destroy');

    // Zapis całego quizu (quiz wraz z pytaniami i odpowiedziami)
    Route::post('/quizzes/{quiz}/saveAll', [QuizManageController::class, 'saveAll'])->name('quizzes.saveAll');

    // Zmiana statusu quizu (np. aktywny/nieaktywny)
    Route::post('/quizzes/{quiz}/toggleStatus', [QuizManageController::class, 'toggleStatus'])->name('quizzes.toggleStatus');

    // Zarządzanie pytaniami w quizach
    Route::post('/questions', [QuizManageController::class, 'storeQuestion'])->name('questions.store');
    Route::put('/questions/{question}', [QuizManageController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{question}', [QuizManageController::class, 'deleteQuestion'])->name('questions.destroy');

    // Zarządzanie odpowiedziami do pytań
    Route::post('/answers', [QuizManageController::class, 'storeAnswer'])->name('answers.store');
    Route::put('/answers/{answer}', [QuizManageController::class, 'updateAnswer'])->name('answers.update');
    Route::delete('/answers/{answer}', [QuizManageController::class, 'deleteAnswer'])->name('answers.destroy');

    // Resetowanie podejść
    Route::post('/quizzes/{quiz}/reset-attempts', [QuizManageController::class, 'resetAttempts'])->name('quizzes.resetAttempts');

    // Rozwiązywanie quizów i przesyłanie odpowiedzi
    Route::get('/quizzes/{quiz}/solve', [QuizSolveController::class, 'solve'])->name('quizzes.solve');
    Route::post('/quizzes/{quiz}/submit', [QuizSolveController::class, 'submitAnswers'])->name('quizzes.submit');

    // Trasa wyników quizu
    Route::get('/quizzes/{quiz}/results', [QuizResultsController::class, 'results'])->name('quizzes.results');
});

// Trasy zarządzania grupami
Route::middleware(['auth'])->group(function () {
    // CRUD dla grup
    Route::resource('groups', GroupController::class);

    // Sprawdź użytkownika po adresie e-mail
    Route::post('/groups/check-user', [GroupController::class, 'checkUser'])->name('groups.checkUser');

    // Wyślij zaproszenie do grupy
    Route::post('/groups/send-invitation', [GroupController::class, 'sendInvitation'])->name('groups.sendInvitation');

    // Dodaj użytkownika do grupy (jeżeli zaproszenie zostało zaakceptowane)
    Route::post('/groups/{group}/add-user', [GroupController::class, 'addUserToGroup'])->name('groups.addUser');

    // Otrzymane zaproszenia do grup
    Route::get('/groups/invitations', [GroupController::class, 'viewInvitations'])->name('groups.invitations');

    // Akceptacja lub odrzucenie zaproszenia do grupy
    Route::post('/groups/invitations/{invitation}/accept', [GroupController::class, 'acceptInvitation'])->name('groups.invitations.accept');
    Route::post('/groups/invitations/{invitation}/reject', [GroupController::class, 'rejectInvitation'])->name('groups.invitations.reject');

    // Usuwanie użytkownika z grupy
    Route::delete('/groups/{group}/remove-user/{user}', [GroupController::class, 'removeUserFromGroup'])->name('groups.removeUser');

    // Nadawanie i odbieranie roli administratora w grupie
    Route::patch('/groups/{group}/toggle-admin/{user}', [GroupController::class, 'toggleAdminRole'])->name('groups.toggleAdmin');


    Route::get('/quizzes/{quizId}/attempts', [QuizAttemptsController::class, 'showAttempts'])->name('quizzes.attempts');
});

// Trasy administratora
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{id}', [AdminDashboardController::class, 'update'])->name('admin.users.update');
});

// Wymagane trasy dla autoryzacji (generowane przez Laravel Breeze lub inne)
require __DIR__.'/auth.php';
