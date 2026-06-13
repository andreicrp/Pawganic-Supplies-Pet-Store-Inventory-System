<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$action = $_POST['action'] ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$action || !$product_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Create favorites table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)";
$conn->query($create_table);

if ($action === 'add') {
    // Add to favorites
    $stmt = $conn->prepare("INSERT IGNORE INTO favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $product_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to favorites', 'is_favorite' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding to favorites']);
    }
    $stmt->close();
    
} elseif ($action === 'remove') {
    // Remove from favorites
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Removed from favorites', 'is_favorite' => false]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing from favorites']);
    }
    $stmt->close();
    
} elseif ($action === 'check') {
    // Check if product is in favorites
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_favorite = $result->num_rows > 0;
    
    echo json_encode(['success' => true, 'is_favorite' => $is_favorite]);
    $stmt->close();
}
?>
