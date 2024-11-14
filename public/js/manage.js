// public/js/manage.js

// Inicjalizacja edytorów po załadowaniu DOM
document.addEventListener('DOMContentLoaded', function () {
    // Inicjalizacja TinyMCE dla wszystkich pól z klasą .tinymce-editor
    tinymce.init({
        selector: '.tinymce-editor',
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });

    // Inicjalizacja CodeMirror dla wszystkich pól z klasą .code-input
    document.querySelectorAll('.code-input').forEach(function(textarea) {
        const editor = CodeMirror.fromTextArea(textarea, {
            lineNumbers: true,
            mode: {
                name: 'php',
                startOpen: true,
            },
            theme: 'monokai',
            tabSize: 2,
        });
        textarea.CodeMirrorInstance = editor; // Przechowaj instancję CodeMirror
    });

    initializeEditors();
    // Ustawienie stanu checkboxów grup na podstawie stanu checkboxa "Wszyscy użytkownicy"
    const publicQuizCheckbox = document.getElementById('public_quiz');
    handlePublicCheckbox(publicQuizCheckbox);
});

// Pobranie tokenu CSRF i identyfikatora quizu z obiektu window
const csrfToken = window.csrfToken;
const quizId = window.quizId;

function initializeEditors() {
    // Inicjalizacja TinyMCE dla wszystkich pól z klasą .tinymce-editor
    tinymce.init({
        selector: '.tinymce-editor',
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });

    // Inicjalizacja CodeMirror dla wszystkich pól z klasą .code-input
    document.querySelectorAll('.code-input').forEach(function(textarea) {
        const editor = CodeMirror.fromTextArea(textarea, {
            lineNumbers: true,
            mode: {
                name: 'php',
                startOpen: true,
            },
            theme: 'monokai',
            tabSize: 2,
        });
        textarea.CodeMirrorInstance = editor; // Przechowaj instancję CodeMirror
    });
}


function getTinyMCEContent(element) {
    const editor = tinymce.editors.find(ed => ed.targetElm === element);
    if (editor) {
        return editor.getContent();
    } else {
        return element.value;
    }
}

async function saveQuiz() {
    const quizName = getTinyMCEContent(document.getElementById('quiz-name')).trim();
    const timeLimit = document.getElementById('quiz-time-limit').value;
    const isPublic = document.getElementById('public_quiz').checked;
    const multipleAttempts = document.getElementById('quiz-multiple-attempts').checked;

    if (!quizName) {
        alert('Nazwa quizu nie może być pusta.');
        return;
    }

    try {
        let data = {
            title: quizName,
            time_limit: timeLimit,
            is_public: isPublic,
            multiple_attempts: multipleAttempts,
            questions: []
        };

        if (!isPublic) {
            const selectedGroups = [];
            document.querySelectorAll('input[name="groups[]"]:checked').forEach((checkbox) => {
                selectedGroups.push(checkbox.value);
            });
            data.groups = selectedGroups;
        }

        const questionDivs = document.querySelectorAll('.question');
        for (const questionDiv of questionDivs) {
            const questionTextElement = questionDiv.querySelector('.question-text');
            const questionText = getTinyMCEContent(questionTextElement).trim();
            const questionType = questionDiv.querySelector('.question-type').value;
            const questionPoints = parseInt(questionDiv.querySelector('.question-points').value);
            const questionId = questionDiv.dataset.questionId || null;

            if (!questionText) {
                alert('Treść pytania nie może być pusta.');
                return;
            }

            let questionData = {
                id: questionId,
                question_text: questionText,
                type: questionType,
                points: questionPoints
            };

            if (questionType === 'multiple_choice') {
                const pointsTypeElement = questionDiv.querySelector('.question-points-type');
                questionData.points_type = pointsTypeElement ? pointsTypeElement.value : 'full';
            }

            if (questionType === 'open') {
                const codeTextarea = questionDiv.querySelector('.code-input');
                const expectedCode = codeTextarea.CodeMirrorInstance
                    ? codeTextarea.CodeMirrorInstance.getValue().trim()
                    : codeTextarea.value.trim();
                questionData.expected_code = expectedCode;
            } else {
                const answerInputs = questionDiv.querySelectorAll('.answer-input');
                if (answerInputs.length === 0) {
                    alert('Pytanie musi zawierać co najmniej jedną odpowiedź.');
                    return;
                }
                const answers = [];
                for (const answerDiv of answerInputs) {
                    const answerTextElement = answerDiv.querySelector('.answer-text');
                    const text = getTinyMCEContent(answerTextElement).trim();
                    const isCorrect = answerDiv.querySelector('.answer-correct').checked;
                    const answerId = answerDiv.dataset.answerId || null;

                    let answerData = {
                        text: text,
                        is_correct: isCorrect
                    };
                    if (answerId) {
                        answerData.id = answerId;
                    }
                    answers.push(answerData);
                }
                questionData.answers = answers;
            }

            data.questions.push(questionData);
        }

        const response = await fetch(`/quizzes/${quizId}/saveAll`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        });

        if (response.status === 401) {
            alert('Sesja wygasła. Proszę zaloguj się ponownie.');
            window.location.href = '/login';
            return;
        }
        if (!response.ok) {
            const text = await response.text();
            throw new Error('Błąd: ' + response.status + ' ' + text);
        }

        const responseData = await response.json();
        alert('Quiz i wszystkie pytania zostały zapisane pomyślnie.');
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd podczas zapisywania quizu lub pytań: ' + error.message);
    }
}

