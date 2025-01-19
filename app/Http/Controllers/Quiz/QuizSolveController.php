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

        $activeVersion = QuizVersion::where('quiz_id', $quizId)
            ->where('is_active', true)
            ->first();
    
        if (!$activeVersion) {
            return redirect()->route('user.dashboard')
                ->with('message', 'Ten quiz nie jest aktywny (brak aktywnej wersji).');
        }
    
        $quizVersion = $activeVersion;
    
        // Dalej bez zmian:
        // Sprawdzenie liczby podejść...
        $userAttempt = UserAttempt::where('user_id', $userId)
            ->where('quiz_version_id', $quizVersion->id)
            ->latest()
            ->first();
    
        if (!$quiz->multiple_attempts && $userAttempt) {
            return redirect()->route('user.dashboard')
                ->with('message', 'Osiągnąłeś maksymalną liczbę podejść do tej wersji quizu.');
        }
    
        $lastAttempt = UserAttempt::where('user_id', $userId)
            ->where('quiz_version_id', $quizVersion->id)
            ->latest('attempt_number')
            ->first();
    
        $attemptNumber = $lastAttempt ? $lastAttempt->attempt_number + 1 : 1;
    
        $userAttempt = UserAttempt::create([
            'user_id' => $userId,
            'quiz_version_id' => $quizVersion->id,
            'quiz_id' => $quizId,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
        ]);
    
        $questions = $quizVersion->versionedQuestions()->with('answers')->get();
    
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
                        $language = $answer->language ?? 'php'; // domyślnie php
                        $testResult = $this->runCodeTest($openAnswer, $answer->expected_code, $language);
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
 * Teraz dla Javy rezygnujemy z rename i wstrzykiwania automatycznego test(...).
 */
protected function runCodeTest($userCode, $expectedCode, $language = 'php')
{
    switch ($language) {
        case 'php':
            // Stary mechanizm rename i generowanie test(...) + testcases
            return $this->runPhpTest($userCode, $expectedCode);

        case 'java':
            // Nowe podejście – user ma 100% kontroli nad metodami
            return $this->runJavaTestNoRename($userCode, $expectedCode);

        default:
            return [
                'is_correct' => false,
                'comparisons' => [],
                'error' => 'Unsupported language'
            ];
    }
}

/**
 * Wersja runJavaTest pozwalająca na wiele metod w Javie,
 * bez rename do "test(...)". Użytkownik musi mieć (lub otrzyma) main(...),
 * który sam wywołuje testA, testB, itp.
 */
protected function runJavaTestNoRename($rawUserCode, $rawExpectedCode)
{
    Log::info('runJavaTestNoRename: Start');

    // 1) Przygotuj kod usera i expected (opakuj w "public class Code { ... }", jeśli brak)
    $userCode = $this->prepareJavaCodeNoRename($rawUserCode);
    $expectedCode = $this->prepareJavaCodeNoRename($rawExpectedCode);

    Log::info('runJavaTestNoRename: After prepareJavaCodeNoRename', [
        'userCode' => $userCode,
        'expectedCode' => $expectedCode,
    ]);

    // 2) Kompilacja + uruchom user code w Dockerze
    $userResult = $this->executeCodeWithDockerJava($userCode);
    Log::info('runJavaTestNoRename: userResult', $userResult);

    // 3) Kompilacja + uruchom expected code w Dockerze
    $expectedResult = $this->executeCodeWithDockerJava($expectedCode);
    Log::info('runJavaTestNoRename: expectedResult', $expectedResult);

    // 4) Porównanie wyjść liniowo
    $comparison = $this->compareResultsLineByLine($userResult, $expectedResult);

    Log::info('runJavaTestNoRename: compareResultsLineByLine', $comparison);

    return $comparison;
}

/**
 * Minimalne opakowanie w "public class Code { ... }" – bez rename metod.
 * Dodanie main(...) jeśli user nie wkleił żadnej.
 */
