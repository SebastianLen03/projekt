<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CodeAnswerTest extends TestCase
{
    public function testUserCodeAnswer()
    {
        $userCode = '<?php echo "Hello, World!"; ?>';
        $expectedCode = '<?php echo "Hello, World!"; ?>';

        // Uruchomienie kodu w sandboxie
        ob_start();
        eval($userCode); // Nigdy nie używaj eval() w kodzie produkcyjnym bez odpowiednich zabezpieczeń
        $output = ob_get_clean();

        // Sprawdzenie, czy wynik jest zgodny z oczekiwanym
        $this->assertEquals('Hello, World!', $output);
    }

    public function testIncorrectUserCodeAnswer()
    {
        $userCode = '<?php echo "Hi, World!"; ?>';
        $expectedCode = '<?php echo "Hello, World!"; ?>';

        // Uruchomienie kodu w sandboxie
        ob_start();
        eval($userCode);
        $output = ob_get_clean();

        // Sprawdzenie, czy wynik nie jest zgodny z oczekiwanym
        $this->assertNotEquals('Hello, World!', $output);
    }
}
