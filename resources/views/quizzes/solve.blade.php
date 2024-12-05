<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rozwiąż Quiz') }}
        </h2>
    </x-slot>

    <!-- Token CSRF -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Dodaj CodeMirror CSS i JS -->
    <!-- Style CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
    <!-- Motyw CodeMirror (np. monokai) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
    <!-- Biblioteka CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>

    <!-- Tryby języków dla CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>

    <!-- Dodaj CodeMirror Addons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/addon/edit/closebrackets.min.js"></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Twoja zawartość tutaj -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">{!! $quiz->title !!}</h1>

                    @if($quizVersion->time_limit)
                        <div id="timer" class="text-xl font-bold text-red-600 mb-4"></div>
                    @endif

                    <form id="quiz-form" action="{{ route('quizzes.submit', $quiz->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="user_attempt_id" value="{{ $userAttemptId }}">

                        @foreach($questions as $question)
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold mb-2">{!! $question->question_text !!}</h3>

                                @if($question->type == 'open')
                                    <!-- Pytanie otwarte z polem edytora kodu CodeMirror -->
                                    <textarea id="question_{{ $question->id }}_open_answer" name="questions[{{ $question->id }}][open_answer]" class="code-input w-full p-2 border border-gray-300 rounded-md"></textarea>
                                @else
                                    <!-- Pytania zamknięte -->
                                    @foreach($question->answers as $answer)
                                        <div class="flex items-center mb-2">
                                            @if($question->type == 'single_choice')
                                                <input type="radio" name="questions[{{ $question->id }}][answers]" value="{{ $answer->id }}" class="mr-2">
                                            @else
                                                <input type="checkbox" name="questions[{{ $question->id }}][answers][]" value="{{ $answer->id }}" class="mr-2">
                                            @endif
                                            <label>{!! $answer->text !!}</label>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach

                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Zapisz odpowiedzi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript do inicjalizacji CodeMirror -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Znajdź wszystkie pola tekstowe dla pytań otwartych i zainicjalizuj CodeMirror
            document.querySelectorAll('.code-input').forEach(function (textarea) {
                CodeMirror.fromTextArea(textarea, {
                    lineNumbers: true,
                    mode: { name: "application/x-httpd-php", startOpen: true },
                    theme: "monokai",
                    lineWrapping: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                });
            });

            @if($quizVersion->time_limit)
                var timeLimit = {{ $quizVersion->time_limit }} * 60; // Czas w sekundach
                var timerElement = document.getElementById('timer');

                function updateTimer() {
                    var minutes = Math.floor(timeLimit / 60);
                    var seconds = timeLimit % 60;
                    timerElement.textContent = 'Pozostały czas: ' + minutes + ' min ' + (seconds < 10 ? '0' : '') + seconds + ' sek';

                    if (timeLimit <= 0) {
                        // Czas się skończył, automatycznie prześlij formularz
                        alert('Czas na rozwiązanie quizu minął. Twoje odpowiedzi zostaną automatycznie zapisane.');
                        document.getElementById('quiz-form').submit();
                    } else {
                        timeLimit--;
                        setTimeout(updateTimer, 1000);
                    }
                }

                updateTimer();
            @endif
        });
    </script>
</x-app-layout>
