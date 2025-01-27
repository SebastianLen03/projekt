{{-- resources/views/quizzes/show-version.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Podgląd wersji: {{ $version->version_name ?? 'bez nazwy' }}
        </h2>
    </x-slot>

    <!-- Chart.js (dla wykresów) i CodeMirror (dla kodu w pytaniach otwartych) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>
    </head>

    <style>
        .collapsible { display: none; }
        .collapsible.expanded { display: block; }
        .selected-answer { background-color: #c6f6d5; }
        .incorrect-answer { background-color: #fed7d7; }
        .CodeMirror { line-height: 1.5; }
    </style>

    <div class="py-12 max-w-7xl mx-auto">
        <!-- INFORMACJE O QUIZIE I WERSJI -->
        <div class="bg-white p-6 mb-6 shadow-sm sm:rounded-lg">
            <h1 class="text-2xl font-semibold mb-4">Quiz: {!! $quiz->title !!}</h1>
            <p class="mb-2"><strong>Nr wersji:</strong> {{ $version->version_number }}</p>
            <p class="mb-2"><strong>Nazwa wersji:</strong> {{ $version->version_name ?? '(brak)' }}</p>
            <p class="mb-2">
                <strong>Aktywna?</strong>
                @if($version->is_active)
                    <span class="text-green-600 font-bold">TAK</span>
                @else
                    <span class="text-gray-600">nie</span>
                @endif
            </p>
            @if($version->has_passing_criteria)
                <p class="mb-2">
                    <strong>Kryterium zdawalności:</strong>
                    @if($version->passing_score)
                        min. {{ $version->passing_score }} pkt
                    @elseif($version->passing_percentage)
                        min. {{ $version->passing_percentage }}%
                    @endif
                </p>
            @else
                <p class="mb-2">Brak kryteriów zdawalności</p>
            @endif
        </div>

        <!-- 1) PYTANIA DOSTĘPNE W TEJ WERSJI -->
        <div class="bg-white p-6 mb-6 shadow-sm sm:rounded-lg">
            <h2 class="text-xl font-semibold mb-4">Pytania dostępne w tej wersji</h2>
            @php
                $versionQuestions = $version->versionedQuestions()->with('answers')->get();
            @endphp

            @forelse($versionQuestions as $vq)
                <div class="mb-4 p-3 border rounded">
                    <h3 class="font-semibold mb-1">{!! $vq->question_text !!}</h3>
                    <p class="text-sm mb-2">Punktacja: {{ $vq->points }}</p>

                    @if($vq->type === 'open')
                        @php
                            // Poprawne pobranie expected_code z versioned_answers
                            $firstAnswer = $vq->answers->first(); 
                            $expectedCode = $firstAnswer ? $firstAnswer->expected_code : null;
                        @endphp
                        @if($expectedCode)
                            <p class="text-sm font-bold mb-1">Oczekiwany kod:</p>
                            <!-- Użycie code-output-container, aby CodeMirror mógł zadziałać: -->
                            <div class="code-output-container" data-code="{{ e($expectedCode) }}"></div>
                        @else
                            <p>(Pytanie otwarte, brak expected_code)</p>
                        @endif
                    @else
                        <p class="text-sm font-bold mb-1">Odpowiedzi (zaznaczone poprawne):</p>
                        @foreach($vq->answers as $ans)
                            <div class="mb-1 px-2 py-1
                                @if($ans->is_correct)
                                    bg-green-50 border-l-4 border-green-400
                                @endif">
                                {!! $ans->text !!}
                                @if($ans->is_correct)
                                    <span class="font-bold text-green-600"> (poprawna)</span>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            @empty
                <p>Brak pytań w tej wersji.</p>
            @endforelse
        </div>

        <!-- 2) STATYSTYKI (WYKRESY) -->
        @if($userAttempts->isNotEmpty())
            <div class="bg-white p-6 mb-6 shadow-sm sm:rounded-lg">
                <h2 class="text-xl font-semibold mb-4">Statystyki</h2>
                <div class="flex flex-wrap gap-6">
                    <!-- Rozkład wyników w % -->
                    <div class="w-full sm:w-1/2 xl:w-1/3">
                        <h3 class="font-bold mb-2">Rozkład wyników w %</h3>
                        <canvas id="scoreDistributionChart"></canvas>
                    </div>

                    <!-- Najczęściej popełniane błędy -->
                    <div class="w-full sm:w-1/2 xl:w-1/3">
                        <h3 class="font-bold mb-2">Najczęściej popełniane błędy</h3>
                        <canvas id="commonMistakesChart"></canvas>
                    </div>

                    <!-- Najpopularniejsze błędne odpowiedzi -->
                    <div class="w-full sm:w-1/2 xl:w-1/3">
                        <h3 class="font-bold mb-2">Najpopularniejsze błędne odpowiedzi</h3>
                        <canvas id="wrongAnswersChart"></canvas>
                    </div>

                    <!-- Ewentualne inne wykresy: Ranking użytkowników, Korelacja, itp. -->
                    @if(!empty($userRankingData))
                        <div class="w-full sm:w-1/2 xl:w-1/3">
                            <h3 class="font-bold mb-2">Ranking użytkowników</h3>
                            <canvas id="userRankingChart"></canvas>
                        </div>
                    @endif

                    @if(!empty($timeVsScoreData))
                        <div class="w-full sm:w-1/2 xl:w-1/3">
                            <h3 class="font-bold mb-2">Korelacja (czas vs wynik)</h3>
                            <canvas id="timeVsScoreChart"></canvas>
                        </div>
                    @endif

                </div>
            </div>
        @endif

        <!-- 3) PODEJŚCIA W TEJ WERSJI -->
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h2 class="text-xl font-semibold mb-4">Podejścia w tej wersji</h2>
            @if($userAttempts->isEmpty())
                <p>Brak podejść.</p>
            @else
                @php
                    $questions = $version->versionedQuestions()->with('answers')->get();
                @endphp

                @foreach($userAttempts as $attempt)
                    @php
                        $totalPossiblePoints=0;
                        foreach($questions as $q){
                               if ($q->type === 'multiple_choice' && $q->points_type === 'partial') {
                                // Załóżmy: 'points' oznacza ilość punktów za jedną poprawną odpowiedź
                                $correctCount = $q->answers->where('is_correct', true)->count();
                                $questionMaxPts = $correctCount * $q->points;
                            } else {
                                $questionMaxPts = $q->points;
                            }
                            $totalPossiblePoints += $questionMaxPts;
                        }
                        $scorePercentage=($totalPossiblePoints>0)
                            ? ($attempt->score/$totalPossiblePoints)*100
                            : 0;

                            $durationFormatted = 'Brak danych';
                            if ($attempt->started_at && $attempt->ended_at) {
                                // Zakładając, że started_at i ended_at to obiekty datetime (Carbon):
                                $sec = $attempt->ended_at->timestamp - $attempt->started_at->timestamp;
                                $sec = max($sec, 0);
                                $durationFormatted = gmdate('H:i:s', $sec);
                            }

                        // Czy zdane?
                        $passed=false;
                        if($version->has_passing_criteria){
                            if($version->passing_score && $attempt->score >= $version->passing_score) $passed=true;
                            elseif($version->passing_percentage && $scorePercentage>=$version->passing_percentage) $passed=true;
                        }

                        // Pogrupowane odpowiedzi
                        $userAnswersForAttempt = $groupedUserAnswers[$attempt->id] ?? collect();
                        $userAnswersByQuestion = $userAnswersForAttempt->keyBy('versioned_question_id');
                    @endphp

                    <div class="mb-4 p-4 border border-gray-300 rounded">
                        <div class="cursor-pointer" onclick="toggleDetails('details-{{ $attempt->id }}')">
                            <h4 class="text-lg font-bold mb-2">
                                Podejście #{{ $attempt->attempt_number }} (Użytkownik: {{ $attempt->user->name }})
                            </h4>
                            <p><strong>Data:</strong> {{ $attempt->created_at }}</p>
                            <p><strong>Wynik:</strong> {{ $attempt->score }} / {{ $totalPossiblePoints }}
                               ({{ number_format($scorePercentage,2) }}%)</p>
                            <p><strong>Czas trwania:</strong> {{ $durationFormatted }}</p>
                            @if($version->has_passing_criteria)
                                <p><strong>Status zdawalności:</strong>
                                    @if($passed)
                                        <span class="text-green-600 font-bold">Zdane</span>
                                    @else
                                        <span class="text-red-600 font-bold">Niezdane</span>
                                    @endif
                                </p>
                            @endif
                            <p class="text-blue-600 text-sm mt-2">
                                Kliknij, aby rozwinąć szczegóły odpowiedzi
                            </p>
                        </div>

                        <!-- Szczegóły pytań w danym podejściu -->
                        <div id="details-{{ $attempt->id }}" class="collapsible mt-4">
                            @foreach($questions as $question)
                                @php
                                    $ua = $userAnswersByQuestion->get($question->id);
                                    $questionScore = $ua ? $ua->score : 0;

                                    if ($question->type === 'multiple_choice' && $question->points_type === 'partial') {
                                        $correctCount = $question->answers->where('is_correct', true)->count();
                                        $questionMaxPts = $correctCount * $question->points;
                                    } else {
                                        $questionMaxPts = $question->points;
                                    }
                                @endphp
                                <div class="mb-3 p-2 border rounded">
                                <h5 class="font-semibold">{!! $question->question_text !!}</h5>
                                <p class="text-sm mb-2">
                                    Punktacja (dla tego usera): {{ $questionScore }} / {{ $questionMaxPts }}
                                </p>                                            <!-- (Opcjonalnie) FORMULARZ EDYCJI PUNKTÓW -->
                                    <form action="{{ route('quiz.updateScore', [$quiz->id, $attempt->id, $question->id]) }}"
                                          method="POST" class="mb-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="text-sm mr-2">Edytuj punktację:</label>
                                        <input type="number" name="new_score"
                                               value="{{ $questionScore }}"
                                               min="0" max="{{ $question->points }}"
                                               class="border rounded w-16 text-center mr-2" />
                                        <button type="submit" class="bg-blue-500 text-white px-2 py-1 rounded text-sm">
                                            Zapisz
                                        </button>
                                    </form>

                                    <!-- Wyświetlanie odpowiedzi usera -->
                                    @if($question->type==='open')
                                        @if($ua && $ua->open_answer)
                                            <div class="code-output-container" data-code="{!! $ua->open_answer !!}"></div>
                                        @else
                                            <p>Brak odpowiedzi</p>
                                        @endif
                                    @elseif($question->type==='multiple_choice')
                                        @php
                                            $selectedIds = $ua ? explode(',', $ua->selected_answers) : [];
                                        @endphp
                                        <p>Odpowiedzi tego użytkownika:</p>
                                        @foreach($question->answers as $ans)
                                            @php
                                                $isSelected = in_array($ans->id, $selectedIds);
                                            @endphp
                                            <div class="p-1 mb-1
                                                {{ $isSelected ? 'selected-answer' : '' }}
                                                {{ (!$ans->is_correct && $isSelected) ? 'incorrect-answer' : '' }}">
                                                {!! $ans->text !!}
                                                @if($isSelected)
                                                    <span class="font-bold">(Wybrano)</span>
                                                @endif
                                                @if($ans->is_correct)
                                                    <span class="font-bold text-green-600">(Poprawna)</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @else
                                        <!-- single_choice -->
                                        @php
                                            $selectedId = $ua ? $ua->versioned_answer_id : null;
                                        @endphp
                                        <p>Odpowiedź usera:</p>
                                        @foreach($question->answers as $ans)
                                            @php
                                                $isSelected = ($selectedId === $ans->id);
                                            @endphp
                                            <div class="p-1 mb-1
                                                {{ $isSelected ? 'selected-answer' : '' }}
                                                {{ (!$ans->is_correct && $isSelected) ? 'incorrect-answer' : '' }}">
                                                {!! $ans->text !!}
                                                @if($isSelected)
                                                    <span class="font-bold">(Wybrano)</span>
                                                @endif
                                                @if($ans->is_correct)
                                                    <span class="font-bold text-green-600">(Poprawna)</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <!-- Skrypty: toggling, CodeMirror, wykresy Chart.js -->
    <script>
        // Funkcja do rozwijania sekcji "collapsible"
        function toggleDetails(id) {
            const el = document.getElementById(id);
            el.classList.toggle('expanded');
            if (el.classList.contains('expanded')) {
                initializeCodeMirrors(el);
                setTimeout(() => {
                    el.querySelectorAll('.CodeMirror').forEach(cm => cm.CodeMirror.refresh());
                }, 100);
            }
        }

        // CodeMirror
        function initializeCodeMirrors(parentEl) {
            const containers = parentEl.querySelectorAll('.code-output-container');
            if (typeof CodeMirror !== 'undefined') {
                containers.forEach(c => {
                    if (!c.classList.contains('initialized')) {
                        const codeContent = c.getAttribute('data-code') || '';
                        const textarea = document.createElement('textarea');
                        textarea.value = codeContent;
                        c.appendChild(textarea);
                        const editor = CodeMirror.fromTextArea(textarea, {
                            lineNumbers: true,
                            mode: { name: 'php', startOpen: true },
                            readOnly: true,
                            theme: 'monokai',
                            tabSize: 2
                        });
                        editor.setSize("100%", null);
                        c.classList.add('initialized');
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function(){
            // Uruchom CodeMirror dla sekcji "Pytania dostępne w tej wersji" od razu:
            initializeCodeMirrors(document);

            // *** WYKRES: Rozkład wyników w % ***
            let scoreDistData = @json($scoreDistData ?? []);
            let distLabels = Object.keys(scoreDistData);
            let distValues = Object.values(scoreDistData);

            new Chart(document.getElementById('scoreDistributionChart'), {
                type: 'bar',
                data: {
                    labels: distLabels,
                    datasets: [{
                        label: 'Ilość podejść',
                        data: distValues,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)'
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } }
                }
            });

            // *** WYKRES: Najczęściej popełniane błędy ***
            let cmData = @json($commonMistakesData ?? []);
            let cmLabels = cmData.map(o => o.question_label);
            let cmValues = cmData.map(o => Math.round(o.error_rate * 100));

            new Chart(document.getElementById('commonMistakesChart'), {
                type: 'bar',
                data: {
                    labels: cmLabels,
                    datasets: [{
                        label: '% błędnych odpowiedzi',
                        data: cmValues,
                        backgroundColor: 'rgba(54,162,235,0.6)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, max: 100 }
                    }
                }
            });

            // *** WYKRES: Najpopularniejsze błędne odpowiedzi (w %) ***
            let wrongData = @json($wrongAnswersData ?? []);
            let waLabels = wrongData.map(o => o.question_label);
            let waValues = wrongData.map(o => Math.round(o.percent_wrong));

            new Chart(document.getElementById('wrongAnswersChart'), {
                type: 'bar',
                data: {
                    labels: waLabels,
                    datasets: [{
                        label: '% błędnych odp',
                        data: waValues,
                        backgroundColor: 'rgba(153,102,255,0.6)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true, max: 100 }
                    }
                }
            });

            // *** WYKRES: Ranking użytkowników (jeśli istnieje $userRankingData) ***
            @if(!empty($userRankingData))
                let urData = @json($userRankingData);
                let urLabels = urData.map(u => u.user_name);
                let urValues = urData.map(u => u.total_score);

                new Chart(document.getElementById('userRankingChart'), {
                    type: 'bar',
                    data: {
                        labels: urLabels,
                        datasets: [{
                            label: 'Zdobyte punkty',
                            data: urValues,
                            backgroundColor: 'rgba(75,192,192,0.6)'
                        }]
                    },
                    options: {
                        scales: { y: { beginAtZero: true } }
                    }
                });
            @endif

            // *** WYKRES: Korelacja (czas vs wynik) (jeśli istnieje $timeVsScoreData) ***
            @if(!empty($timeVsScoreData))
                let tvData = @json($timeVsScoreData);
                let scatterPoints = tvData.map(obj => ({ x: obj.duration, y: obj.score }));

                new Chart(document.getElementById('timeVsScoreChart'), {
                    type: 'scatter',
                    data: {
                        datasets: [{
                            label: 'czas(s) vs wynik',
                            data: scatterPoints,
                            backgroundColor: 'rgba(255,159,64,0.6)'
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'linear',
                                position: 'bottom',
                                title: { display: true, text: 'Czas (sekundy)' }
                            },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Wynik (pkt)' }
                            }
                        }
                    }
                });
            @endif
        });
    </script>
</x-app-layout>
