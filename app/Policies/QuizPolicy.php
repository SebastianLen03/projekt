<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Quiz;

class QuizPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $user, Quiz $quiz)
    {
        return $quiz->groups()->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->exists();
    }

}
