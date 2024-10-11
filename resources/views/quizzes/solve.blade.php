<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rozwiąż quiz') }}: {{ $quiz->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <form action="{{ route('quizzes.submit', $quiz->id) }}" method="POST">
                        @csrf

                        <!-- Wyświetlanie pytań quizu -->
                        @foreach($quiz->questions as $question)
                            <div class="mb-4">
                                <h4>{{ $loop->iteration }}. {{ $question->question_text }}</h4> <!-- Wyświetlanie treści pytania -->

                                <!-- Pytania zamknięte (ABCD) -->
                                @if (is_null($question->expected_code))
                                    <div>
                                        <label>
                                            <input type="radio" name="answers[{{ $question->id }}]" value="A">
                                            A: {{ $question->option_a }}
                                        </label><br>
                                        <label>
                                            <input type="radio" name="answers[{{ $question->id }}]" value="B">
                                            B: {{ $question->option_b }}
                                        </label><br>
                                        <label>
                                            <input type="radio" name="answers[{{ $question->id }}]" value="C">
                                            C: {{ $question->option_c }}
                                        </label><br>
                                        <label>
                                            <input type="radio" name="answers[{{ $question->id }}]" value="D">
                                            D: {{ $question->option_d }}
                                        </label>
                                    </div>

                                <!-- Pytania otwarte (kodowanie) -->
                                @else
                                    <!-- Pole tekstowe, które automatycznie się rozszerza -->
                                    <textarea name="answers[{{ $question->id }}]" rows="3" class="w-full auto-expand shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Wpisz odpowiedź (kod tutaj)"></textarea>
                                @endif
                            </div>
                        @endforeach

                        <!-- Przycisk do zatwierdzenia odpowiedzi -->
                        <button type="submit" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">Zatwierdź odpowiedzi</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Funkcja do automatycznego rozszerzania pola tekstowego w zależności od ilości tekstu
        document.addEventListener('input', function (event) {
            if (event.target.classList.contains('auto-expand')) {
                event.target.style.height = 'auto';
                event.target.style.height = (event.target.scrollHeight) + 'px';
            }
        });
    </script>
</x-app-layout>
