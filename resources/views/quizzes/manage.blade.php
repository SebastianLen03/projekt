{{-- resources/views/quizzes/manage.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edytuj Quiz') }}
        </h2>
    </x-slot>

    <!-- Token CSRF -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- CodeMirror CSS i JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/theme/monokai.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/codemirror.min.js"></script>
    <!-- Tryby CodeMirror -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>

    <!-- TinyMCE -->
    <script src="{{ asset('js/tinymce/tinymce.min.js') }}"></script>

    <!-- Przekazanie zmiennych do JavaScript -->
    <script>
        window.csrfToken = "{{ csrf_token() }}";
        window.quizId = "{{ $quiz->id }}";
    </script>

    <!-- Główny plik manage.js (inicjalizacja TinyMCE i CodeMirror) -->
    <script src="{{ asset('js/manage.js') }}" defer></script>

    <div class="py-12 max-w-7xl mx-auto">
        @if(session('message'))
            <div class="mb-4 text-green-600">
                {{ session('message') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 text-red-600">
                <ul class="list-disc ml-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- 1. Sekcja z listą pełnych wersji (is_draft=0) na samej górze -->
        @php
            $versions = $quiz->quizVersions()
                ->where('is_draft', false)
                ->orderBy('version_number','asc')
                ->get();
        @endphp

        <div class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h3 class="text-xl font-semibold mb-4">Wersje Quizu (poza draftem)</h3>

            @if($versions->count())
                <table class="min-w-full table-auto border-collapse">
                    <thead>
                    <tr class="bg-gray-200">
                        <th class="px-4 py-2">Nr</th>
                        <th class="px-4 py-2">Nazwa Wersji</th>
                        <th class="px-4 py-2">Aktywna?</th>
                        <th class="px-4 py-2">Akcje</th>
                        <th class="px-4 py-2">Reset Podejść</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($versions as $version)
                        <tr class="border-b">
                            <td class="px-4 py-2">{{ $version->version_number }}</td>

                            <!-- Zmiana nazwy wersji -->
                            <td class="px-4 py-2">
                                <form method="POST" action="{{ route('quizzes.renameVersion', [$quiz->id, $version->id]) }}">
                                    @csrf
                                    <input type="text" name="version_name"
                                           value="{{ $version->version_name }}"
                                           class="border border-gray-300 rounded p-1 text-sm">
                                    <button type="submit" class="ml-2 text-blue-600 underline text-sm">
                                        Zmień
                                    </button>
                                </form>
                            </td>

                            <td class="px-4 py-2">
                                @if($version->is_active)
                                    <span class="text-green-600 font-bold">TAK</span>
                                @else
                                    <span class="text-gray-600">nie</span>
                                @endif
                            </td>

                            <!-- Podgląd, aktywacja/deaktywacja, usunięcie wersji -->
                            <td class="px-4 py-2 flex space-x-2">
                                <!-- Podgląd wersji -->
                                <a href="{{ route('quizzes.showVersion', [$quiz->id, $version->id]) }}"
                                   class="bg-gray-500 hover:bg-gray-700 text-white py-1 px-2 rounded text-sm">
                                    Podgląd
                                </a>

                                @if(!$version->is_active)
                                    <!-- Aktywuj -->
                                    <form method="POST"
                                          action="{{ route('quizzes.activateVersion', [$quiz->id, $version->id]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="bg-green-500 hover:bg-green-700 text-white py-1 px-2 rounded text-sm">
                                            Aktywuj
                                        </button>
                                    </form>

                                    <!-- Usuń wersję (tylko nieaktywną) -->
                                    <form method="POST"
                                          action="{{ route('quizzes.deleteVersion', [$quiz->id, $version->id]) }}"
                                          onsubmit="return confirm('Na pewno usunąć tę wersję?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm">
                                            Usuń
                                        </button>
                                    </form>
                                @else
                                    <!-- Deaktywuj -->
                                    <form method="POST"
                                          action="{{ route('quizzes.deactivateVersion', [$quiz->id, $version->id]) }}">
                                        @csrf
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm">
                                            Deaktywuj
                                        </button>
                                    </form>
                                @endif
                            </td>

                            <!-- Reset podejść -->
                            <td class="px-4 py-2">
                                <!-- 1) Reset ALL attempts w tej wersji -->
                                <form method="POST"
                                      action="{{ route('quizzes.resetVersionAttempts', [$quiz->id, $version->id]) }}"
                                      onsubmit="return confirm('Na pewno zresetować wszystkie podejścia w tej wersji?')">
                                    @csrf
                                    <button type="submit"
                                            class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm">
                                        Reset Wszystkich
                                    </button>
                                </form>

                                <!-- 2) Reset attempts wybranego użytkownika -->
                                @php
                                    // Wyciągnięcie userów, którzy mieli attempt w TEJ wersji:
                                    $attemptsInVersion = $quiz->userAttempts->where('quiz_version_id', $version->id);
                                    $usersInVersion = $attemptsInVersion->unique('user_id')->pluck('user');
                                @endphp

                                @if($usersInVersion->count())
                                    <form method="POST"
                                          action="{{ route('quizzes.resetVersionAttempts', [$quiz->id, $version->id]) }}"
                                          class="mt-2">
                                        @csrf
                                        <label class="text-sm block">Reset usera:</label>
                                        <select name="user_id" class="border border-gray-300 p-1 text-sm">
                                            <option value="">-- wybierz usera --</option>
                                            @foreach($usersInVersion as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                                class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm ml-1"
                                                onclick="return confirm('Na pewno zresetować podejścia wybranego usera w tej wersji?')">
                                            Reset
                                        </button>
                                    </form>
                                @else
                                    <p class="text-gray-400 text-sm mt-2">Brak podejść w tej wersji.</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-gray-600">Brak zapisanych pełnych wersji.</p>
            @endif

            <!-- Tuż pod tabelą wersji -->
            @php
            $anyActiveVersion = $versions->where('is_active', true)->count() > 0;
            @endphp

            <div class="mt-4 p-4 border border-gray-300 rounded">
            <h3 class="text-lg font-semibold mb-2">Ustawienia dostępu do quizu</h3>

            <!-- Checkbox: wielokrotne podejścia -->
            <label class="block font-bold mb-2">Czy quiz można rozwiązać wiele razy?</label>
            <div class="flex items-center mb-4">
                <input type="checkbox"
                    id="quiz-multiple-attempts"
                    name="multiple_attempts"
                    value="1"
                    {{ $quiz->multiple_attempts ? 'checked' : '' }}
                    {{ $anyActiveVersion ? 'disabled' : '' }}
                    onchange="updateMultipleAttempts(this)">
                <label for="quiz-multiple-attempts" class="ml-2">
                    Tak, quiz może być rozwiązywany wiele razy.
                </label>
            </div>

            <!-- Checkbox: publiczny quiz -->
            <label class="block font-bold mb-2">Udostępnij quiz:</label>
                <div id="group-checkboxes" class="mb-4">
                    <!-- Publiczny? (wszyscy) -->
                    <div class="flex items-center mb-2">
                        <input type="checkbox"
                            id="public_quiz"
                            name="is_public"
                            value="1"
                            {{ $quiz->is_public ? 'checked' : '' }}
                            {{ $anyActiveVersion ? 'disabled' : '' }}
                            onchange="updatePublicQuiz(this)">
                        <label for="public_quiz" class="ml-2">Wszyscy użytkownicy</label>
                    </div>

                    <!-- Grupy (tylko jeśli nie jest public i brak aktywnej wersji) -->
                    @foreach ($userGroups as $group)
                        <div class="flex items-center mb-2">
                            <input type="checkbox"
                                id="group_{{ $group->id }}"
                                name="groups[]"
                                value="{{ $group->id }}"
                                {{ in_array($group->id, $quiz->groups->pluck('id')->toArray()) && !$quiz->is_public ? 'checked' : '' }}
                                {{ $quiz->is_public || $anyActiveVersion ? 'disabled' : '' }}
                                onchange="updateGroupSelection(this, '{{ $group->id }}')">
                            <label for="group_{{ $group->id }}" class="ml-2">
                                {{ $group->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 2. Sekcja EDYCJI QUIZU (DRAFT) -->
        <div class="bg-white shadow-sm p-6 rounded-lg">
            <h3 class="text-xl font-semibold mb-4">Edycja bieżącego Draftu</h3>

            @php
                // Znajdź draft lub ostatnią finalną
                $draftVersion = $quiz->quizVersions->where('is_draft', true)->first();
                $latestVersion = $draftVersion 
                    ?: $quiz->quizVersions->where('is_draft', false)->sortByDesc('version_number')->first();
                $timeLimitValue = $latestVersion ? $latestVersion->time_limit : null;

                $pScore = $latestVersion ? $latestVersion->passing_score : null;
                $pPct   = $latestVersion ? $latestVersion->passing_percentage : null;
                $passingType = '';
                if ($pScore) {
                    $passingType = 'points';
                } elseif ($pPct) {
                    $passingType = 'percentage';
                }
            @endphp

            <!-- Nazwa quizu, limit czasu, multiple_attempts, etc. -->
            <label class="block font-bold mb-2">Nazwa Quizu:</label>
            <textarea id="quiz-name" name="title"
                      class="tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded"
            >{!! $quiz->title !!}</textarea>

            <label class="block font-bold mb-2">Czy chcesz ograniczyć czas rozwiązywania quizu?</label>
            <div class="flex items-center mb-4">
                <input type="checkbox" id="enable-time-limit" name="has_time_limit"
                       value="1" {{ $timeLimitValue ? 'checked' : '' }}
                       onchange="toggleTimeLimitField()">
                <label for="enable-time-limit" class="ml-2">Tak, chcę ograniczyć czas.</label>
            </div>

            <div id="time-limit-field" style="display: {{ $timeLimitValue ? 'block' : 'none' }};">
                <label class="block font-bold mb-2">Limit czasu (w minutach):</label>
                <input type="number" id="quiz-time-limit" name="time_limit"
                       value="{{ $timeLimitValue }}"
                       class="w-full mb-4 p-2 border border-gray-300 rounded" min="1">
            </div>

            <!-- Typ zdawalności -->
            <label class="block font-bold mb-2">Typ zdawalności:</label>
            <select id="passing-type" name="passing_type"
                    class="shadow border rounded w-full py-2 px-3 text-gray-700 mb-4"
                    onchange="togglePassingScoreFields()">
                <option value="" {{ (!$pScore && !$pPct) ? 'selected' : '' }}>Brak</option>
                <option value="points" {{ $passingType === 'points' ? 'selected' : '' }}>Punktowy</option>
                <option value="percentage" {{ $passingType === 'percentage' ? 'selected' : '' }}>Procentowy</option>
            </select>

            <div id="passing-score-field" class="mb-4" style="display: {{ $pScore ? 'block' : 'none' }};">
                <label class="block font-bold mb-2">Minimalna liczba punktów do zaliczenia:</label>
                <input type="number" id="passing-score" name="passing_score"
                       value="{{ $pScore }}"
                       class="w-full p-2 border border-gray-300 rounded" min="1">
            </div>

            <div id="passing-percentage-field" class="mb-4" style="display: {{ $pPct ? 'block' : 'none' }};">
                <label class="block font-bold mb-2">Minimalny procent do zaliczenia:</label>
                <input type="number" id="passing-percentage" name="passing_percentage"
                       value="{{ $pPct }}"
                       class="w-full p-2 border border-gray-300 rounded" min="1" max="100">
            </div>

            <!-- Przyciski zapisu draftu/finalizacji -->
            <div class="flex items-center mb-4">
                <button type="button"
                        onclick="saveQuiz()"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                    Zapisz Quiz (Draft)
                </button>

                @if($quiz->id)
                    <!-- Jeżeli quiz ma ID, możemy wywołać finalizeDraftVersion -->
                    <form method="POST" action="{{ route('quizzes.finalizeDraftVersion', $quiz->id) }}">
                        @csrf
                        <label for="version_name" class="block font-bold text-sm mb-1">
                            Nazwa pełnej wersji (opcjonalnie):
                        </label>
                        <input type="text" name="version_name"
                            class="border border-gray-300 p-1 rounded text-sm mb-2"
                            placeholder="np. Wersja 2.0" />
                        <button type="submit"
                                class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                            Zapisz jako pełną wersję
                        </button>
                    </form>
                @else
                    <!-- Jeżeli quiz->id jest null (tryb 'create'), informujemy usera: -->
                    <p class="text-gray-500 text-sm ml-4">
                        Najpierw zapisz quiz jako "Draft", aby móc utworzyć pełną wersję.
                    </p>
                @endif
            </div>

            <hr class="my-6">

            <!-- Sekcja Pytań -->
            <div id="questions-section">
                @foreach($questions as $question)
                    <div class="question mb-6 p-4 border border-gray-300 rounded"
                         data-question-id="{{ $question->id }}">
                        <label class="block font-bold mb-2">Treść Pytania:</label>
                        <textarea class="question-text tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded"
                        >{!! $question->question_text !!}</textarea>

                        <label class="block font-bold mb-2">Typ pytania:</label>
                        <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-type"
                                required onchange="toggleAnswerSection(this)">
                            <option value="multiple_choice" {{ $question->type == 'multiple_choice' ? 'selected' : '' }}>
                                Wielokrotnego wyboru
                            </option>
                            <option value="single_choice" {{ $question->type == 'single_choice' ? 'selected' : '' }}>
                                Jednokrotnego wyboru
                            </option>
                            <option value="open" {{ $question->type == 'open' ? 'selected' : '' }}>
                                Otwarte
                            </option>
                        </select>

                        <!-- If multiple_choice => partial/full -->
                        <div class="question-points-type-div mb-4"
                             style="display: {{ $question->type == 'multiple_choice' ? 'block' : 'none' }};">
                            <label class="block font-bold mb-2">Typ przyznawania punktów:</label>
                            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-type"
                                    onchange="togglePointsField(this)">
                                <option value="full" {{ $question->points_type == 'full' ? 'selected' : '' }}>
                                    Za wszystkie poprawne odpowiedzi
                                </option>
                                <option value="partial" {{ $question->points_type == 'partial' ? 'selected' : '' }}>
                                    Za każdą poprawną odpowiedź
                                </option>
                            </select>

                            <div class="points-value-div mt-4">
                                <label class="block font-bold mb-2 points-label">
                                    @if($question->points_type == 'partial')
                                        Punkty za każdą poprawną odpowiedź:
                                    @else
                                        Punkty za wszystkie poprawne odpowiedzi:
                                    @endif
                                </label>
                                <input type="number" class="points-value-input shadow border rounded w-full py-2 px-3 text-gray-700"
                                       value="{{ $question->points }}" min="1">
                            </div>
                        </div>

                        <!-- single_choice / open => proste points -->
                        <div class="question-points-div mb-4"
                             style="display: {{ $question->type == 'multiple_choice' ? 'none' : 'block' }};">
                            <label class="block font-bold mb-2">Punkty za pytanie:</label>
                            <input type="number" class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-input"
                                   name="points" value="{{ $question->points }}" min="1">
                        </div>

                        <!-- Sekcja odpowiedzi/kod -->
                        <div class="answers-section mb-4">
                            @if($question->type == 'open')
                                @php
                                    $answer = $question->answers->first();
                                    $expectedCode = $answer->expected_code ?? '';
                                    $language = $answer->language ?? 'php';
                                @endphp
                                <label class="block font-bold mb-2">Język:</label>
                                <select class="open-question-language shadow border rounded w-full py-2 px-3 text-gray-700">
                                    <option value="php"  {{ $language === 'php'  ? 'selected' : '' }}>PHP</option>
                                    <option value="java" {{ $language === 'java' ? 'selected' : '' }}>Java</option>
                                </select>

                                <label class="block font-bold mb-2 mt-4">Oczekiwany kod:</label>
                                <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded"
                                >{{ $expectedCode }}</textarea>
                            @else
                                @foreach($question->answers as $answer)
                                    <div class="answer-input flex items-center mb-2"
                                         data-answer-id="{{ $answer->id }}">
                                        <textarea class="answer-text tinymce-editor w-full p-2 border border-gray-300 rounded mr-2"
                                        >{!! $answer->text !!}</textarea>
                                        @if($question->type == 'single_choice')
                                            <input type="radio" class="answer-correct mr-2"
                                                   name="correct_answer_{{ $question->id }}"
                                                   {{ $answer->is_correct ? 'checked' : '' }}>
                                        @else
                                            <input type="checkbox" class="answer-correct mr-2"
                                                   {{ $answer->is_correct ? 'checked' : '' }}>
                                        @endif
                                        <button type="button" onclick="removeAnswer(this)"
                                                class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">
                                            Usuń
                                        </button>
                                    </div>
                                @endforeach
                                <button type="button" onclick="addAnswer(this)"
                                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">
                                    Dodaj odpowiedź
                                </button>
                            @endif
                        </div>

                        <div class="flex justify-between mt-4">
                            <button type="button" onclick="saveQuestion(this)"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Zapisz Pytanie
                            </button>
                            <button type="button" onclick="deleteQuestion(this)"
                                    class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Usuń Pytanie
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" onclick="addQuestion()"
                    class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mt-4">
                Dodaj Pytanie
            </button>
        </div>
    </div>
</x-app-layout>
