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
                    <ul class="list-none space-y-4 mb-6">
                        @forelse ($quizzesCreatedByUser as $quiz)
                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-lg">{{ $quiz->title }}</span>

                                    <div class="flex space-x-4">
                                        <!-- Przycisk "Zobacz szczegóły" -->
                                        <a href="{{ route('quizzes.show', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Zobacz szczegóły
                                        </a>

                                        <!-- Formularz usuwania quizu -->
                                        <form action="{{ route('quizzes.destroy', $quiz->id) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć ten quiz?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                                Usuń
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                                Nie masz jeszcze żadnych quizów.
                            </li>
                        @endforelse
                    </ul>

                    <a href="{{ route('quizzes.create') }}" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mb-6 inline-block">
                        Stwórz nowy quiz
                    </a>

                    <h2 class="text-xl font-semibold text-gray-800 mt-8 mb-4">Quizy do rozwiązania</h2>

                    <!-- Formularz wyszukiwania quizów innych użytkowników -->
                    <form action="{{ route('user.dashboard') }}" method="GET" class="mb-6">
                        <div class="flex space-x-4">
                            <input type="text" name="search" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Wyszukaj quizy" value="{{ request('search') }}">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Szukaj
                            </button>
                        </div>
                    </form>

                    <!-- Wyświetlanie quizów, które użytkownik może rozwiązać -->
                    <ul class="list-none space-y-4">
                        @forelse ($availableQuizzes as $quiz)
                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                <div class="flex justify-between items-center">
                                    <span class="font-semibold text-lg">{{ $quiz->title }}</span>
                                    <div class="flex space-x-4">
                                        <a href="{{ route('quizzes.solve', $quiz->id) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Rozpocznij quiz
                                        </a>
                                        <!-- Przycisk "Moje podejścia" -->
                                        <button class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded show-attempts" data-quiz-id="{{ $quiz->id }}">
                                            Moje podejścia
                                        </button>
                                    </div>
                                </div>

                                <!-- Miejsce na tabelę z podejściami użytkownika (ukryta domyślnie) -->
                                <div class="user-attempts hidden mt-4" id="user-attempts-{{ $quiz->id }}">
                                    <h3 class="text-lg font-semibold">Moje podejścia</h3>
                                    <table class="table-auto w-full text-left mt-4">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2">Data podejścia</th>
                                                <th class="px-4 py-2">Wynik</th>
                                                <th class="px-4 py-2">Szczegóły</th>
                                            </tr>
                                        </thead>
                                        <tbody id="attempts-body-{{ $quiz->id }}">
                                            <!-- Wyniki zostaną tutaj dynamicznie załadowane -->
                                        </tbody>
                                    </table>
                                </div>
                            </li>
                        @empty
                            <li class="bg-gray-100 p-4 rounded-lg shadow-sm text-gray-700">
                                Brak dostępnych quizów do rozwiązania.
                            </li>
                        @endforelse
                    </ul>

                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript do obsługi przycisków "Moje podejścia" i "Pokaż odpowiedzi" -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const attemptButtons = document.querySelectorAll('.show-attempts');

            attemptButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const quizId = this.getAttribute('data-quiz-id');
                    const attemptsContainer = document.querySelector('#user-attempts-' + quizId);
                    const attemptsBody = document.querySelector('#attempts-body-' + quizId);

                    // Jeśli podejścia już są widoczne, ukrywamy je
                    if (!attemptsContainer.classList.contains('hidden')) {
                        attemptsContainer.classList.add('hidden');
                        return;
                    }

                    // Wyślij zapytanie AJAX, aby pobrać podejścia użytkownika
                    fetch(`/quizzes/${quizId}/attempts`)
                        .then(response => response.json())
                        .then(data => {
                            attemptsBody.innerHTML = ''; // Wyczyść poprzednie dane

                            if (data.attempts.length > 0) {
                                data.attempts.forEach(attempt => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td class="border px-4 py-2">${new Date(attempt.created_at).toLocaleString()}</td>
                                        <td class="border px-4 py-2">${attempt.correct} / ${attempt.total}</td>
                                        <td class="border px-4 py-2">
                                            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded toggle-answers" data-attempt-uuid="${attempt.attempt_uuid}">
                                                Pokaż odpowiedzi
                                            </button>
                                        </td>
                                    `;
                                    attemptsBody.appendChild(row);

                                    // Obsługa przycisku "Pokaż odpowiedzi"
                                    const answerButton = row.querySelector('.toggle-answers');
                                    answerButton.addEventListener('click', function () {
                                        const attemptUuid = this.getAttribute('data-attempt-uuid');
                                        const answerContainer = document.querySelector(`#answers-${attemptUuid}`);

                                        if (!answerContainer) {
                                            // Pobranie odpowiedzi z serwera
                                            fetch(`/quizzes/attempts/${attemptUuid}/answers`)
                                                .then(response => response.json())
                                                .then(data => {
                                                    // Tworzenie tabeli z odpowiedziami
                                                    const answersHtml = data.answers.map(answer => `
                                                        <tr>
                                                            <td class="border px-4 py-2">${answer.question}</td>
                                                            <td class="border px-4 py-2">${answer.user_answer ? `<pre><code>${answer.user_answer}</code></pre>` : ''}</td>
                                                            <td class="border px-4 py-2">${answer.is_correct}</td>
                                                        </tr>
                                                    `).join('');
                                                    
                                                    // Wstawienie tabeli poniżej odpowiedniego elementu
                                                    const answersTable = `
                                                        <tr id="answers-${attemptUuid}">
                                                            <td colspan="3">
                                                                <table class="table-auto w-full mt-4">
                                                                    <thead>
                                                                        <tr>
                                                                            <th class="px-4 py-2">Pytanie</th>
                                                                            <th class="px-4 py-2">Moja odpowiedź</th>
                                                                            <th class="px-4 py-2">Poprawna/niepoprawna</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        ${answersHtml}
                                                                    </tbody>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    `;
                                                    row.insertAdjacentHTML('afterend', answersTable);
                                                })
                                                .catch(error => {
                                                    console.error('Błąd podczas pobierania odpowiedzi:', error);
                                                });
                                        } else {
                                            // Ukrywanie odpowiedzi
                                            answerContainer.remove();
                                        }
                                    });
                                });
                            } else {
                                attemptsBody.innerHTML = '<tr><td colspan="3" class="border px-4 py-2 text-center">Brak podejść.</td></tr>';
                            }

                            attemptsContainer.classList.remove('hidden'); // Pokaż podejścia
                        })
                        .catch(error => {
                            console.error('Błąd podczas pobierania podejść:', error);
                        });
                });
            });
        });
    </script>
</x-app-layout>
