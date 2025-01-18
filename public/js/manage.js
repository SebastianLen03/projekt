""; // public/js/manage.js

// Inicjalizacja edytorów po załadowaniu DOM
document.addEventListener("DOMContentLoaded", function () {
    // -- Inicjalizacja TinyMCE dla istniejących .tinymce-editor
    tinymce.init({
        selector: ".tinymce-editor",
        menubar: false,
        plugins: "lists link image table code",
        toolbar: "undo redo | bold italic underline | bullist numlist | link table | code",
        branding: false,
        license_key: "gpl",
        forced_root_block: "div", // lub "p"
        height: 200,
        setup: function (editor) {
            editor.on("change", function () {
                editor.save();
            });
        },
    });

    // -- Inicjalizacja CodeMirror dla istniejących .code-input
    document.querySelectorAll(".code-input").forEach(function (textarea) {
        const editor = CodeMirror.fromTextArea(textarea, {
            lineNumbers: true,
            mode: {
                name: "php",
                startOpen: true,
            },
            theme: "monokai",
            tabSize: 2,
        });
        textarea.CodeMirrorInstance = editor;
    });

    // -- Obsługa checkboxów / Pola limitu czasu / Pola zdawalności
    toggleTimeLimitField();
    togglePassingScoreFields();
    const publicQuizCheckbox = document.getElementById("public_quiz");
    if (publicQuizCheckbox) {
        handlePublicCheckbox(publicQuizCheckbox);
    }
});

// Pobranie tokenu CSRF i identyfikatora quizu z obiektu window
const csrfToken = window.csrfToken;
const quizId = window.quizId;

function getTinyMCEContent(element) {
    // Sprawdzamy, czy element jest już zainicjalizowany przez tinymce
    if (tinymce.get(element.id)) {
        return tinymce.get(element.id).getContent();
    } else {
        return element.value;
    }
}