function handlePublicCheckbox(checkbox) {
    const groupCheckboxes = document.querySelectorAll('#group-checkboxes input[name="groups[]"]');
    groupCheckboxes.forEach(cb => {
        cb.disabled = checkbox.checked;
        if (checkbox.checked) {
            cb.checked = false;
        }
    });
}

async function saveQuestion(button) {
    const questionDiv = button.closest('.question');
    let questionId = questionDiv.dataset.questionId;
    const questionTextElement = questionDiv.querySelector('.question-text');
    const questionText = getTinyMCEContent(questionTextElement).trim();
    const questionType = questionDiv.querySelector('.question-type').value;
    const points = parseInt(questionDiv.querySelector('.question-points').value);

    if (!questionText) {
        alert('Treść pytania nie może być pusta.');
        return;
    }

    if (!points || points < 1) {
        alert('Punkty za pytanie muszą być większe niż 0.');
        return;
    }

    let data = {
        question_text: questionText,
        type: questionType,
        points: points,
    };

    if (questionType === 'multiple_choice') {
        const pointsTypeElement = questionDiv.querySelector('.question-points-type');
        data.points_type = pointsTypeElement ? pointsTypeElement.value : 'full';
    }

    if (questionId) {
    } else {
        data.quiz_id = quizId;
    }

    if (questionType === 'open') {
        const codeTextarea = questionDiv.querySelector('.code-input');
        const expectedCode = codeTextarea.CodeMirrorInstance
            ? codeTextarea.CodeMirrorInstance.getValue().trim()
            : codeTextarea.value.trim();
        if (!expectedCode) {
            alert('Pole "Oczekiwany kod" nie może być puste.');
            return;
        }
        data.expected_code = expectedCode;
    } else {
        const answerInputs = questionDiv.querySelectorAll('.answer-input');
        if (answerInputs.length === 0) {
            alert('Pytanie musi zawierać co najmniej jedną odpowiedź.');
            return;
        }
        const answers = [];
        let hasCorrectAnswer = false;
        for (const answerDiv of answerInputs) {
            const answerTextElement = answerDiv.querySelector('.answer-text');
            const text = getTinyMCEContent(answerTextElement).trim();
            const isCorrect = answerDiv.querySelector('.answer-correct').checked;
            const answerId = answerDiv.dataset.answerId || null;

            if (!text) {
                alert('Pola odpowiedzi nie mogą być puste.');
                return;
            }
            if (isCorrect) {
                hasCorrectAnswer = true;
            }

            let answerData = {
                text: text,
                is_correct: isCorrect
            };
            if (answerId) {
                answerData.id = answerId;
            }
            answers.push(answerData);
        }
        if (!hasCorrectAnswer) {
            alert('Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna.');
            return;
        }
        data.answers = answers;
    }

    const method = questionId ? 'PUT' : 'POST';
    const url = questionId ? `/questions/${questionId}` : '/questions';

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        });

        if (response.status === 401) {
            alert('Sesja wygasła. Proszę zaloguj się ponownie.');
            window.location.href = '/login';
            return;
        }
        if (!response.ok) {
            const text = await response.text();
            throw new Error('Błąd: ' + response.status + ' ' + text);
        }

        const responseData = await response.json();
        if (!questionId) {
            questionDiv.dataset.questionId = responseData.question_id;
            questionId = responseData.question_id;
        }

        if (data.answers) {
            const answerDivs = questionDiv.querySelectorAll('.answer-input');
            for (let i = 0; i < answerDivs.length; i++) {
                const answerDiv = answerDivs[i];
                answerDiv.dataset.answerId = null;
            }
        }

        alert(responseData.message);
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd podczas zapisywania pytania: ' + error.message);
    }
}

