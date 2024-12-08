<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Podejścia użytkowników do quizu') }}
        </h2>
    </x-slot>

    <!-- Dodaj CodeMirror CSS i JS -->
    <head>
    <!-- Dodaj CodeMirror CSS i JS -->
    <!-- Style CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
    <!-- Motyw CodeMirror (np. monokai) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
    <!-- Biblioteka CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>

    <!-- Tryby języków dla CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>

    <!-- Dodaj TinyMCE -->
    <script src="{{ asset('js/tinymce/tinymce.min.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>

    <style>
        .collapsible {
            display: none;
        }
        .collapsible.expanded {
            display: block;
        }

        .selected-answer {
            background-color: #c6f6d5;
            border: 2px solid #38a169;
        }

        .correct-answer-only {
            background-color: #e6fffa;
            border: 2px dashed #2c7a7b;
        }

        .incorrect-answer {
            background-color: #fed7d7;
            border: 2px solid #e53e3e;
        }

        .points-input {
            width: 60px;
            padding: 2px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
        }

        .points-display {
            font-weight: bold;
        }

        .chart-container {
            margin-top: 30px;
            padding: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .attempt-summary {
            cursor: pointer;
        }

        .version-header {
            cursor: pointer;
            padding: 10px;
            margin-bottom: 10px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .version-header:hover {
            background: #e2e2e2;
        }

        .version-details {
            margin-bottom: 20px;
        }

        .question-block {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .question-block h5 {
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .answer-item {
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 3px;
        }

        .answer-item.correct::after {
            content: " (Poprawna)";
            color: #38a169;
            font-weight: bold;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Quiz: {!! $quiz->title !!}</h1>

                    @php
                        $allAttempts = collect($groupedAttemptsByVersion)->flatten();
                    @endphp

                    @if($allAttempts->isEmpty())
                        <p class="text-gray-700">Brak podejść do tego quizu przez użytkowników.</p>
                    @else
                        @if(session('success'))
                            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">
                                {{ session('success') }}
                            </div>
                        @endif

                        <!-- Lista wersji quizu -->
                        @foreach($quizVersions as $quizVersion)
                            @php
                                $versionId = $quizVersion->id;
                                $attempts = $groupedAttemptsByVersion[$versionId] ?? collect();
                                $versionQuestions = $quizVersion->versionedQuestions()->with('answers')->get();
                            @endphp

                            <div class="version-header" onclick="toggleVersionDetails('version-details-{{ $versionId }}')">
                                <h2 class="text-lg font-bold mb-1 inline-block">Wersja Quizu: {{ $quizVersion->version_number }}</h2>
                                <p class="inline-block ml-4 text-sm text-gray-700">
                                    Utworzono: {{ $quizVersion->created_at }}
                                    @if($quizVersion->has_passing_criteria)
                                        | Kryteria zdawalności: 
                                        @if($quizVersion->passing_score)
                                            min. {{ $quizVersion->passing_score }} pkt
                                        @elseif($quizVersion->passing_percentage)
                                            min. {{ $quizVersion->passing_percentage }}%
                                        @endif
                                    @else
                                        | Brak kryteriów zdawalności
                                    @endif
                                </p>
                            </div>

                            <div id="version-details-{{ $versionId }}" class="version-details collapsible">
                                <h3 class="text-xl font-semibold text-gray-800 mb-4">Pytania w Wersji {{ $quizVersion->version_number }}</h3>
                                @foreach($versionQuestions as $vQuestion)
                                    <div class="question-block">
                                        <h5 class="font-semibold">{!! $vQuestion->question_text !!}</h5>
                                        @if($vQuestion->type === 'open')
                                            @php
                                                $expected = $vQuestion->answers->first();
                                                $expectedCode = $expected ? $expected->expected_code : '';
                                            @endphp
                                            <p><strong>Oczekiwany kod:</strong></p>
                                            @if($expectedCode)
                                                <div class="code-output-container" data-code="{!! $expectedCode !!}"></div>
                                            @else
                                                <p>Brak oczekiwanego kodu</p>
                                            @endif
                                        @else
                                            <p><strong>Odpowiedzi:</strong></p>
                                            @foreach($vQuestion->answers as $vAnswer)
                                                <div class="answer-item {{ $vAnswer->is_correct ? 'correct' : '' }}">
                                                    {!! $vAnswer->text !!}
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endforeach

                                @if($attempts->isEmpty())
                                    <p class="text-gray-700 mt-4">Brak podejść dla tej wersji.</p>
                                @else
                                    <div class="chart-container">
                                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Analiza wyników dla wersji {{ $quizVersion->version_number }}</h3>
                                        <canvas id="scoresHistogramChart_{{ $versionId }}" width="400" height="200"></canvas>
                                        <canvas id="averageScorePerQuestionChart_{{ $versionId }}" width="400" height="200"></canvas>
                                        <canvas id="passFailPieChart_{{ $versionId }}" width="400" height="200"></canvas>
                                        <canvas id="durationChart_{{ $versionId }}" width="400" height="200"></canvas>
                                    </div>

                                    @foreach($attempts as $attempt)
                                        @if ($attempt && $attempt->quizVersion && $attempt->user)
                                            @php
                                                $questions = $quizVersion->versionedQuestions()->with('answers')->get();
                                                $totalPossiblePoints = 0;
                                                foreach ($questions as $question) {
                                                    if ($question->type === 'multiple_choice' && $question->points_type === 'partial') {
                                                        $totalPossiblePoints += $question->answers->where('is_correct', true)->count() * $question->points;
                                                    } else {
                                                        $totalPossiblePoints += $question->points;
                                                    }
                                                }
                                                $userAnswers = $groupedUserAnswers->get($attempt->id, collect())->keyBy('versioned_question_id');

                                                if ($attempt->started_at && $attempt->ended_at) {
                                                    $durationInSeconds = $attempt->ended_at->timestamp - $attempt->started_at->timestamp;
                                                    $durationInSeconds = max($durationInSeconds, 0);
                                                    // Formatowanie czasu jako HH:MM:SS
                                                    $durationFormatted = gmdate('H:i:s', $durationInSeconds);
                                                } else {
                                                    $durationFormatted = 'Brak danych';
                                                }

                                                $scorePercentage = ($totalPossiblePoints > 0) ? ($attempt->score / $totalPossiblePoints) * 100 : 0;

                                                $passed = false;
                                                if ($quizVersion->has_passing_criteria) {
                                                    if ($quizVersion->passing_score && $attempt->score >= $quizVersion->passing_score) {
                                                        $passed = true;
                                                    } elseif ($quizVersion->passing_percentage && $scorePercentage >= $quizVersion->passing_percentage) {
                                                        $passed = true;
                                                    }
                                                }
                                            @endphp

                                            <form action="{{ route('quiz.update_scores', ['quiz' => $quiz->id]) }}" method="POST" onsubmit="return handleFormSubmit(event)">
                                                @csrf
                                                @method('POST')
                                                <input type="hidden" name="attempt_id" value="{{ $attempt->id }}">
                                                <div class="attempt-summary mb-4 p-4 border border-gray-300 rounded" onclick="toggleDetails('details-{{ $attempt->id }}')">
                                                    <h4 class="text-lg font-bold mb-2">
                                                        Podejście nr {{ $attempt->attempt_number }} - Użytkownik: {{ $attempt->user->email }} ({{ $attempt->created_at }})
                                                    </h4>
                                                    <p class="font-bold text-xl">Zdobyte punkty: {{ $attempt->score }} / {{ $totalPossiblePoints }} ({{ number_format($scorePercentage, 2) }}%)</p>
                                                    <p>
                                                        <strong>Status zdawalności:</strong>
                                                        @if ($passed)
                                                            <span class="text-green-600 font-bold">Zdane</span>
                                                        @else
                                                            <span class="text-red-600 font-bold">Nie zdane</span>
                                                        @endif
                                                    </p>
                                                    <p><strong>Czas trwania podejścia:</strong> {{ $durationFormatted }}</p>
                                                </div>

                                                <!-- Szczegóły podejścia użytkownika, ukryte domyślnie -->
                                                <div id="details-{{ $attempt->id }}" class="attempt-details mb-6 p-4 border border-gray-300 rounded collapsible">
                                                    @foreach($questions as $question)
                                                        @php
                                                            $userAnswer = $userAnswers->get($question->id);
                                                            $possiblePoints = ($question->type === 'multiple_choice' && $question->points_type === 'partial') ?
                                                                $question->answers->where('is_correct', true)->count() * $question->points : $question->points;
                                                            $currentScore = $userAnswer->score ?? 0;

                                                            $selectedAnswerIds = $userAnswer ? explode(',', $userAnswer->selected_answers) : [];
                                                            $selectedAnswerId = $userAnswer ? $userAnswer->versioned_answer_id : null;
                                                        @endphp

                                                        <div class="question mb-4">
                                                            <h5 class="font-semibold">{!! $question->question_text !!}</h5>

                                                            @if($question->type === 'open')
                                                                <label class="block font-bold mb-2">Odpowiedź użytkownika:</label>
                                                                @if($userAnswer && !empty($userAnswer->open_answer))
                                                                    <div class="code-output-container" data-code="{!! $userAnswer->open_answer !!}" data-question-id="{{ $question->id }}" data-attempt-id="{{ $attempt->id }}"></div>
                                                                @else
                                                                    <p>Brak odpowiedzi</p>
                                                                @endif
                                                            @elseif($question->type === 'multiple_choice' || $question->type === 'single_choice')
                                                                <p>Odpowiedzi:</p>
                                                                @foreach($question->answers as $answer)
                                                                @php
                                                                    $isSelected = ($question->type === 'multiple_choice') ? in_array($answer->id, $selectedAnswerIds) : ($answer->id == $selectedAnswerId);
                                                                    $isCorrect = $answer->is_correct;
                                                                @endphp
                                                                <div class="p-2 mb-1 
                                                                    {{ $isSelected && $isCorrect ? 'selected-answer' : '' }}
                                                                    {{ !$isCorrect && $isSelected ? 'incorrect-answer' : '' }}
                                                                    {{ !$isSelected && $isCorrect ? 'correct-answer-only' : '' }}">
                                                                    {!! $answer->text !!}
                                                                    @if ($isSelected)
                                                                        <span class="font-bold"> (Wybrano)</span>
                                                                    @endif
                                                                    @if ($isCorrect)
                                                                        <span class="font-bold text-green-500"> (Poprawna)</span>
                                                                    @endif
                                                                </div>
                                                                @endforeach
                                                            @endif

                                                            <p class="mt-2">
                                                                <strong>Punkty za to pytanie:</strong> <span class="points-display">{{ $currentScore }} / {{ $possiblePoints }}</span>
                                                            </p>

                                                            @if($userAnswer)
                                                                <label class="block mt-2"><strong>Zmień punkty:</strong></label>
                                                                <input type="number" name="scores[{{ $userAnswer->id }}]" value="{{ $currentScore }}" min="0" max="{{ $possiblePoints }}" class="points-input">
                                                                / {{ $possiblePoints }}
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                    <button type="submit" class="mt-4 p-2 bg-blue-600 text-white rounded">Zaktualizuj Punkty</button>
                                                </div>
                                            </form>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript do inicjalizacji CodeMirror i Chart.js -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.toggleDetails = function (detailsId) {
                const details = document.getElementById(detailsId);
                if (details) {
                    details.classList.toggle('expanded');
                    if (details.classList.contains('expanded')) {
                        initializeCodeMirrors(details);
                    }
                }
            }

            window.toggleVersionDetails = function (detailsId) {
                const details = document.getElementById(detailsId);
                if (details) {
                    details.classList.toggle('expanded');
                    if (details.classList.contains('expanded')) {
                        initializeCodeMirrors(details);
                    }
                }
            }

            function initializeCodeMirrors(parentElement) {
                const codeContainers = parentElement.querySelectorAll('.code-output-container');
                if (typeof CodeMirror !== 'undefined') {
                    codeContainers.forEach(function (container) {
                        if (!container.classList.contains('initialized')) {
                            const codeContent = container.getAttribute('data-code');
                            const textarea = document.createElement('textarea');
                            textarea.value = codeContent;
                            container.appendChild(textarea);
                            CodeMirror.fromTextArea(textarea, {
                                lineNumbers: true,
                                mode: "text/x-php",
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

            window.handleFormSubmit = function(event) {
                event.preventDefault();
                event.target.submit();
            }

            // Inicjalizacja wykresów Chart.js dla każdej wersji quizu
            @foreach($quizVersions as $quizVersion)
                @php
                    $versionId = $quizVersion->id;
                    $attemptData = ($groupedAttemptsByVersion[$versionId] ?? collect())->values();
                    $attemptsJson = $attemptData->toJson();
                    $passFailData = $passingDataByVersion[$versionId] ?? ['passed' => 0, 'failed' => 0];
                    $averageData = $averageScorePerQuestionDataByVersion[$versionId] ?? [];
                @endphp

                const attemptData_{{ $versionId }} = {!! $attemptsJson !!};

                const scores_{{ $versionId }} = attemptData_{{ $versionId }}.map(attempt => attempt.score);
                const emails_{{ $versionId }} = attemptData_{{ $versionId }}.map(attempt => attempt.user.email);
                const durations_{{ $versionId }} = attemptData_{{ $versionId }}.map(attempt => {
                    if (attempt.ended_at && attempt.started_at) {
                        const durationInSeconds = (new Date(attempt.ended_at).getTime() - new Date(attempt.started_at).getTime()) / 1000;
                        // Zamiast zaokrąglać do minut w formie dziesiętnej, pokazujemy tylko dane dla wykresu. 
                        // Możesz też sformatować czas przed wyświetleniem, jeśli wykres ma wyświetlać dane czasu w formacie HH:MM:SS, 
                        // należałoby wprowadzić inną logikę. Na razie zostawiamy w minutach całkowitych.
                        return Math.round(durationInSeconds / 60);
                    }
                    return 0;
                });

                const passFailData_{{ $versionId }} = @json($passFailData);
                const averageScorePerQuestionData_{{ $versionId }} = @json($averageData);

                function initChart(chartId, config) {
                    const canvas = document.getElementById(chartId);
                    if (canvas) {
                        new Chart(canvas.getContext('2d'), config);
                    }
                }

                // Wykres histogramu wyników
                initChart('scoresHistogramChart_{{ $versionId }}', {
                    type: 'bar',
                    data: {
                        labels: emails_{{ $versionId }},
                        datasets: [{
                            label: 'Wyniki użytkowników',
                            data: scores_{{ $versionId }},
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Wykres średniego wyniku na pytanie
                initChart('averageScorePerQuestionChart_{{ $versionId }}', {
                    type: 'bar',
                    data: {
                        labels: averageScorePerQuestionData_{{ $versionId }}.map((data, index) => `Pytanie ${index + 1}`),
                        datasets: [{
                            label: 'Średni wynik na pytanie',
                            data: averageScorePerQuestionData_{{ $versionId }}.map(data => data.average_score),
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Wykres wyniku zdane/niezdane
                initChart('passFailPieChart_{{ $versionId }}', {
                    type: 'pie',
                    data: {
                        labels: ['Zdane', 'Nie zdane'],
                        datasets: [{
                            data: [passFailData_{{ $versionId }}.passed, passFailData_{{ $versionId }}.failed],
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(255, 99, 132, 0.6)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });

                // Wykres czasu trwania podejścia (minuty)
                initChart('durationChart_{{ $versionId }}', {
                    type: 'line',
                    data: {
                        labels: emails_{{ $versionId }},
                        datasets: [{
                            label: 'Czas trwania podejścia (minuty)',
                            data: durations_{{ $versionId }},
                            backgroundColor: 'rgba(153, 102, 255, 0.6)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            @endforeach
        });
    </script>
</x-app-layout>
