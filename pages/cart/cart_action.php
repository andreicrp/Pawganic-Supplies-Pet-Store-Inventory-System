<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$product_id = $_POST['product_id'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

$response = ['success' => false, 'message' => 'Unknown action', 'data' => null];

// Validate user is logged in
if (!$user_id) {
    http_response_code(401);
    $response['message'] = 'Please login first';
    echo json_encode($response);
    exit;
}

if ($action === 'add' && $product_id && $quantity !== null) {
    // Handle adding items to cart
    $quantity = intval($quantity);
    
    if ($quantity <= 0) {
        http_response_code(400);
        $response['message'] = 'Quantity must be greater than 0';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Check if product exists and has stock
        $product_check = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
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
        $product_name = $product['name'];
        $available_stock = $product['stock'];
        
        if ($available_stock <= 0) {
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
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            // Product already in cart - update quantity
            $cart_item = $check_result->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + $quantity;
            
            if ($new_quantity > $available_stock) {
                http_response_code(400);
                $response['message'] = "Not enough stock. Only $available_stock available, you already have " . $cart_item['quantity'] . " in cart.";
                $response['data'] = ['available_stock' => $available_stock];
                echo json_encode($response);
                exit;
            }
            
            // Update quantity
            $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $update->bind_param("iii", $new_quantity, $user_id, $product_id);
            
            if ($update->execute()) {
                $response['success'] = true;
                $response['message'] = "$product_name - Quantity updated in cart";
                $response['data'] = ['quantity' => $new_quantity];
            } else {
                http_response_code(500);
                $response['message'] = 'Failed to update cart';
            }
            $update->close();
        } else {
            // Insert new cart item
            if ($quantity > $available_stock) {
                http_response_code(400);
                $response['message'] = "Not enough stock. Only $available_stock available.";
                $response['data'] = ['available_stock' => $available_stock];
                echo json_encode($response);
                exit;
            }
            
            $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert->bind_param("iii", $user_id, $product_id, $quantity);
            
            if ($insert->execute()) {
                $response['success'] = true;
                $response['message'] = "$product_name - Added to cart";
                $response['data'] = ['quantity' => $quantity];
            } else {
                http_response_code(500);
                $response['message'] = 'Failed to add item to cart';
            }
            $insert->close();
        }
        
        $check->close();
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = 'Error adding item: ' . $e->getMessage();
    }
} 
elseif ($action === 'remove' && $product_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM cart WHERE product_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $product_id, $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Item removed from cart';
            } else {
                http_response_code(404);
                $response['message'] = 'Item not found in cart';
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = 'Error removing item: ' . $e->getMessage();
    }
} 
elseif ($action === 'update' && $product_id && $quantity !== null) {
    // Validate quantity
    $quantity = intval($quantity);
    
    if ($quantity <= 0) {
        http_response_code(400);
        $response['message'] = 'Quantity must be greater than 0';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Check product stock
        $stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stock_check->bind_param("i", $product_id);
        $stock_check->execute();
        $stock_result = $stock_check->get_result();
        
        if ($stock_result->num_rows === 0) {
            http_response_code(404);
            $response['message'] = 'Product not found';
            echo json_encode($response);
            exit;
        }
        
        $product = $stock_result->fetch_assoc();
        $available_stock = $product['stock'];
        
        if ($quantity > $available_stock) {
            http_response_code(400);
            $response['message'] = "Not enough stock. Only $available_stock available.";
            $response['data'] = ['available_stock' => $available_stock];
            echo json_encode($response);
            exit;
        }
        
        $stock_check->close();
        
        // Update cart quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE product_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $product_id, $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Cart updated successfully';
            $response['data'] = ['quantity' => $quantity];
        } else {
            http_response_code(500);
            $response['message'] = 'Failed to update cart';
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = 'Error updating cart: ' . $e->getMessage();
    }
} else {
    http_response_code(400);
    $response['message'] = 'Missing required parameters';
}

echo json_encode($response);
?>
