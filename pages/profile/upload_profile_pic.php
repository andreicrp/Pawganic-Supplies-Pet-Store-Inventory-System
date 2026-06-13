<?php
require_once __DIR__ . '/../../config/db.php';
// Session is started in db.php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if profile_pic column exists, if not, add it
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");

if ($check_column->num_rows === 0) {
    // Add the column if it doesn't exist
    $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$upload_dir = __DIR__ . '/../../uploads/profiles/';

// Create uploads/profiles directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Check if file was uploaded
if (!isset($_FILES['profile_pic']) || $_FILES['profile_pic']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_pic'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file extension
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file extension. Only JPEG, PNG, GIF, and WebP are allowed']);
    exit;
}

// Validate MIME type server-side (not trusting client-supplied Content-Type)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$real_mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($real_mime, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed']);
    exit;
}

// Validate that it is actually an image
$image_info = @getimagesize($file['tmp_name']);
if ($image_info === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The uploaded file is not a valid image']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit']);
    exit;
}

// Generate unique filename
$filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $filename;

// Path to store in database (relative for web access)
$db_path = 'uploads/profiles/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Delete old profile picture if exists
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($old_pic);
$stmt->fetch();
$stmt->close();

$old_pic_abs = __DIR__ . '/../../' . $old_pic;
if ($old_pic && file_exists($old_pic_abs)) {
    unlink($old_pic_abs);
}

// Update database with new profile picture path
$stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
$stmt->bind_param("si", $db_path, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    $_SESSION['profile_pic'] = $db_path;
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'profile_pic' => $db_path
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed']);
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    exit;
}
?>
