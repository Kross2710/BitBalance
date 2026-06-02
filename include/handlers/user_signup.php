<?php
require_once __DIR__ . '/log_attempt.php';
require_once __DIR__ . '/username.php';

$error_message = '';
$success_message = '';

// Risk-based bot protection. The math captcha is NOT shown by default — that
// extra step measurably hurts real sign-ups. Two invisible checks run on every
// submit instead: a honeypot field and a minimum fill time. Only when one of
// them looks bot-like do we "escalate" — flag the session so the visible
// captcha is shown and required from then on, until a successful sign-up.
$SIGNUP_MIN_SECONDS = 2; // a human cannot complete the whole form faster than this

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    // Username is no longer entered by the user — it is auto-generated from the
    // first name as a Discord-style handle (e.g. "Hung4821"). See username.php.
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $captcha_answer = $_POST['captcha_answer'] ?? '';
    $accept_terms = isset($_POST['accept_terms']);
    $honeypot = trim($_POST['website'] ?? ''); // hidden decoy; real users leave it empty

    // Invisible bot heuristics. A filled honeypot or an implausibly fast submit
    // (or a POST with no prior form render) escalates the session to a captcha.
    $formShownAt = (int) ($_SESSION['signup_form_time'] ?? 0);
    $tooFast     = ($formShownAt === 0) || ((time() - $formShownAt) < $SIGNUP_MIN_SECONDS);
    if ($honeypot !== '' || $tooFast) {
        $_SESSION['signup_challenge'] = true;
    }
    $challengeActive = !empty($_SESSION['signup_challenge']);

    // Validation. The captcha is only enforced for challenged (suspicious)
    // sessions; everyone else skips it entirely.
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif (!$accept_terms) {
        $error_message = "You must accept the Terms and Conditions to create an account.";
    } elseif ($challengeActive && !CustomCaptcha::verifyCaptcha($captcha_answer)) {
        // Either a bot signal just fired (no puzzle was on screen yet) or the
        // answer was blank/wrong — a fresh puzzle is generated below.
        $error_message = "Quick check: please solve the puzzle below to continue.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $error_message = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = "An account with this email already exists.";
            } else {
                // Auto-generate a unique handle from the first name (Hung → Hung4821).
                $username = generate_handle($pdo, $first_name);

                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user. The UNIQUE key on user_name is the final guard
                // against a rare race; the outer catch surfaces a retry message.
                $stmt = $pdo->prepare("INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, 'regular', NOW())");
                $stmt->execute([$username, $first_name, $last_name, $email, $hashed_password]);

                $user_id = $pdo->lastInsertId();

                // Insert default user status
                $stmt = $pdo->prepare("INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until) VALUES (?, 'active', 'system', 0, NULL)");
                $stmt->execute([$user_id]);

                // Auto-login the user after successful registration.
                // 'is_new_signup' marks this as a brand-new account so the
                // dashboard greets with "Welcome" instead of "Welcome back". The
                // flag lives only for this first session — a later real login
                // rebuilds $_SESSION['user'] without it (see user_login.php).
                $_SESSION['user'] = [
                    'user_id' => $user_id,
                    'user_name' => $username,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'role' => 'regular',
                    'is_new_signup' => true
                ];

                // Human verified — clear the anti-bot session state.
                unset(
                    $_SESSION['signup_challenge'],
                    $_SESSION['signup_form_time'],
                    $_SESSION['captcha_answer'],
                    $_SESSION['captcha_time'],
                    $_SESSION['captcha_question']
                );

                // Log the signup attempt
                log_attempt($pdo, $user_id, 'signup', 'User signed up successfully');

                // Send new users straight into the personalized plan wizard.
                header("Location: dashboard/set-goal.php");
                exit();
            }
        } catch (PDOException $e) {
            $error_message = "Registration failed. Please try again.";
            error_log("Signup error: " . $e->getMessage());
        }
    }
}

// Decide whether the visible captcha should render, and make sure a fresh
// puzzle is ready when it does. $showCaptcha is consumed by signup.php.
$showCaptcha = !empty($_SESSION['signup_challenge']);
$captcha_question = $showCaptcha ? CustomCaptcha::generateCaptcha() : '';

// Stamp the render time so the NEXT submit is measured against this page load.
$_SESSION['signup_form_time'] = time();
?>
