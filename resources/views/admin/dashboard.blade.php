<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Panel Admina') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-bold mb-4">Zarządzanie użytkownikami</h1>

                    <!-- Tabela użytkowników -->
                    <table class="table-auto w-full">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">ID</th>
                                <th class="px-4 py-2">Nazwa</th>
                                <th class="px-4 py-2">Email</th>
                                <th class="px-4 py-2">Admin</th>
                                <th class="px-4 py-2">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td class="border px-4 py-2">{{ $user->id }}</td>
                                    <td class="border px-4 py-2">{{ $user->name }}</td>
                                    <td class="border px-4 py-2">{{ $user->email }}</td>
                                    <td class="border px-4 py-2">{{ $user->admin ? 'Tak' : 'Nie' }}</td>
                                    <td class="border px-4 py-2">
                                        <!-- Formularz do edycji danych użytkownika -->
                                        <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')

                                            <!-- Edycja nazwy -->
                                            <input type="text" name="name" value="{{ $user->name }}" class="border rounded p-2 mb-2" required>
                                            <!-- Edycja emaila -->
                                            <input type="email" name="email" value="{{ $user->email }}" class="border rounded p-2 mb-2" required>
                                            <!-- Zaznaczenie, czy użytkownik jest adminem -->
                                            <select name="admin" class="border rounded p-2 mb-2" required>
                                                <option value="1" {{ $user->admin ? 'selected' : '' }}>Tak</option>
                                                <option value="0" {{ !$user->admin ? 'selected' : '' }}>Nie</option>
                                            </select>

                                            <!-- Przycisk do aktualizacji -->
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                Zaktualizuj
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
