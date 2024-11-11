<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Twoje Grupy') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <!-- Przycisk do tworzenia nowej grupy -->
                    <div class="mb-6">
                        <a href="{{ route('groups.create') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Stwórz Nową Grupę
                        </a>
                    </div>

                    <!-- Grupy, którymi zarządzasz -->
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Grupy, którymi Zarządzasz</h1>
                    @if($adminGroups->isNotEmpty())
                        <ul class="list-none space-y-4 mb-6">
                            @foreach ($adminGroups as $group)
                                <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-semibold text-lg">{{ $group->name }}</span>
                                            <p class="text-sm text-gray-600">{{ $group->description }}</p>
                                        </div>
                                        <div class="flex space-x-4">
                                            <a href="{{ route('groups.edit', $group->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                Zarządzaj
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-700">Nie zarządzasz żadną grupą.</p>
                    @endif

                    <!-- Grupy, do których należysz -->
                    <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-6">Grupy, do Których Należysz</h2>
                    @if($memberGroups->isNotEmpty())
                        <ul class="list-none space-y-4 mb-6">
                            @foreach ($memberGroups as $group)
                                <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-semibold text-lg">{{ $group->name }}</span>
                                            <p class="text-sm text-gray-600">{{ $group->description }}</p>
                                        </div>
                                        <!-- Brak przycisku "Zarządzaj" -->
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-700">Nie należysz do żadnej grupy.</p>
                    @endif

                    <!-- Otrzymane Zaproszenia -->
                    <h2 class="text-xl font-semibold text-gray-800 mt-8 mb-4">Otrzymane Zaproszenia do Grup</h2>
                    @if($invitations->isNotEmpty())
                        <ul class="list-none space-y-4 mb-6">
                            @foreach ($invitations as $invitation)
                                <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-semibold text-lg">{{ $invitation->group->name }}</span>
                                            <p class="text-sm text-gray-600">{{ $invitation->group->description }}</p>
                                        </div>
                                        <div class="flex space-x-4">
                                            <!-- Akceptuj zaproszenie -->
                                            <form action="{{ route('groups.invitations.accept', $invitation->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                                    Akceptuj
                                                </button>
                                            </form>

                                            <!-- Odrzuć zaproszenie -->
                                            <form action="{{ route('groups.invitations.reject', $invitation->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                                    Odrzuć
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-700">Nie masz żadnych oczekujących zaproszeń do grup.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