async function saveQuiz() {
    const quizName = getTinyMCEContent(document.getElementById("quiz-name")).trim();
    const isPublic = document.getElementById("public_quiz").checked;
    const multipleAttempts = document.getElementById("quiz-multiple-attempts").checked;

    if (!quizName) {
        alert("Nazwa quizu nie może być pusta.");
        return;
    }

    const passingType = document.getElementById("passing-type").value;
    const passingScore = document.getElementById("passing-score").value;
    const passingPercentage =
        document.getElementById("passing-percentage").value;

    let hasPassingCriteria = false;
    if (passingType === "points" || passingType === "percentage") {
        hasPassingCriteria = true;
    }

    const hasTimeLimit = document.getElementById("enable-time-limit").checked;
    const timeLimit = document.getElementById("quiz-time-limit").value;

    try {
        let data = {
            title: quizName,
            is_public: isPublic,
            multiple_attempts: multipleAttempts,
            has_passing_criteria: hasPassingCriteria,
            has_time_limit: hasTimeLimit,
            questions: [],
        };

        if (passingType === "points") {
            data.passing_score = parseInt(passingScore);
            data.passing_percentage = null;
        } else if (passingType === "percentage") {
            data.passing_percentage = parseInt(passingPercentage);
            data.passing_score = null;
        } else {
            data.passing_score = null;
            data.passing_percentage = null;
        }

        if (hasTimeLimit) {
            data.time_limit = parseInt(timeLimit);
        } else {
            data.time_limit = null;
        }

        if (!isPublic) {
            const selectedGroups = [];
            document
                .querySelectorAll('input[name="groups[]"]:checked')
                .forEach((checkbox) => {
                    selectedGroups.push(checkbox.value);
                });
            data.groups = selectedGroups;
        }

        // Zbieranie danych pytań
        const questionDivs = document.querySelectorAll(".question");
        for (const questionDiv of questionDivs) {
            const questionTextElement =
                questionDiv.querySelector(".question-text");
            const questionText = getTinyMCEContent(questionTextElement).trim();
            const questionType =
                questionDiv.querySelector(".question-type").value;

            let questionPoints;
            const pointsInput = questionDiv.querySelector(
                ".question-points-input"
            );
            const pointsValueInput = questionDiv.querySelector(
                ".points-value-input"
            );

            if (
                pointsInput &&
                pointsInput.closest(".question-points-div").style.display !==
                    "none"
            ) {
                questionPoints = parseInt(pointsInput.value);
            } else if (
                pointsValueInput &&
                pointsValueInput.closest(".question-points-type-div").style
                    .display !== "none"
            ) {
                questionPoints = parseInt(pointsValueInput.value);
            } else {
                alert("Brak pola punktów.");
                return;
            }

            const questionId = questionDiv.dataset.questionId || null;

            if (!questionText) {
                alert("Treść pytania nie może być pusta.");
                return;
            }

            if (!questionPoints || questionPoints < 1) {
                alert("Punkty za pytanie muszą być większe niż 0.");
                return;
            }

            let questionData = {
                id: questionId,
                question_text: questionText,
                type: questionType,
                points: questionPoints,
            };

            if (questionType === "multiple_choice") {
                const pointsTypeElement = questionDiv.querySelector(
                    ".question-points-type"
                );
                questionData.points_type = pointsTypeElement
                    ? pointsTypeElement.value
                    : "full";
            }

            // Jeżeli pytanie otwarte, pobieramy expected_code + language
            if (questionType === "open") {
                const codeTextarea = questionDiv.querySelector(".code-input");
                const expectedCode = codeTextarea.CodeMirrorInstance
                    ? codeTextarea.CodeMirrorInstance.getValue().trim()
                    : codeTextarea.value.trim();

                if (!expectedCode) {
                    alert('Pole "Oczekiwany kod" nie może być puste.');
                    return;
                }

                // Pobieramy też wartość selecta "open-question-language"
                const languageSelect = questionDiv.querySelector(
                    ".open-question-language"
                );
                const language = languageSelect ? languageSelect.value : "php";

                questionData.expected_code = expectedCode;
                questionData.language = language;
            } else {
                const answerInputs =
                    questionDiv.querySelectorAll(".answer-input");
                if (answerInputs.length === 0) {
                    alert("Pytanie musi zawierać co najmniej jedną odpowiedź.");
                    return;
                }
                const answers = [];
                let hasCorrectAnswer = false;
                for (const answerDiv of answerInputs) {
                    const answerTextElement =
                        answerDiv.querySelector(".answer-text");
                    const text = getTinyMCEContent(answerTextElement).trim();
                    const isCorrect =
                        answerDiv.querySelector(".answer-correct").checked;
                    const answerId = answerDiv.dataset.answerId || null;

                    if (!text) {
                        alert("Pola odpowiedzi nie mogą być puste.");
                        return;
                    }
                    if (isCorrect) {
                        hasCorrectAnswer = true;
                    }

                    let answerData = {
                        text: text,
                        is_correct: isCorrect,
                    };
                    if (answerId) {
                        answerData.id = answerId;
                    }
                    answers.push(answerData);
                }
                if (!hasCorrectAnswer) {
                    alert(
                        "Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna."
                    );
                    return;
                }
                questionData.answers = answers;
            }

            data.questions.push(questionData);
        }

        // Wysyłka do /quizzes/{quizId}/saveAll
        const response = await fetch(`/quizzes/${quizId}/saveAll`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify(data),
        });

        if (response.status === 401) {
            alert("Sesja wygasła. Proszę zaloguj się ponownie.");
            window.location.href = "/login";
            return;
        }
        if (!response.ok) {
            const text = await response.text();
            throw new Error("Błąd: " + response.status + " " + text);
        }

        const responseData = await response.json();
        alert("Quiz i wszystkie pytania zostały zapisane pomyślnie.");

        // Quiz staje się nieaktywny po zapisie
        const quizStatusElement = document.getElementById("quiz-status");
        quizStatusElement.innerText = "Nieaktywny";
        quizStatusElement.classList.remove("text-green-600");
        quizStatusElement.classList.add("text-red-600");
    } catch (error) {
        console.error("Error:", error);
        alert(
            "Wystąpił błąd podczas zapisywania quizu lub pytań: " +
                error.message
        );
    }
}

// Funkcja do przełączania pola limitu czasu
function toggleTimeLimitField() {
    const enableTimeLimit = document.getElementById("enable-time-limit").checked;
    const timeLimitField = document.getElementById("time-limit-field");
    timeLimitField.style.display = enableTimeLimit ? "block" : "none";
}

document.addEventListener("DOMContentLoaded", function () {
    toggleTimeLimitField();
    togglePassingScoreFields();
});

function handlePublicCheckbox(checkbox) {
    const groupCheckboxes = document.querySelectorAll(
        '#group-checkboxes input[name="groups[]"]'
    );
    groupCheckboxes.forEach((cb) => {
        cb.disabled = checkbox.checked;
        if (checkbox.checked) {
            cb.checked = false;
        }
    });
}

