<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    /**
     * Wyświetla stronę rozwiązywania quizu.
     *
     * @param Quiz $quiz Quiz, który użytkownik będzie rozwiązywał.
     * @return \Illuminate\View\View Zwraca widok strony, na której użytkownik może rozwiązać quiz.
     */
    public function solve(Quiz $quiz)
    {
        return view('quizzes.solve', compact('quiz'));
    }

    /**
     * Przetwarza odpowiedzi użytkownika na quiz i zapisuje je w bazie danych.
     *
     * @param Quiz $quiz Quiz, który użytkownik rozwiązał.
     * @param Request $request Obiekt żądania zawierający odpowiedzi użytkownika.
     * @return \Illuminate\Http\RedirectResponse Przekierowuje użytkownika na stronę wyników po przetworzeniu odpowiedzi.
     */
    public function submitAnswers(Quiz $quiz, Request $request)
    {
        $userId = Auth::id();
        $answers = $request->input('answers'); // Pobranie odpowiedzi z formularza

        // Generowanie nowego UUID dla bieżącego podejścia
        $attemptUuid = (string) Str::uuid(); // Generowanie unikalnego UUID dla tego podejścia

        Log::info('Generowanie UUID dla podejścia:', ['attempt_uuid' => $attemptUuid]);

        // Logika przetwarzania odpowiedzi
        foreach ($quiz->questions as $question) {
            // Sprawdzenie odpowiedzi użytkownika
            $userAnswer = $answers[$question->id] ?? null;

            // Logowanie odpowiedzi użytkownika
            Log::info('Przetwarzanie pytania:', [
                'question_id' => $question->id,
                'user_answer' => $userAnswer,
                'attempt_uuid' => $attemptUuid,
            ]);

            if (is_null($userAnswer) || trim($userAnswer) === '') {
                // Jeśli odpowiedź jest pusta, oznaczamy ją jako błędną
                UserAnswer::create([
                    'user_id' => $userId,
                    'question_id' => $question->id,
                    'selected_option' => null,
                    'answer' => null,
                    'is_correct' => false,
                    'attempt_uuid' => $attemptUuid, // Zapisujemy UUID podejścia
                ]);

                Log::info('Odpowiedź pusta - zapis do bazy:', [
                    'question_id' => $question->id,
                    'is_correct' => false,
                    'attempt_uuid' => $attemptUuid,
                ]);
                continue;
            }

            // Przetwarzanie odpowiedzi (pytania zamknięte)
            if (is_null($question->expected_code)) {
                $isCorrect = trim($userAnswer) === trim($question->correct_option);

                UserAnswer::create([
                    'user_id' => $userId,
                    'question_id' => $question->id,
                    'selected_option' => $userAnswer,
                    'answer' => null,
                    'is_correct' => $isCorrect,
                    'attempt_uuid' => $attemptUuid, // Zapisujemy UUID podejścia
                ]);

                Log::info('Odpowiedź zamknięta - zapis do bazy:', [
                    'question_id' => $question->id,
                    'user_answer' => $userAnswer,
                    'is_correct' => $isCorrect,
                    'attempt_uuid' => $attemptUuid,
                ]);
            } else {
                // Przetwarzanie odpowiedzi (pytania otwarte)
                $this->handleOpenQuestion($userId, $question, $userAnswer, $attemptUuid);
            }
        }

        // Po przesłaniu odpowiedzi przekierowanie na stronę wyników
        return redirect()->route('quizzes.results', $quiz);
    }

    /**
     * Obsługuje pytania otwarte, weryfikując odpowiedzi użytkownika poprzez testowanie kodu.
     *
     * @param int $userId ID użytkownika.
     * @param object $question Pytanie quizowe.
     * @param string $userAnswer Odpowiedź użytkownika (kod).
     * @param string $attemptUuid UUID bieżącego podejścia.
     */
    protected function handleOpenQuestion($userId, $question, $userAnswer, $attemptUuid)
    {
        // Logowanie odpowiedzi użytkownika
        Log::info('Obsługa pytania otwartego:', [
            'user_id' => $userId,
            'question_id' => $question->id,
            'user_answer' => $userAnswer,
            'attempt_uuid' => $attemptUuid,
        ]);

        // Sprawdzenie, czy odpowiedź użytkownika jest pusta
        if (trim($userAnswer) === '') {
            // Jeśli odpowiedź jest pusta, oznaczamy ją jako błędną i zapisujemy wynik
            UserAnswer::create([
                'user_id' => $userId,
                'question_id' => $question->id,
                'selected_option' => null,
                'answer' => null,
                'is_correct' => false,
                'attempt_uuid' => $attemptUuid, // Zapisanie UUID podejścia
            ]);

            Log::info('Odpowiedź otwarta pusta - zapis do bazy:', [
                'question_id' => $question->id,
                'is_correct' => false,
                'attempt_uuid' => $attemptUuid,
            ]);

            return;
        }

        // Przeprowadzenie testu kodu użytkownika
        $testResult = $this->runCodeTest($userAnswer, $question->expected_code);

        // Zapisanie odpowiedzi użytkownika i wyniku testu
        UserAnswer::create([
            'user_id' => $userId,
            'question_id' => $question->id,
            'selected_option' => null,
            'answer' => $userAnswer,
            'is_correct' => $testResult['is_correct'],
            'attempt_uuid' => $attemptUuid, // Zapisanie UUID podejścia
        ]);

        Log::info('Odpowiedź otwarta - zapis do bazy:', [
            'question_id' => $question->id,
            'user_answer' => $userAnswer,
            'is_correct' => $testResult['is_correct'],
            'attempt_uuid' => $attemptUuid,
        ]);
    }

    /**
     * Uruchamia testy kodu użytkownika w porównaniu do oczekiwanego kodu.
     *
     * @param string $userCode Kod użytkownika.
     * @param string $expectedCode Oczekiwany kod.
     * @return array Wynik testów kodu użytkownika i oczekiwanego kodu.
     */
    protected function runCodeTest($userCode, $expectedCode)
    {
        set_time_limit(120); // Ustawienie limitu czasu na wykonanie testu

        Log::info('Testowanie kodu:', [
            'user_code' => $userCode,
            'expected_code' => $expectedCode,
        ]);

        // Dodanie tagu PHP do kodu, jeśli go brakuje
        if (strpos($userCode, '<?php') !== 0) {
            $userCode = "<?php\n" . $userCode;
        }
        if (strpos($expectedCode, '<?php') !== 0) {
            $expectedCode = "<?php\n" . $expectedCode;
        }

        // Zastąpienie nazwy funkcji na "test"
        $userCode = $this->replaceFunctionNameWithTest($userCode);
        $expectedCode = $this->replaceFunctionNameWithTest($expectedCode);

        // Ustalanie liczby parametrów w funkcji i generowanie przypadków testowych
        $paramCount = $this->getFunctionParamCount($userCode);

        // Generowanie 10 losowych przypadków testowych
        $testCases = [];
        for ($i = 0; $i < 10; $i++) {
            $inputs = [];
            for ($j = 0; $j < $paramCount; $j++) {
                // Analiza typu danych i generowanie stringów lub liczb
                if ($this->isStringArgument($userCode, $j)) {
                    $inputs[] = $this->generateRandomString(); // Generowanie stringa
                } else {
                    $inputs[] = rand(-30, 30); // Generowanie liczby
                }
            }
            $testCases[] = ['input' => $inputs];
        }

        // Generowanie kodu testowego dla użytkownika i oczekiwanego wyniku
        $codeToRunUser = $userCode . "\n";
        $codeToRunExpected = $expectedCode . "\n";

        foreach ($testCases as $testCase) {
            $input = json_encode($testCase['input']);
            $codeToRunUser .= 'echo json_encode(call_user_func_array("test", ' . $input . ')) . "\n";';
            $codeToRunExpected .= 'echo json_encode(call_user_func_array("test", ' . $input . ')) . "\n";';
        }

        // Uruchomienie testów za pomocą Dockera
        $userResult = $this->executeCodeWithDocker($codeToRunUser, 15);
        $expectedResult = $this->executeCodeWithDocker($codeToRunExpected, 15);

        // Porównanie wyników użytkownika i oczekiwanych wyników
        return $this->compareResults($userResult, $expectedResult, $testCases);
    }

    /**
     * Zastępuje nazwę funkcji w kodzie na "test".
     *
     * @param string $code Kod użytkownika.
     * @return string Kod z zamienioną nazwą funkcji.
     */
    protected function replaceFunctionNameWithTest($code)
    {
        // Zastępuje dowolną nazwę funkcji w kodzie na "test"
        $matches = [];
        if (preg_match('/function\s+(\w+)\s*\(/', $code, $matches)) {
            $functionName = $matches[1];
            // Zamienia wszystkie wystąpienia nazwy funkcji na "test"
            $code = preg_replace('/function\s+' . $functionName . '\s*\(/', 'function test(', $code);
            $code = preg_replace('/\b' . $functionName . '\b/', 'test', $code);
        }
        return $code;
    }

    /**
     * Zwraca liczbę parametrów funkcji w kodzie.
     *
     * @param string $code Kod zawierający funkcję.
     * @return int Liczba parametrów w funkcji.
     */
    protected function getFunctionParamCount($code)
    {
        $pattern = '/function\s+\w+\s*\(([^)]*)\)/';
        if (preg_match($pattern, $code, $matches)) {
            $params = $matches[1];
            if (trim($params) === '') {
                return 0; // Jeśli brak parametrów
            }
            return count(array_filter(array_map('trim', explode(',', $params)))); // Liczenie parametrów
        }
        return 0;
    }

    /**
     * Wykonuje kod za pomocą Dockera i zwraca wynik.
     *
     * @param string $code Kod do uruchomienia.
     * @param int $timeout Limit czasu na wykonanie.
     * @return array Wynik wykonania kodu.
     */
    protected function executeCodeWithDocker($code, $timeout = 15)
    {
        $codeFile = tempnam(sys_get_temp_dir(), 'usercode_') . '.php';
        file_put_contents($codeFile, $code);

        // Uruchomienie kodu w kontenerze Docker
        $command = sprintf(
            'docker run --rm --net none --memory="64m" --cpus="0.5" -v %s:/app/code.php php:8.2-cli php /app/code.php',
            escapeshellarg($codeFile)
        );

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (is_resource($process)) {
            $startTime = time();
            $output = '';
            $errors = '';

            while (!feof($pipes[1]) || !feof($pipes[2])) {
                $output .= stream_get_contents($pipes[1]);
                $errors .= stream_get_contents($pipes[2]);

                if (time() - $startTime > $timeout) {
                    proc_terminate($process);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    unlink($codeFile);

                    Log::error('Przekroczono limit czasu', [
                        'code' => $code,
                        'output' => $output,
                        'error' => $errors,
                    ]);

                    return [
                        'output' => null,
                        'error' => 'Przekroczono limit czasu dla testu (' . $timeout . ' sekund)',
                    ];
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $return_value = proc_close($process);
            unlink($codeFile);

            Log::info('Wynik testu Dockera', [
                'code' => $code,
                'output' => $output,
                'error' => $errors,
            ]);

            if ($return_value !== 0) {
                return [
                    'output' => null,
                    'error' => trim($errors),
                ];
            } else {
                return [
                    'output' => trim($output),
                    'error' => null,
                ];
            }
        } else {
            unlink($codeFile);
            return [
                'output' => null,
                'error' => 'Nie udało się uruchomić procesu Dockera.',
            ];
        }
    }
    
    /**
     * Porównuje wyniki testów kodu użytkownika i oczekiwanego kodu.
     *
     * @param array $userResult Wynik kodu użytkownika.
     * @param array $expectedResult Wynik oczekiwanego kodu.
     * @param array $testCases Przypadki testowe.
     * @return array Wyniki porównania.
     */
    protected function compareResults($userResult, $expectedResult, $testCases)
    {
        $userOutputs = explode("\n", trim($userResult['output']));
        $expectedOutputs = explode("\n", trim($expectedResult['output']));

        $allPassed = true;
        $comparisons = [];

        foreach ($testCases as $index => $testCase) {
            $input = $testCase['input'];

            if (isset($userResult['error']) || isset($expectedResult['error'])) {
                Log::warning('Błąd testu:', ['user_result_error' => $userResult['error'], 'expected_result_error' => $expectedResult['error']]);
                return ['is_correct' => false, 'comparisons' => []];
            }

            $userOutput = json_decode($userOutputs[$index], true);
            $expectedOutput = json_decode($expectedOutputs[$index], true);

            $passed = ($userOutput === $expectedOutput);
            if (!$passed) {
                $allPassed = false;
            }

            $comparisons[] = [
                'input' => $input,
                'user_output' => $userOutput,
                'expected_output' => $expectedOutput,
                'passed' => $passed,
            ];
        }

        return [
            'is_correct' => $allPassed,
            'comparisons' => $comparisons,
        ];
    }

    /**
     * Sprawdza, czy dany argument funkcji jest stringiem, bazując na jego użyciu w kodzie.
     *
     * @param string $code Kod funkcji.
     * @param int $argIndex Indeks argumentu.
     * @return bool True, jeśli argument jest stringiem, false jeśli jest liczbą.
     */
    protected function isStringArgument($code, $argIndex)
    {
        // Analiza argumentów funkcji
        if (preg_match('/function\s+\w+\s*\(([^)]*)\)/', $code, $matches)) {
            $params = array_map('trim', explode(',', $matches[1]));
            if (isset($params[$argIndex])) {
                $paramName = $params[$argIndex];

                // Sprawdzanie, czy argument bierze udział w operacjach stringowych
                $stringFunctions = ['strtoupper', 'strtolower', 'substr', 'strlen', 'strrev'];
                foreach ($stringFunctions as $function) {
                    if (preg_match('/' . $function . '\s*\(\s*' . preg_quote($paramName) . '\s*\)/', $code)) {
                        return true; // Argument jest stringiem
                    }
                }

                // Sprawdzanie, czy argument bierze udział w operacjach matematycznych
                if (preg_match('/' . preg_quote($paramName) . '\s*[\+\-\*\/]/', $code)) {
                    return false; // Argument jest liczbą
                }
            }
        }

        // Domyślnie, zakładamy, że argument jest liczbą
        return false;
    }
 
    /**
     * Generuje losowy ciąg znaków.
     *
     * @param int $length Długość ciągu.
     * @return string Losowy ciąg znaków.
     */
    protected function generateRandomString($length = 8)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