function deleteQuestion(button) {
    const questionDiv = button.closest('.question');
    const questionId = questionDiv.dataset.questionId;

    const editors = questionDiv.querySelectorAll('.tinymce-editor');
    editors.forEach(editorElement => {
        const editor = tinymce.editors.find(ed => ed.targetElm === editorElement);
        if (editor) {
            editor.remove();
        }
    });

    const codeTextarea = questionDiv.querySelector('.code-input');
    if (codeTextarea && codeTextarea.CodeMirrorInstance) {
        codeTextarea.CodeMirrorInstance.toTextArea();
    }

    if (!questionId) {
        questionDiv.remove();
        return;
    }

    if (!confirm('Czy na pewno chcesz usunąć to pytanie?')) {
        return;
    }

    fetch(`/questions/${questionId}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => {
        if (response.status === 401) {
            alert('Sesja wygasła. Proszę zaloguj się ponownie.');
            window.location.href = '/login';
            return;
        }
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('Błąd: ' + response.status + ' ' + text);
            });
        }
        return response.json();
    })
    .then(data => {
        alert(data.message);
        questionDiv.remove();
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message);
    });
}

function addQuestion() {
    const questionsSection = document.getElementById('questions-section');
    const newQuestionDiv = document.createElement('div');
    const newQuestionId = 'new-question-' + Date.now();

    newQuestionDiv.classList.add('question', 'mb-6', 'p-4', 'border', 'border-gray-300', 'rounded');
    newQuestionDiv.innerHTML = `
        <label class="block font-bold mb-2">Treść Pytania:</label>
        <textarea id="${newQuestionId}" class="question-text tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded" placeholder="Nowe pytanie"></textarea>
        
        <label class="block font-bold mb-2">Typ pytania:</label>
        <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-type" required onchange="toggleAnswerSection(this)">
            <option value="multiple_choice">Wielokrotnego wyboru</option>
            <option value="single_choice">Jednokrotnego wyboru</option>
            <option value="open">Otwarte</option>
        </select>

        <!-- Typ przyznawania punktów dla pytania wielokrotnego wyboru (tylko dla multiple_choice) -->
        <div class="question-points-type-div mb-4" style="display: block;">
            <label class="block font-bold mb-2">Typ przyznawania punktów:</label>
            <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-type" onchange="togglePointsField(this)">
                <option value="full">Za wszystkie poprawne odpowiedzi</option>
                <option value="partial">Za każdą poprawną odpowiedź</option>
            </select>
            <div class="points-value-div mt-4">
                <label class="block font-bold mb-2 points-label">Punkty za wszystkie poprawne odpowiedzi:</label>
                <input type="number" class="points-value-input shadow border rounded w-full py-2 px-3 text-gray-700" value="1" min="1">
            </div>
        </div>

        <!-- Pole punktów dla pytania (widoczne tylko jeśli typ to nie multiple_choice) -->
        <div class="question-points mb-4" style="display: none;">
            <label class="block font-bold mb-2">Punkty za pytanie:</label>
            <input type="number" class="shadow border rounded w-full py-2 px-3 text-gray-700" name="points" value="1" min="1">
        </div>

        <div class="answers-section mb-4">
        </div>
        
        <div class="flex justify-between mt-4">
            <button type="button" onclick="saveQuestion(this)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Zapisz Pytanie</button>
            <button type="button" onclick="deleteQuestion(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Usuń Pytanie</button>
        </div>
    `;
    
    questionsSection.appendChild(newQuestionDiv);
    
    // Inicjalizacja TinyMCE dla dynamicznie dodanego pytania
    tinymce.init({
        selector: `#${newQuestionId}`,
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });

    const editor = CodeMirror.fromTextArea(codeTextarea, {
        lineNumbers: true,
        mode: {
            name: 'php',
            startOpen: true,
        },
        theme: 'monokai',
        tabSize: 2,
    });

    // Wywołanie toggleAnswerSection, aby ustawić odpowiednie pola na podstawie domyślnego typu pytania
    const selectElement = newQuestionDiv.querySelector('.question-type');
    toggleAnswerSection(selectElement);
}

