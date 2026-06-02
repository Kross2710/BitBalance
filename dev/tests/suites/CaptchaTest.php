<?php
/**
 * Test suite for the custom Anti-Spam Captcha system.
 */

require_once __DIR__ . '/../../../include/handlers/captcha.php';

class CaptchaTest {
    public $useDatabase = false; // Pure unit test (uses sessions, no DB needed)

    public function testCaptchaGenerationFormatting() {
        $_SESSION = [];
        
        $question = CustomCaptcha::generateCaptcha();
        
        // Question shape: "X op Y = ?"
        Assert::true((bool)preg_match('/^\d+ [\+\-\*] \d+ = \?$/', $question), "Captcha question format should match expression. Got: " . $question);
        
        // Assert session variables populated
        Assert::notNull($_SESSION['captcha_answer']);
        Assert::notNull($_SESSION['captcha_question']);
        Assert::notNull($_SESSION['captcha_time']);
        Assert::equals($question, $_SESSION['captcha_question']);
    }

    public function testCaptchaGenerationRules() {
        // Run generation 50 times to verify math boundaries (subtraction positive, multiplication small)
        for ($i = 0; $i < 50; $i++) {
            $_SESSION = [];
            $question = CustomCaptcha::generateCaptcha();
            $answer = $_SESSION['captcha_answer'];
            
            // Parse numbers and operator
            preg_match('/^(\d+) ([\+\-\*]) (\d+) = \?$/', $question, $matches);
            $num1 = (int)$matches[1];
            $op = $matches[2];
            $num2 = (int)$matches[3];
            
            if ($op === '-') {
                Assert::true($num1 >= $num2, "Subtraction must always yield a positive or zero result ($num1 - $num2)");
                Assert::equals($num1 - $num2, $answer);
            } elseif ($op === '*') {
                Assert::true($num1 <= 10, "Multiplication factors must be small (num1 = $num1)");
                Assert::true($num2 <= 10, "Multiplication factors must be small (num2 = $num2)");
                Assert::equals($num1 * $num2, $answer);
            } elseif ($op === '+') {
                Assert::equals($num1 + $num2, $answer);
            }
        }
    }

    public function testCaptchaVerificationFlow() {
        $_SESSION = [];
        
        // Generate captcha
        CustomCaptcha::generateCaptcha();
        $correctAnswer = $_SESSION['captcha_answer'];
        
        // Test wrong answer
        $wrongAnswer = $correctAnswer + 5;
        $verifyWrong = CustomCaptcha::verifyCaptcha($wrongAnswer);
        Assert::false($verifyWrong, "Verification should fail for an incorrect answer");
        
        // Session should be cleared after a verification attempt
        Assert::null($_SESSION['captcha_answer'] ?? null);
        
        // Generate again for correct answer test
        CustomCaptcha::generateCaptcha();
        $correctAnswer2 = $_SESSION['captcha_answer'];
        
        $verifyCorrect = CustomCaptcha::verifyCaptcha($correctAnswer2);
        Assert::true($verifyCorrect, "Verification should succeed for the correct answer");
        
        // Session should be cleared after verification
        Assert::null($_SESSION['captcha_answer'] ?? null);
    }

    public function testCaptchaTimeoutExpiration() {
        $_SESSION = [];
        
        CustomCaptcha::generateCaptcha();
        $correctAnswer = $_SESSION['captcha_answer'];
        
        // Mock time to be 305 seconds (more than 5 minutes) in the past
        $_SESSION['captcha_time'] = time() - 305;
        
        // Attempt verification with the correct answer. It should fail due to timeout!
        $verify = CustomCaptcha::verifyCaptcha($correctAnswer);
        Assert::false($verify, "Verification should fail for expired captcha");
        
        // Session keys must be cleared
        Assert::null($_SESSION['captcha_answer'] ?? null);
        Assert::null($_SESSION['captcha_time'] ?? null);
        Assert::null($_SESSION['captcha_question'] ?? null);
    }
}
