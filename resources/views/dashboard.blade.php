<x-app-layout>
    <!-- Nagłówek strony -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <!-- Główna zawartość strony -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <!-- Blok powiadomienia o zalogowaniu -->
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- Wiadomość o zalogowaniu -->
                    <h3 class="text-2xl font-semibold text-green-600">
                        {{ __("You're logged in!") }}
                    </h3>
                    <p class="text-gray-600 mt-2">
                        Cieszymy się, że wróciłeś! Teraz możesz kontynuować swoją pracę lub zacząć nowy quiz.
                    </p>
                </div>

                <!-- Przyciski szybkiego dostępu -->
                <div class="mt-6 flex space-x-4">
                    <a href="{{ route('user.dashboard') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Zobacz swoje quizy
                    </a>
                    <a href="{{ route('quizzes.create') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Stwórz nowy quiz
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
