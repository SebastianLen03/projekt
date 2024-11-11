<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
    ];

    // Definicja relacji z użytkownikami
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('is_admin')->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
        public function groups()
    {
        return $this->belongsToMany(Group::class)->withPivot('is_admin')->withTimestamps();
    }

    public function ownedGroups()
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function quizzes()
    {
        return $this->belongsToMany(Quiz::class, 'group_quiz', 'group_id', 'quiz_id');
    }
}
