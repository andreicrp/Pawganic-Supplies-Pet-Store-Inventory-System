<?php
require_once __DIR__ . '/../config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    header("Location: main.php");
    exit;
}

if (!isset($_GET['file'])) {
    header("Location: admin.php");
    exit;
}

$file = $_GET['file'];
$backup_dir = 'backups/';
$file_path = $backup_dir . basename($file);

// Verify file exists and is a valid backup file
if (!file_exists($file_path) || !preg_match('/^db_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', basename($file))) {
    header("Location: admin.php?error=invalid");
    exit;
}

// Delete the backup file
if (unlink($file_path)) {
    header("Location: admin.php?backup_deleted=true");
} else {
    header("Location: admin.php?error=delete_failed");
}
exit;
?>
