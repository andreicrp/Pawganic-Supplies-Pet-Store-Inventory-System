<?php
/**
 * Configuration file for Pawganic Supplies
 * Contains sensitive database and application settings
 */

// Database Configuration
// SECURITY: Change these credentials for production!
// Create a dedicated MySQL user with limited privileges instead of root.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'pet_store_inventory');

// Application URL (used in emails and redirects)
// Change this to your production domain when deploying
define('BASE_URL', 'http://localhost/petv10');

// Session Configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('SESSION_NAME', 'pawganic_session');

// Security Configuration
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour in seconds
define('ENABLE_SESSION_TIMEOUT', true);

// Rate Limiting Configuration
define('MAX_LOGIN_ATTEMPTS', 5); // Maximum failed login attempts
define('LOGIN_LOCKOUT_TIME', 900); // Lockout period in seconds (15 minutes)

// Error Logging
define('LOG_ERRORS', true);
define('LOG_FILE', __DIR__ . '/logs/errors.log');

// Load local overrides first if present (contains sensitive keys, ignored by Git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// SMTP Mail Configuration (Gmail)
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'ssl://smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465);
if (!defined('SMTP_USER')) define('SMTP_USER', 'andreicarpio11@gmail.com'); // Put your Gmail address here
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'your-gmail-app-password'); // Put your Gmail App Password here
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Pawganic Supplies');

// Google OAuth Configuration
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', 'your-google-client-id');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
if (!defined('GOOGLE_REDIRECT_URI')) define('GOOGLE_REDIRECT_URI', BASE_URL . '/google_callback.php');
?>
