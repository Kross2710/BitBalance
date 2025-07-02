<?php
require_once __DIR__ . '/log_attempt.php';

$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.password, u.role, u.profile_image,
                       us.status, us.failed_attempts, us.locked_until, us.theme_preference
                FROM user u 
                JOIN userStatus us ON u.user_id = us.user_id 
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error_message = "Invalid email or password.";
            } else {
                // Check if account is locked
                $now = new DateTime();
                $lockedUntil = $user['locked_until'] ? new DateTime($user['locked_until']) : null;
                
                if ($lockedUntil && $now < $lockedUntil) {
                    $remainingTime = $lockedUntil->diff($now);
                    $minutes = $remainingTime->i;
                    $seconds = $remainingTime->s;
                    $error_message = "Account is locked. Try again in {$minutes} minutes and {$seconds} seconds.";
                } elseif ($user['status'] === 'archived') {
                    $error_message = "This account has been archived. Please contact support.";
                } else {
                    // Clear lock if time has passed
                    if ($lockedUntil && $now >= $lockedUntil) {
                        $stmt = $pdo->prepare("
                            UPDATE userStatus 
                            SET failed_attempts = 0, locked_until = NULL 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user['user_id']]);
                        $user['failed_attempts'] = 0;
                    }
                    
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Login successful - reset failed attempts
                        $stmt = $pdo->prepare("
                            UPDATE userStatus 
                            SET failed_attempts = 0, locked_until = NULL 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$user['user_id']]);
                        
                        // Set session variables including profile image and theme
                        $_SESSION['user'] = [
                            'user_id' => $user['user_id'],
                            'user_name' => $user['user_name'],
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'profile_image' => $user['profile_image'],
                            'theme_preference' => $user['theme_preference'] ?? 'light'
                        ];

                        // Set user ID for further processing
                        $userId = $user['user_id'];
                        
                        // Update last login time
                        $stmt = $pdo->prepare("UPDATE user SET last_login = NOW() WHERE user_id = ?");
                        $stmt->execute([$userId]);

                        // Log the login attempt
                        log_attempt($pdo, $userId, 'login', 'User logged in successfully');

                        // Redirect to dashboard instead of index
                        header("Location: dashboard/dashboard.php");
                        exit();
                    } else {
                        // Password incorrect - increment failed attempts
                        $newFailedAttempts = $user['failed_attempts'] + 1;
                        
                        if ($newFailedAttempts >= 3) {
                            // Lock account for 1 hour
                            $lockUntil = new DateTime();
                            $lockUntil->add(new DateInterval('PT1H')); // Add 1 hour
                            
                            $stmt = $pdo->prepare("
                                UPDATE userStatus 
                                SET failed_attempts = ?, locked_until = ? 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$newFailedAttempts, $lockUntil->format('Y-m-d H:i:s'), $user['user_id']]);
                            
                            $error_message = "Account locked due to 3 failed login attempts. Try again in 1 hour.";
                        } else {
                            // Update failed attempts
                            $stmt = $pdo->prepare("
                                UPDATE userStatus 
                                SET failed_attempts = ? 
                                WHERE user_id = ?
                            ");
                            $stmt->execute([$newFailedAttempts, $user['user_id']]);
                            
                            $remainingAttempts = 3 - $newFailedAttempts;
                            $error_message = "Invalid email or password. {$remainingAttempts} attempts remaining.";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>