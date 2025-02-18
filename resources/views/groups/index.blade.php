<x-app-layout>
    <div class="grid grid-cols-10 py-5 sm:px-6 lg:px-8">
        <div class="col-span-4 p-4 border-r border-gray-300">
            <!-- Otrzymane Zaproszenia -->
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Zaproszenia do grup</h2>
            @if($invitations->isNotEmpty())
                <ul class="list-none space-y-4 mb-6">
                    @foreach ($invitations as $invitation)
                        <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold text-lg">{{ $invitation->group->name }}</span>
                                    <p class="text-sm text-gray-600">{{ $invitation->group->description }}</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <!-- Akceptuj zaproszenie -->
                                    <form action="{{ route('groups.invitations.accept', $invitation->id) }}" method="POST">
                                        @csrf
                                        <x-green-button type="submit">
                                            Akceptuj    
                                        </x-green-button>
                                    </form>

                                    <!-- Odrzuć zaproszenie -->
                                    <form action="{{ route('groups.invitations.reject', $invitation->id) }}" method="POST">
                                        @csrf
                                        <x-red-button type="submit">
                                            Odrzuć
                                        </x-red-button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                    Brak.
                </p>
            @endif
            <hr class="my-4">
      
            <!-- Przycisk do tworzenia nowej grupy -->
            <div class="mb-6 text-right">
                <x-dark-a href="{{ route('groups.create') }}">
                    Stwórz nową grupę
                </x-dark-a>
            </div>

            </div>
            <div class="col-span-6 p-4">

            <!-- Grupy, do których należysz -->
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Grupy, do których należysz</h2>
            @if($adminGroups->isNotEmpty() | $memberGroups->isNotEmpty())
                <ul class="list-none space-y-4 mb-6">
                    @foreach ($adminGroups as $group)
                        <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold text-lg">{{ $group->name }}</span>
                                    <p class="text-sm text-gray-600">{{ $group->description }}</p>
                                </div>
                                <div class="flex space-x-4">
                                    <!-- Przycisk przeniesienia do zarządzania daną grupą -->
                                    <x-dark-a href="{{ route('groups.edit', $group->id) }}">
                                            Zarządzaj
                                    </x-dark-a>
                                </div>
                            </div>
                        </li>
                    @endforeach
                    @foreach ($memberGroups as $group)
                    <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-semibold text-lg">{{ $group->name }}</span>
                                <p class="text-sm text-gray-600">{{ $group->description }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
                </ul>
            @else
                <p class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                    Brak.
                </p>
            @endif
        </div>
    </div>
</x-app-layout>
