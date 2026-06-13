<?php
require_once __DIR__ . '/../config/db.php';

// Clear all session variables
$_SESSION = array();

// Regenerate session ID and destroy old session (prevents session fixation)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_regenerate_id(true);
session_destroy();

header("Location: login.php");
exit;
?>
