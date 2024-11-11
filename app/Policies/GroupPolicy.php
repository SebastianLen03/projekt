<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function update(User $user, Group $group)
    {
        // Sprawdź, czy użytkownik jest administratorem grupy
        return $group->users()
            ->where('group_user.group_id', $group->id) // Dodaj prefiks 'group_user' do 'group_id', aby uniknąć niejednoznaczności
            ->where('group_user.user_id', $user->id) // Określ wyraźnie, że chodzi o 'group_user.user_id'
            ->where('group_user.is_admin', 1)
            ->exists();
    }
    
    
    public function delete(User $user, Group $group)
    {
        return $user->id === $group->owner_id;
    }
    
}
