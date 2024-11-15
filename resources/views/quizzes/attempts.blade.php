<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Twoje podejścia do quizu') }}
        </h2>
    </x-slot>

    <!-- Dodaj CodeMirror CSS i JS -->
    <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <!-- Dodaj wszystkie wymagane tryby języków -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
    </head>

    <!-- Dodaj niestandardowe style dla efektu rozwijania -->
    <style>
        .collapsible {
            display: none;
        }
        .collapsible.expanded {
            display: block;
        }

        .selected-answer {
            background-color: #c6f6d5; /* zielone tło dla wybranych odpowiedzi */
        }

        .incorrect-answer {
            background-color: #fed7d7; /* czerwone tło dla błędnych odpowiedzi */
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Twoja zawartość tutaj -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Quiz: {!! $quiz->title !!}</h1>

                    @if($userAttempts->isEmpty())
                        <p class="text-gray-700">Nie masz jeszcze podejść do tego quizu.</p>
                    @else
                        @php
                            $totalPossiblePoints = $quiz->questions->sum('points');
                        @endphp

                        <!-- Iteracja przez każde podejście użytkownika -->
                        @foreach($userAttempts as $attempt)
                            <div class="attempt mb-6 p-4 border border-gray-300 rounded">
                                <div class="attempt-summary cursor-pointer" onclick="toggleDetails('details-{{ $attempt->id }}')">
                                    <h4 class="text-lg font-bold mb-2">Podejście nr {{ $attempt->attempt_number }} ({{ $attempt->created_at }})</h4>
                                    <p class="mb-4"><strong>Punkty zdobyte:</strong> {{ $attempt->score }} / {{ $totalPossiblePoints }}</p>
                                    @if ($quiz->passing_score || $quiz->passing_percentage)
                                        <p class="mb-4">
                                            <strong>Status zdawalności:</strong>
                                            @if ($quiz->passing_score && $attempt->score >= $quiz->passing_score)
                                                <span class="text-green-600 font-bold">Zdane</span> (próg punktowy: {{ $quiz->passing_score }})
                                            @elseif ($quiz->passing_percentage)
                                                @php
                                                    $scorePercentage = ($attempt->score / $totalPossiblePoints) * 100;
                                                @endphp
                                                @if ($scorePercentage >= $quiz->passing_percentage)
                                                    <span class="text-green-600 font-bold">Zdane</span> (próg procentowy: {{ $quiz->passing_percentage }}%)
                                                @else
                                                    <span class="text-red-600 font-bold">Nie zdane</span> (próg procentowy: {{ $quiz->passing_percentage }}%)
                                                @endif
                                            @else
                                                <span class="text-red-600 font-bold">Nie zdane</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <div id="details-{{ $attempt->id }}" class="attempt-details collapsible">
                                    @php
                                        $userAnswers = $groupedUserAnswers->get($attempt->id, collect());
                                    @endphp

                                    <!-- Iteracja przez każde pytanie w quizie -->
                                    @foreach($quiz->questions as $question)
                                        @php
                                            $userAnswer = $userAnswers->firstWhere('question_id', $question->id);
                                            $questionScore = 0;
                                        @endphp

                                        <div class="question mb-4">
                                            <h5 class="font-semibold">{!! $question->question_text !!} (maksymalnie {{ $question->points }} pkt)</h5>

                                            @if($question->type === 'open')
                                                <label class="block font-bold mb-2">Twoja odpowiedź:</label>
                                                @if($userAnswer && !empty($userAnswer->open_answer))
                                                    <!-- Zapisz odpowiedź w data-atrybucie bez kodowania encji -->
                                                    <div class="code-output-container" data-code="{!! $userAnswer->open_answer !!}" data-question-id="{{ $question->id }}" data-attempt-id="{{ $attempt->id }}"></div>
                                                    @if($userAnswer->is_correct)
                                                        @php
                                                            $questionScore = $question->points;
                                                        @endphp
                                                        <span class="text-green-500 font-bold">(Poprawna odpowiedź!)</span>
                                                    @else
                                                        <span class="text-red-500 font-bold">(Błędna odpowiedź)</span>
                                                    @endif
                                                @else
                                                    <p>Brak odpowiedzi</p>
                                                @endif
                                            @else
                                                <p>Odpowiedzi:</p>
                                                <!-- Iteracja przez wszystkie możliwe odpowiedzi -->
                                                @foreach($question->answers as $answer)
                                                    @php
                                                        $isSelected = $userAnswer && $userAnswer->answer_id == $answer->id;
                                                    @endphp
                                                    <div class="p-2 mb-1 {{ $isSelected ? 'selected-answer' : '' }} {{ !$answer->is_correct && $isSelected ? 'incorrect-answer' : '' }}">
                                                        {!! $answer->text !!}
                                                        @if ($isSelected)
                                                            <span class="font-bold"> (Wybrano)</span>
                                                        @endif
                                                        @if ($answer->is_correct)
                                                            <span class="font-bold text-green-500"> (Poprawna)</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif

                                            <!-- Wyświetlenie punktów uzyskanych za pytanie -->
                                            <p class="mt-2"><strong>Punkty za to pytanie:</strong> {{ $questionScore }} / {{ $question->points }}</p>
                                        </div>
                                    @endforeach

                                </div>
                            </div>
                        @endforeach
                    @endif

                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript do inicjalizacji CodeMirror -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.toggleDetails = function (id) {
                const details = document.getElementById(id);
                if (details.classList.contains('expanded')) {
                    details.classList.remove('expanded');
                } else {
                    details.classList.add('expanded');
                    // Generuj pola kodu po rozwinięciu
                    initializeCodeMirrors(details);
                }
            }

            function initializeCodeMirrors(parentElement) {
                const codeContainers = parentElement.querySelectorAll('.code-output-container');
                if (typeof CodeMirror !== 'undefined') {
                    codeContainers.forEach(function (container) {
                        if (!container.classList.contains('initialized')) {
                            const codeContent = container.getAttribute('data-code');
                            const questionId = container.getAttribute('data-question-id');
                            const attemptId = container.getAttribute('data-attempt-id');
                            const textarea = document.createElement('textarea');
                            textarea.value = codeContent;
                            textarea.id = `code_output_${questionId}_${attemptId}`;
                            container.appendChild(textarea);
                            // Inicjalizuj CodeMirror
                            CodeMirror.fromTextArea(textarea, {
                                lineNumbers: true,
                                mode: "php",
                                readOnly: true,
                                theme: 'monokai',
                                tabSize: 2
                            });
                            container.classList.add('initialized');
                        }
                    });
                } else {
                    console.error('CodeMirror nie jest zdefiniowany. Upewnij się, że biblioteka została poprawnie załadowana.');
                }
            }
        });
    </script>
</x-app-layout>
