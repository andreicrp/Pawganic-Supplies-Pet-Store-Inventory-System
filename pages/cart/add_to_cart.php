<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login to add to cart']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? null;

$response = ['success' => false, 'message' => 'Error adding to cart'];

if (!$product_id) {
    http_response_code(400);
    $response['message'] = 'Product ID is required';
    echo json_encode($response);
    exit;
}

try {
    // Check if product exists and has stock
    $product_check = $conn->prepare("SELECT id, stock, price, name FROM products WHERE id = ?");
    $product_check->bind_param("i", $product_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        http_response_code(404);
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit;
    }
    
    $product = $product_result->fetch_assoc();
    
    if ($product['stock'] <= 0) {
        http_response_code(400);
        $response['message'] = 'This product is currently out of stock';
        echo json_encode($response);
        exit;
    }
    
    $product_check->close();
    
    // Check if product already exists in cart
    $check = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Check if adding would exceed stock
        $cart_item = $result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + 1;
        
        if ($new_quantity > $product['stock']) {
            http_response_code(400);
            $response['message'] = "Only " . $product['stock'] . " available. Already have " . $cart_item['quantity'] . " in cart.";
            echo json_encode($response);
            exit;
        }
        
        // Update quantity
        $update = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
        $update->bind_param("ii", $user_id, $product_id);
        $update->execute();
        $update->close();
        
        $response['success'] = true;
        $response['message'] = $product['name'] . ' - Quantity updated in cart';
    } else {
        // Insert new cart item
        $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $insert->bind_param("ii", $user_id, $product_id);
        $insert->execute();
        $insert->close();
        
        $response['success'] = true;
        $response['message'] = $product['name'] . ' - Added to cart';
    }
    
    $check->close();
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
