<?php
/**
 * Test bootstrap for BitBalance.
 * Configures the database connection, session mock, and global test utilities.
 */

ob_start(); // Prevent CLI 'headers already sent' warnings when loading API bootstraps
define('BITBALANCE_API_REQUEST', true);

// Start mock session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = []; // Clear for testing

class TestPDO extends PDO {
    private $transactionDepth = 0;

    public function beginTransaction(): bool {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;
            return true;
        }
        $this->transactionDepth = 1;
        return parent::beginTransaction();
    }

    public function commit(): bool {
        if ($this->transactionDepth > 1) {
            $this->transactionDepth--;
            return true;
        }
        if ($this->transactionDepth === 1) {
            $this->transactionDepth = 0;
            return parent::commit();
        }
        return false;
    }

    public function rollBack(): bool {
        if ($this->transactionDepth > 1) {
            $this->transactionDepth--;
            return true;
        }
        if ($this->transactionDepth === 1) {
            $this->transactionDepth = 0;
            return parent::rollBack();
        }
        return false;
    }

    public function realBeginTransaction() {
        $this->transactionDepth = 1;
        return parent::beginTransaction();
    }

    public function realRollback() {
        $this->transactionDepth = 0;
        if (parent::inTransaction()) {
            return parent::rollBack();
        }
        return false;
    }
}

// Connect to DB
global $pdo;

$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';
$port = 3306;

// Load standard configuration variables, catching any connection errors thrown during inclusion
try {
    @include __DIR__ . '/../../include/db_config.php';
} catch (PDOException $ignore) {
    // Connection exception is expected on CLI local mac due to socket path, we will recreate using TestPDO below.
}

try {
    // Try connecting via parameters loaded from db_config
    $pdo = new TestPDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+07:00';");
} catch (PDOException $e) {
    // TCP Fallback for CLI loopback environments on Mac
    try {
        $host = '127.0.0.1';
        $pdo = new TestPDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+07:00';");
    } catch (PDOException $ex) {
        fwrite(STDERR, "Database connection failed! Make sure MySQL is running.\n");
        fwrite(STDERR, "Error: " . $ex->getMessage() . "\n");
        exit(1);
    }
}

/**
 * Creates a unique test user in the database.
 * Wrapped in transactions, so it will be automatically deleted after the test finishes.
 */
function test_create_user(PDO $pdo, $firstName = 'TestUser', $email = null) {
    if (!$email) {
        $email = 'test_' . bin2hex(random_bytes(4)) . '@example.com';
    }
    
    // Generate unique handle
    $handle = strtolower($firstName) . '#' . random_int(1000, 9999);
    
    // Check if exists (extremely rare collision)
    $chk = $pdo->prepare("SELECT 1 FROM user WHERE user_name = ?");
    $chk->execute([$handle]);
    while ($chk->fetchColumn()) {
        $handle = strtolower($firstName) . '#' . random_int(1000, 9999);
        $chk->execute([$handle]);
    }

    $pass = password_hash('password123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO user (user_name, first_name, last_name, email, password, role)
         VALUES (?, ?, 'User', ?, ?, 'regular')"
    );
    $stmt->execute([$handle, $firstName, $email, $pass]);
    $userId = (int)$pdo->lastInsertId();

    // Bootstrap userStatus row (needed for streaks, freezes, visibility)
    $pdo->prepare(
        "INSERT INTO userStatus (user_id, status, profile_visibility, logging_streak, streak_freezes)
         VALUES (?, 'active', 'friends', 0, 0)"
    )->execute([$userId]);

    return $userId;
}
