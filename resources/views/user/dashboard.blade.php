<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Panel użytkownika') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Twoje quizy</h1>

                    <!-- Wyświetlanie quizów stworzonych przez użytkownika -->
                    @if($quizzesCreatedByUser->isNotEmpty())
                        <ul class="list-none space-y-4 mb-6">
                            @foreach ($quizzesCreatedByUser as $quiz)
                                <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <span class="font-semibold text-lg">{!! $quiz->title !!}</span>
                                            <span class="ml-2 text-sm {{ $quiz->is_active ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $quiz->is_active ? 'Aktywny' : 'Nieaktywny' }}
                                            </span>
                                        </div>

                                        <div class="flex space-x-4">
                                            <a href="{{ route('quizzes.edit', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                Edytuj
                                            </a>

                                            <form action="{{ route('quizzes.destroy', $quiz->id) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć ten quiz?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                                    Usuń
                                                </button>
                                            </form>

                                            <!-- Dodano przycisk "Podejścia" -->
                                            <a href="{{ route('quizzes.owner_attempts', $quiz->id) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                                Podejścia
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-700">Nie masz jeszcze żadnych quizów.</p>
                    @endif

                    <a href="{{ route('quizzes.create') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mb-6 inline-block">
                        Stwórz nowy quiz
                    </a>

                    <!-- Quizy przypisane do grup użytkownika -->
                    <h2 class="text-xl font-semibold text-gray-800 mt-8 mb-4">Quizy przypisane do Twoich grup</h2>

                    @if($groupedQuizzes->filter(fn($quizzes) => $quizzes->isNotEmpty())->isNotEmpty())
                        @foreach ($groupedQuizzes as $groupName => $quizzes)
                            @if($quizzes->isNotEmpty())
                                <h3 class="text-lg font-semibold text-gray-700 mt-6 mb-2">{{ $groupName }}</h3>
                                <ul class="list-none space-y-4 mb-4">
                                    @foreach ($quizzes as $quiz)
                                        @if($quiz->is_active)
                                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold text-lg">{!! $quiz->title !!}</span>
                                                    <div class="flex space-x-4">
                                                        @php
                                                            $attemptCount = $userAttempts[$quiz->id] ?? 0;
                                                        @endphp

                                                        <!-- Nowy warunek dla quizów jednorazowych -->
                                                        @if(!$quiz->multiple_attempts && $attemptCount >= 1)
                                                            <span class="text-gray-600 font-bold">Nie można ponownie przystąpić do quizu</span>
                                                        @else
                                                            <a href="{{ route('quizzes.solve', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                                Rozpocznij quiz
                                                            </a>
                                                        @endif

                                                        <button onclick="window.location='{{ route('quizzes.user_attempts', $quiz->id) }}'" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                                            Moje podejścia
                                                        </button>
                                                    </div>
                                                </div>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    @else
                        <p class="text-gray-700">Nie masz jeszcze quizów przypisanych do Twoich grup.</p>
                    @endif

                    <!-- Quizy ogólnodostępne -->
                    <h2 class="text-xl font-semibold text-gray-800 mt-8 mb-4">Quizy dostępne dla wszystkich</h2>

                    <ul class="list-none space-y-4">
                        @if($publicQuizzes->isNotEmpty())
                            @foreach ($publicQuizzes as $quiz)
                                @if($quiz->is_active)
                                    <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                        <div class="flex justify-between items-center">
                                            <span class="font-semibold text-lg">{!! $quiz->title !!}</span>
                                            <div class="flex space-x-4">
                                                @php
                                                    $attemptCount = $userAttempts[$quiz->id] ?? 0;
                                                @endphp

                                                <!-- Nowy warunek dla quizów jednorazowych -->
                                                @if(!$quiz->multiple_attempts && $attemptCount >= 1)
                                                    <span class="text-gray-600 font-bold">Nie można ponownie przystąpić do quizu</span>
                                                @else
                                                    <a href="{{ route('quizzes.solve', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                                        Rozpocznij quiz
                                                    </a>
                                                @endif
                                                
                                                <button onclick="window.location='{{ route('quizzes.user_attempts', $quiz->id) }}'" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded show-attempts" data-quiz-id="{{ $quiz->id }}">
                                                    Moje podejścia
                                                </button>
                                            </div>
                                        </div>
                                    </li>
                                @endif
                            @endforeach
                        @else
                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                                Brak quizów dostępnych dla wszystkich.
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
