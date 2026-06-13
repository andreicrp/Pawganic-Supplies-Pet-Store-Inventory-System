<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-warning">Please <a href="../auth/login.php">login</a> to view your cart.</div>';
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch cart items with product details including images and stock
$stmt = $conn->prepare("SELECT c.id, c.product_id, c.quantity, p.id as pid, p.name, p.price AS original_price, IF(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price, p.sale_price, p.price) AS price, p.stock, p.image, p.category FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total = 0;
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $item_total = $row['quantity'] * $row['price'];
    $total += $item_total;
}
$stmt->close();

// Check if this is an AJAX request for sidebar
$is_ajax = $_GET['sidebar'] === '1' || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
?>

<?php if (!$is_ajax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .product-image { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; }
        .low-stock { color: #ff9800; font-weight: 600; }
        .out-of-stock { color: #d32f2f; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
<?php endif; ?>
        <?php if (empty($cart_items)): ?>
            <div style="color: #8b6f47; font-size: 1rem;">
                <i class="fas fa-shopping-cart me-2"></i>
                Your cart is empty. Continue shopping
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Shopping Cart
                    </h5>
                </div>
                <div class="card-body" style="<?= $is_ajax ? 'max-height: 60vh; overflow-y: auto;' : '' ?>">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="font-size: 0.85rem;">Product</th>
                                    <th style="font-size: 0.85rem;">Price</th>
                                    <th style="font-size: 0.85rem;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <?php
                                    $item_total = $item['quantity'] * $item['price'];
                                    $low_stock = $item['stock'] <= 5;
                                    $out_of_stock = $item['stock'] <= 0;
                                ?>
                                <tr>
                                    <td style="font-size: 0.8rem;">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($item['image']): ?>
                                                <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="product-image" style="width: 50px; height: 50px;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 8px;">
                                                    <i class="fas fa-box text-muted" style="font-size: 0.8rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <strong style="font-size: 0.9rem;"><?= htmlspecialchars($item['name']) ?></strong>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.9rem; white-space: nowrap; font-weight: 600;">
                                        <?php if ($item['original_price'] > $item['price']): ?>
                                            <span style="color: #c0392b;">₱<?= number_format($item['price'], 2) ?></span>
                                            <span style="text-decoration: line-through; font-size: 0.75rem; color: #7f8c8d; font-weight: normal; margin-left: 4px;">₱<?= number_format($item['original_price'], 2) ?></span>
                                        <?php else: ?>
                                            ₱<?= number_format($item['price'], 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger p-1" style="font-size: 0.7rem;" onclick="removeFromCart(<?= $item['product_id'] ?>, this)">
                                            <i class="fas fa-trash-alt me-1"></i>Remove
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr class="my-2">
                    
                    <div class="card bg-light border-0">
                        <div class="card-body p-3">
                            <h6 class="card-title mb-2">Cart Summary</h6>
                            <div class="d-flex justify-content-between" style="font-size: 1rem;">
                                <span>Subtotal:</span>
                                <strong style="white-space: nowrap; color: #27ae60;">₱<?= number_format($total, 2) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<?php if (!$is_ajax): ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="cart.js"></script>
</body>
</html>
<?php endif; ?>
