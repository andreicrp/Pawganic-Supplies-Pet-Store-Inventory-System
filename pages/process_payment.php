<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    echo "<script>alert('Security validation failed. Please try again.'); window.location='checkout.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_method = sanitizeInput($_POST['method'] ?? '', 'text');
$payment_credential = sanitizeInput($_POST['credential'] ?? '', 'text');
$delivery_location = sanitizeInput($_POST['location'] ?? '', 'text');
$full_name = sanitizeInput($_POST['full_name'] ?? '', 'text');
$phone = sanitizeInput($_POST['phone'] ?? '', 'phone');
$city = sanitizeInput($_POST['city'] ?? '', 'text');
$postal_code = sanitizeInput($_POST['postal_code'] ?? '', 'postal');
$save_delivery_info = isset($_POST['save_delivery_info']) && $_POST['save_delivery_info'] == '1';
$coupon_code = sanitizeInput($_POST['coupon_code'] ?? '', 'text');
$discount_percent = floatval($_POST['discount_percent'] ?? 0);
$discount_amount = floatval($_POST['discount_amount'] ?? 0);
$subtotal = floatval($_POST['subtotal'] ?? 0);
$total_with_discount = floatval($_POST['total'] ?? 0);
$buy_now = isset($_POST['buy_now']) && $_POST['buy_now'] == '1';

$validation_errors = [];
if (empty($payment_method)) $validation_errors[] = 'Payment method is required.';
if (empty($payment_credential)) $validation_errors[] = 'Payment credential is required.';
if (empty($delivery_location)) $validation_errors[] = 'Delivery location is required.';
if (empty($full_name)) $validation_errors[] = 'Full name is required.';
if (empty($phone) || !validatePhone($phone)) $validation_errors[] = 'Valid phone number is required.';
if (empty($city) || !validateCity($city)) $validation_errors[] = 'Valid city name is required.';
if (empty($postal_code) || !validatePostalCode($postal_code)) $validation_errors[] = 'Valid postal code is required.';

if (!empty($validation_errors)) {
    echo "<script>alert('" . implode('\\n', $validation_errors) . "'); window.location='checkout.php';</script>";
    exit;
}

$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$vat_rate = 0.12;
$vat_amount = ($subtotal - $discount_amount) * $vat_rate;
$final_total = $total_with_discount;

if (!$user || $user['balance'] < $final_total) {
    echo "<script>alert('Insufficient balance!'); window.location='checkout.php';</script>";
    exit;
}

$items_to_purchase = [];

if ($buy_now) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $stmt = $conn->prepare("SELECT id, name, IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) AS price, stock, image FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($product = $result->fetch_assoc()) {
        if ($product['stock'] < $quantity) {
            echo "<script>alert('Not enough stock.'); window.location='shop.php';</script>"; exit;
        }
        $items_to_purchase[] = ['product_id' => $product_id, 'quantity' => $quantity, 'price' => $product['price'], 'name' => $product['name'], 'image' => $product['image']];
    }
} else {
    $stmt = $conn->prepare("SELECT c.product_id, c.quantity, IF(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price, p.sale_price, p.price) AS price, p.stock, p.name, p.image FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['stock'] < $row['quantity']) {
            echo "<script>alert('Not enough stock for one or more items.'); window.location='checkout.php';</script>"; exit;
        }
        $items_to_purchase[] = ['product_id' => $row['product_id'], 'quantity' => $row['quantity'], 'price' => $row['price'], 'name' => $row['name'], 'image' => $row['image']];
    }
}

$stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
$stmt->bind_param("di", $final_total, $user_id);
$stmt->execute();