function toggleAnswerSection(selectElement) {
    const questionDiv = selectElement.closest('.question');
    const questionType = selectElement.value;
    const answersSection = questionDiv.querySelector('.answers-section');

    const existingCodeTextarea = answersSection.querySelector('.code-input');
    if (existingCodeTextarea && existingCodeTextarea.CodeMirrorInstance) {
        existingCodeTextarea.CodeMirrorInstance.toTextArea();
    }

    // Reset odpowiedzi
    answersSection.innerHTML = '';

    // Dla pytania otwartego - dodaj pole na oczekiwany kod
    if (questionType === 'open') {
        answersSection.innerHTML = `
            <label class="block font-bold mb-2">Oczekiwany kod:</label>
            <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded"></textarea>
        `;

        const codeTextarea = answersSection.querySelector('.code-input');
        const editor = CodeMirror.fromTextArea(codeTextarea, {
            lineNumbers: true,
            mode: {
                name: 'php',
                startOpen: true,
            },
            theme: 'monokai',
            tabSize: 2,
        });
        codeTextarea.CodeMirrorInstance = editor;

        // Ukryj pole z liczbą punktów, gdy pytanie jest otwarte
        questionDiv.querySelector('.question-points').closest('.mb-4').style.display = 'block';
        const pointsTypeDiv = questionDiv.querySelector('.question-points-type-div');
        if (pointsTypeDiv) pointsTypeDiv.remove();

    } else if (questionType === 'multiple_choice') {
        // Dla pytań wielokrotnego wyboru - dodaj przycisk dodawania odpowiedzi oraz wybór sposobu punktacji
        answersSection.innerHTML = `
            <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
        `;

        // Ukryj pole z liczbą punktów, ponieważ mamy szczegółową kontrolę nad punktowaniem
        questionDiv.querySelector('.question-points').closest('.mb-4').style.display = 'none';

        // Dodaj wybór typu punktowania dla pytania wielokrotnego wyboru
        if (!questionDiv.querySelector('.question-points-type-div')) {
            const pointsTypeDiv = document.createElement('div');
            pointsTypeDiv.className = 'question-points-type-div mb-4';
            pointsTypeDiv.innerHTML = `
                <label class="block font-bold mb-2">Typ przyznawania punktów:</label>
                <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-type" onchange="togglePointsField(this)">
                    <option value="full">Za wszystkie poprawne odpowiedzi</option>
                    <option value="partial">Za każdą poprawną odpowiedź</option>
                </select>
                <div class="points-value-div mt-4">
                    <label class="block font-bold mb-2 points-label">Punkty za wszystkie poprawne odpowiedzi:</label>
                    <input type="number" class="points-value-input shadow border rounded w-full py-2 px-3 text-gray-700" value="1" min="1">
                </div>
            `;
            questionDiv.insertBefore(pointsTypeDiv, answersSection);
        }

    } else {
        // Dla pytań jednokrotnego wyboru - dodaj przycisk dodawania odpowiedzi
        answersSection.innerHTML = `
            <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
        `;

        // Pokaż pole z liczbą punktów dla pytań jednokrotnego wyboru
        questionDiv.querySelector('.question-points').closest('.mb-4').style.display = 'block';

        // Usuń wybór typu punktowania, jeśli istnieje
        const pointsTypeDiv = questionDiv.querySelector('.question-points-type-div');
        if (pointsTypeDiv) pointsTypeDiv.remove();
    }

    // Resetuj zaznaczenia poprawnych odpowiedzi i zaktualizuj nazwy radiobuttonów
    resetAnswerSelections(questionDiv);
    updateRadioNames();
}

