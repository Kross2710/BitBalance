<?php
class CustomCaptcha {
    
    public static function generateCaptcha() {
        // Generate random math problem
        $num1 = rand(1, 20);
        $num2 = rand(1, 20);
        $operations = ['+', '-', '*'];
        $operation = $operations[array_rand($operations)];
        
        switch($operation) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                // Ensure positive result
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
                break;
            case '*':
                // Keep numbers small for multiplication
                $num1 = rand(1, 10);
                $num2 = rand(1, 10);
                $answer = $num1 * $num2;
                break;
        }
        
        $question = "$num1 $operation $num2 = ?";
        
        // Store answer in session
        $_SESSION['captcha_answer'] = $answer;
        $_SESSION['captcha_time'] = time();
        $_SESSION['captcha_question'] = $question;
        
        return $question;
    }
    
    public static function verifyCaptcha($userAnswer) {
        // Check if captcha session exists and is not expired (5 minutes)
        if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check if captcha has expired (5 minutes = 300 seconds)
        if (time() - $_SESSION['captcha_time'] > 300) {
            unset($_SESSION['captcha_answer']);
            unset($_SESSION['captcha_time']);
            unset($_SESSION['captcha_question']);
            return false;
        }
        
        $isCorrect = (int)$userAnswer === (int)$_SESSION['captcha_answer'];
        
        // Clear captcha after verification attempt
        unset($_SESSION['captcha_answer']);
        unset($_SESSION['captcha_time']);
        unset($_SESSION['captcha_question']);
        
        return $isCorrect;
    }
    
    public static function generateImageCaptcha() {
        // Create image
        $width = 200;
        $height = 80;
        $image = imagecreate($width, $height);
        
        // Colors
        $bg_color = imagecolorallocate($image, 255, 255, 255);
        $text_color = imagecolorallocate($image, 0, 0, 0);
        $line_color = imagecolorallocate($image, 128, 128, 128);
        
        // Add background noise (lines)
        for ($i = 0; $i < 5; $i++) {
            imageline($image, rand(0, $width), rand(0, $height), 
                     rand(0, $width), rand(0, $height), $line_color);
        }
        
        // Generate math problem
        $num1 = rand(1, 20);
        $num2 = rand(1, 20);
        $operations = ['+', '-', '*'];
        $operation = $operations[array_rand($operations)];
        
        switch($operation) {
            case '+':
                $answer = $num1 + $num2;
                break;
            case '-':
                if ($num1 < $num2) {
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }
                $answer = $num1 - $num2;
                break;
            case '*':
                $num1 = rand(1, 10);
                $num2 = rand(1, 10);
                $answer = $num1 * $num2;
                break;
        }
        
        $captcha_text = "$num1 $operation $num2 = ?";
        
        // Store answer in session
        $_SESSION['captcha_answer'] = $answer;
        $_SESSION['captcha_time'] = time();
        $_SESSION['captcha_question'] = $captcha_text;
        
        // Add text to image
        $font_size = 5;
        $text_x = ($width - strlen($captcha_text) * imagefontwidth($font_size)) / 2;
        $text_y = ($height - imagefontheight($font_size)) / 2;
        
        imagestring($image, $font_size, $text_x, $text_y, $captcha_text, $text_color);
        
        // Output image
        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }
}
?>