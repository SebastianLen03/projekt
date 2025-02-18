<x-app-layout>
    <!-- Główny plik manage.js (inicjalizacja TinyMCE i CodeMirror) -->
    <script src="{{ asset('js/manage.js') }}" defer></script>

    @if(isset($quiz))
        <script>
            window.csrfToken = "{{ csrf_token() }}";
            window.quizId = "{{ $quiz->id }}";
        </script>
    @else
        <script>
            window.csrfToken = "{{ csrf_token() }}";
        </script>
    @endif

    <!-- Przekazanie zmiennych do JavaScript -->
    <div class="grid grid-cols-10 py-5 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="mb-3 text-red-600 col-span-10">
                <ul class="list-disc ml-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
        $versions = $quiz->quizVersions()
            ->where('is_draft', false)
            ->orderBy('version_number','asc')
            ->get();
        @endphp

        @php
            $anyActiveVersion = $versions->where('is_active', true)->count() > 0;
        @endphp

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
            <!-- 2. Sekcja EDYCJI QUIZU (DRAFT) - Single Column Layout -->
<div class="col-span-4 p-4 border-r border-gray-300">
    <h2 class="text-2xl font-semibold mb-5">Edytuj nową wersję</h2>
  
    <!-- Pole nazwy Quizu -->
    <div class="px-2 mb-4">
      <label class="block font-bold mb-2">Nazwa Quizu:</label>
      <textarea id="quiz-name" name="title"
        class="tinymce-editor w-full mb-2 p-2 border border-gray-300 rounded">{!! $quiz->title !!}</textarea>
    </div>
  
    <!-- Pole ustawień limitu czasu -->
    <div class="px-2 mb-4">
      <label class="block font-bold mb-2">Czy chcesz ograniczyć czas rozwiązywania quizu?</label>
      <div class="flex items-center mb-4">
        <input type="checkbox" id="enable-time-limit" name="has_time_limit"
               value="1" {{ $timeLimitValue ? 'checked' : '' }} onchange="toggleTimeLimitField()">
        <label for="enable-time-limit" class="ml-2">Tak, chcę ograniczyć czas.</label>
      </div>
      <div id="time-limit-field" style="display: {{ $timeLimitValue ? 'block' : 'none' }};">
        <label class="block font-bold mb-2">Limit czasu (w minutach):</label>
        <input type="number" id="quiz-time-limit" name="time_limit"
               value="{{ $timeLimitValue }}"
               class="w-full mb-4 p-2 border border-gray-300 rounded" min="1">
      </div>
    </div>
  
    <!-- Pole ustawień zdawalności -->
    <div class="px-2 mb-4">
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
    </div>
  
    <!-- Przyciski zapisu (zachowują układ poziomy) -->
    <div class="px-2 mb-4">
      <div class="flex items-center mt-2 space-x-4">
        <button type="button" onclick="saveQuiz()"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
          Zapisz Quiz (Draft)
        </button>
  
        @if($quiz->id)
        <form method="POST" action="{{ route('quizzes.finalizeDraftVersion', $quiz->id) }}"
              class="flex items-start space-x-2">
          @csrf
          <div>
            <label for="version_name" class="block font-bold text-sm mb-1">
              Nazwa pełnej wersji (opcjonalnie):
            </label>
            <input type="text" name="version_name"
                   class="border border-gray-300 p-1 rounded text-sm"
                   placeholder="np. Wersja 2.0" />
          </div>
          <button type="submit"
                  class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded self-end">
            Zapisz jako pełną wersję
          </button>
        </form>
        @else
        <p class="text-gray-500 text-sm">
          Najpierw zapisz quiz jako "Draft", aby móc utworzyć pełną wersję.
        </p>
        @endif
      </div>
    </div>
  
    <hr class="my-6">
  
    <!-- Sekcja Pytań -->
    <div id="questions-section">
      @foreach($questions as $question)
        <div class="question mb-6 p-4 border border-gray-300 rounded"
             data-question-id="{{ $question->id }}">
          <!-- Treść pytania -->
          <div class="mb-4">
            <label class="block font-bold mb-2">Treść Pytania:</label>
            <textarea class="question-text tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded">
              {!! $question->question_text !!}
            </textarea>
          </div>
          <!-- Typ pytania + punkty -->
          <div class="mb-4">
            <label class="block font-bold mb-2">Typ pytania:</label>
            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-type mb-4"
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
            <!-- Dodatkowe pola punktowe -->
            <div class="question-points-type-div mb-4" style="display: {{ $question->type == 'multiple_choice' ? 'block' : 'none' }};">
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
            <div class="question-points-div mb-4" style="display: {{ $question->type == 'multiple_choice' ? 'none' : 'block' }};">
              <label class="block font-bold mb-2">Punkty za pytanie:</label>
              <input type="number"
                     class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-input"
                     name="points"
                     value="{{ $question->points }}" min="1">
            </div>
          </div>
  
          <!-- Odpowiedzi / Kod dla pytań -->
          <div class="answers-section mb-4 mt-4">
            @if($question->type == 'open')
              @php
                $answer = $question->answers->first();
                $expectedCode = $answer->expected_code ?? '';
                $language = $answer->language ?? 'php';
              @endphp
              <label class="block font-bold mb-2">Język:</label>
              <select class="open-question-language shadow border rounded w-full py-2 px-3 text-gray-700 mb-4">
                <option value="php"  {{ $language === 'php'  ? 'selected' : '' }}>PHP</option>
                <option value="java" {{ $language === 'java' ? 'selected' : '' }}>Java</option>
              </select>
              <label class="block font-bold mb-2">Oczekiwany kod:</label>
              <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded">{{ $expectedCode }}</textarea>
            @else
              @foreach($question->answers as $answer)
                <div class="answer-input flex items-center mb-2"
                     data-answer-id="{{ $answer->id }}">
                  <textarea class="answer-text tinymce-editor w-full p-2 border border-gray-300 rounded mr-2">
                    {!! $answer->text !!}
                  </textarea>
                  @if($question->type == 'single_choice')
                    <input type="radio" class="answer-correct mr-2"
                           name="correct_answer_{{ $question->id }}"
                           {{ $answer->is_correct ? 'checked' : '' }}>
                  @else
                    <input type="checkbox" class="answer-correct mr-2"
                           {{ $answer->is_correct ? 'checked' : '' }}>
                  @endif
                  <x-red-button type="button" onclick="removeAnswer(this)"
                          class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">
                    Usuń
                  </x-red-button>
                </div>
              @endforeach
              <button type="button" onclick="addAnswer(this)"
                      class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">
                Dodaj odpowiedź
              </button>
            @endif
          </div>
  
          <!-- Przyciski zapisu/usunięcia pytania -->
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

  <div class="p-6 rounded-lg mb-6 col-span-6">
            <h2 class="text-2xl font-semibold mb-4">Zapisane wersje</h2>
            <!-- 1. Sekcja z listą pełnych wersji (is_draft=0) na samej górze -->

            @if($versions->count())
                <table class="min-w-full table-auto border-collapse bg-gray-100 rounded-lg shadow-sm text-gray-700">
                    <thead>
                    <tr>
                        <th class="px-4 py-2" style="width: 350px;">Wersja</th>
                        <th class="px-4 py-2">Akcje</th>
                        <th class="px-4 py-2" style="width: 195px;">Reset Podejść</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($versions as $version)
                        <tr class="border-b">

                            <!-- Zmiana nazwy wersji -->
                            <td class="px-4 py-2" style="width: 100px;"">
                                <form method="POST" action="{{ route('quizzes.renameVersion', [$quiz->id, $version->id]) }}">
                                    @csrf
                                    <input type="text" name="version_name"
                                           value="{{ $version->version_name }}"
                                           class="border border-gray-300 rounded p-1 text-sm">
                                    <x-dark-button type="submit">
                                        Zmień
                                    </x-dark-button>
                                </form>
                            </td>

                            <!-- Podgląd, aktywacja/deaktywacja, usunięcie wersji -->
                            <td class="px-4 py-2 flex items-center space-x-2">
                                <!-- Podgląd wersji -->
                                <x-dark-a href="{{ route('quizzes.showVersion', [$quiz->id, $version->id]) }}"
                                   class="bg-gray-500 hover:bg-gray-700 text-white py-1 px-2 rounded text-sm">
                                    Podgląd
                                </x-dark-a>

                                @if(!$version->is_active)
                                    <!-- Aktywuj -->
                                    <form method="POST"
                                          action="{{ route('quizzes.activateVersion', [$quiz->id, $version->id]) }}">
                                        @csrf
                                        <x-green-button type="submit">
                                            Aktywuj
                                        </x-green-button>
                                    </form>

                                    <!-- Usuń wersję (tylko nieaktywną) -->
                                    <form method="POST"
                                          action="{{ route('quizzes.deleteVersion', [$quiz->id, $version->id]) }}"
                                          onsubmit="return confirm('Na pewno usunąć tę wersję?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-red-button type="submit">
                                            Usuń
                                        </x-red-button>
                                    </form>
                                @else
                                    <!-- Deaktywuj -->
                                    <form method="POST"
                                          action="{{ route('quizzes.deactivateVersion', [$quiz->id, $version->id]) }}">
                                        @csrf
                                        <x-red-button type="submit"
                                                class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm">
                                            Dezaktywuj
                                        </x-red-button>
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
                                    <x-red-button type="submit"
                                            class="bg-red-500 hover:bg-red-700 text-white py-1 px-2 rounded text-sm">
                                        Reset Wszystkich podejść
                                    </x-red-button>
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
                <p class="text-gray-600">Brak.</p>
            @endif



            <div class="mt-4 p-4 rounded">
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

  
    </div>
</x-app-layout>
