<x-app-layout> 
    <div class="grid grid-cols-10 py-5 sm:px-6 lg:px-8">
 <!-- Segment 30% -->
        <div class="col-span-4 p-4 border-r border-gray-300">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Twoje testy</h2>
                @if($quizzesCreatedByUser->isNotEmpty())
                <ul class="list-none space-y-4 mb-6">
                    @foreach ($quizzesCreatedByUser as $quiz)
                        <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span>{!! $quiz->title !!}</span>
                                    <span class="ml-2 text-sm {{ $quiz->is_active ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $quiz->is_active ? 'Aktywny' : 'Nieaktywny' }}
                                    </span>
                                </div>

                                <div class="flex space-x-4">
                                    <x-dark-a href="{{ route('quizzes.edit', $quiz->id) }}">
                                        Edytuj
                                </x-dark-a>

                                    <form action="{{ route('quizzes.destroy', $quiz->id) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć ten quiz?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                            Usuń
                                        </button>
                                    </form>

                                    <!-- Dodano przycisk "Podejścia" -->
                                    {{-- <a href="{{ route('quiz.owner_attempts', $quiz->id) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                        Podejścia
                                    </a> --}}
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
            <br>
            <div class="text-right">
                <x-dark-a href="{{ route('quizzes.create') }}">
                    Stwórz nowy test
                </x-dark-a>
            </div>
        </div>
        <!-- Segment 70% -->
        <div class="col-span-6 p-4">
                        <!-- Quizy przypisane do grup użytkownika -->
                        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Testy przypisane dla Twoich grup</h2>

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
                                                                // Liczba podejść użytkownika do najnowszej wersji quizu
                                                                $attemptCount = $userAttempts[$quiz->id] ?? 0;
                                                            @endphp
    
                                                            <!-- Zaktualizowany warunek dla quizów jednorazowych -->
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
                        <p class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                            Brak.
                        </p>
                        @endif
    
                        <!-- Quizy ogólnodostępne -->
                        <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-4">Testy dostępne dla wszystkich</h2>
    
                        <ul class="list-none space-y-4">
                            @if($publicQuizzes->isNotEmpty())
                                @foreach ($publicQuizzes as $quiz)
                                    @if($quiz->is_active)
                                        <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                            <div class="flex justify-between items-center">
                                                <span class="font-semibold text-lg">{!! $quiz->title !!}</span>
                                                <div class="flex space-x-4">
                                                    @php
                                                        // Liczba podejść użytkownika do najnowszej wersji quizu
                                                        $attemptCount = $userAttempts[$quiz->id] ?? 0;
                                                    @endphp
    
                                                    <!-- Zaktualizowany warunek dla quizów jednorazowych -->
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
                                <p class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                                    Brak.
                                </p>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
