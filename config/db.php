<?php
// Load configuration
require_once __DIR__ . '/config.php';

// Set timezone to align with database system time
date_default_timezone_set('Asia/Singapore');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Check session timeout
if (ENABLE_SESSION_TIMEOUT && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log("Database Connection failed: " . $conn->connect_error);
    if (LOG_ERRORS) {
        @mkdir(dirname(LOG_FILE), 0755, true);
        @file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] DB Connection Error: " . $conn->connect_error . "\n", FILE_APPEND);
    }
    die("Connection failed. Please try again later.");
}

// Set connection charset
$conn->set_charset("utf8mb4");

// Ensure database schema is complete (self-healing migration)
ensureDatabaseSchema($conn);

/**
 * Ensure login_attempts table exists (auto-migration)
 */
function ensureLoginAttemptsTable($conn) {
    static $checked = false;
    if ($checked) return;
    
    $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($result->num_rows === 0) {
        $conn->query("
            CREATE TABLE `login_attempts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip_address` varchar(45) NOT NULL,
                `username` varchar(255) NOT NULL,
                `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_ip_username` (`ip_address`, `username`),
                KEY `idx_attempted_at` (`attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
    $checked = true;
}

/**
 * Get the client's real IP address
 */
function getClientIP() {
    // Only trust REMOTE_ADDR in production (proxy headers can be spoofed)
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Login attempt tracking functions for rate limiting (database-backed)
 */
function checkLoginAttempts($username) {
    global $conn;
    ensureLoginAttemptsTable($conn);
    
    $ip = getClientIP();
    $lockout_time = LOGIN_LOCKOUT_TIME;
    $max_attempts = MAX_LOGIN_ATTEMPTS;
    
    // Clean up old attempts (older than lockout period)
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("i", $lockout_time);
    $stmt->execute();
    $stmt->close();
    
    // Count recent attempts for this IP + username combo
    $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("ssi", $ip, $username, $lockout_time);
    $stmt->execute();
    $stmt->bind_result($attempts);
    $stmt->fetch();
    $stmt->close();
    
    return $attempts < $max_attempts;
}

function incrementLoginAttempts($username) {
    global $conn;
    ensureLoginAttemptsTable($conn);
    
    $ip = getClientIP();
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
    $stmt->bind_param("ss", $ip, $username);
    $stmt->execute();
    $stmt->close();
}

function resetLoginAttempts($username) {
    global $conn;
    ensureLoginAttemptsTable($conn);
    
    $ip = getClientIP();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND username = ?");
    $stmt->bind_param("ss", $ip, $username);
    $stmt->execute();
    $stmt->close();
}



/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token
 */
function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Sanitize input data
 */
function sanitizeInput($data, $type = 'text') {
    global $conn;
    
    $data = trim($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
        case 'int':
            return intval($data);
        case 'float':
            return floatval($data);
        case 'phone':
            return preg_replace('/[^0-9\-\+\(\) ]/', '', $data);
        case 'postal':
            return preg_replace('/[^0-9\-A-Za-z ]/', '', $data);
        case 'text':
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        default:
            return $conn->real_escape_string($data);
    }
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    // Basic validation: 7-20 digits, spaces, hyphens, +, ()
    return preg_match('/^[\d\+\-\(\)\s]{7,20}$/', $phone);
}

/**
 * Validate postal code
 */
function validatePostalCode($postal) {
    // Basic validation: 3-10 characters, alphanumeric, spaces, hyphens
    return preg_match('/^[A-Za-z0-9\-\s]{3,10}$/', $postal);
}

/**
 * Validate city name
 */
function validateCity($city) {
    // Only letters, spaces, hyphens, periods
    return preg_match('/^[A-Za-z\s\-\.]{2,100}$/', $city);
}

/**
 * Log error
 */
function logError($message) {
    if (LOG_ERRORS) {
        @mkdir(dirname(LOG_FILE), 0755, true);
        @file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
    }
    error_log($message);
}

/**
 * Verify and self-heal the database schema if tables or columns are missing
 */
function ensureDatabaseSchema($conn) {
    // 1. Check/Add users columns
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL");
    }
    
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
    }

    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    // 2. Check/Add products columns
    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'sale_price'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) DEFAULT NULL");
    }

    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'badge'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN badge VARCHAR(255) DEFAULT NULL");
    }

    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'rating'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN rating DECIMAL(3,2) DEFAULT NULL");
    }

    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'reviews_count'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN reviews_count INT DEFAULT NULL");
    }

    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'created_at'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    // 3. Modify category column if needed (from ENUM to VARCHAR)
    $res = $conn->query("SHOW COLUMNS FROM products LIKE 'category'");
    if ($res && $row = $res->fetch_assoc()) {
        if (strpos(strtolower($row['Type']), 'enum') !== false) {
            $conn->query("ALTER TABLE products MODIFY COLUMN category VARCHAR(100) NOT NULL");
        }
    }

    // 4. Create product_reviews table
    $conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        username VARCHAR(255) NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // 5. Create coupons table
    $conn->query("CREATE TABLE IF NOT EXISTS coupons (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_percent DECIMAL(5,2) NOT NULL,
        expiry_date DATETIME NOT NULL,
        status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        usage_limit INT DEFAULT NULL,
        usage_count INT DEFAULT 0,
        description VARCHAR(255),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // 6. Create featured_products table
    $conn->query("CREATE TABLE IF NOT EXISTS featured_products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // 7. Create password_resets table
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(100) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (email),
        INDEX (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
?>
