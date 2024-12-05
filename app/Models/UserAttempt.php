<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAttempt extends Model
{
    use HasFactory;

    protected $fillable = 
    ['user_id',
    'quiz_id',
    'quiz_version_id',
    'attempt_number',
    'total_score',
    'score',
    'started_at',
    'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Relacja do użytkownika
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relacja do quizu
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

        /**
     * Relacja z modelem UserAnswer.
     */
    public function answers()
    {
        return $this->hasMany(UserAnswer::class, 'attempt_id');
    }

    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class, 'attempt_id');
    }
    
    public function quizVersion()
    {
        return $this->belongsTo(QuizVersion::class, 'quiz_version_id');
    }
    
}
