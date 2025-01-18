<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'version_number',
        'has_passing_criteria',
        'passing_score',
        'passing_percentage',
        'time_limit',
        'is_draft',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function questions()
    {
        return $this->hasMany(VersionedQuestion::class);
    }

    public function versionedQuestions()
    {
        return $this->hasMany(VersionedQuestion::class);
    }
}
