<?php
require_once __DIR__ . '/../../config/db.php';

// Check if user is logged in and has a balance
if (!isset($_SESSION['user_id']) || !isset($_SESSION['balance'])) {
    header("Location: login.php"); // Redirect to login if user isn't logged in
    exit();
}

$user_id = $_SESSION['user_id'];
$user_balance = $_SESSION['balance'];

// Check if product ID is provided in the request
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];

    // Fetch product details from the database
    $stmt = $conn->prepare("SELECT IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) AS price, stock FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        $product_price = $product['price'];
        $product_stock = $product['stock'];

        // Check if the user has enough balance and stock is available
        if ($user_balance >= $product_price && $product_stock > 0) {
            // Deduct the price from user's balance
            $_SESSION['balance'] -= $product_price;

            // Update balance in the database
            $update_balance_stmt = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update_balance_stmt->bind_param("di", $_SESSION['balance'], $user_id);
            $update_balance_stmt->execute();

            // Decrease stock by 1
            $new_stock = $product_stock - 1;
            $update_stock_stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $update_stock_stmt->bind_param("ii", $new_stock, $product_id);
            $update_stock_stmt->execute();

            // Check if the update was successful
            if ($update_balance_stmt->affected_rows > 0 && $update_stock_stmt->affected_rows > 0) {
                echo 'Purchase successful!';
                // Optionally redirect back to the shop page or show a success message
            } else {
                echo 'Error updating stock or balance.';
            }
        } else {
            echo 'Insufficient balance or stock is out.';
        }
    } else {
        echo 'Product not found.';
    }
} else {
    echo 'Invalid product ID.';
}
?>
