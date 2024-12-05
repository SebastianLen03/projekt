<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    // Tabela przypisana do modelu
    protected $table = 'user_answers';

    // Wypełnialne pola (fields) - zdefiniuj kolumny, które będą wypełniane
    protected $fillable = ['user_id', 
    'question_id', 
    'quiz_version_id', 
    'answer_id', 
    'versioned_question_id',
    'versioned_answer_id',
    'attempt_id', 
    'selected_answers',
    'open_answer', 
    'is_correct', 
    'score',
    'is_manual_score'
    ];


    /**
     * Relacja do użytkownika.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacja z modelem Quiz.
     */
    public function quiz()
    {
        return $this->hasOneThrough(Quiz::class, Question::class);
    }

    public function quizVersion()
    {
        return $this->belongsTo(QuizVersion::class);
    }

    public function attempt()
    {
        return $this->belongsTo(UserAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(VersionedQuestion::class, 'versioned_question_id');
    }

    public function answer()
    {
        return $this->belongsTo(VersionedAnswer::class, 'versioned_answer_id');
    }
}
