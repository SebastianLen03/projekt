<x-app-layout>
    <!-- Nagłówek strony -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Szczegóły quizu: ') . $quiz->title }}
        </h2>
    </x-slot>

    <!-- Główna zawartość strony -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- Tytuł quizu -->
                    <h1 class="text-2xl font-bold mb-4">Szczegóły quizu: {{ $quiz->title }}</h1>

                    <!-- Sekcja pytań -->
                    <h3 class="text-xl font-semibold mb-4">Pytania</h3>
                    <ul class="list-none">
                        @foreach ($quiz->questions as $question)
                            <!-- Wyświetlanie każdego pytania oraz jego opcji -->
                            <li class="mb-6">
                                <p class="text-lg"><strong>Pytanie:</strong> {{ $question->question_text }}</p>
                                
                                @if (is_null($question->expected_code))
                                    <div class="ml-4">
                                        <p><strong>A:</strong> {{ $question->option_a }}</p>
                                        <p><strong>B:</strong> {{ $question->option_b }}</p>
                                        <p><strong>C:</strong> {{ $question->option_c }}</p>
                                        <p><strong>D:</strong> {{ $question->option_d }}</p>
                                        <!-- Wyświetlanie poprawnej odpowiedzi -->
                                        <p><strong>Poprawna odpowiedź:</strong> {{ $question->correct_option }}</p>
                                    </div>
                                @else
                                    <div class="ml-4 bg-gray-100 p-4 rounded">
                                        <strong>Oczekiwany kod:</strong>
                                        <pre><code>{{ $question->expected_code }}</code></pre>
                                    </div>
                                @endif
                            </li>
                            <hr class="border-gray-300 mb-6">
                        @endforeach
                    </ul>

                    <!-- Przyciski akcji (tylko dla zalogowanych użytkowników) -->
                    @if (Auth::check())
                        <div class="flex space-x-4">
                            <!-- Przycisk do rozpoczęcia quizu -->
                            <a href="{{ route('quizzes.solve', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Rozpocznij quiz</a>

                            <!-- Przycisk powrotu do panelu -->
                            <a href="{{ route('user.dashboard') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">Powrót do panelu</a>
                        </div>
                    @else
                        <!-- Informacja, że trzeba być zalogowanym, aby rozwiązać quiz -->
                        <p class="text-yellow-500 mt-3">Zaloguj się, aby rozwiązać quiz.</p>
                    @endif

                    <!-- Wyniki użytkowników -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-8">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h2 class="text-xl font-semibold mb-4">Podejścia użytkowników</h2>

                            <!-- Tabela wyników użytkowników -->
                            <div id="results-section">
                                <table class="table-auto w-full text-left">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2">Użytkownik</th>
                                            <th class="px-4 py-2">Email</th>
                                            <th class="px-4 py-2">Wynik</th>
                                            <th class="px-4 py-2">Szczegóły</th>
                                        </tr>
                                    </thead>
                                    <tbody id="results">
                                        @foreach($userResults as $result)
                                            <tr>
                                                <td class="border px-4 py-2">{{ $result['user']->name }}</td>
                                                <td class="border px-4 py-2">{{ $result['user']->email }}</td>
                                                <td class="border px-4 py-2">{{ $result['correct'] }} / {{ $result['total'] }}</td>
                                                <td class="border px-4 py-2 text-right">
                                                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded toggle-answers" type="button" data-target="#details-{{ $result['attempt_uuid'] }}">
                                                        Pokaż odpowiedzi
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="border px-4 py-2">
                                                    <div class="answers-section collapse" id="details-{{ $result['attempt_uuid'] }}">
                                                        <table class="table-auto w-full text-left mt-4">
                                                            <thead>
                                                                <tr>
                                                                    <th class="px-4 py-2">Pytanie</th>
                                                                    <th class="px-4 py-2">Odpowiedź</th>
                                                                    <th class="px-4 py-2">Poprawność</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($result['answers'] as $answer)
                                                                    <tr>
                                                                        <td class="border px-4 py-2">{{ $answer->question->question_text }}</td>
                                                                        <td class="border px-4 py-2">
                                                                            @if ($answer->answer)
                                                                                <pre><code>{{ $answer->answer }}</code></pre>
                                                                            @else
                                                                                {{ $answer->selected_option }}
                                                                            @endif
                                                                        </td>
                                                                        <td class="border px-4 py-2">
                                                                            @if($answer->is_correct)
                                                                                <span class="text-green-500">Poprawna</span>
                                                                            @else
                                                                                <span class="text-red-500">Błędna</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript do obsługi przycisków "Pokaż odpowiedzi" -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Funkcja do obsługi rozwijania odpowiedzi
            function addAnswerToggleListeners() {
                const toggleButtons = document.querySelectorAll('.toggle-answers');

                toggleButtons.forEach(button => {
                    button.addEventListener('click', function () {
                        const targetId = this.getAttribute('data-target');
                        const targetElement = document.querySelector(targetId);

                        if (targetElement.classList.contains('collapse')) {
                            targetElement.classList.remove('collapse');
                            this.textContent = 'Ukryj odpowiedzi';
                        } else {
                            targetElement.classList.add('collapse');
                            this.textContent = 'Pokaż odpowiedzi';
                        }
                    });
                });
            }

            // Początkowe podłączenie listenerów dla przycisków
            addAnswerToggleListeners();
        });
    </script>
</x-app-layout>
