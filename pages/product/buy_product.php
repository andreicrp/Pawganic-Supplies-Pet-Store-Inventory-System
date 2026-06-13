<?php
require_once __DIR__ . '/../../config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = array('success' => false, 'message' => 'Something went wrong.');

if (isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    
    // Fetch the current user's balance from the session
    $user_balance = isset($_SESSION['balance']) ? $_SESSION['balance'] : 0;
    
    // Fetch the product's price and stock from the database
    $stmt = $conn->prepare("SELECT IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) AS price, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    
    if ($product) {
        // Check if the user has enough balance and if stock is available
        if ($user_balance >= $product['price'] && $product['stock'] > 0) {
            // Deduct the product's price from the user's balance (assuming it's stored in session)
            $_SESSION['balance'] -= $product['price'];
            
            // Update the user's balance in the database
            $update_balance_stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update_balance_stmt->bind_param("di", $_SESSION['balance'], $_SESSION['user_id']); // Assuming user_id is stored in session
            $update_balance_stmt->execute();

            // Decrease the stock by 1
            $new_stock = $product['stock'] - 1;
            
            // Update the stock in the database
            $update_stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_stock, $product_id);
            $update_stmt->execute();
            
            // Check if the update was successful
            if ($update_stmt->affected_rows > 0 && $update_balance_stmt->affected_rows > 0) {
                $response = array('success' => true, 'message' => 'Purchase successful!');
            } else {
                $response['message'] = 'Failed to update the stock or balance.';
            }
        } else {
            $response['message'] = 'Insufficient balance or out of stock.';
        }
    } else {
        $response['message'] = 'Product not found.';
    }
} else {
    $response['message'] = 'Product ID is missing.';
}

// Return the response as JSON
echo json_encode($response);
?>