function addAnswer(button) {
    const answersSection = button.parentElement;
    const questionDiv = button.closest('.question');
    const questionType = questionDiv.querySelector('.question-type').value;
    const newAnswerId = 'new-answer-' + Date.now();

    const newAnswerDiv = document.createElement('div');
    newAnswerDiv.classList.add('answer-input', 'flex', 'items-center', 'mb-2');
    newAnswerDiv.innerHTML = `
        <textarea id="${newAnswerId}" class="answer-text tinymce-editor w-full p-2 border border-gray-300 rounded mr-2" placeholder="Nowa odpowiedź"></textarea>
        ${questionType === 'single_choice' ?
            `<input type="radio" class="answer-correct mr-2">` :
            `<input type="checkbox" class="answer-correct mr-2">`}
        <button type="button" onclick="removeAnswer(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">Usuń</button>
    `;
    answersSection.insertBefore(newAnswerDiv, button);

    tinymce.init({
        selector: `#${newAnswerId}`,
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });

    updateRadioNames();
}

function removeAnswer(button) {
    const answerDiv = button.parentElement;
    const answerTextElement = answerDiv.querySelector('.answer-text');
    const answerId = answerDiv.dataset.answerId;

    const editor = tinymce.editors.find(ed => ed.targetElm === answerTextElement);
    if (editor) {
        editor.remove();
    }

    if (answerId) {
        if (!confirm('Czy na pewno chcesz usunąć tę odpowiedź?')) {
            return;
        }

        fetch(`/answers/${answerId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => {
            if (response.status === 401) {
                alert('Sesja wygasła. Proszę zaloguj się ponownie.');
                window.location.href = '/login';
                return;
            }
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('Błąd: ' + response.status + ' ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            alert(data.message);
            answerDiv.remove();
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message);
        });
    } else {
        answerDiv.remove();
    }
}

function resetAnswerSelections(questionDiv) {
    const answerCorrectInputs = questionDiv.querySelectorAll('.answer-correct');
    answerCorrectInputs.forEach(input => {
        input.checked = false;
    });
}

function updateRadioNames() {
    const questions = document.querySelectorAll('.question');
    questions.forEach((questionDiv, index) => {
        const questionId = questionDiv.dataset.questionId || `new_${index}`;
        const questionType = questionDiv.querySelector('.question-type').value;
        if (questionType === 'single_choice') {
            const answerCorrectInputs = questionDiv.querySelectorAll('.answer-correct[type="radio"]');
            answerCorrectInputs.forEach(input => {
                input.name = `correct_answer_${questionId}`;
            });
        }
    });
}

async function toggleQuizStatus() {
    try {
        const response = await fetch(`/quizzes/${quizId}/toggleStatus`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (response.status === 401) {
            alert('Sesja wygasła. Proszę zaloguj się ponownie.');
            window.location.href = '/login';
            return;
        }

        if (!response.ok) {
            const text = await response.text();
            throw new Error('Błąd: ' + response.status + ' ' + text);
        }

        const responseData = await response.json();
        alert(responseData.message);

        const quizStatusElement = document.getElementById('quiz-status');
        quizStatusElement.innerText = responseData.is_active ? 'Aktywny' : 'Nieaktywny';
        quizStatusElement.classList.toggle('text-green-600', responseData.is_active);
        quizStatusElement.classList.toggle('text-red-600', !responseData.is_active);
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd podczas zmiany statusu quizu: ' + error.message);
    }

    function toggleAnswerSection(selectElement) {
        const questionDiv = selectElement.closest('.question');
        const questionType = selectElement.value;
        const answersSection = questionDiv.querySelector('.answers-section');
        const pointsTypeDiv = questionDiv.querySelector('.question-points-type-div');
        const pointsInputDiv = questionDiv.querySelector('.question-points');
    
        // Usunięcie poprzedniej konfiguracji, jeśli istnieje
        const existingCodeTextarea = answersSection.querySelector('.code-input');
        if (existingCodeTextarea && existingCodeTextarea.CodeMirrorInstance) {
            existingCodeTextarea.CodeMirrorInstance.toTextArea();
        }
    
        // Reset odpowiedzi
        answersSection.innerHTML = '';
    
        // Dla pytania otwartego - dodaj pole na oczekiwany kod
        if (questionType === 'open') {
            answersSection.innerHTML = `
                <label class="block font-bold mb-2">Oczekiwany kod:</label>
                <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded"></textarea>
            `;
    
            const codeTextarea = answersSection.querySelector('.code-input');
            const editor = CodeMirror.fromTextArea(codeTextarea, {
                lineNumbers: true,
                mode: {
                    name: 'php',
                    startOpen: true,
                },
                theme: 'monokai',
                tabSize: 2,
            });
            codeTextarea.CodeMirrorInstance = editor;
    
            // Ukryj pole z liczbą punktów, gdy pytanie jest otwarte
            pointsInputDiv.style.display = 'block';
            pointsTypeDiv.style.display = 'none';
    
        } else if (questionType === 'multiple_choice') {
            // Dla pytań wielokrotnego wyboru - dodaj przycisk dodawania odpowiedzi oraz wybór sposobu punktacji
            answersSection.innerHTML = `
                <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
            `;
    
            // Pokaż wybór typu punktowania dla pytania wielokrotnego wyboru
            pointsTypeDiv.style.display = 'block';
            pointsInputDiv.style.display = 'none';
    
        } else {
            // Dla pytań jednokrotnego wyboru - dodaj przycisk dodawania odpowiedzi
            answersSection.innerHTML = `
                <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
            `;
    
            // Pokaż pole z liczbą punktów dla pytań jednokrotnego wyboru
            pointsInputDiv.style.display = 'block';
            pointsTypeDiv.style.display = 'none';
        }
    
        // Resetuj zaznaczenia poprawnych odpowiedzi i zaktualizuj nazwy radiobuttonów
        resetAnswerSelections(questionDiv);
        updateRadioNames();
    }

    // Funkcja przełącza wyświetlanie pól do ustawienia punktowego lub procentowego progu zdawalności
    function togglePassingScoreFields() {
        const passingType = document.getElementById('passing-type').value;
        document.getElementById('passing-score-field').style.display = (passingType === 'points') ? 'block' : 'none';
        document.getElementById('passing-percentage-field').style.display = (passingType === 'percentage') ? 'block' : 'none';
    }

    // Funkcja dynamicznie zmieniająca wyświetlanie pól dla punktowania
    function togglePointsField(selectElement) {
        const pointsType = selectElement.value;
        const pointsValueDiv = selectElement.closest('.question-points-type-div').querySelector('.points-value-div');
        if (pointsType === 'full') {
            pointsValueDiv.querySelector('.points-label').innerText = 'Punkty za wszystkie poprawne odpowiedzi:';
        } else if (pointsType === 'partial') {
            pointsValueDiv.querySelector('.points-label').innerText = 'Punkty za każdą poprawną odpowiedź:';
        }
    }
}
