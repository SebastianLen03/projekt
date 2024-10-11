{{-- <x-app-layout>
    <!-- Sekcja nagłówka strony, wyświetlająca tytuł -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Twoje quizy') }}
        </h2>
    </x-slot>

    <!-- Główna zawartość strony -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Nagłówek strony z listą quizów -->
                    <h1 class="text-2xl font-bold mb-6">Twoje quizy</h1>

                    <!-- Lista quizów -->
                    <ul class="mb-6">
                        <!-- Pętla przez quizy, jeśli są dostępne -->
                        @forelse ($quizzes as $quiz)
                            <li class="border rounded-md p-4 mb-4 flex justify-between items-center">
                                <span class="font-semibold">{{ $quiz->title }}</span>
                                <!-- Przycisk do szczegółów quizu -->
                                <a href="{{ route('quizzes.show', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Zobacz szczegóły
                                </a>
                            </li>
                        @empty
                            <!-- Informacja, jeśli nie ma quizów -->
                            <li class="border rounded-md p-4 mb-4 text-gray-600">Nie masz jeszcze żadnych quizów.</li>
                        @endforelse
                    </ul>

                    <!-- Przycisk do stworzenia nowego quizu -->
                    <a href="{{ route('quizzes.create') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Stwórz nowy quiz</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout> --}}
