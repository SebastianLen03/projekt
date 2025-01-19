<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VersionedQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_version_id', 'question_text', 'type', 'points', 'points_type'];

    public function quizVersion()
    {
        return $this->belongsTo(QuizVersion::class);
    }

    public function answers()
    {
        return $this->hasMany(VersionedAnswer::class);
    }

    public function versionedAnswers()
{
    return $this->hasMany(VersionedAnswer::class, 'versioned_question_id', 'id');
}
}