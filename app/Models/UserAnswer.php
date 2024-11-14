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
    protected $fillable = [
        'user_id',
        'question_id',
        'answer_id',
        'open_answer',
        'is_correct',
        'attempt_id',
        'score',
    ];

    /**
     * Relacja do użytkownika.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacja do pytania.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Relacja do odpowiedzi (jeśli odpowiedź jest dla pytania zamkniętego).
     */
    public function answer()
    {
        return $this->belongsTo(Answer::class);
    }

        /**
     * Relacja z modelem UserAttempt.
     */
    public function attempt()
    {
        return $this->belongsTo(UserAttempt::class);
    }

    /**
     * Relacja z modelem Quiz.
     */
    public function quiz()
    {
        return $this->hasOneThrough(Quiz::class, Question::class);
    }
}
