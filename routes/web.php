<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizShowController;
use App\Http\Controllers\QuizCreateController;
use App\Http\Controllers\QuizDestroyController;
use App\Http\Controllers\QuizResultsController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [UserDashboardController::class, 'index'])
->name('dashboard')
->middleware('auth');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Route odpowiedzialne za edycje profilu
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])  
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])  
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])    
        ->name('profile.destroy');
});

//Route dla admina
Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
    ->name('admin.dashboard')
    ->middleware('auth');
Route::put('/admin/users/{id}', [AdminDashboardController::class, 'update'])->name('admin.users.update');

    

// Route dla użytkownika
Route::get('/user/dashboard', [UserDashboardController::class, 'index'])
    ->name('user.dashboard')
    ->middleware('auth');

// Trasy dla quizów dostępne tylko dla zalogowanych użytkowników
Route::middleware('auth')->group(function () {

    Route::get('/quizzes/create', [QuizcreateController::class, 'create'])
        ->name('quizzes.create');
    Route::post('/quizzes', [QuizCreateController::class, 'store'])
        ->name('quizzes.store');

    Route::get('/quizzes/{quiz}', [QuizShowController::class, 'show'])
        ->name('quizzes.show');

    Route::get('/quizzes/{quiz}/solve', [QuizController::class, 'solve'])
        ->name('quizzes.solve'); 
    Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submitAnswers'])
        ->name('quizzes.submit');

    Route::get('/quizzes/{quiz}/results', [QuizResultsController::class, 'results'])
        ->name('quizzes.results');
       
    Route::delete('/quizzes/{quiz}', [QuizDestroyController::class, 'destroy'])
    ->name('quizzes.destroy');

    Route::get('/quizzes/{quiz}/attempts', [UserDashboardController::class, 'getUserAttempts'])
        ->name('quizzes.attempts');

    Route::get('/quizzes/attempts/{attempt_uuid}/answers', [UserDashboardController::class, 'answers']);
});

require __DIR__.'/auth.php';


