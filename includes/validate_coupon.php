<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not logged in']));
}

header('Content-Type: application/json');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Security validation failed']));
}

$coupon_code = sanitizeInput($_POST['coupon_code'] ?? '', 'text');
$subtotal = floatval($_POST['subtotal'] ?? 0);

if (empty($coupon_code) || $subtotal <= 0) {
    exit(json_encode(['success' => false, 'error' => 'Invalid request']));
}

$stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active'");
$stmt->bind_param("s", $coupon_code);
$stmt->execute();
$coupon_result = $stmt->get_result();

if ($coupon = $coupon_result->fetch_assoc()) {
    $expiry = new DateTime($coupon['expiry_date']);
    $now = new DateTime();
    
    if ($expiry < $now) {
        exit(json_encode(['success' => false, 'error' => 'This coupon has expired']));
    }
    
    if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
        exit(json_encode(['success' => false, 'error' => 'This coupon has reached its usage limit']));
    }
    
    $discount_percent = floatval($coupon['discount_percent']);
    $discount_amount = ($subtotal * $discount_percent) / 100;
    $final_total = $subtotal - $discount_amount;
    
    exit(json_encode([
        'success' => true,
        'coupon_code' => $coupon_code,
        'discount_percent' => $discount_percent,
        'discount_amount' => round($discount_amount, 2),
        'subtotal' => round($subtotal, 2),
        'final_total' => round($final_total, 2)
    ]));
} else {
    exit(json_encode(['success' => false, 'error' => 'Invalid coupon code']));
}
?>
