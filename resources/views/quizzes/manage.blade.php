<!-- resources/views/quizzes/manage.blade.php -->

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edytuj Quiz') }}
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.5/mode/clike/clike.min.js"></script>

    <!-- Dodaj TinyMCE z kluczem API -->
    <script src="https://cdn.tiny.cloud/1/dzv7br6mbv4b8a5rvgkg45vkccpmo3sxrkntg0pu2450lkgu/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>

    <!-- Przekazanie zmiennych do JavaScript -->
    <script>
        window.csrfToken = "{{ csrf_token() }}";
        window.quizId = "{{ $quiz->id }}";
    </script>

    <!-- Dodaj odwołanie do pliku manage.js -->
    <script src="{{ asset('js/manage.js') }}" defer></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('message'))
                <div class="mb-4 text-green-600">
                    {{ session('message') }}
                </div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- Formularz Quizu -->
                    <div id="quiz-section" class="mb-6">
                        <!-- Pole nazwy quizu -->
                        <label class="block font-bold mb-2">Nazwa Quizu:</label>
                        <textarea id="quiz-name" name="title" class="tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded">{!! $quiz->title !!}</textarea>

                        <!-- Pole limitu czasu -->
                        <label class="block font-bold mb-2">Limit czasu (w minutach):</label>
                        <input type="number" id="quiz-time-limit" name="time_limit" value="{{ $quiz->time_limit }}" class="w-full mb-4 p-2 border border-gray-300 rounded">

                        <!-- Wybór udostępnienia quizu -->
                        <label class="block font-bold mb-2">Udostępnij quiz:</label>
                        <div id="group-checkboxes" class="mb-4">
                            <!-- Checkbox dla udostępnienia quizu wszystkim -->
                            <div class="flex items-center mb-2">
                                <input type="checkbox" id="public_quiz" name="is_public" value="1" {{ $quiz->is_public ? 'checked' : '' }} onclick="handlePublicCheckbox(this)">
                                <label for="public_quiz" class="ml-2">Wszyscy użytkownicy</label>
                            </div>
                            <!-- Checkboxy dla grup -->
                            @foreach ($userGroups as $group)
                                <div class="flex items-center mb-2">
                                    <input type="checkbox" id="group_{{ $group->id }}" name="groups[]" value="{{ $group->id }}" 
                                           {{ in_array($group->id, $quiz->groups->pluck('id')->toArray()) && !$quiz->is_public ? 'checked' : '' }}>
                                    <label for="group_{{ $group->id }}" class="ml-2">{{ $group->name }}</label>
                                </div>
                            @endforeach
                        </div>

                        <!-- Przyciski akcji dla quizu -->
                        <div class="flex items-center mb-4">
                            <button type="button" onclick="saveQuiz()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">Zapisz Quiz</button>
                            <button type="button" onclick="toggleQuizStatus()" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">Aktywuj/Dezaktywuj Quiz</button>
                            <span id="quiz-status" class="ml-4 font-bold {{ $quiz->is_active ? 'text-green-600' : 'text-red-600' }}">
                                {{ $quiz->is_active ? 'Aktywny' : 'Nieaktywny' }}
                            </span>
                        </div>
                    </div>

                    <hr class="my-6">

                    <!-- Sekcja Pytań -->
                    <div id="questions-section">
                        @foreach($questions as $question)
                            <div class="question mb-6 p-4 border border-gray-300 rounded" data-question-id="{{ $question->id }}">
                                <!-- Pole treści pytania -->
                                <label class="block font-bold mb-2">Treść Pytania:</label>
                                <textarea class="question-text tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded">{!! $question->question_text !!}</textarea>

                                <!-- Wybór typu pytania -->
                                <label class="block font-bold mb-2">Typ pytania:</label>
                                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-type" required onchange="toggleAnswerSection(this)">
                                    <option value="multiple_choice" {{ $question->type == 'multiple_choice' ? 'selected' : '' }}>Wielokrotnego wyboru</option>
                                    <option value="single_choice" {{ $question->type == 'single_choice' ? 'selected' : '' }}>Jednokrotnego wyboru</option>
                                    <option value="open" {{ $question->type == 'open' ? 'selected' : '' }}>Otwarte</option>
                                </select>

                                <!-- Sekcja odpowiedzi -->
                                <div class="answers-section mb-4">
                                    @if($question->type == 'open')
                                        @php
                                            $expectedCode = $question->answers->first()->expected_code ?? '';
                                        @endphp
                                        <!-- Pole oczekiwanego kodu dla pytania otwartego -->
                                        <label class="block font-bold mb-2">Oczekiwany kod:</label>
                                        <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded">{{ $expectedCode }}</textarea>
                                    @else
                                        <!-- Lista odpowiedzi dla pytań zamkniętych -->
                                        @foreach($question->answers as $answer)
                                            <div class="answer-input flex items-center mb-2" data-answer-id="{{ $answer->id }}">
                                                <!-- Pole treści odpowiedzi -->
                                                <textarea class="answer-text tinymce-editor w-full p-2 border border-gray-300 rounded mr-2">{!! $answer->text !!}</textarea>
                                                <!-- Pole wyboru poprawności odpowiedzi -->
                                                @if($question->type == 'single_choice')
                                                    <input type="radio" class="answer-correct mr-2" name="correct_answer_{{ $question->id }}" {{ $answer->is_correct ? 'checked' : '' }}>
                                                @else
                                                    <input type="checkbox" class="answer-correct mr-2" {{ $answer->is_correct ? 'checked' : '' }}>
                                                @endif
                                                <!-- Przycisk usunięcia odpowiedzi -->
                                                <button type="button" onclick="removeAnswer(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">Usuń</button>
                                            </div>
                                        @endforeach
                                        <!-- Przycisk dodania nowej odpowiedzi -->
                                        <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
                                    @endif
                                </div>

                                <!-- Przyciski akcji dla pytania -->
                                <div class="flex justify-between mt-4">
                                    <button type="button" onclick="saveQuestion(this)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Zapisz Pytanie</button>
                                    <button type="button" onclick="deleteQuestion(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Usuń Pytanie</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Przycisk dodania nowego pytania -->
                    <button type="button" onclick="addQuestion()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded mt-4">Dodaj Pytanie</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
