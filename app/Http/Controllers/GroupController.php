<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use App\Models\GroupInvitation;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    use AuthorizesRequests;

    // Wyświetla listę grup użytkownika
    public function index()
    {
        $user = Auth::user();
    
        // Grupy, którymi zarządzasz (jesteś właścicielem lub adminem)
        $adminGroups = Group::where('owner_id', $user->id)
            ->orWhereHas('users', function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->where('is_admin', true);
            })
            ->get();
    
        // Grupy, do których należysz, ale nie jesteś administratorem
        $memberGroups = Group::whereHas('users', function($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->where('is_admin', false);
        })->where('owner_id', '!=', $user->id)
          ->get();
    
        // Otrzymane zaproszenia
        $invitations = GroupInvitation::where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();
    
        return view('groups.index', compact('adminGroups', 'memberGroups', 'invitations'));
    }

    // Pokaż formularz tworzenia grupy
    public function create()
    {
        return view('groups.create');
    }

    // Zapisz nową grupę
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);
    
        // Upewnij się, że właściciel grupy (owner_id) jest ustawiony
        $group = Group::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'owner_id' => Auth::id(), // Przypisz bieżącego użytkownika jako właściciela grupy
        ]);
    
        // Dodaj właściciela do grupy jako administratora
        $group->users()->attach(Auth::id(), ['is_admin' => true]);
    
        // Przekierowanie do edycji grupy po jej utworzeniu
        return redirect()->route('groups.edit', $group->id)->with('message', 'Grupa została stworzona.');
    }

    // Edytuj grupę
    public function edit(Group $group)
    {
        // Użyj polityki, aby sprawdzić, czy użytkownik ma dostęp do edycji grupy
        $this->authorize('update', $group);

        // Pobierz członków grupy, ale wyklucz zalogowanego użytkownika
        $groupMembers = $group->users->filter(function($member) {
            return $member->id !== Auth::id();
        });

        return view('groups.edit', compact('group', 'groupMembers'));
    }

    // Zaktualizuj grupę
    public function update(Request $request, Group $group)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $this->authorize('update', $group);

        $group->update($request->only(['name', 'description']));
        return redirect()->route('groups.index')->with('message', 'Grupa została zaktualizowana.');
    }

    // Usuń grupę
    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);

        $group->delete();
        return redirect()->route('groups.index')->with('message', 'Grupa została usunięta.');
    }

    // Sprawdź użytkownika po adresie e-mail
    public function checkUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'group_id' => 'required|exists:groups,id',
        ]);
    
        $email = $request->input('email');
        $groupId = $request->input('group_id');
    
        // Sprawdź, czy użytkownik próbuje wyszukać swój własny e-mail
        if (Auth::user()->email === $email) {
            return response()->json(['status' => 'not_found', 'message' => 'Nie możesz wyszukiwać swojego własnego adresu e-mail.']);
        }
    
        // Szukaj użytkownika po adresie e-mail
        $user = User::where('email', $email)->first();
    
        if ($user) {
            // Sprawdź, czy użytkownik już należy do grupy
            $group = Group::find($groupId);
            if ($group && $group->users()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'status' => 'already_member',
                    'message' => 'Użytkownik już należy do tej grupy.',
                ]);
            }
    
            return response()->json([
                'status' => 'found',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }
    
        return response()->json([
            'status' => 'not_found',
            'message' => 'Nie znaleziono użytkownika o podanym adresie e-mail.',
        ]);
    }

    // Metoda do wysyłania zaproszenia
    public function sendInvitation(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        $userId = $request->input('user_id');
        $groupId = $request->input('group_id');

        // Sprawdzenie, czy użytkownik już jest członkiem grupy
        $group = Group::findOrFail($groupId);
        if ($group->users()->where('user_id', $userId)->exists()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Użytkownik już należy do grupy.',
            ]);
        }

        // Sprawdzenie, czy zaproszenie do tej grupy już istnieje
        $existingInvitation = GroupInvitation::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if ($existingInvitation) {
            return response()->json(['status' => 'failed', 'message' => 'Zaproszenie już istnieje.']);
        }

        // Tworzenie zaproszenia do grupy
        GroupInvitation::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => 'pending',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Zaproszenie zostało wysłane.']);
    }

    // Akceptuj zaproszenie do grupy
    public function acceptInvitation($invitationId)
    {
        $invitation = GroupInvitation::findOrFail($invitationId);

        // Użyj polityki do sprawdzenia autoryzacji
        $this->authorize('respond', $invitation);

        // Dodaj użytkownika do grupy
        $invitation->group->users()->attach($invitation->user_id);

        // Usuń zaproszenie z bazy danych po zaakceptowaniu
        $invitation->delete();

        return redirect()->route('groups.index')->with('message', 'Dołączyłeś do grupy.');
    }

    // Odrzuć zaproszenie do grupy
    public function rejectInvitation($invitationId)
    {
        $invitation = GroupInvitation::findOrFail($invitationId);

        // Użyj polityki do sprawdzenia autoryzacji
        $this->authorize('respond', $invitation);

        // Usuń zaproszenie z bazy danych po odrzuceniu
        $invitation->delete();

        return redirect()->route('groups.index')->with('message', 'Zaproszenie zostało odrzucone.');
    }

    // Usuń użytkownika z grupy
    public function removeUserFromGroup(Request $request, Group $group, User $user)
    {
        $this->authorize('update', $group);

        // Usuń użytkownika z grupy, z wyjątkiem właściciela
        if ($group->owner_id == $user->id) {
            return back()->with('error', 'Nie możesz usunąć właściciela grupy.');
        }

        $group->users()->detach($user->id);

        return back()->with('message', 'Użytkownik został usunięty z grupy.');
    }

    // Nadaj/Odbierz rolę administratora w grupie
    public function toggleAdminRole(Request $request, Group $group, User $user)
    {
        $this->authorize('update', $group);

        // Nie można zabrać sobie samemu uprawnień administratora
        if ($user->id == Auth::id()) {
            return back()->with('error', 'Nie możesz odebrać sobie uprawnień administratora.')
                         ->withInput()->with('alert', 'Nie możesz odebrać sobie uprawnień administratora.');
        }

        // Sprawdź, czy użytkownik jest członkiem grupy
        if (!$group->users()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Użytkownik nie jest członkiem tej grupy.');
        }

        // Zmień status administratora
        $isAdmin = $group->users()->where('user_id', $user->id)->first()->pivot->is_admin;
        $group->users()->updateExistingPivot($user->id, ['is_admin' => !$isAdmin]);

        return back()->with('message', 'Status administratora został zmieniony.');
    }
}
