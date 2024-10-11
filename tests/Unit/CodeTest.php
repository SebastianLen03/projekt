<?php 

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\QuizController;
use ReflectionMethod;

class CodeTest extends TestCase
{
    protected function invokeProtectedMethod($object, $methodName, $parameters = [])
    {
        $reflection = new ReflectionMethod($object, $methodName);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $parameters);
    }

    public function testCorrectUserCode()
    {
        $quizController = new QuizController();
        
        $userCode = '<?php function add($a, $b) { return $a + $b; } ?>';
        $expectedCode = '<?php function add($a, $b) { return $a + $b; } ?>';

        // Wywołanie chronionej metody runCodeTest przy użyciu refleksji
        $result = $this->invokeProtectedMethod($quizController, 'runCodeTest', [$userCode, $expectedCode]);
        
        $this->assertTrue($result);
    }

    public function testIncorrectUserCode()
    {
        $quizController = new QuizController();

        $userCode = '<?php function add($a, $b) { return $a - $b; } ?>';
        $expectedCode = '<?php function add($a, $b) { return $a + $b; } ?>';

        // Wywołanie chronionej metody runCodeTest przy użyciu refleksji
        $result = $this->invokeProtectedMethod($quizController, 'runCodeTest', [$userCode, $expectedCode]);

        $this->assertFalse($result);
    }
}
