<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Quiz extends Model
{
    use HasFactory;

    // Tabela w bazie danych
    protected $table = 'quizzes';

    // Kolumny, które mogą być masowo przypisywane
    protected $fillable = [
        'title',
        'user_id',
        'is_public',
        'multiple_attempts',
        'has_passing_criteria',
        'passing_score',
        'passing_percentage',
    ];

    // Relacja z modelem User (zakładamy, że quiz jest przypisany do użytkownika, który go stworzył)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Przykład relacji z modelem Question, jeśli quiz ma pytania
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // Scope do filtrowania aktywnych quizów (przydatne w kontrolerze)
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // W modelu Quiz.php
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_quiz', 'quiz_id', 'group_id');
    }

    /**
     * Relacja z próbami podejścia użytkowników.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userAttempts()
    {
        return $this->hasMany(UserAttempt::class);
    }

    public function versions()
    {
        return $this->hasMany(QuizVersion::class);
    }
    public function quizVersions()
{
    return $this->hasMany(QuizVersion::class, 'quiz_id');
}
}
