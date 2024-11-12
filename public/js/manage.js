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

    // Ustawienie stanu checkboxów grup na podstawie stanu checkboxa "Wszyscy użytkownicy"
    const publicQuizCheckbox = document.getElementById('public_quiz');
    handlePublicCheckbox(publicQuizCheckbox);
});

// Pobranie tokenu CSRF i identyfikatora quizu z obiektu window
const csrfToken = window.csrfToken;
const quizId = window.quizId;

/**
 * Pobiera treść z edytora TinyMCE lub z elementu textarea.
 * @param {HTMLElement} element - Element textarea powiązany z edytorem TinyMCE.
 * @returns {string} - Treść edytora.
 */
function getTinyMCEContent(element) {
    const editor = tinymce.editors.find(ed => ed.targetElm === element);
    if (editor) {
        return editor.getContent();
    } else {
        return element.value;
    }
}

/**
 * Zapisuje quiz wraz z pytaniami, odpowiedziami i przypisanymi grupami.
 */
async function saveQuiz() {
    const quizName = getTinyMCEContent(document.getElementById('quiz-name')).trim();
    const timeLimit = document.getElementById('quiz-time-limit').value;
    const isPublic = document.getElementById('public_quiz').checked; // Pobranie wartości is_public
    const multipleAttempts = document.getElementById('quiz-multiple-attempts').checked; // Pobranie wartości multiple_attempts

    if (!quizName) {
        alert('Nazwa quizu nie może być pusta.');
        return;
    }

    try {
        // Przygotowanie danych quizu
        let data = {
            title: quizName,
            time_limit: timeLimit,
            is_public: isPublic,
            multiple_attempts: multipleAttempts, // Dodanie multiple_attempts do danych
            questions: []
        };

        // Zbieranie zaznaczonych grup (tylko jeśli quiz nie jest publiczny)
        if (!isPublic) {
            const selectedGroups = [];
            document.querySelectorAll('input[name="groups[]"]:checked').forEach((checkbox) => {
                selectedGroups.push(checkbox.value);
            });
            data.groups = selectedGroups;
        }

        // Zbieranie danych pytań (pozostaje bez zmian)
        const questionDivs = document.querySelectorAll('.question');
        for (const questionDiv of questionDivs) {
            const questionTextElement = questionDiv.querySelector('.question-text');
            const questionText = getTinyMCEContent(questionTextElement).trim();
            const questionType = questionDiv.querySelector('.question-type').value;
            const questionId = questionDiv.dataset.questionId || null;

            if (!questionText) {
                alert('Treść pytania nie może być pusta.');
                return;
            }

            let questionData = {
                id: questionId,
                question_text: questionText,
                type: questionType,
            };

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

        // Wysłanie danych do kontrolera
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


/**
 * Funkcja obsługująca zmianę stanu checkboxa "Wszyscy użytkownicy".
 * @param {HTMLElement} checkbox - Checkbox "Wszyscy użytkownicy".
 */
function handlePublicCheckbox(checkbox) {
    const groupCheckboxes = document.querySelectorAll('#group-checkboxes input[name="groups[]"]');
    groupCheckboxes.forEach(cb => {
        cb.disabled = checkbox.checked;
        if (checkbox.checked) {
            cb.checked = false; // Odznacz grupy, jeśli quiz jest publiczny
        }
    });
}

/**
 * Zapisuje pojedyncze pytanie wraz z odpowiedziami.
 * @param {HTMLElement} button - Przycisk, który wywołał funkcję.
 */
async function saveQuestion(button) {
    const questionDiv = button.closest('.question');
    let questionId = questionDiv.dataset.questionId;
    const questionTextElement = questionDiv.querySelector('.question-text');
    const questionText = getTinyMCEContent(questionTextElement).trim();
    const questionType = questionDiv.querySelector('.question-type').value;

    if (!questionText) {
        alert('Treść pytania nie może być pusta.');
        return;
    }

    let data = {
        question_text: questionText,
        type: questionType
    };

    if (questionId) {
        // Edycja istniejącego pytania
    } else {
        // Nowe pytanie
        data.quiz_id = quizId;
    }

    if (questionType === 'open') {
        // Pobierz oczekiwany kod dla pytania otwartego
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
        // Zbierz odpowiedzi dla pytania zamkniętego
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

        // Aktualizacja identyfikatorów odpowiedzi
        if (data.answers) {
            const answerDivs = questionDiv.querySelectorAll('.answer-input');
            for (let i = 0; i < answerDivs.length; i++) {
                const answerDiv = answerDivs[i];
                answerDiv.dataset.answerId = null; // Resetuj identyfikatory
            }
        }

        alert(responseData.message);
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd podczas zapisywania pytania: ' + error.message);
    }
}

/**
 * Usuwa pytanie.
 * @param {HTMLElement} button - Przycisk, który wywołał funkcję.
 */
function deleteQuestion(button) {
    const questionDiv = button.closest('.question');
    const questionId = questionDiv.dataset.questionId;

    // Usuń edytory TinyMCE
    const editors = questionDiv.querySelectorAll('.tinymce-editor');
    editors.forEach(editorElement => {
        const editor = tinymce.editors.find(ed => ed.targetElm === editorElement);
        if (editor) {
            editor.remove();
        }
    });

    // Usuń edytor CodeMirror
    const codeTextarea = questionDiv.querySelector('.code-input');
    if (codeTextarea && codeTextarea.CodeMirrorInstance) {
        codeTextarea.CodeMirrorInstance.toTextArea();
    }

    if (!questionId) {
        // Jeśli pytanie nie zostało zapisane w bazie danych
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

/**
 * Dodaje nowe pytanie do quizu.
 */
function addQuestion() {
    const questionsSection = document.getElementById('questions-section');
    const newQuestionDiv = document.createElement('div');
    const newQuestionId = 'new-question-' + Date.now(); // Unikalny identyfikator

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
        <div class="answers-section mb-4">
            <!-- Sekcja odpowiedzi zostanie wygenerowana dynamicznie -->
        </div>
        <div class="flex justify-between mt-4">
            <button type="button" onclick="saveQuestion(this)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Zapisz Pytanie</button>
            <button type="button" onclick="deleteQuestion(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Usuń Pytanie</button>
        </div>
    `;
    questionsSection.appendChild(newQuestionDiv);
    toggleAnswerSection(newQuestionDiv.querySelector('.question-type'));

    // Inicjalizacja TinyMCE na nowym polu
    tinymce.init({
        selector: `#${newQuestionId}`,
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });
}

/**
 * Przełącza sekcję odpowiedzi w zależności od typu pytania.
 * @param {HTMLElement} selectElement - Element select z wyborem typu pytania.
 */
function toggleAnswerSection(selectElement) {
    const questionDiv = selectElement.closest('.question');
    const questionType = selectElement.value;
    const answersSection = questionDiv.querySelector('.answers-section');

    // Usuń istniejącą instancję CodeMirror
    const existingCodeTextarea = answersSection.querySelector('.code-input');
    if (existingCodeTextarea && existingCodeTextarea.CodeMirrorInstance) {
        existingCodeTextarea.CodeMirrorInstance.toTextArea();
    }

    if (questionType === 'open') {
        // Dla pytania otwartego wyświetl pole na oczekiwany kod
        answersSection.innerHTML = `
            <label class="block font-bold mb-2">Oczekiwany kod:</label>
            <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded"></textarea>
        `;

        // Inicjalizacja CodeMirror na nowym polu
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
    } else {
        // Dla pytań zamkniętych wyświetl przycisk dodania odpowiedzi
        answersSection.innerHTML = `
            <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
        `;
    }

    // Resetuj zaznaczenia poprawnych odpowiedzi
    resetAnswerSelections(questionDiv);
    // Aktualizuj nazwy grup radiowych
    updateRadioNames();
}

/**
 * Dodaje nową odpowiedź do pytania.
 * @param {HTMLElement} button - Przycisk, który wywołał funkcję.
 */
function addAnswer(button) {
    const answersSection = button.parentElement;
    const questionDiv = button.closest('.question');
    const questionType = questionDiv.querySelector('.question-type').value;
    const newAnswerId = 'new-answer-' + Date.now(); // Unikalny identyfikator

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

    // Inicjalizacja TinyMCE na nowym polu odpowiedzi
    tinymce.init({
        selector: `#${newAnswerId}`,
        plugins: 'advlist autolink link image lists charmap print preview',
        toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
        menubar: false,
    });

    // Aktualizuj nazwy grup radiowych
    updateRadioNames();
}

/**
 * Usuwa odpowiedź z pytania.
 * @param {HTMLElement} button - Przycisk, który wywołał funkcję.
 */
function removeAnswer(button) {
    const answerDiv = button.parentElement;
    const answerTextElement = answerDiv.querySelector('.answer-text');
    const answerId = answerDiv.dataset.answerId;

    // Usuń edytor TinyMCE
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

/**
 * Resetuje zaznaczenia poprawnych odpowiedzi w pytaniu.
 * @param {HTMLElement} questionDiv - Kontener pytania.
 */
function resetAnswerSelections(questionDiv) {
    const answerCorrectInputs = questionDiv.querySelectorAll('.answer-correct');
    answerCorrectInputs.forEach(input => {
        input.checked = false;
    });
}

/**
 * Aktualizuje nazwy grup radiowych dla pytań jednokrotnego wyboru.
 */
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

/**
 * Przełącza status quizu między aktywnym a nieaktywnym.
 */
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

        // Aktualizacja statusu w interfejsie
        const quizStatusElement = document.getElementById('quiz-status');
        quizStatusElement.innerText = responseData.is_active ? 'Aktywny' : 'Nieaktywny';
        quizStatusElement.classList.toggle('text-green-600', responseData.is_active);
        quizStatusElement.classList.toggle('text-red-600', !responseData.is_active);
    } catch (error) {
        console.error('Error:', error);
        alert('Wystąpił błąd podczas zmiany statusu quizu: ' + error.message);
    }
}