async function saveQuestion(button) {
    const questionDiv = button.closest(".question");
    let questionId = questionDiv.dataset.questionId;
    const questionTextElement = questionDiv.querySelector(".question-text");
    const questionText = getTinyMCEContent(questionTextElement).trim();
    const questionType = questionDiv.querySelector(".question-type").value;

    let pointsInput = questionDiv.querySelector(".question-points-input");
    let pointsValueInput = questionDiv.querySelector(".points-value-input");
    let points;
    if (
        pointsInput &&
        pointsInput.closest(".question-points-div").style.display !== "none"
    ) {
        points = parseInt(pointsInput.value);
    } else if (
        pointsValueInput &&
        pointsValueInput.closest(".question-points-type-div").style.display !==
            "none"
    ) {
        points = parseInt(pointsValueInput.value);
    } else {
        alert("Brak pola punktów.");
        return;
    }

    if (!questionText) {
        alert("Treść pytania nie może być pusta.");
        return;
    }

    if (!points || points < 1) {
        alert("Punkty za pytanie muszą być większe niż 0.");
        return;
    }

    let data = {
        question_text: questionText,
        type: questionType,
        points: points,
    };

    if (questionType === "multiple_choice") {
        const pointsTypeElement = questionDiv.querySelector(
            ".question-points-type"
        );
        data.points_type = pointsTypeElement ? pointsTypeElement.value : "full";
    }

    // Jeśli pytanie nie istnieje w DB, trzeba dodać quiz_id
    if (!questionId) {
        data.quiz_id = quizId;
    }

    if (questionType === "open") {
        // Odczyt kodu i języka
        const codeTextarea = questionDiv.querySelector(".code-input");
        const expectedCode = codeTextarea.CodeMirrorInstance
            ? codeTextarea.CodeMirrorInstance.getValue().trim()
            : codeTextarea.value.trim();

        if (!expectedCode) {
            alert('Pole "Oczekiwany kod" nie może być puste.');
            return;
        }
        data.expected_code = expectedCode;

        // Odczyt języka
        const languageSelect = questionDiv.querySelector(
            ".open-question-language"
        );
        const language = languageSelect ? languageSelect.value : "php";
        data.language = language;
    } else {
        const answerInputs = questionDiv.querySelectorAll(".answer-input");
        if (answerInputs.length === 0) {
            alert("Pytanie musi zawierać co najmniej jedną odpowiedź.");
            return;
        }
        const answers = [];
        let hasCorrectAnswer = false;
        for (const answerDiv of answerInputs) {
            const answerTextElement = answerDiv.querySelector(".answer-text");
            const text = getTinyMCEContent(answerTextElement).trim();
            const isCorrect =
                answerDiv.querySelector(".answer-correct").checked;
            const answerId = answerDiv.dataset.answerId || null;

            if (!text) {
                alert("Pola odpowiedzi nie mogą być puste.");
                return;
            }
            if (isCorrect) {
                hasCorrectAnswer = true;
            }

            let answerData = {
                text: text,
                is_correct: isCorrect,
            };
            if (answerId) {
                answerData.id = answerId;
            }
            answers.push(answerData);
        }
        if (!hasCorrectAnswer) {
            alert(
                "Przynajmniej jedna odpowiedź musi być zaznaczona jako poprawna."
            );
            return;
        }
        data.answers = answers;
    }

    const method = questionId ? "PUT" : "POST";
    const url = questionId ? `/questions/${questionId}` : "/questions";

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
            body: JSON.stringify(data),
        });

        if (response.status === 401) {
            alert("Sesja wygasła. Proszę zaloguj się ponownie.");
            window.location.href = "/login";
            return;
        }
        if (!response.ok) {
            const text = await response.text();
            throw new Error("Błąd: " + response.status + " " + text);
        }

        const responseData = await response.json();
        if (!questionId) {
            questionDiv.dataset.questionId = responseData.question_id;
            questionId = responseData.question_id;
        }

        if (data.answers) {
            const answerDivs = questionDiv.querySelectorAll(".answer-input");
            for (let i = 0; i < answerDivs.length; i++) {
                const answerDiv = answerDivs[i];
                answerDiv.dataset.answerId = null;
            }
        }

        alert(responseData.message);
    } catch (error) {
        console.error("Error:", error);
        alert("Wystąpił błąd podczas zapisywania pytania: " + error.message);
    }
}

