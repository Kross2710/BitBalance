<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/handlers/edit_user.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

// Get the user ID from the GET request
$user_id = (int) $_GET['user_id'] ?? null;

// Get the user data from the database
$stmt = $pdo->prepare("SELECT u.user_name, u.first_name, u.last_name, u.email, u.role, s.status
FROM   user u
JOIN   userStatus s  ON s.user_id = u.user_id
WHERE  u.user_id = ?;");

$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$first_name = $user["first_name"];
$last_name = $user["last_name"];
$username = $user["user_name"];
$email = $user["email"];
$role = $user["role"];
$status = $user["status"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Edit</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row input {
            flex: 1;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Disabled button styling */
        .login-button:disabled {
            background-color: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.6 !important;
        }

        .login-button:disabled:hover {
            background-color: #6c757d !important;
        }

        select {
            font-size: 14px;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 4px;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: black;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- Back arrow -->
    <a href="admin-users.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to User List
    </a>

    <div class="container">
        <div class="form-section">
            <h2>User Edit</h2>

            <?php if (!empty($error_message)): ?>

                <div class="error-message"
                    style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>

                <div class="success-message"
                    style="color: #2e7d32; margin-bottom: 15px; padding: 12px; background-color: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form action="edit-user.php" method="POST">
                <input type="hidden" name="user_id" value="<?php echo (int)$user_id ?? ''; ?>">

                <div class="form-row">
                    <input type="text" placeholder="First Name" name="first_name" required
                        value="<?php echo $first_name ?? ''; ?>">
                    <input type="text" placeholder="Last Name" name="last_name" required
                        value="<?php echo $last_name ?? ''; ?>">
                </div>

                <input type="text" placeholder="Username" name="username" required
                    value="<?php echo $username ?? ''; ?>">

                <input type="email" placeholder="Email" name="email" required value="<?php echo $email ?? ''; ?>">

                <select name="role" required>
                    <option value="" disabled <?php echo empty($role) ? 'selected' : ''; ?>>Select Role
                    </option>
                    <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="regular" <?php echo ($role === 'regular') ? 'selected' : ''; ?>>Regular</option>
                </select>

                <select name="status" required>
                    <option value="" disabled <?php echo empty($status) ? 'selected' : ''; ?>>Select Status
                    </option>
                    <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="archived" <?php echo ($status === 'archived') ? 'selected' : ''; ?>>Archived</option>
                    <option value="banned" <?php echo ($status === 'banned') ? 'selected' : ''; ?>>Banned</option>

                </select>

                <button type="submit" class="login-button" id="signup-btn">Edit User</button>

            </form>
        </div>
        <div class="side-section">
            <img src="../images/food.jpg" alt="Food Image">
        </div>
    </div>
</body>

</html>