protected function prepareJavaCodeNoRename($rawCode)
{
    Log::info('prepareJavaCodeNoRename: Original code', [
        'length' => strlen($rawCode),
        'snippet' => substr($rawCode, 0, 200),
    ]);

    // Sprawdź, czy user wkleił "public class"
    if (!str_contains($rawCode, 'public class')) {
        Log::info('prepareJavaCodeNoRename: Wrapping user code in public class Code');
        $rawCode = "public class Code {\n" . $rawCode . "\n}\n";
    }

    // Czy user ma main?
    if (!$this->hasJavaMainMethod($rawCode)) {
        Log::info('prepareJavaCodeNoRename: No main found, adding minimal main');
        $rawCode .= "\npublic static void main(String[] args) {\n"
                  . "   // Placeholder main – user did not provide any\n"
                  . "   System.out.println(\"[INFO] (generated main) No user main provided.\");\n"
                  . "}\n";
    } else {
        Log::info('prepareJavaCodeNoRename: main(...) found, leaving code as-is');
    }

    return $rawCode;
}

/**
 * Sprawdza, czy w kodzie występuje "main(" (względnie proste),
 * co pozwala nam uznać, że user ma metodę main.
 */
protected function hasJavaMainMethod($code)
{
    return str_contains($code, 'main(');
}

/**
 * Porównuje wyjścia programów usera i expected line-by-line.
 * Dodaje logi dla wglądu.
 */
protected function compareResultsLineByLine($userResult, $expectedResult)
{
    // Sprawdź error (np. błąd kompilacji)
    if ($userResult['error'] || $expectedResult['error']) {
        Log::warning('compareResultsLineByLine: compilation/runtime error', [
            'userError' => $userResult['error'],
            'expError'  => $expectedResult['error'],
        ]);
        return [
            'is_correct' => false,
            'error' => trim($userResult['error'] . ' | ' . $expectedResult['error']),
            'comparisons' => [],
        ];
    }

    // Rozdziel output na linie
    $userLines = explode("\n", trim($userResult['output'] ?? ''));
    $expLines  = explode("\n", trim($expectedResult['output'] ?? ''));

    $max = max(count($userLines), count($expLines));
    $comparisons = [];
    $allPassed = true;

    for ($i = 0; $i < $max; $i++) {
        $uLine = $userLines[$i] ?? '';
        $eLine = $expLines[$i] ?? '';
        $passed = ($uLine === $eLine);
        if (!$passed) {
            $allPassed = false;
        }
        $comparisons[] = [
            'line_index' => $i,
            'user_line' => $uLine,
            'expected_line' => $eLine,
            'passed' => $passed,
        ];
    }

    Log::info('compareResultsLineByLine: done', [
        'allPassed' => $allPassed,
        'linesCompared' => $max,
    ]);

    return [
        'is_correct' => $allPassed,
        'comparisons' => $comparisons,
    ];
}






















    protected function runPhpTest($userCode, $expectedCode)
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

    // protected function runJavaTest($userCode, $expectedCode)
    // {
    //     Log::info('runJavaTest: userCode (raw)', ['code' => $userCode]);
    //     Log::info('runJavaTest: expectedCode (raw)', ['code' => $expectedCode]);
    
    //     // 1. Przygotuj (opakuj) userCode i expectedCode w klasę (o ile trzeba)
    //     //    + ewentualnie wymuś nazwę "test(...)"
    //     $userCode = $this->prepareJavaCode($userCode);
    //     $expectedCode = $this->prepareJavaCode($expectedCode);
    
    //     Log::info('runJavaTest: userCode (prepared)', ['code' => $userCode]);
    //     Log::info('runJavaTest: expectedCode (prepared)', ['code' => $expectedCode]);
    
    //     // 2. Określ liczbę parametrów, by generować testCases
    //     $paramCount = $this->getFunctionParamCountJava($userCode);
    //     $testCases = [];
    //     for ($i = 0; $i < 10; $i++) {
    //         $inputs = [];
    //         for ($j = 0; $j < $paramCount; $j++) {
    //             $inputs[] = rand(-30, 30);
    //         }
    //         $testCases[] = ['input' => $inputs];
    //     }
    
    //     Log::info('runJavaTest: testCases', $testCases);
    
    //     // 3. Doklej main(...) TYLKO jeśli user code NIE zawiera main
    //     $userHasMain = $this->userHasMainMethod($userCode); 
    //     $codeToRunUser = $userCode;
    //     if (!$userHasMain) {
    //         // nie ma main → dodajemy
    //         $codeToRunUser .= $this->generateMainJava($testCases);
    //     }
    
    //     // to samo dla expectedCode
    //     $expectedHasMain = $this->userHasMainMethod($expectedCode);
    //     $codeToRunExpected = $expectedCode;
    //     if (!$expectedHasMain) {
    //         $codeToRunExpected .= $this->generateMainJava($testCases);
    //     }
    
    //     Log::info('runJavaTest: codeToRunUser', ['code' => $codeToRunUser]);
    //     Log::info('runJavaTest: codeToRunExpected', ['code' => $codeToRunExpected]);
    
    //     // 4. Wykonaj w Dockerze
    //     $userResult = $this->executeCodeWithDockerJava($codeToRunUser);
    //     $expectedResult = $this->executeCodeWithDockerJava($codeToRunExpected);
    
    //     Log::info('runJavaTest: userResult', $userResult);
    //     Log::info('runJavaTest: expectedResult', $expectedResult);
    
    //     // 5. Porównaj
    //     $comparison = $this->compareResults($userResult, $expectedResult, $testCases);
    //     Log::info('runJavaTest: compareResults', $comparison);
    
    //     return $comparison;
    // }
    

    /**
 * Sprawdza, czy w kodzie Javy jest zdefiniowana metoda `public static void main(`.
 * Wystarczy proste wyszukiwanie substringu, choć można użyć regex.
 *
 * @param string $code
 * @return bool
 */
