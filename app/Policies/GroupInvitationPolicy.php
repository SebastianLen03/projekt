<?php

namespace App\Policies;

use App\Models\GroupInvitation;
use App\Models\User;

class GroupInvitationPolicy
{
    /**
     * Sprawdź, czy użytkownik może zaakceptować lub odrzucić zaproszenie.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\GroupInvitation  $invitation
     * @return bool
     */
    public function respond(User $user, GroupInvitation $invitation)
    {
        // Tylko użytkownik, do którego skierowane jest zaproszenie, może je zaakceptować lub odrzucić
        return $user->id === $invitation->user_id;
    }
}
