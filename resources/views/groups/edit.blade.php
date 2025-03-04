<x-app-layout>
    <div class="grid grid-cols-10 py-5 sm:px-6 lg:px-8">
        <div class="col-span-4 p-4 border-r border-gray-300">
                    <!-- Wyszukiwanie użytkownika po adresie e-mail i dodawanie do grupy -->
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Dodaj członków do grupy</h2>

                <!-- Wyszukiwanie użytkownika po adresie e-mail -->
                <div class="mb-4">
                    <label for="user-email" class="block text-sm font-medium text-gray-700">Sprawdź użytkownika po adresie e-mail:</label>
                    <input type="email" id="user-email" class="w-full p-2 border border-gray-300 rounded-md mb-2" placeholder="Podaj adres e-mail">
                    <div class="text-right">
                        <x-dark-button type="button" onclick="checkUser()">
                            Sprawdź
                        </x-dark-button>
                    </div>
                </div>

                <!-- Dane znalezionego użytkownika lub komunikat o błędzie -->
                <div id="user-details" class="hidden mb-4 p-4 border border-gray-300 rounded-md bg-gray-50">
                    <div id="user-details-content"></div>
                </div>
            </div>

            <hr class="my-6">

            <!-- Lista członków grupy -->
            <div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Członkowie grupy</h2>
                <ul class="list-none space-y-4">
                    @foreach ($groupMembers as $member)
                        <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold">{{ $member->name }}</span>
                                    <span class="text-gray-600 text-sm">({{ $member->email }})</span>
                                    @if($group->owner_id === $member->id)
                                        <span class="ml-2 text-green-600 font-semibold"><p>(Właściciel)</p></span>
                                    @elseif($member->pivot->is_admin)
                                        <span class="ml-2 text-blue-600 font-semibold"><p>(Administrator)</p></span>
                                    @else
                                        <span class="ml-2 font-semibold"><p>(Użytkownik)</p></span>
                                    @endif
                                </div>

                                @can('update', $group)
                                    <div class="flex items-center space-x-4">
                                        @if ($group->owner_id !== $member->id)
                                            <form action="{{ route('groups.removeUser', ['group' => $group->id, 'user' => $member->id]) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika z grupy?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-red-button type="submit">
                                                    Usuń
                                                </x-red-button>
                                            </form>

                                            <form action="{{ route('groups.toggleAdmin', ['group' => $group->id, 'user' => $member->id]) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <x-dark-button type="submit">
                                                    {{ $member->pivot->is_admin ? 'Odbierz rolę administratora' : 'Nadaj rolę administratora' }}
                                                </x-dark-button>
                                            </form>
                                        @endif
                                    </div>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-span-6 p-4">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edycja Grupy</h2>
        
            <!-- Formularz aktualizacji (bez przycisku) -->
            <form action="{{ route('groups.update', $group->id) }}" method="POST" id="updateForm">
                @csrf
                @method('PUT')
                
                <!-- Nazwa grupy -->
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nazwa Grupy:</label>
                    <input type="text" name="name" id="name" value="{{ $group->name }}" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
        
                <!-- Opis grupy -->
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700">Opis:</label>
                    <textarea name="description" id="description" class="w-full p-2 border border-gray-300 rounded-md">{{ $group->description }}</textarea>
                </div>
            </form>
        
            <!-- Kontener dla przycisków, ustawiony jako flex -->
            <div class="flex mb-6">
                <!-- Lewa połowa: przycisk aktualizacji, wysyłający formularz update -->
                <div class="w-1/2">
                    <x-dark-button type="submit" form="updateForm">
                        Aktualizuj Grupę
                    </x-dark-button>
                </div>
        
                <!-- Prawa połowa: przycisk usunięcia (oddzielny formularz) -->
                <div class="w-1/2 flex justify-end">
                    @if ($group->owner_id === Auth::id())
                        <form action="{{ route('groups.destroy', $group->id) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć tę grupę?');">
                            @csrf
                            @method('DELETE')
                            <x-red-button type="submit">
                                Usuń Grupę
                            </x-red-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        

    <!-- JavaScript do wyszukiwania użytkownika i wysyłania zaproszeń -->
    <script>
        async function checkUser() {
            const email = document.getElementById('user-email').value.trim();
            if (!email) {
                alert('Podaj adres e-mail użytkownika.');
                return;
            }

            try {
                const response = await fetch('{{ route('groups.checkUser') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ email, group_id: '{{ $group->id }}' }),
                });

                const result = await response.json();
                const userDetailsDiv = document.getElementById('user-details');
                const userDetailsContentDiv = document.getElementById('user-details-content');

                if (result.status === 'found') {
                    // Pokaż dane użytkownika
                    userDetailsDiv.classList.remove('hidden');
                    userDetailsContentDiv.innerHTML = `
                    <h3 class="text-lg font-semibold mb-2">Dane użytkownika</h3>
                    <p><strong>Imię i nazwisko:</strong> ${result.user.name}</p>
                    <p><strong>Email:</strong> ${result.user.email}</p>
                    <div class="text-right">
                        <x-green-button type="button" onclick="sendInvitation(${result.user.id})">
                            Wyślij zaproszenie do grupy
                        </x-green-button>
                    </div>
                    `;
                } else if (result.status === 'already_member') {
                    // Wyświetlenie komunikatu, jeśli użytkownik już jest członkiem grupy
                    userDetailsDiv.classList.remove('hidden');
                    userDetailsContentDiv.innerHTML = `
                        <p class="text-blue-500 font-semibold">Użytkownik już jest członkiem tej grupy.</p>
                    `;
                } else {
                    // Wyświetlenie komunikatu, jeśli użytkownik nie zostanie znaleziony
                    userDetailsDiv.classList.remove('hidden');
                    userDetailsContentDiv.innerHTML = `
                        <p class="text-red-500 font-semibold">Nie znaleziono użytkownika z podanym adresem e-mail.</p>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                const userDetailsDiv = document.getElementById('user-details');
                const userDetailsContentDiv = document.getElementById('user-details-content');

                userDetailsDiv.classList.remove('hidden');
                userDetailsContentDiv.innerHTML = `
                    <p class="text-red-500 font-semibold">Wystąpił błąd podczas wyszukiwania użytkownika.</p>
                `;
            }
        }

        async function sendInvitation(userId) {
            const groupId = '{{ $group->id }}';

            if (!userId) {
                alert('Nie można wysłać zaproszenia. Nie znaleziono użytkownika.');
                return;
            }

            try {
                const response = await fetch('{{ route('groups.sendInvitation') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ user_id: userId, group_id: groupId }),
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Zaproszenie zostało wysłane.');
                } else {
                    alert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Wystąpił błąd podczas wysyłania zaproszenia.');
            }
        }
    </script>
</x-app-layout>
