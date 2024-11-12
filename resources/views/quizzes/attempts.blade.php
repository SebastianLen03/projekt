<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Twoje podejścia do quizu') }}
        </h2>
    </x-slot>

    <!-- Przenieś załadowanie bibliotek CodeMirror do nagłówka -->
    <head>
        <!-- Dodaj CodeMirror CSS i JS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
    </head>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Quiz: {!! $quiz->title !!}</h1>

                    <!-- Sprawdzamy, czy użytkownik ma podejścia do quizu -->
                    @if($userAttempts->isEmpty())
                        <p class="text-gray-700">Nie masz jeszcze podejść do tego quizu.</p>
                    @else
                        <!-- Iteracja przez każde podejście użytkownika -->
                        @foreach($userAttempts as $attempt)
                            <div class="attempt mb-6 p-4 border border-gray-300 rounded">
                                <h4 class="text-lg font-bold mb-4">Podejście nr {{ $attempt->attempt_number }} ({{ $attempt->created_at }})</h4>

                                @php
                                    $userAnswers = $groupedUserAnswers->get($attempt->id, collect());
                                @endphp

                                <!-- Iteracja przez każde pytanie w quizie -->
                                @foreach($quiz->questions as $question)
                                    @php
                                        $userAnswer = $userAnswers->firstWhere('question_id', $question->id);
                                    @endphp

                                    <div class="question mb-4">
                                        <h5 class="font-semibold">{!! $question->question_text !!}</h5> <!-- Renderowanie pytania jako HTML -->

                                        <!-- Sprawdzamy, czy pytanie jest typu open -->
                                        @if($question->type === 'open')
                                            <label class="block font-bold mb-2">Twoja odpowiedź:</label>
                                            @if($userAnswer && !empty($userAnswer->open_answer))
                                                <!-- Przechowujemy odpowiedź w `textarea` dla CodeMirror -->
                                                <textarea id="code_output_{{ $question->id }}" class="code-output w-full p-2 border border-gray-300 rounded">{{ $userAnswer->open_answer }}</textarea>
                                            @else
                                                <p>Brak odpowiedzi</p>
                                            @endif
                                        @else
                                            <p>Twoja odpowiedź:
                                                <!-- Sprawdzamy, czy użytkownik odpowiedział na pytanie -->
                                                @if($userAnswer && $userAnswer->answer_id)
                                                    {!! $question->answers->firstWhere('id', $userAnswer->answer_id)->text !!}
                                                @else
                                                    Brak odpowiedzi
                                                @endif
                                            </p>
                                        @endif <!-- Zamknięcie if dla pytania typu open -->
                                    </div> <!-- Zamknięcie div dla pytania -->
                                @endforeach <!-- Zamknięcie pętli foreach dla pytań w quizie -->

                            </div> <!-- Zamknięcie div dla podejścia -->
                        @endforeach <!-- Zamknięcie pętli foreach dla prób użytkownika -->
                    @endif <!-- Zamknięcie warunku if dla sprawdzenia, czy użytkownik ma podejścia -->

                </div> <!-- Zamknięcie div dla głównej zawartości quizu -->
            </div> <!-- Zamknięcie div dla karty quizu -->
        </div> <!-- Zamknięcie div dla kontenera -->
    </div> <!-- Zamknięcie div dla py-12 -->

    <!-- JavaScript do inicjalizacji CodeMirror -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Czekaj na pełne załadowanie DOM-u
            const codeMirrorAreas = document.querySelectorAll('.code-output');
            if (typeof CodeMirror !== 'undefined') {
                codeMirrorAreas.forEach(function (textarea, index) {
                    console.log(`Inicjalizacja CodeMirror dla pola #${index + 1}:`, textarea.value);
                    if (textarea.value.trim() !== "Brak odpowiedzi") {
                        CodeMirror.fromTextArea(textarea, {
                            lineNumbers: true,
                            mode: "php", // Użycie trybu php dla lepszego wsparcia kodu PHP
                            readOnly: true,
                            theme: 'monokai',
                            tabSize: 2
                        });
                    }
                });
            } else {
                console.error('CodeMirror nie jest zdefiniowany. Upewnij się, że biblioteka została poprawnie załadowana.');
            }
        });
    </script>
</x-app-layout>