function deleteQuestion(button) {
    const questionDiv = button.closest(".question");
    const questionId = questionDiv.dataset.questionId;

    // Bezpiecznie usuń edytory TinyMCE (jeśli istnieją)
    if (typeof tinymce !== "undefined" && tinymce && tinymce.editors) {
        const editors = questionDiv.querySelectorAll(".tinymce-editor");
        editors.forEach((editorElement) => {
            const editor = tinymce.editors.find(
                (ed) => ed.targetElm === editorElement
            );
            if (editor) {
                editor.remove();
            }
        });
    }

    // Usuń CodeMirror
    const codeTextarea = questionDiv.querySelector(".code-input");
    if (codeTextarea?.CodeMirrorInstance) {
        codeTextarea.CodeMirrorInstance.toTextArea();
    }

    if (!questionId) {
        questionDiv.remove();
        return;
    }

    if (!confirm("Czy na pewno chcesz usunąć to pytanie?")) {
        return;
    }

    fetch(`/questions/${questionId}`, {
        method: "DELETE",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
    })
        .then((response) => {
            // ...
            return response.json();
        })
        .then((data) => {
            alert(data.message);
            questionDiv.remove();
        })
        .catch((error) => {
            console.error("Error:", error);
            alert(error.message);
        });
}

function addQuestion() {
    const questionsSection = document.getElementById("questions-section");
    const newQuestionDiv = document.createElement("div");
    const newQuestionId = "new-question-" + Date.now();

    newQuestionDiv.classList.add(
        "question",
        "mb-6",
        "p-4",
        "border",
        "border-gray-300",
        "rounded"
    );
    newQuestionDiv.innerHTML = `
        <label class="block font-bold mb-2">Treść Pytania:</label>
        <textarea id="${newQuestionId}" class="question-text tinymce-editor w-full mb-4 p-2 border border-gray-300 rounded" placeholder="Nowe pytanie"></textarea>
        
        <label class="block font-bold mb-2">Typ pytania:</label>
        <select class="shadow border rounded w-full py-2 px-3 text-gray-700 question-type" required onchange="toggleAnswerSection(this)">
            <option value="multiple_choice" selected>Wielokrotnego wyboru</option>
            <option value="single_choice">Jednokrotnego wyboru</option>
            <option value="open">Otwarte</option>
        </select>

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

        <div class="question-points-div mb-4" style="display: none;">
            <label class="block font-bold mb-2">Punkty za pytanie:</label>
            <input type="number" class="shadow border rounded w-full py-2 px-3 text-gray-700 question-points-input" name="points" value="1" min="1">
        </div>

        <div class="answers-section mb-4"></div>
        
        <div class="flex justify-between mt-4">
            <button type="button" onclick="saveQuestion(this)" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Zapisz Pytanie</button>
            <button type="button" onclick="deleteQuestion(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">Usuń Pytanie</button>
        </div>
    `;
    questionsSection.appendChild(newQuestionDiv);

    // Inicjalizacja TinyMCE tylko dla tego nowego textarea
    tinymce.init({
        selector: `#${newQuestionId}`,
        menubar: false,
        plugins: "lists link image table code",
        toolbar: "undo redo | bold italic underline | bullist numlist | link table | code",
        branding: false,
        license_key: "gpl",
        forced_root_block: "div",
        height: 200,
        setup: function (editor) {
            editor.on("change", function () {
                editor.save();
            });
        },
    });

    // Wywołujemy toggleAnswerSection, aby ustawić pola w zależności od domyślnego typ pytania
    const selectElement = newQuestionDiv.querySelector(".question-type");
    toggleAnswerSection(selectElement);
}

