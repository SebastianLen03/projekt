{{-- resources/views/quizzes/attempts.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Twoje podejścia do quizu') }}
        </h2>
    </x-slot>

    <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
    </head>

    <style>
        .collapsible { display: none; }
        .collapsible.expanded { display: block; }
        .selected-answer { background-color: #c6f6d5; }
        .incorrect-answer { background-color: #fed7d7; }
        .CodeMirror { line-height: 1.5; }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800 mb-6">
                        Quiz: {!! $quiz->title !!}
                    </h1>

                    @if($userAttempts->isEmpty())
                        <p class="text-gray-700">Nie masz jeszcze podejść do tego quizu.</p>
                    @else
                        @foreach($userAttempts as $versionId => $attempts)
                            @php
                                $quizVersion = $attempts->first()->quizVersion;
                                $questions = $quizVersion->versionedQuestions()->with('answers')->get();
                            @endphp

                            <h2 class="text-xl font-semibold mb-4">
                                Wersja Quizu: {{ $quizVersion->version_number }}
                                @if($quizVersion->is_active)
                                    (AKTYWNA)
                                @elseif($quizVersion->is_draft)
                                    (DRAFT)
                                @else
                                    (ARCHIWALNA)
                                @endif
                            </h2>

                            @foreach($attempts as $attempt)
                                @php
                                    $userAnswers = $groupedUserAnswers->get($attempt->id, collect())->keyBy('versioned_question_id');

                                    $totalPossiblePoints = 0;
                                    foreach ($questions as $question) {
                                        if ($question->type === 'multiple_choice' && $question->points_type === 'partial') {
                                            $correctCount = $question->answers->where('is_correct', true)->count();
                                            $questionTotalPts = $correctCount * $question->points;
                                        } else {
                                            $questionTotalPts = $question->points;
                                        }
                                        $totalPossiblePoints += $questionTotalPts;
                                    }

                                    if ($attempt->started_at && $attempt->ended_at) {
                                        $durationInSeconds = $attempt->ended_at->timestamp - $attempt->started_at->timestamp;
                                        $durationInSeconds = max($durationInSeconds, 0);
                                        $durationFormatted = gmdate('H:i:s', $durationInSeconds);
                                    } else {
                                        $durationFormatted = 'Brak danych';
                                    }

                                    $scorePercentage = ($totalPossiblePoints > 0) ? ($attempt->score / $totalPossiblePoints) * 100 : 0;
                                @endphp

                                <div class="attempt mb-6 p-4 border border-gray-300 rounded">
                                    <div class="attempt-summary cursor-pointer" onclick="toggleDetails('details-{{ $attempt->id }}')">
                                        <h4 class="text-lg font-bold mb-2">Podejście nr {{ $attempt->attempt_number }} ({{ $attempt->created_at }})</h4>
                                        <p class="mb-2"><strong>Punkty zdobyte:</strong> {{ $attempt->score }} / {{ $totalPossiblePoints }}</p>
                                        <p class="mb-2"><strong>Procent zdobytych punktów:</strong> {{ number_format($scorePercentage, 2) }}%</p>
                                        @if($quizVersion->has_passing_criteria)
                                            <p class="mb-2"><strong>Wymagane do zdania:</strong>
                                                @if($quizVersion->passing_score)
                                                    {{ $quizVersion->passing_score }} pkt
                                                @elseif($quizVersion->passing_percentage)
                                                    {{ $quizVersion->passing_percentage }}%
                                                @endif
                                            </p>
                                        @endif
                                        <p><strong>Czas trwania podejścia:</strong> {{ $durationFormatted }}</p>

                                        @if ($quizVersion->has_passing_criteria)
                                            @php
                                                $passed = false;
                                                if ($quizVersion->passing_score && $attempt->score >= $quizVersion->passing_score) {
                                                    $passed = true;
                                                } elseif ($quizVersion->passing_percentage && $scorePercentage >= $quizVersion->passing_percentage) {
                                                    $passed = true;
                                                }
                                            @endphp
                                            <p class="mb-4">
                                                <strong>Status zdawalności:</strong>
                                                @if($passed)
                                                    <span class="text-green-600 font-bold">Zdane</span>
                                                @else
                                                    <span class="text-red-600 font-bold">Nie zdane</span>
                                                @endif
                                            </p>
                                        @else
                                            <p class="mb-4"><strong>Status zdawalności:</strong> Brak kryteriów zdawalności</p>
                                        @endif
                                    </div>

                                    <div id="details-{{ $attempt->id }}" class="attempt-details collapsible">
                                        @foreach($questions as $question)
                                            @php
                                                $userAnswer = $userAnswers->get($question->id);
                                                if ($question->type === 'multiple_choice' && $question->points_type === 'partial') {
                                                    $correctCount = $question->answers->where('is_correct', true)->count();
                                                    $totalQuestionPoints = $correctCount * $question->points;
                                                } else {
                                                    $totalQuestionPoints = $question->points;
                                                }
                                                $questionScore = $userAnswer ? $userAnswer->score : 0;
                                            @endphp

                                            <div class="question mb-4">
                                                <h5 class="font-semibold">{!! $question->question_text !!} ({{ $questionScore }} / {{ $totalQuestionPoints }} pkt)</h5>

                                                @if($question->type === 'open')
                                                    <label class="block font-bold mb-2">Twoja odpowiedź:</label>
                                                    @if($userAnswer && !empty($userAnswer->open_answer))
                                                        <div class="code-output-container" data-code="{!! $userAnswer->open_answer !!}"></div>
                                                        @if($userAnswer->is_correct)
                                                            <span class="text-green-500 font-bold">(Poprawna odpowiedź!)</span>
                                                        @else
                                                            <span class="text-red-500 font-bold">(Błędna odpowiedź)</span>
                                                        @endif
                                                    @else
                                                        <p>Brak odpowiedzi</p>
                                                    @endif
                                                @elseif($question->type === 'multiple_choice')
                                                    <p>Odpowiedzi:</p>
                                                    @php
                                                        $selectedAnswerIds = $userAnswer ? explode(',', $userAnswer->selected_answers) : [];
                                                    @endphp
                                                    @foreach($question->answers as $answer)
                                                        @php
                                                            $isSelected = in_array($answer->id, $selectedAnswerIds);
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
                                                @else
                                                    <!-- Pytanie jednokrotnego wyboru -->
                                                    <p>Odpowiedzi:</p>
                                                    @php
                                                        $selectedAnswerId = $userAnswer ? $userAnswer->versioned_answer_id : null;
                                                    @endphp
                                                    @foreach($question->answers as $answer)
                                                        @php
                                                            $isSelected = $selectedAnswerId == $answer->id;
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

                                                <p class="mt-2">
                                                    <strong>Punkty za to pytanie:</strong> {{ $questionScore }} / {{ $totalQuestionPoints }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.toggleDetails = function (id) {
                const details = document.getElementById(id);
                details.classList.toggle('expanded');
                if (details.classList.contains('expanded')) {
                    initializeCodeMirrors(details);
                    setTimeout(() => {
                        details.querySelectorAll('.CodeMirror').forEach(cmEl => cmEl.CodeMirror.refresh());
                    }, 100);
                }
            }

            function initializeCodeMirrors(parentElement) {
                const codeContainers = parentElement.querySelectorAll('.code-output-container');
                if (typeof CodeMirror !== 'undefined') {
                    codeContainers.forEach(container => {
                        if (!container.classList.contains('initialized')) {
                            const codeContent = container.getAttribute('data-code');
                            const textarea = document.createElement('textarea');
                            textarea.value = codeContent;
                            container.appendChild(textarea);
                            const editor = CodeMirror.fromTextArea(textarea, {
                                lineNumbers: true,
                                mode: { name: 'php', startOpen: true },
                                readOnly: true,
                                theme: 'monokai',
                                tabSize: 2
                            });
                            editor.setSize("100%", null);
                            container.classList.add('initialized');
                        }
                    });
                }
            }
        });
    </script>
</x-app-layout>
