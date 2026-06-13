<?php
require_once __DIR__ . '/../config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
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
    header("Location: admin.php");
    exit;
}

// Download the file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
header('Pragma: no-cache');
header('Expires: 0');

readfile($file_path);
exit;
?>