function toggleAnswerSection(selectElement) {
    const questionDiv = selectElement.closest(".question");
    const questionType = selectElement.value;
    const answersSection = questionDiv.querySelector(".answers-section");
    const pointsTypeDiv = questionDiv.querySelector(
        ".question-points-type-div"
    );
    const pointsInputDiv = questionDiv.querySelector(".question-points-div");

    // Usunięcie poprzedniej konfiguracji tylko jeśli typ pytania się zmienił
    if (questionDiv.dataset.previousQuestionType !== questionType) {
        // Reset zawartości
        answersSection.innerHTML = "";

        // (Ostrożnie) usunięcie instancji CodeMirror, jeśli istnieje
        const existingCodeTextarea =
            answersSection.querySelector(".code-input");
        if (existingCodeTextarea && existingCodeTextarea.CodeMirrorInstance) {
            existingCodeTextarea.CodeMirrorInstance.toTextArea();
        }

        if (questionType === "open") {
            // Dodaj select języka, pole kodu i zainicjuj CodeMirror
            answersSection.innerHTML = `
                <label class="block font-bold mb-2">Język:</label>
                <select class="open-question-language shadow border rounded w-full py-2 px-3 text-gray-700" onchange="updateCodeMirrorMode(this)">
                    <option value="php">PHP</option>
                    <option value="java">Java</option>
                </select>

                <label class="block font-bold mb-2 mt-4">Oczekiwany kod:</label>
                <textarea class="code-input w-full mb-4 p-2 border border-gray-300 rounded"></textarea>
            `;

            // Znajdujemy textarea i language select:
            const codeTextarea = answersSection.querySelector(".code-input");
            const languageSelect = answersSection.querySelector(
                ".open-question-language"
            );

            // Ustawiamy domyślny kod w zależności od opcji w <select> (tu default "php")
            let defaultCode = `<?php
function test($a, $b) {

    // Twój kod tutaj (PHP)

}
`;

            codeTextarea.value = defaultCode;

            // Inicjalizacja CodeMirror domyślnie w trybie PHP
            const editor = CodeMirror.fromTextArea(codeTextarea, {
                lineNumbers: true,
                mode: { name: "php", startOpen: true },
                theme: "monokai",
                tabSize: 2,
            });
            codeTextarea.CodeMirrorInstance = editor;
        } else if (
            questionType === "multiple_choice" ||
            questionType === "single_choice"
        ) {
            // Dla pytań zamkniętych dodajemy przycisk 'Dodaj odpowiedź'
            answersSection.innerHTML = `
                <button type="button" onclick="addAnswer(this)" class="bg-green-500 hover:bg-green-700 text-white font-bold py-1 px-2 rounded mt-2">Dodaj odpowiedź</button>
            `;
        }

        // Zapamiętaj bieżący typ pytania
        questionDiv.dataset.previousQuestionType = questionType;
    }

    // Pokaż/ukryj odpowiednie pola punktów
    if (questionType === "multiple_choice") {
        if (pointsTypeDiv) pointsTypeDiv.style.display = "block";
        if (pointsInputDiv) pointsInputDiv.style.display = "none";
    } else {
        if (pointsInputDiv) pointsInputDiv.style.display = "block";
        if (pointsTypeDiv) pointsTypeDiv.style.display = "none";
    }

    // Aktualizacja nazwy dla radio buttonów
    resetAnswerSelections(questionDiv);
    updateRadioNames();
}

function updateCodeMirrorMode(selectElement) {
    const language = selectElement.value; // "php" lub "java"
    const questionDiv = selectElement.closest(".question");
    const codeTextarea = questionDiv.querySelector(".code-input");
    if (!codeTextarea || !codeTextarea.CodeMirrorInstance) {
        return;
    }

    // Ustal nowy 'mode' dla CodeMirror:
    if (language === "java") {
        codeTextarea.CodeMirrorInstance.setOption("mode", "text/x-java");
    } else {
        // default php
        codeTextarea.CodeMirrorInstance.setOption("mode", {
            name: "php",
            startOpen: true,
        });
    }

    // Opcjonalnie wstaw minimalny szablon kodu, jeśli user nie wpisał własnego:
    let currentContent = codeTextarea.CodeMirrorInstance.getValue().trim();
    if (
        !currentContent ||
        currentContent.startsWith("<?php") ||
        currentContent.startsWith("public class Code")
    ) {
        // Zakładamy, że mamy do czynienia z poprzednim szablonem, wstawiamy nowy
        let defaultCode;
        if (language === "java") {
            // Przykład minimalnego Java:
            defaultCode = `public class Code {

   public static void main(String[] args) { 
   }
}
`;
        } else {
            // Domyślnie PHP:
            defaultCode = `<?php
function test($a, $b) {

    // Twój kod tutaj (PHP)

}
`;
        }
        codeTextarea.CodeMirrorInstance.setValue(defaultCode);
    }
}