if ($save_delivery_info) {
    $check_columns = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME IN ('delivery_full_name', 'delivery_phone', 'delivery_city', 'delivery_postal_code', 'delivery_address')");
    $existing_columns = [];
    if ($check_columns) { while ($col = $check_columns->fetch_assoc()) { $existing_columns[] = $col['COLUMN_NAME']; } }
    if (!in_array('delivery_full_name', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_full_name VARCHAR(255)");
    if (!in_array('delivery_phone', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_phone VARCHAR(20)");
    if (!in_array('delivery_city', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_city VARCHAR(100)");
    if (!in_array('delivery_postal_code', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_postal_code VARCHAR(20)");
    if (!in_array('delivery_address', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_address LONGTEXT");
    $stmt = $conn->prepare("UPDATE users SET delivery_full_name = ?, delivery_phone = ?, delivery_city = ?, delivery_postal_code = ?, delivery_address = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $full_name, $phone, $city, $postal_code, $delivery_location, $user_id);
    $stmt->execute();
    $conn->query("CREATE TABLE IF NOT EXISTS user_addresses (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL, address LONGTEXT NOT NULL, is_default BOOLEAN DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");
    
    // Check if the address already exists for this user
    $chk_dup = $conn->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND full_name = ? AND phone = ? AND city = ? AND postal_code = ? AND address = ?");
    $chk_dup->bind_param("isssss", $user_id, $full_name, $phone, $city, $postal_code, $delivery_location);
    $chk_dup->execute();
    $chk_dup->bind_result($existing_addr_id);
    $chk_dup->fetch();
    $chk_dup->close();

    // Clear defaults
    $reset_def = $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND is_default = 1");
    $reset_def->bind_param("i", $user_id);
    $reset_def->execute();
    $reset_def->close();

    if ($existing_addr_id) {
        // Just set the existing one as default
        $up_stmt = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
        $up_stmt->bind_param("i", $existing_addr_id);
        $up_stmt->execute();
        $up_stmt->close();
    } else {
        // Insert new default address
        $insert_stmt = $conn->prepare("INSERT INTO user_addresses (user_id, full_name, phone, city, postal_code, address, is_default) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $insert_stmt->bind_param("isssss", $user_id, $full_name, $phone, $city, $postal_code, $delivery_location);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

$order_ids = [];
foreach ($items_to_purchase as $item) {
    $product_id = $item['product_id'];
    $quantity = $item['quantity'];
    $price = $item['price'];
    $total_price = $price * $quantity;
    $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stmt->bind_param("ii", $quantity, $product_id);
    $stmt->execute();
    $check_discount_col = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='transactions' AND COLUMN_NAME='discount_amount'");
    if ($check_discount_col->num_rows == 0) {
        $conn->query("ALTER TABLE transactions ADD COLUMN discount_amount DECIMAL(10, 2) DEFAULT 0");
        $conn->query("ALTER TABLE transactions ADD COLUMN coupon_code VARCHAR(50)");
    }
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, product_id, quantity, payment_method, payment_credential, total_price, delivery_location, discount_amount, coupon_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissdsds", $user_id, $product_id, $quantity, $payment_method, $payment_credential, $total_price, $delivery_location, $discount_amount, $coupon_code);
    $stmt->execute();
    $order_ids[] = $conn->insert_id;
}

if (!empty($coupon_code) && $discount_percent > 0) {
    $stmt = $conn->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE code = ?");
    $stmt->bind_param("s", $coupon_code);
    $stmt->execute();
}

if (!$buy_now) {
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

$order_id = $order_ids[0] ?? 0;
$order_number = str_pad($order_id, 6, '0', STR_PAD_LEFT);
$order_date = date('M d, Y');
$order_time = date('h:i A');

// Fetch updated balance
$stmt = $conn->prepare("SELECT balance, email, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$new_balance = $user_data['balance'] ?? 0;
$_SESSION['balance'] = $new_balance;

// Send email
if (!empty($user_data['email'])) {
    $delivery_info = ['full_name' => $full_name, 'address' => $delivery_location, 'city' => $city, 'postal_code' => $postal_code, 'phone' => $phone];
    sendOrderConfirmationEmail($user_data['email'], $user_data['username'], $order_number, $items_to_purchase, $subtotal, $discount_amount, $discount_percent, $vat_amount, $final_total, $delivery_info, $payment_method);
}

// Nav data
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role = $_SESSION['role'] ?? 'customer';
$nav_balance = $new_balance;
$profile_pic = 'images/profile.jpg';
$pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $user_id);
$pic_stmt->execute();
$pic_stmt->bind_result($db_profile_pic);
$pic_stmt->fetch();
$pic_stmt->close();
if ($db_profile_pic) $profile_pic = $db_profile_pic;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    :root {
        --espresso:   #2c1a0e;
        --mahogany:   #5a2d0c;
        --caramel:    #9b6a2f;
        --gold:       #c9912a;
        --honey:      #e8b86d;
        --cream:      #f5ead6;
        --ivory:      #fdf8f0;
        --mist:       #ede4d2;
        --sage:       #7a9e7e;
        --sage-light: #b5ceb8;
        --danger:     #c0392b;
        --white:      #ffffff;
        --shadow-sm:  0 2px 12px rgba(44,26,14,0.10);
        --shadow-md:  0 8px 32px rgba(44,26,14,0.16);
        --shadow-lg:  0 20px 60px rgba(44,26,14,0.22);
        --radius:     18px;
        --radius-sm:  10px;
        --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
        background-color: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--espresso);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    /* ===================== NAVBAR ===================== */
    .navbar {
        display: flex; justify-content: space-between; align-items: center;
        background: rgba(253,248,240,0.92); backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px); padding: 0 5%; height: 72px;
        position: sticky; top: 0; z-index: 1000;
        border-bottom: 1px solid rgba(201,145,42,0.18);
        box-shadow: 0 2px 24px rgba(44,26,14,0.08);
    }
    .logo-img { height: 46px; width: auto; transition: transform 0.3s ease; }
    .logo-img:hover { transform: scale(1.05); }
    .nav-links { display: flex; align-items: center; gap: 6px; }
    .nav-links a {
        color: var(--mahogany); text-decoration: none; padding: 8px 16px;
        border-radius: 50px; font-weight: 500; font-size: 0.9rem;
        letter-spacing: 0.3px; transition: var(--transition);
    }
    .nav-links a:hover { background: var(--gold); color: var(--white); }
    .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
    .profile-pic { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2.5px solid var(--gold); transition: var(--transition); }
    .profile-pic:hover { transform: scale(1.06); box-shadow: 0 0 0 4px rgba(201,145,42,0.18); }
    .dropdown-content {
        display: none; position: absolute; right: 0; top: calc(100% + 10px);
        background: var(--ivory); border-radius: var(--radius-sm);
        box-shadow: var(--shadow-lg); min-width: 220px; z-index: 1000;
        border: 1px solid rgba(201,145,42,0.15); overflow: hidden;
        animation: dropDown 0.25s ease;
    }
    @keyframes dropDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .profile-dropdown:hover .dropdown-content, .profile-dropdown.open .dropdown-content { display: block; }
    .dropdown-profile-info { padding: 16px; border-bottom: 1px solid var(--mist); background: linear-gradient(135deg, var(--cream), var(--ivory)); }
    .dropdown-profile-name { font-weight: 700; color: var(--mahogany); font-size: 0.95rem; }
    .dropdown-profile-role { font-size: 0.78rem; color: var(--caramel); margin-top: 2px; }
    .dropdown-profile-balance { font-size: 0.85rem; color: var(--gold); font-weight: 600; margin-top: 5px; }
    .dropdown-content a { display: flex; align-items: center; gap: 10px; color: var(--espresso); text-decoration: none; padding: 12px 16px; font-size: 0.9rem; transition: var(--transition); }
    .dropdown-content a:hover { background: var(--cream); color: var(--mahogany); padding-left: 22px; }
    .dropdown-content a i { width: 18px; color: var(--caramel); }

    /* ===================== CONFIRMATION HERO ===================== */
    .confirm-hero {
        background: linear-gradient(135deg, #1a5c35 0%, #27ae60 50%, #2ecc71 100%);
        padding: 70px 5% 80px;
        position: relative; overflow: hidden; text-align: center;
    }
    .confirm-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 70% 40%, rgba(255,255,255,0.1) 0%, transparent 60%);
    }
    .confirm-hero::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream); clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco { position: absolute; border-radius: 50%; opacity: 0.08; background: white; }
    .hero-deco-1 { width: 360px; height: 360px; top: -100px; right: -80px; }
    .hero-deco-2 { width: 200px; height: 200px; bottom: 20px; left: 3%; }
    .hero-deco-3 { width: 100px; height: 100px; top: 20px; left: 20%; opacity: 0.05; }

    .confirm-hero-inner { position: relative; z-index: 2; }
    .success-ring {
        width: 110px; height: 110px; border-radius: 50%;
        background: rgba(255,255,255,0.15); border: 3px solid rgba(255,255,255,0.4);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 24px; animation: ringPop 0.7s cubic-bezier(0.34,1.56,0.64,1);
        backdrop-filter: blur(8px);
    }
    @keyframes ringPop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .success-ring i { font-size: 3rem; color: white; animation: checkIn 0.5s ease 0.3s both; }
    @keyframes checkIn { from { transform: scale(0) rotate(-45deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }

    .confirm-hero h1 {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.2rem, 4vw, 3.4rem);
        font-weight: 900; color: white; line-height: 1.1; margin-bottom: 12px;
        animation: fadeUp 0.6s ease 0.4s both;
    }
    .confirm-hero h1 em { font-style: italic; color: rgba(255,255,255,0.85); }
    .confirm-hero p {
        color: rgba(255,255,255,0.8); font-size: 1.05rem; max-width: 500px;
        margin: 0 auto; line-height: 1.7;
        animation: fadeUp 0.6s ease 0.5s both;
    }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    .order-pill {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
        color: white; padding: 8px 20px; border-radius: 50px;
        font-size: 0.9rem; font-weight: 700; letter-spacing: 1px;
        margin-top: 18px; backdrop-filter: blur(8px);
        animation: fadeUp 0.6s ease 0.6s both;
    }

    /* Progress steps */
    .confirm-progress {
        display: flex; align-items: center; justify-content: center; gap: 0;
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
        border-radius: 50px; padding: 6px 8px; backdrop-filter: blur(10px);
        width: fit-content; margin: 24px auto 0;
        animation: fadeUp 0.6s ease 0.7s both;
    }
    .cprog-step { display: flex; align-items: center; gap: 7px; padding: 7px 16px; border-radius: 50px; }
    .cprog-step.done { background: rgba(255,255,255,0.15); }
    .cprog-step.active { background: rgba(255,255,255,0.25); }
    .cprog-num {
        width: 24px; height: 24px; border-radius: 50%;
        background: rgba(255,255,255,0.2); color: white;
        display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;
    }
    .cprog-step.done .cprog-num, .cprog-step.active .cprog-num { background: white; color: #27ae60; }
    .cprog-label { font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.7); }
    .cprog-step.done .cprog-label, .cprog-step.active .cprog-label { color: white; }
    .cprog-div { width: 16px; height: 2px; background: rgba(255,255,255,0.2); margin: 0 2px; }

    /* ===================== MAIN CONTENT ===================== */
    .confirm-main {
        max-width: 960px; margin: 44px auto 60px;
        padding: 0 24px;
        display: flex; flex-direction: column; gap: 24px;
    }

    /* ===================== INFO CARD ===================== */
    .info-card {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
        animation: cardIn 0.5s ease both;
    }
    @keyframes cardIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .info-card:nth-child(1) { animation-delay: 0.1s; }
    .info-card:nth-child(2) { animation-delay: 0.18s; }
    .info-card:nth-child(3) { animation-delay: 0.26s; }
    .info-card:nth-child(4) { animation-delay: 0.34s; }
    .info-card:nth-child(5) { animation-delay: 0.42s; }

    .card-header-styled {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        padding: 16px 24px;
        display: flex; align-items: center; gap: 10px;
    }
    .card-header-styled h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.05rem; font-weight: 700; color: var(--honey); margin: 0;
    }
    .card-header-styled i { color: var(--gold); width: 18px; text-align: center; }
    .card-body-styled { padding: 24px; }

    /* Order meta grid */
    .meta-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }
    .meta-item {
        background: var(--cream); border-radius: var(--radius-sm);
        padding: 16px 18px; border: 1px solid rgba(201,145,42,0.1);
        transition: var(--transition);
    }
    .meta-item:hover { border-color: rgba(201,145,42,0.25); box-shadow: var(--shadow-sm); }
    .meta-label {
        font-size: 0.72rem; font-weight: 700; letter-spacing: 1.5px;
        text-transform: uppercase; color: var(--caramel); margin-bottom: 6px;
        display: flex; align-items: center; gap: 5px;
    }
    .meta-label i { color: var(--gold); font-size: 0.7rem; }
    .meta-value { font-size: 1rem; font-weight: 700; color: var(--espresso); }
    .meta-value.green { color: var(--sage); font-size: 1.15rem; font-family: 'Playfair Display', serif; }

    /* Order items */
    .order-item {
        display: flex; gap: 14px; align-items: center;
        padding: 14px; background: var(--cream); border-radius: var(--radius-sm);
        border: 1px solid rgba(201,145,42,0.1); margin-bottom: 12px;
        transition: var(--transition);
    }
    .order-item:last-of-type { margin-bottom: 0; }
    .order-item:hover { border-color: rgba(201,145,42,0.25); box-shadow: var(--shadow-sm); }
    .order-item-img {
        width: 72px; height: 72px; border-radius: var(--radius-sm);
        background: var(--ivory); overflow: hidden; flex-shrink: 0;
        border: 2px solid var(--mist); display: flex; align-items: center; justify-content: center;
    }
    .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
    .order-item-info { flex: 1; min-width: 0; }
    .order-item-name { font-weight: 700; color: var(--espresso); font-size: 0.95rem; margin-bottom: 4px; }
    .order-item-qty {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(201,145,42,0.1); color: var(--caramel);
        padding: 2px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600;
    }
    .order-item-price { text-align: right; white-space: nowrap; }
    .order-item-price .unit { font-size: 0.75rem; color: var(--caramel); }
    .order-item-price .total { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 700; color: var(--mahogany); }

    /* Pricing breakdown */
    .pricing-block {
        background: var(--cream); border-radius: var(--radius-sm); padding: 20px;
        border: 1px solid rgba(201,145,42,0.12); margin-top: 16px;
    }
    .p-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid rgba(201,145,42,0.08);
        font-size: 0.9rem; color: var(--caramel);
    }
    .p-row:last-child { border-bottom: none; }
    .p-row span:last-child { font-weight: 600; color: var(--espresso); }
    .p-row.discount span:last-child { color: var(--sage); }
    .p-row.grand {
        margin-top: 8px; padding-top: 14px; border-top: 2px solid var(--mist);
        border-bottom: none; font-size: 1.1rem; font-weight: 700; color: var(--espresso);
    }
    .p-row.grand span:last-child { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--gold); }
    .coupon-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(122,158,126,0.12); border: 1px dashed rgba(122,158,126,0.4);
        color: var(--sage); padding: 3px 10px; border-radius: 6px;
        font-family: monospace; font-size: 0.85rem; font-weight: 700; letter-spacing: 1px;
    }

    /* Delivery card */
    .delivery-detail {
        display: flex; gap: 14px; align-items: flex-start; padding: 14px;
        background: var(--cream); border-radius: var(--radius-sm);
        border: 1px solid rgba(201,145,42,0.1); margin-bottom: 14px;
    }
    .delivery-icon {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        display: flex; align-items: center; justify-content: center;
        color: var(--espresso); font-size: 1rem;
    }
    .delivery-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; color: var(--caramel); margin-bottom: 4px; }
    .delivery-value { font-weight: 600; color: var(--espresso); font-size: 0.9rem; line-height: 1.5; }
    .eta-banner {
        background: linear-gradient(135deg, rgba(122,158,126,0.12), rgba(122,158,126,0.06));
        border: 1px solid rgba(122,158,126,0.3); border-radius: var(--radius-sm);
        padding: 14px 18px; display: flex; align-items: center; gap: 12px;
        color: var(--sage);
    }
    .eta-banner i { font-size: 1.2rem; flex-shrink: 0; }
    .eta-banner strong { color: var(--mahogany); }

    /* What's next */
    .next-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
    .next-step {
        background: var(--cream); border-radius: var(--radius-sm); padding: 18px;
        border: 1px solid rgba(201,145,42,0.1); text-align: center;
        transition: var(--transition);
    }
    .next-step:hover { border-color: rgba(201,145,42,0.25); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
    .next-step-num {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); font-weight: 700; font-size: 0.9rem;
        display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;
    }
    .next-step h4 { font-size: 0.88rem; font-weight: 700; color: var(--mahogany); margin-bottom: 5px; }
    .next-step p { font-size: 0.78rem; color: var(--caramel); line-height: 1.5; }

    /* Action buttons */
    .actions-row { display: flex; gap: 12px; flex-wrap: wrap; }
    .btn-confirm-action {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        padding: 13px 24px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: var(--transition);
        text-decoration: none; border: none; white-space: nowrap;
    }
    .btn-primary-c {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); flex: 1; min-width: 180px;
        box-shadow: 0 4px 16px rgba(201,145,42,0.3);
    }
    .btn-primary-c:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); transform: translateY(-2px); }
    .btn-secondary-c {
        background: transparent; color: var(--mahogany);
        border: 2px solid var(--mist) !important; flex: 1; min-width: 160px;
    }
    .btn-secondary-c:hover { border-color: var(--caramel) !important; background: var(--mist); color: var(--espresso); }
    .btn-print-c {
        background: transparent; color: var(--caramel);
        border: 2px solid var(--mist) !important;
    }
    .btn-print-c:hover { background: var(--cream); color: var(--mahogany); }

    /* Trust strip */
    .trust-strip {
        display: flex; justify-content: center; gap: 24px; flex-wrap: wrap;
        padding: 20px 0 4px; border-top: 1px solid var(--mist);
        margin-top: 20px;
    }
    .trust-item { display: flex; align-items: center; gap: 7px; font-size: 0.8rem; color: var(--caramel); }
    .trust-item i { color: var(--sage); }

    /* Balance update flash */
    .balance-updated {
        background: linear-gradient(135deg, rgba(122,158,126,0.12), rgba(122,158,126,0.06));
        border: 1px solid rgba(122,158,126,0.3); border-radius: var(--radius-sm);
        padding: 14px 18px; display: flex; align-items: center; gap: 14px;
    }
    .balance-icon-sm {
        width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, var(--sage), var(--sage-light));
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 1rem;
    }
    .balance-updated-label { font-size: 0.8rem; color: var(--caramel); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
    .balance-updated-val { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--sage); font-weight: 700; }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso); color: rgba(255,255,255,0.75);
        padding: 64px 5% 28px; margin-top: auto; position: relative;
    }
    footer::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey), var(--gold), var(--caramel));
    }
    .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 40px; margin-bottom: 40px; }
    .footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--honey); margin-bottom: 20px; }
    .footer-section p { font-size: 0.88rem; line-height: 1.8; margin-bottom: 14px; }
    .social-links { display: flex; gap: 12px; margin-top: 16px; }
    .social-links a {
        width: 40px; height: 40px; border-radius: 50%;
        background: rgba(201,145,42,0.15); border: 1px solid rgba(201,145,42,0.3);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.9rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }
    .footer-links { display: flex; flex-direction: column; gap: 10px; }
    .footer-links a { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.88rem; transition: var(--transition); }
    .footer-links a:hover { color: var(--honey); padding-left: 6px; }
    .copyright { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px; text-align: center; font-size: 0.82rem; color: rgba(255,255,255,0.35); }

    /* Scroll top */
    .scroll-to-top {
        position: fixed; bottom: 30px; right: 30px; z-index: 999;
        width: 48px; height: 48px; border-radius: 50%;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border: none; color: var(--honey); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; box-shadow: var(--shadow-md);
        opacity: 0; visibility: hidden; transition: var(--transition);
    }
    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    @media print {
        .navbar, footer, .scroll-to-top, .actions-row { display: none !important; }
        .confirm-hero { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        body { background: white; }
    }

    @media (max-width: 768px) {
        .navbar { padding: 0 20px; }
        .nav-links a:not(.active) { display: none; }
        .confirm-hero { padding: 50px 24px 64px; }
        .confirm-main { margin: 28px auto 40px; padding: 0 16px; }
        .card-body-styled { padding: 18px; }
        .meta-grid { grid-template-columns: 1fr 1fr; }
        .next-steps { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
        .meta-grid { grid-template-columns: 1fr; }
        .next-steps { grid-template-columns: 1fr; }
        .actions-row { flex-direction: column; }
        .btn-primary-c, .btn-secondary-c { min-width: 100%; }
    }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none;">
      <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <div class="profile-dropdown">
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic" onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                    <div class="dropdown-profile-role"><?= htmlspecialchars($nav_role) ?></div>
                    <div class="dropdown-profile-balance">₱<?= number_format($nav_balance, 2) ?></div>
                </div>
                <a href="favorites.php"><i class="fas fa-heart"></i>My Favorites</a>
                <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                <a href="purchase_history.php"><i class="fas fa-history"></i>Purchase History</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ===================== CONFIRMATION HERO ===================== -->
<section class="confirm-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-deco hero-deco-3"></div>
    <div class="confirm-hero-inner">
        <div class="success-ring"><i class="fas fa-check"></i></div>
        <h1>Order <em>Confirmed!</em></h1>
        <p>Your payment has been processed and your order is on its way to being prepared with care.</p>
        <div class="order-pill"><i class="fas fa-receipt"></i> Order #<?= $order_number ?> · <?= $order_date ?> at <?= $order_time ?></div>
        <div class="confirm-progress">
            <div class="cprog-step done"><div class="cprog-num"><i class="fas fa-check" style="font-size:0.65rem;"></i></div><span class="cprog-label">Cart</span></div>
            <div class="cprog-div"></div>
            <div class="cprog-step done"><div class="cprog-num"><i class="fas fa-check" style="font-size:0.65rem;"></i></div><span class="cprog-label">Checkout</span></div>
            <div class="cprog-div"></div>
            <div class="cprog-step active"><div class="cprog-num"><i class="fas fa-check" style="font-size:0.65rem;"></i></div><span class="cprog-label">Confirmed</span></div>
        </div>
    </div>
</section>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="confirm-main">

    <!-- Order Overview -->
    <div class="info-card">
        <div class="card-header-styled"><i class="fas fa-clipboard-check"></i><h3>Order Overview</h3></div>
        <div class="card-body-styled">
            <div class="meta-grid">
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-hashtag"></i> Order Number</div>
                    <div class="meta-value">#<?= $order_number ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-calendar"></i> Order Date</div>
                    <div class="meta-value"><?= $order_date ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-clock"></i> Time Placed</div>
                    <div class="meta-value"><?= $order_time ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-credit-card"></i> Payment</div>
                    <div class="meta-value"><?= htmlspecialchars($payment_method) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-box"></i> Items</div>
                    <div class="meta-value"><?= count($items_to_purchase) ?> <?= count($items_to_purchase) > 1 ? 'items' : 'item' ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label"><i class="fas fa-coins"></i> Total Paid</div>
                    <div class="meta-value green">₱<?= number_format($final_total, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Ordered -->
    <div class="info-card">
        <div class="card-header-styled"><i class="fas fa-shopping-bag"></i><h3>Items Ordered</h3></div>
        <div class="card-body-styled">
            <?php foreach ($items_to_purchase as $item): ?>
            <div class="order-item">
                <div class="order-item-img">
                    <?php if (!empty($item['image'])): ?>
                        <img loading="lazy" src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php else: ?>
                        <i class="fas fa-box" style="color:var(--mist); font-size:1.5rem;"></i>
                    <?php endif; ?>
                </div>
                <div class="order-item-info">
                    <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <span class="order-item-qty"><i class="fas fa-times" style="font-size:0.65rem;"></i><?= $item['quantity'] ?> unit<?= $item['quantity'] > 1 ? 's' : '' ?></span>
                </div>
                <div class="order-item-price">
                    <div class="unit">₱<?= number_format($item['price'], 2) ?> each</div>
                    <div class="total">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="pricing-block">
                <div class="p-row"><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
                <?php if ($discount_percent > 0): ?>
                <div class="p-row discount">
                    <span>Discount (<?= number_format($discount_percent, 1) ?>%)</span>
                    <span>−₱<?= number_format($discount_amount, 2) ?></span>
                </div>
                <div class="p-row">
                    <span>Coupon</span>
                    <span><span class="coupon-badge"><i class="fas fa-tag"></i><?= htmlspecialchars($coupon_code) ?></span></span>
                </div>
                <?php endif; ?>
                <div class="p-row"><span>VAT (12%)</span><span>₱<?= number_format($vat_amount, 2) ?></span></div>
                <div class="p-row"><span>Shipping</span><span style="color:var(--sage);">FREE</span></div>
                <div class="p-row grand"><span>Total Paid</span><span>₱<?= number_format($final_total, 2) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Delivery Info -->
    <div class="info-card">
        <div class="card-header-styled"><i class="fas fa-map-marker-alt"></i><h3>Delivery Details</h3></div>
        <div class="card-body-styled">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:12px; margin-bottom:16px;">
                <div class="delivery-detail">
                    <div class="delivery-icon"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="delivery-label">Recipient</div>
                        <div class="delivery-value"><?= htmlspecialchars($full_name) ?></div>
                    </div>
                </div>
                <div class="delivery-detail">
                    <div class="delivery-icon"><i class="fas fa-phone"></i></div>
                    <div>
                        <div class="delivery-label">Phone</div>
                        <div class="delivery-value"><?= htmlspecialchars($phone) ?></div>
                    </div>
                </div>
                <div class="delivery-detail">
                    <div class="delivery-icon"><i class="fas fa-city"></i></div>
                    <div>
                        <div class="delivery-label">City</div>
                        <div class="delivery-value"><?= htmlspecialchars($city) ?>, <?= htmlspecialchars($postal_code) ?></div>
                    </div>
                </div>
            </div>
            <div class="delivery-detail" style="margin-bottom:16px;">
                <div class="delivery-icon"><i class="fas fa-home"></i></div>
                <div>
                    <div class="delivery-label">Full Address</div>
                    <div class="delivery-value"><?= htmlspecialchars($delivery_location) ?></div>
                </div>
            </div>
            <div class="eta-banner">
                <i class="fas fa-truck"></i>
                <span>Estimated delivery: <strong>3–5 business days</strong>. You'll receive a tracking number by email once dispatched.</span>
            </div>
        </div>
    </div>

    <!-- Updated Balance -->
    <div class="info-card">
        <div class="card-header-styled"><i class="fas fa-wallet"></i><h3>Account Balance</h3></div>
        <div class="card-body-styled">
            <div class="balance-updated">
                <div class="balance-icon-sm"><i class="fas fa-wallet"></i></div>
                <div>
                    <div class="balance-updated-label">Remaining Balance</div>
                    <div class="balance-updated-val">₱<?= number_format($new_balance, 2) ?></div>
                </div>
                <div style="margin-left:auto; text-align:right;">
                    <div style="font-size:0.75rem; color:var(--caramel);">Deducted</div>
                    <div style="font-size:0.9rem; font-weight:700; color:var(--danger);">−₱<?= number_format($final_total, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- What's Next -->
    <div class="info-card">
        <div class="card-header-styled"><i class="fas fa-route"></i><h3>What Happens Next?</h3></div>
        <div class="card-body-styled">
            <div class="next-steps">
                <div class="next-step">
                    <div class="next-step-num">1</div>
                    <h4>Order Processing</h4>
                    <p>We're preparing your order right now with care and precision.</p>
                </div>
                <div class="next-step">
                    <div class="next-step-num">2</div>
                    <h4>Email Confirmation</h4>
                    <p>A receipt and order details have been sent to your email.</p>
                </div>
                <div class="next-step">
                    <div class="next-step-num">3</div>
                    <h4>Shipped & Tracked</h4>
                    <p>You'll receive a tracking number once your parcel is dispatched.</p>
                </div>
                <div class="next-step">
                    <div class="next-step-num">4</div>
                    <h4>Delivered!</h4>
                    <p>Your Pawganic order arrives in 3–5 business days.</p>
                </div>
            </div>

            <div class="actions-row" style="margin-top:24px;">
                <a href="purchase_history.php" class="btn-confirm-action btn-primary-c">
                    <i class="fas fa-history"></i> View Purchase History
                </a>
                <a href="shop.php" class="btn-confirm-action btn-secondary-c">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
                <button onclick="window.print()" class="btn-confirm-action btn-print-c">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>

            <div class="trust-strip">
                <div class="trust-item"><i class="fas fa-lock"></i> Secure Transaction</div>
                <div class="trust-item"><i class="fas fa-truck"></i> Fast Delivery</div>
                <div class="trust-item"><i class="fas fa-undo"></i> Easy Returns</div>
                <div class="trust-item"><i class="fas fa-headset"></i> 24/7 Support</div>
            </div>

            <div style="text-align:center; margin-top:24px; padding-top:20px; border-top:1px solid var(--mist);">
                <p style="font-family:'Playfair Display',serif; font-size:1rem; color:var(--mahogany); font-weight:700;">Thank you for choosing Pawganic Supplies!</p>
                <p style="font-size:0.82rem; color:var(--caramel); margin-top:4px;">Questions? Reach us at <span style="color:var(--gold);">meow@pawganic.com</span></p>
            </div>
        </div>
    </div>

</div><!-- /confirm-main -->

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Since 2020, crafting premium, health-conscious treats by devoted cat lovers to support feline wellness in every bite.</p>
            <div class="social-links">
                <a href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/home"><i class="fab fa-twitter"></i></a>
                <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                <a href="https://www.tiktok.com/en/"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <div class="footer-links">
                <a href="main.php">Home</a>
                <a href="shop.php">Shop</a>
                <a href="about.php">About</a>
                <a href="main.php#faq">FAQs</a>
                <a href="cat_care_tips.php">Cat Care Tips</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey); margin-right:8px;"></i> 123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey); margin-right:8px;"></i> +1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey); margin-right:8px;"></i> meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey); margin-right:8px;"></i> Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile dropdown
    const profileDropdown = document.querySelector('.profile-dropdown');
    if (profileDropdown) {
        const profilePic = profileDropdown.querySelector('.profile-pic');
        profilePic.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('open');
        });
    }

    // Scroll to top
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => { btn.classList.toggle('show', window.pageYOffset > 300); });
    btn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
});
</script>
</body>
</html>