protected function userHasMainMethod($code)
{
    // Najprostsza wersja: case-insensitive poszukiwanie
    return stripos($code, 'public static void main(') !== false;
}

    
    /**
     * Umieszcza kod w klasie Code i ewentualnie wymusza nazwę funkcji na "test".
     */
    protected function prepareJavaCode($rawCode)
    {
        // 1. Sprawdź, czy user nie wkleił już "class Code"
        //    Jeśli nie - opakuj w "public class Code { ... }"
        if (!str_contains($rawCode, 'class Code')) {
            $rawCode = "public class Code {\n" . $rawCode . "\n}\n";
        }
    
        // 2. Ewentualnie wymuszamy nazwę "test" dla jedynej metody - uproszczenie:
        $pattern = '/public\s+static\s+(\w+)\s+(\w+)\s*\(/'; 
        if (preg_match($pattern, $rawCode, $matches)) {
            $returnType = $matches[1]; // np. int
            $oldName    = $matches[2]; // np. someFunc
            // Zamień starą nazwę na "test"
            $rawCode = preg_replace(
                '/public\s+static\s+' . $returnType . '\s+' . $oldName . '\s*\(/',
                "public static $returnType test(",
                $rawCode
            );
            // Ewentualnie można także poszukać wywołań "someFunc(" i zamienić na "test("
        }
    
        return $rawCode;
    }
    

    protected function getFunctionParamCountJava($code)
    {
        // Załóżmy, że w prepareJavaCode wymuszamy sygnaturę "public static X test(...)"
        // Więc wystarczy poszukać tego:
        $pattern = '/public\s+static\s+\w+\s+test\s*\(([^)]*)\)/';
        if (preg_match($pattern, $code, $matches)) {
            $paramsInside = trim($matches[1]); // np. "int a, int b"
            if ($paramsInside === '') {
                return 0;
            }
            // Rozbij po przecinkach i zlicz
            $params = explode(',', $paramsInside); // np. ["int a", " int b"]
            return count($params);
        }
        return 0; 
    }

    protected function generateMainJava($testCases)
    {
        $mainCode = "public static void main(String[] args) {\n";

        foreach ($testCases as $index => $test) {
            $inputs = $test['input']; // np. [-5, 10]
            // w Javie musisz zrzutować to na parametry int lub cokolwiek
            // np. int a = -5; int b = 10; System.out.println( ... )

            // zrobimy to dynamicznie
            // "int param0 = -5; int param1 = 10;"
            $lines = [];
            foreach ($inputs as $i => $val) {
                $lines[] = "int param{$i} = {$val};";
            }
            $mainCode .= "    " . implode(" ", $lines) . "\n";

            // Wywołanie test i wyprintowanie w JSON:
            // System.out.println( toJson( test(param0, param1) ) );
            // Ponieważ nie mamy natywnie toJson, robimy uproszczenie:
            // "System.out.println(\"\" + test(param0, param1));"

            $call = "test(";
            for ($i = 0; $i < count($inputs); $i++) {
                $call .= "param{$i}";
                if ($i < count($inputs) - 1) {
                    $call .= ", ";
                }
            }
            $call .= ")";

            // Wersja uproszczona: print w formie JSON np. "System.out.println(\"[\"+test(param0, param1)+\"]\");"
            // lepiej -> "System.out.println(test(param0, param1));"
            // i w "compareResults" robisz json_decode linii

            $mainCode .= "    System.out.println( $call );\n";
        }

        $mainCode .= "}\n"; // koniec main
        return $mainCode;
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

    protected function executeCodeWithDockerJava($code, $timeout = 15)
    {
        // Spróbuj znaleźć w kodzie nazwę klasy po 'public class ...'
        $className = $this->findPublicClassName($code);
    
        if (!$className) {
            // Brak publicznej klasy -> opakuj w "public class Code { ... }"
            $code = "public class Code {\n" . $code . "\n}\n";
            $className = "Code";
        }
    
        // Stwórz plik tymczasowy .java
        $tempFile = tempnam(sys_get_temp_dir(), 'usercode_');
        $javaFile = $tempFile . '.java';
    
        // Zapisz kod do pliku
        file_put_contents($javaFile, $code);
    
        // Przygotuj komendę do uruchomienia w Dockerze (javac, a potem java)
        $command = sprintf(
            'docker run --rm --net none --memory="64m" --cpus="0.5" '
            . '-v %s:/app/%s.java openjdk:17 '
            . 'bash -c "cd /app && javac %s.java && java %s"',
            escapeshellarg($javaFile),
            escapeshellarg($className),
            escapeshellarg($className),
            escapeshellarg($className)
        );
    
        Log::info('executeCodeWithDockerJava: command', ['command' => $command]);
    
        // Uruchamiamy proces i zbieramy output
        $descriptorSpec = [
            1 => ['pipe', 'w'], // STDOUT
            2 => ['pipe', 'w'], // STDERR
        ];
    
        $process = proc_open($command, $descriptorSpec, $pipes);
    
        if (is_resource($process)) {
            $startTime = time();
            $output = '';
            $errors = '';
    
            while (!feof($pipes[1]) || !feof($pipes[2])) {
                $output .= stream_get_contents($pipes[1]);
                $errors .= stream_get_contents($pipes[2]);
    
                // Kontrola czasu
                if (time() - $startTime > $timeout) {
                    proc_terminate($process);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    unlink($javaFile);
    
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
            unlink($javaFile);
    
            Log::info('executeCodeWithDockerJava: finished', [
                'output'       => $output,
                'error'        => $errors,
                'return_value' => $return_value
            ]);
    
            if ($return_value !== 0) {
                // Błąd kompilacji lub runtime
                return [
                    'output' => null,
                    'error'  => trim($errors),
                ];
            } else {
                // Sukces
                return [
                    'output' => trim($output),
                    'error'  => null,
                ];
            }
        } else {
            // Nie udało się nawet uruchomić Dockera
            unlink($javaFile);
            Log::error('executeCodeWithDockerJava: cannot open process');
            return [
                'output' => null,
                'error'  => 'Nie udało się uruchomić procesu Dockera.',
            ];
        }
    }

    /**
     * Szuka fragmentu "public class XYZ" w kodzie.
     * Jeśli znajdzie - zwraca "XYZ".
     * Jeśli w kodzie brak publicznej klasy, zwraca null.
     *
     * Uwaga: jeśli kod ma wiele publicznych klas, ta metoda zwróci pierwszą z brzegu,
     * co i tak może zakończyć się błędem kompilacji. Użytkownik musi wtedy poprawić kod.
     */
    protected function findPublicClassName($code)
    {
        if (preg_match('/\bpublic\s+class\s+([A-Za-z_]\w*)/', $code, $matches)) {
            return $matches[1];
        }
        return null;
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
