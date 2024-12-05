<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\UserAttempt;
use App\Models\UserAnswer;
use App\Models\QuizVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuizSolveController extends Controller
{
    // Pokaż quiz do rozwiązania
    public function solve($quizId)
    {
        $userId = Auth::id();
        $quiz = Quiz::findOrFail($quizId);

        // Sprawdzenie, czy quiz jest aktywny
        if (!$quiz->is_active) {
            return redirect()->route('user.dashboard')->with('message', 'Ten quiz nie jest aktywny.');
        }

        // Pobierz najnowszą wersję quizu
        $quizVersion = QuizVersion::where('quiz_id', $quizId)->latest('version_number')->first();

        if (!$quizVersion) {
            return redirect()->route('user.dashboard')->with('message', 'Brak dostępnej wersji tego quizu.');
        }

        // Sprawdzenie liczby podejść użytkownika do konkretnej wersji quizu
        $userAttempt = UserAttempt::where('user_id', $userId)
            ->where('quiz_version_id', $quizVersion->id)
            ->latest()
            ->first();

        if (!$quiz->multiple_attempts && $userAttempt) {
            return redirect()->route('user.dashboard')
                ->with('message', 'Osiągnąłeś maksymalną liczbę podejść do tej wersji quizu.');
        }

        // Pobranie ostatniego numeru podejścia do tej wersji quizu
        $lastAttempt = UserAttempt::where('user_id', $userId)
            ->where('quiz_version_id', $quizVersion->id)
            ->latest('attempt_number')
            ->first();

        $attemptNumber = $lastAttempt ? $lastAttempt->attempt_number + 1 : 1;

        // Jeśli użytkownik może przystąpić do quizu, tworzymy nowe podejście
        $userAttempt = UserAttempt::create([
            'user_id' => $userId,
            'quiz_version_id' => $quizVersion->id,
            'quiz_id' => $quizId,
            'attempt_number' => $attemptNumber,
            'started_at' => now(), // Zapisujemy czas rozpoczęcia
        ]);

        // Pobranie pytań i odpowiedzi z wersjonowanych tabel
        $questions = $quizVersion->versionedQuestions()->with('answers')->get();

        // Przekazanie quizu, wersji quizu, pytań oraz id podejścia do widoku
        return view('quizzes.solve', [
            'quiz' => $quiz,
            'quizVersion' => $quizVersion,
            'questions' => $questions,
            'userAttemptId' => $userAttempt->id,
        ]);
    }

    // Zapisz odpowiedzi użytkownika
    // Zapisz odpowiedzi użytkownika
    public function submitAnswers(Request $request, $quizId)
    {
        $user = Auth::user();
        $questionsInput = $request->input('questions', []);
        $userAttemptId = $request->input('user_attempt_id');

        // Pobierz istniejące podejście użytkownika
        $userAttempt = UserAttempt::findOrFail($userAttemptId);

        // Sprawdź, czy podejście należy do zalogowanego użytkownika
        if ($userAttempt->user_id !== $user->id) {
            return redirect()->route('user.dashboard')->with('message', 'Nie masz uprawnień do tego działania.');
        }

        // Pobierz wersję quizu
        $quizVersion = QuizVersion::findOrFail($userAttempt->quiz_version_id);

        $totalPoints = 0;

        // Pobranie pytań z wersji quizu
        $questions = $quizVersion->versionedQuestions()->with('answers')->get()->keyBy('id');

        // Przetwarzanie odpowiedzi użytkownika
        foreach ($questionsInput as $questionId => $response) {
            $question = $questions->get($questionId);

            if (!$question) {
                continue; // Pomijamy nieprawidłowe ID pytań
            }

            if ($question->type === 'open') {
                // Odpowiedź otwarta
                $openAnswer = $response['open_answer'] ?? null;

                if ($openAnswer !== null) {
                    // Pobierz oczekiwany kod
                    $answer = $question->answers->first(); // Zakładamy jedno oczekiwane rozwiązanie
                    $testResult = ['is_correct' => false];

                    if ($answer && $answer->expected_code) {
                        $testResult = $this->runCodeTest($openAnswer, $answer->expected_code);
                    }

                    // Ustal punktację
                    $questionScore = $testResult['is_correct'] ? $question->points : 0;

                    // Dodaj punkty do sumy
                    $totalPoints += $questionScore;

                    // Zapisz odpowiedź użytkownika
                    UserAnswer::create([
                        'user_id' => $user->id,
                        'attempt_id' => $userAttempt->id,
                        'quiz_version_id' => $quizVersion->id,
                        'versioned_question_id' => $question->id,
                        'open_answer' => $openAnswer,
                        'is_correct' => $testResult['is_correct'],
                        'score' => $questionScore,
                    ]);
                }
            } else {
                // Odpowiedzi zamknięte (single_choice, multiple_choice)
                $answersInput = $response['answers'] ?? [];

                $selectedAnswerIds = is_array($answersInput) ? $answersInput : [$answersInput];

                // Upewnij się, że identyfikatory odpowiedzi są liczbami
                $selectedAnswerIds = array_map('intval', $selectedAnswerIds);

                // Pobierz poprawne odpowiedzi
                $correctAnswerIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();

                if ($question->type === 'multiple_choice') {
                    if ($question->points_type === 'full') {
                        // Pełne punkty tylko jeśli wszystkie poprawne odpowiedzi są wybrane i żadna niepoprawna
                        $isCorrect = empty(array_diff($correctAnswerIds, $selectedAnswerIds)) && empty(array_diff($selectedAnswerIds, $correctAnswerIds));

                        $questionScore = $isCorrect ? $question->points : 0;
                    } elseif ($question->points_type === 'partial') {
                        // Częściowe punkty za każdą poprawną odpowiedź
                        $correctSelected = array_intersect($correctAnswerIds, $selectedAnswerIds);
                        $incorrectSelected = array_diff($selectedAnswerIds, $correctAnswerIds);

                        $pointsPerCorrect = $question->points; // Punkty za każdą poprawną odpowiedź
                        $questionScore = count($correctSelected) * $pointsPerCorrect;

                        // Opcjonalnie można odjąć punkty za błędne odpowiedzi
                        // $penaltyPerIncorrect = 1;
                        // $questionScore -= count($incorrectSelected) * $penaltyPerIncorrect;

                        // Upewnij się, że wynik nie jest ujemny
                        $questionScore = max($questionScore, 0);
                    } else {
                        $questionScore = 0;
                    }

                    // Dodaj punkty do sumy
                    $totalPoints += $questionScore;

                    // Zapisz odpowiedź użytkownika
                    UserAnswer::create([
                        'user_id' => $user->id,
                        'attempt_id' => $userAttempt->id,
                        'quiz_version_id' => $quizVersion->id,
                        'versioned_question_id' => $question->id,
                        'selected_answers' => implode(',', $selectedAnswerIds),
                        'is_correct' => $questionScore > 0,
                        'score' => $questionScore,
                    ]);
                } else {
                    // Pytanie jednokrotnego wyboru
                    $selectedAnswerId = intval($selectedAnswerIds[0]);
                    $answer = $question->answers->find($selectedAnswerId);

                    if ($answer) {
                        $isCorrect = $answer->is_correct;
                        $questionScore = $isCorrect ? $question->points : 0;

                        $totalPoints += $questionScore;

                        // Zapisz odpowiedź użytkownika
                        UserAnswer::create([
                            'user_id' => $user->id,
                            'attempt_id' => $userAttempt->id,
                            'quiz_version_id' => $quizVersion->id,
                            'versioned_question_id' => $question->id,
                            'versioned_answer_id' => $answer->id,
                            'is_correct' => $isCorrect,
                            'score' => $questionScore,
                        ]);
                    }
                }
            }
        }

        // Aktualizacja podejścia użytkownika z sumą punktów i czasem zakończenia
        $userAttempt->update([
            'score' => $totalPoints,
            'ended_at' => now(), // Zapisujemy czas zakończenia
        ]);

        // Przekierowanie na stronę wyników
        return redirect()->route('quizzes.user_attempts', ['quizId' => $quizId])
            ->with('message', 'Twoje odpowiedzi zostały zapisane. Uzyskałeś ' . $totalPoints . ' punktów.');
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
        set_time_limit(120);
        Log::info('Testowanie kodu:', ['user_code' => $userCode, 'expected_code' => $expectedCode]);

        if (strpos($userCode, '<?php') !== 0) {
            $userCode = "<?php\n" . $userCode;
        }
        if (strpos($expectedCode, '<?php') !== 0) {
            $expectedCode = "<?php\n" . $expectedCode;
        }

        $userCode = $this->replaceFunctionNameWithTest($userCode);
        $expectedCode = $this->replaceFunctionNameWithTest($expectedCode);

        $paramCount = $this->getFunctionParamCount($userCode);
        $testCases = [];

        for ($i = 0; $i < 10; $i++) {
            $inputs = [];
            for ($j = 0; $j < $paramCount; $j++) {
                $inputs[] = rand(-30, 30);
            }
            $testCases[] = ['input' => $inputs];
        }

        $codeToRunUser = $userCode . "\n";
        $codeToRunExpected = $expectedCode . "\n";

        foreach ($testCases as $testCase) {
            $input = json_encode($testCase['input']);
            $codeToRunUser .= 'echo json_encode(call_user_func_array("test", ' . $input . ')) . "\n";';
            $codeToRunExpected .= 'echo json_encode(call_user_func_array("test", ' . $input . ')) . "\n";';
        }

        $userResult = $this->executeCodeWithDocker($codeToRunUser, 15);
        $expectedResult = $this->executeCodeWithDocker($codeToRunExpected, 15);

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
        $matches = [];
        if (preg_match('/function\s+(\w+)\s*\(/', $code, $matches)) {
            $functionName = $matches[1];
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
                return 0;
            }
            return count(array_filter(array_map('trim', explode(',', $params))));
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
}
