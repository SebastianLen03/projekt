<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quiz_id',
        'attempt_number',
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
    
}
