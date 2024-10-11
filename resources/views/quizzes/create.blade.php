<x-app-layout>
    <!-- Sekcja nagłówka strony, wyświetlająca tytuł strony -->
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Stwórz nowy quiz') }}
        </h2>
    </x-slot>

    <!-- Główna zawartość strony -->
    <div class="py-12">
        <!-- Kontener na formularz tworzenia quizu, wyrównany do środka z maksymalną szerokością -->
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h1 class="text-2xl font-bold mb-6">Stwórz nowy quiz</h1>

                    <!-- Formularz do tworzenia nowego quizu -->
                    <form action="{{ route('quizzes.store') }}" method="POST">
                        @csrf

                        <!-- Pole do wprowadzania tytułu quizu -->
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 font-bold mb-2">Tytuł quizu:</label>
                            <input type="text" id="title" name="title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        </div>

                        <!-- Nagłówek sekcji pytań -->
                        <h3 class="text-xl font-semibold mb-4">Pytania</h3>

                        <div id="questions-container">
                            <!-- Pierwsze pytanie (generowane dynamicznie) -->
                            <div class="question-block mb-6">
                                <h4 class="font-semibold text-lg mb-2">Pytanie 1</h4>
                                <!-- Treść pytania -->
                                <div class="mb-4">
                                    <label for="question_text" class="block text-gray-700 font-bold mb-2">Treść pytania:</label>
                                    <input type="text" name="questions[0][question_text]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>

                                <!-- Wybór typu pytania (zamknięte/otwarte) -->
                                <div class="mb-4">
                                    <label for="question_type" class="block text-gray-700 font-bold mb-2">Rodzaj pytania:</label>
                                    <select name="questions[0][type]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline question-type" required>
                                        <option value="closed">Zamknięte (ABCD)</option>
                                        <option value="open">Otwarte (Kod)</option>
                                    </select>
                                </div>

                                <!-- Opcje dla pytania zamkniętego (ABCD) -->
                                <div class="closed-question">
                                    <div class="mb-4">
                                        <label for="option_a" class="block text-gray-700 font-bold mb-2">Odpowiedź A:</label>
                                        <input type="text" name="questions[0][option_a]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div class="mb-4">
                                        <label for="option_b" class="block text-gray-700 font-bold mb-2">Odpowiedź B:</label>
                                        <input type="text" name="questions[0][option_b]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div class="mb-4">
                                        <label for="option_c" class="block text-gray-700 font-bold mb-2">Odpowiedź C:</label>
                                        <input type="text" name="questions[0][option_c]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div class="mb-4">
                                        <label for="option_d" class="block text-gray-700 font-bold mb-2">Odpowiedź D:</label>
                                        <input type="text" name="questions[0][option_d]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    </div>
                                    <div class="mb-4">
                                        <label for="correct_option" class="block text-gray-700 font-bold mb-2">Poprawna odpowiedź:</label>
                                        <select name="questions[0][correct_option]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Pole dla pytania otwartego (Kod) -->
                                <div class="open-question" style="display: none;">
                                    <div class="mb-4">
                                        <label for="expected_code" class="block text-gray-700 font-bold mb-2">Oczekiwany kod:</label>
                                        <!-- Pole tekstowe, które automatycznie się rozszerza -->
                                        <textarea name="questions[0][expected_code]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline auto-expand" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Przycisk do dodawania nowych pytań -->
                        <button type="button" id="add-question-btn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Dodaj pytanie</button>

                        <!-- Przycisk do zapisu quizu -->
                        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 mt-4 rounded">Zapisz quiz</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let questionCount = 0; // Ustawienie na 0, aby zaczynało się od indeksu 0

        // Funkcja do obsługi zmiany typu pytania (otwarte/zamknięte)
        function handleQuestionTypeChange() {
            document.querySelectorAll('.question-type').forEach(function(select) {
                select.addEventListener('change', function() {
                    let parent = select.closest('.question-block');
                    if (select.value === 'open') {
                        // Ukrywamy opcje dla pytania zamkniętego (ABCD)
                        parent.querySelector('.closed-question').style.display = 'none';
                        parent.querySelector('.open-question').style.display = 'block';
                        // Ustawienie "correct_option" na null dla pytań otwartych
                        const correctOptionSelect = parent.querySelector('[name$="[correct_option]"]');
                        if (correctOptionSelect) {
                            correctOptionSelect.value = '';
                        }
                    } else {
                        // Pokazujemy opcje dla pytania zamkniętego (ABCD)
                        parent.querySelector('.closed-question').style.display = 'block';
                        parent.querySelector('.open-question').style.display = 'none';
                    }
                });
            });
        }

        // Obsługa pierwszego pytania, które jest już dodane na początku
        handleQuestionTypeChange();

        document.getElementById('add-question-btn').addEventListener('click', function() {
            questionCount++; // Zwiększamy licznik pytań za każdym razem, gdy dodajemy nowe pytanie
            const questionsContainer = document.getElementById('questions-container');

            // Dynamiczne tworzenie nowego pytania
            const newQuestionBlock = `
                <div class="question-block mb-6">
                    <h4 class="font-semibold text-lg mb-2">Pytanie ${questionCount + 1}</h4>
                    <div class="mb-4">
                        <label for="question_text" class="block text-gray-700 font-bold mb-2">Treść pytania:</label>
                        <input type="text" name="questions[${questionCount}][question_text]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>

                    <!-- Wybór rodzaju pytania -->
                    <div class="mb-4">
                        <label for="question_type" class="block text-gray-700 font-bold mb-2">Rodzaj pytania:</label>
                        <select name="questions[${questionCount}][type]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline question-type" required>
                            <option value="closed">Zamknięte (ABCD)</option>
                            <option value="open">Otwarte (Kod)</option>
                        </select>
                    </div>

                    <!-- Opcje dla pytania zamkniętego (ABCD) -->
                    <div class="closed-question">
                        <div class="mb-4">
                            <label for="option_a" class="block text-gray-700 font-bold mb-2">Odpowiedź A:</label>
                            <input type="text" name="questions[${questionCount}][option_a]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="option_b" class="block text-gray-700 font-bold mb-2">Odpowiedź B:</label>
                            <input type="text" name="questions[${questionCount}][option_b]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="option_c" class="block text-gray-700 font-bold mb-2">Odpowiedź C:</label>
                            <input type="text" name="questions[${questionCount}][option_c]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="option_d" class="block text-gray-700 font-bold mb-2">Odpowiedź D:</label>
                            <input type="text" name="questions[${questionCount}][option_d]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="mb-4">
                            <label for="correct_option" class="block text-gray-700 font-bold mb-2">Poprawna odpowiedź:</label>
                            <select name="questions[${questionCount}][correct_option]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                    </div>

                    <!-- Pole dla pytania otwartego (Kod) -->
                    <div class="open-question" style="display: none;">
                        <div class="mb-4">
                            <label for="expected_code" class="block text-gray-700 font-bold mb-2">Oczekiwany kod:</label>
                            <!-- Pole tekstowe, które automatycznie się rozszerza -->
                            <textarea name="questions[${questionCount}][expected_code]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline auto-expand" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            `;

            // Wstawienie nowego pytania do formularza
            questionsContainer.insertAdjacentHTML('beforeend', newQuestionBlock);

            // Dodanie obsługi zdarzeń dla nowo dodanych pytań
            handleQuestionTypeChange();
        });

        // Funkcja do automatycznego rozszerzania pola tekstowego w zależności od ilości tekstu
        document.addEventListener('input', function (event) {
            if (event.target.classList.contains('auto-expand')) {
                event.target.style.height = 'auto';
                event.target.style.height = (event.target.scrollHeight) + 'px';
            }
        });
    </script>
</x-app-layout>