function addAnswer(button) {
    const answersSection = button.parentElement;
    const questionDiv = button.closest(".question");
    const questionType = questionDiv.querySelector(".question-type").value;
    const newAnswerId = "new-answer-" + Date.now();

    const newAnswerDiv = document.createElement("div");
    newAnswerDiv.classList.add("answer-input", "flex", "items-center", "mb-2");
    newAnswerDiv.innerHTML = `
        <textarea id="${newAnswerId}" class="answer-text tinymce-editor w-full p-2 border border-gray-300 rounded mr-2" placeholder="Nowa odpowiedź"></textarea>
        ${
            questionType === "single_choice"
                ? `<input type="radio" class="answer-correct mr-2">`
                : `<input type="checkbox" class="answer-correct mr-2">`
        }
        <button type="button" onclick="removeAnswer(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-2 rounded">Usuń</button>
    `;
    answersSection.insertBefore(newAnswerDiv, button);

    tinymce.init({
        selector: `#${newAnswerId}`,
        license_key: 'gpl',
        plugins: "advlist autolink link image lists charmap preview",
        toolbar:
            "undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat",
        menubar: false,
        forced_root_block: "div",
    });

    updateRadioNames();
}

function removeAnswer(button) {
    const answerDiv = button.parentElement;
    const answerTextElement = answerDiv.querySelector(".answer-text");
    const answerId = answerDiv.dataset.answerId;

    const editor = tinymce.editors.find(
        (ed) => ed.targetElm === answerTextElement
    );
    if (editor) {
        editor.remove();
    }

    if (answerId) {
        if (!confirm("Czy na pewno chcesz usunąć tę odpowiedź?")) {
            return;
        }

        fetch(`/answers/${answerId}`, {
            method: "DELETE",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        })
            .then((response) => {
                if (response.status === 401) {
                    alert("Sesja wygasła. Proszę zaloguj się ponownie.");
                    window.location.href = "/login";
                    return;
                }
                if (!response.ok) {
                    return response.text().then((text) => {
                        throw new Error(
                            "Błąd: " + response.status + " " + text
                        );
                    });
                }
                return response.json();
            })
            .then((data) => {
                alert(data.message);
                answerDiv.remove();
            })
            .catch((error) => {
                console.error("Error:", error);
                alert(error.message);
            });
    } else {
        answerDiv.remove();
    }
}

function resetAnswerSelections(questionDiv) {
    const answerCorrectInputs = questionDiv.querySelectorAll(".answer-correct");
    answerCorrectInputs.forEach((input) => {
        input.checked = false;
    });
}

function updateRadioNames() {
    const questions = document.querySelectorAll(".question");
    questions.forEach((questionDiv, index) => {
        const questionId = questionDiv.dataset.questionId || `new_${index}`;
        const questionType = questionDiv.querySelector(".question-type").value;
        if (questionType === "single_choice") {
            const answerCorrectInputs = questionDiv.querySelectorAll(
                '.answer-correct[type="radio"]'
            );
            answerCorrectInputs.forEach((input) => {
                input.name = `correct_answer_${questionId}`;
            });
        }
    });
}

async function toggleQuizStatus() {
    try {
        const response = await fetch(`/quizzes/${quizId}/toggleStatus`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": csrfToken,
            },
        });
        // ...
        const responseData = await response.json();
        alert(responseData.message);

        const quizStatusElement = document.getElementById("quiz-status");
        quizStatusElement.innerText = responseData.is_active ? "Aktywny" : "Nieaktywny";
        quizStatusElement.classList.toggle("text-green-600", responseData.is_active);
        quizStatusElement.classList.toggle("text-red-600", !responseData.is_active);
    } catch (error) {
        console.error("Error:", error);
        alert("Błąd toggleQuizStatus: " + error.message);
    }
}

// Funkcja przełącza wyświetlanie pól do ustawienia punktowego lub procentowego progu zdawalności
function togglePassingScoreFields() {
    const passingType = document.getElementById("passing-type").value;
    document.getElementById("passing-score-field").style.display =
        passingType === "points" ? "block" : "none";
    document.getElementById("passing-percentage-field").style.display =
        passingType === "percentage" ? "block" : "none";
}

function togglePointsField(selectElement) {
    const pointsType = selectElement.value;
    const pointsValueDiv = selectElement.closest(".question-points-type-div").querySelector(".points-value-div");
    if (pointsType === "full") {
        pointsValueDiv.querySelector(".points-label").innerText =
            "Punkty za wszystkie poprawne odpowiedzi:";
    } else if (pointsType === "partial") {
        pointsValueDiv.querySelector(".points-label").innerText =
            "Punkty za każdą poprawną odpowiedź:";
    }
}
