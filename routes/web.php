<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\User\UserDashboardController;
use App\Http\Controllers\QuizManageController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\Quiz\QuizSolveController;
use App\Http\Controllers\Quiz\QuizResultsController;
use App\Http\Controllers\Quiz\QuizAttemptsController;
use App\Http\Controllers\Quiz\QuizOwnerAttemptsController;

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Home page
Route::get('/', function () {
    return view('welcome');
});

// User dashboard
Route::get('/user/dashboard', [UserDashboardController::class, 'index'])
    ->name('user.dashboard')
    ->middleware('auth');

// Quiz management routes
Route::middleware('auth')->group(function () {
    // Create, edit, and delete quizzes
    Route::get('/quizzes/create', [QuizManageController::class, 'createNewQuiz'])->name('quizzes.create');
    Route::get('/quizzes/{quiz}/edit', [QuizManageController::class, 'edit'])->name('quizzes.edit');
    Route::post('/quizzes', [QuizManageController::class, 'store'])->name('quizzes.store');
    Route::put('/quizzes/{quiz}', [QuizManageController::class, 'update'])->name('quizzes.update');
    Route::delete('/quizzes/{quiz}', [QuizManageController::class, 'destroy'])->name('quizzes.destroy');

    // Save entire quiz (quiz along with questions and answers)
    Route::post('/quizzes/{quiz}/saveAll', [QuizManageController::class, 'saveAll'])->name('quizzes.saveAll');

    // Toggle quiz status (e.g., active/inactive)
    Route::post('/quizzes/{quiz}/toggleStatus', [QuizManageController::class, 'toggleStatus'])->name('quizzes.toggleStatus');

    // Manage questions in quizzes
    Route::post('/questions', [QuizManageController::class, 'storeQuestion'])->name('questions.store');
    Route::put('/questions/{question}', [QuizManageController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{question}', [QuizManageController::class, 'deleteQuestion'])->name('questions.destroy');

    // Manage answers to questions
    Route::post('/answers', [QuizManageController::class, 'storeAnswer'])->name('answers.store');
    Route::put('/answers/{answer}', [QuizManageController::class, 'updateAnswer'])->name('answers.update');
    Route::delete('/answers/{answer}', [QuizManageController::class, 'deleteAnswer'])->name('answers.destroy');

    // Reset attempts
    Route::post('/quizzes/{quiz}/reset-attempts', [QuizManageController::class, 'resetAttempts'])->name('quizzes.resetAttempts');

    // Solve quizzes and submit answers
    Route::get('/quizzes/{quiz}/solve', [QuizSolveController::class, 'solve'])->name('quizzes.solve');
    Route::post('/quizzes/{quiz}/submit', [QuizSolveController::class, 'submitAnswers'])->name('quizzes.submit');

    // Quiz results route
    Route::get('/quizzes/{quiz}/results', [QuizResultsController::class, 'results'])->name('quizzes.results');

    // Route for quiz owner to see attempts by other users
    Route::get('/quiz/{quiz}/owner-attempts', [QuizOwnerAttemptsController::class, 'showAttempts'])
        ->name('quiz.owner_attempts')
        ->middleware('auth');

    // Route for updating scores
    Route::post('/quiz/{quiz}/update-scores', [QuizOwnerAttemptsController::class, 'updateScores'])
        ->name('quiz.update_scores')
        ->middleware('auth');

    // Route for users to view their own attempts
    Route::get('/quizzes/{quizId}/user-attempts', [QuizAttemptsController::class, 'showAttempts'])
        ->name('quizzes.user_attempts')
        ->middleware('auth');
});

// Group management routes
Route::middleware(['auth'])->group(function () {
    // CRUD for groups
    Route::resource('groups', GroupController::class);

    // Check user by email
    Route::post('/groups/check-user', [GroupController::class, 'checkUser'])->name('groups.checkUser');

    // Send group invitation
    Route::post('/groups/send-invitation', [GroupController::class, 'sendInvitation'])->name('groups.sendInvitation');

    // Add user to group (if invitation accepted)
    Route::post('/groups/{group}/add-user', [GroupController::class, 'addUserToGroup'])->name('groups.addUser');

    // View received group invitations
    Route::get('/groups/invitations', [GroupController::class, 'viewInvitations'])->name('groups.invitations');

    // Accept or reject group invitation
    Route::post('/groups/invitations/{invitation}/accept', [GroupController::class, 'acceptInvitation'])->name('groups.invitations.accept');
    Route::post('/groups/invitations/{invitation}/reject', [GroupController::class, 'rejectInvitation'])->name('groups.invitations.reject');

    // Remove user from group
    Route::delete('/groups/{group}/remove-user/{user}', [GroupController::class, 'removeUserFromGroup'])->name('groups.removeUser');

    // Grant or revoke admin role in group
    Route::patch('/groups/{group}/toggle-admin/{user}', [GroupController::class, 'toggleAdminRole'])->name('groups.toggleAdmin');
});

// Admin routes
Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/users/{id}', [AdminDashboardController::class, 'update'])->name('admin.users.update');
});

// Required routes for authentication
require __DIR__.'/auth.php';
