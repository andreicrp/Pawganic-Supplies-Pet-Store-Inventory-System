<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$total = 0;
$coupon_code = '';
$discount_percent = 0;
$discount_amount = 0;
$is_empty_cart = false;
$buy_now = false;

// Handle coupon validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = strtoupper($_POST['coupon_code'] ?? '');
    if (!empty($coupon_code)) {
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active'");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $coupon_result = $stmt->get_result();
        if ($coupon = $coupon_result->fetch_assoc()) {
            $expiry = new DateTime($coupon['expiry_date']);
            $now = new DateTime();
            if ($expiry < $now) {
                $coupon_error = "This coupon has expired.";
                $coupon_code = '';
            } else {
                $discount_percent = floatval($coupon['discount_percent']);
            }
        } else {
            $coupon_error = "Invalid coupon code.";
            $coupon_code = '';
        }
    }
}

if (isset($_POST['buy_now']) && $_POST['buy_now'] == "1" && isset($_POST['product_id']) && isset($_POST['quantity'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $stmt = $conn->prepare("SELECT id, name, IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) AS price, stock, image, category FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['stock'] < $quantity) {
            echo "<script>alert('Not enough stock for Buy Now.'); window.location='shop.php';</script>";
            exit;
        }
        $cart_items[] = ['product_id' => $row['id'], 'name' => $row['name'], 'price' => $row['price'], 'quantity' => $quantity, 'image' => $row['image'], 'category' => $row['category']];
        $total = $row['price'] * $quantity;
        $buy_now = true;
    } else {
        echo "<script>alert('Product not found.'); window.location='shop.php';</script>";
        exit;
    }
} else {
    $stmt = $conn->prepare("SELECT c.product_id, p.name, IF(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price, p.sale_price, p.price) AS price, p.image, c.quantity, p.category FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }
    $is_empty_cart = empty($cart_items);
}

// Column checks & address setup
$check_delivery = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME IN ('delivery_full_name', 'delivery_phone', 'delivery_city', 'delivery_postal_code', 'delivery_address')");
$existing_columns = [];
if ($check_delivery) { while ($col = $check_delivery->fetch_assoc()) { $existing_columns[] = $col['COLUMN_NAME']; } }
if (!in_array('delivery_full_name', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_full_name VARCHAR(255)");
if (!in_array('delivery_phone', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_phone VARCHAR(20)");
if (!in_array('delivery_city', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_city VARCHAR(100)");
if (!in_array('delivery_postal_code', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_postal_code VARCHAR(20)");
if (!in_array('delivery_address', $existing_columns)) $conn->query("ALTER TABLE users ADD COLUMN delivery_address LONGTEXT");

$conn->query("CREATE TABLE IF NOT EXISTS user_addresses (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, city VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL, address LONGTEXT NOT NULL, is_default BOOLEAN DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)");

$saved_delivery = [];
$stmt = $conn->prepare("SELECT delivery_full_name, delivery_phone, delivery_city, delivery_postal_code, delivery_address FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) { $saved_delivery = $row; }
$stmt->close();

$addresses = [];
$addr_stmt = $conn->prepare("SELECT id, full_name, phone, city, postal_code, address, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addr_result = $addr_stmt->get_result();
while ($addr_row = $addr_result->fetch_assoc()) { $addresses[] = $addr_row; }
$addr_stmt->close();

$subtotal = $total;
$discount_amount = ($subtotal * $discount_percent) / 100;
$subtotal_after_discount = $subtotal - $discount_amount;
$tax = $subtotal_after_discount * 0.12;
$grand_total = $subtotal_after_discount + $tax;

// Nav data
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role = $_SESSION['role'] ?? 'customer';
$nav_balance = $_SESSION['balance'] ?? 0;
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
    <title>Secure Checkout — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* ===================== ROOT & BASE ===================== */
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
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(253, 248, 240, 0.92);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 0 5%;
        height: 72px;
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 1px solid rgba(201,145,42,0.18);
        box-shadow: 0 2px 24px rgba(44,26,14,0.08);
    }
    .logo-img { height: 46px; width: auto; transition: transform 0.3s ease; }
    .logo-img:hover { transform: scale(1.05); }
    .nav-links { display: flex; align-items: center; gap: 6px; }
    .nav-links a {
        color: var(--mahogany); text-decoration: none;
        padding: 8px 16px; border-radius: 50px;
        font-weight: 500; font-size: 0.9rem; letter-spacing: 0.3px;
        transition: var(--transition);
    }
    .nav-links a:hover, .nav-links a.active { background: var(--gold); color: var(--white); }
    .cart-btn {
        background: var(--espresso); border: none; color: var(--honey);
        width: 42px; height: 42px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition); box-shadow: var(--shadow-sm);
    }
    .cart-btn:hover { background: var(--gold); color: var(--white); transform: scale(1.08); }
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

    /* ===================== CHECKOUT HERO BANNER ===================== */
    .checkout-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 56px 5% 50px;
        position: relative;
        overflow: hidden;
    }
    .checkout-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                    radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
    }
    .checkout-hero::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 55px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco { position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey); }
    .hero-deco-1 { width: 320px; height: 320px; top: -80px; right: -60px; }
    .hero-deco-2 { width: 180px; height: 180px; bottom: 10px; left: 5%; }
    .checkout-hero-inner {
        position: relative; z-index: 2;
        max-width: 1200px; margin: 0 auto;
        display: flex; align-items: center; justify-content: space-between; gap: 40px;
        flex-wrap: wrap;
    }
    .hero-label {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
        margin-bottom: 14px;
    }
    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2rem, 4vw, 3.2rem);
        font-weight: 900; color: var(--white); line-height: 1.1; margin-bottom: 12px;
    }
    .hero-title em { font-style: italic; color: var(--honey); }
    .hero-subtitle { color: rgba(255,255,255,0.65); font-size: 1rem; line-height: 1.7; max-width: 440px; }

    /* Progress Steps */
    .hero-progress {
        display: flex; align-items: center; gap: 0;
        background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
        border-radius: 50px; padding: 6px 8px; backdrop-filter: blur(10px);
    }
    .prog-step {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 18px; border-radius: 50px; cursor: default;
        transition: var(--transition); position: relative;
    }
    .prog-step.active { background: var(--gold); }
    .prog-step.done { background: rgba(122,158,126,0.3); }
    .prog-num {
        width: 26px; height: 26px; border-radius: 50%;
        background: rgba(255,255,255,0.15); color: var(--white);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 700;
    }
    .prog-step.active .prog-num { background: var(--espresso); color: var(--honey); }
    .prog-step.done .prog-num { background: var(--sage); color: var(--white); }
    .prog-label { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.7); }
    .prog-step.active .prog-label { color: var(--white); }
    .prog-divider { width: 20px; height: 2px; background: rgba(255,255,255,0.15); margin: 0 2px; }

    /* ===================== MAIN CONTENT ===================== */
    .checkout-main {
        max-width: 1200px; margin: 40px auto 60px;
        padding: 0 24px;
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 32px;
        align-items: start;
    }
    @media (max-width: 900px) { .checkout-main { grid-template-columns: 1fr; } }

    /* ===================== FORM CARD ===================== */
    .form-card {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
    }
    .form-card-header {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        padding: 20px 28px;
        display: flex; align-items: center; gap: 12px;
    }
    .form-card-header .step-badge {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(201,145,42,0.3); border: 2px solid var(--gold);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        font-family: 'Playfair Display', serif; font-weight: 700; font-size: 1rem;
        flex-shrink: 0;
    }
    .form-card-header h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem; font-weight: 700; color: var(--honey); margin: 0;
    }
    .form-card-header p { color: rgba(255,255,255,0.5); font-size: 0.82rem; margin: 2px 0 0; }
    .form-card-body { padding: 28px; }

    /* Inputs */
    .form-section { margin-bottom: 32px; }
    .form-section:last-child { margin-bottom: 0; }
    .section-label {
        font-size: 0.75rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: var(--caramel);
        margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
    }
    .section-label::after { content: ''; flex: 1; height: 1px; background: var(--mist); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }
    .form-group { margin-bottom: 16px; position: relative; }
    .form-group:last-child { margin-bottom: 0; }
    .form-label-styled {
        display: block; font-weight: 600; font-size: 0.85rem;
        color: var(--mahogany); margin-bottom: 8px;
        display: flex; align-items: center; gap: 6px;
    }
    .form-label-styled i { color: var(--caramel); font-size: 0.8rem; width: 14px; }
    .input-styled {
        width: 100%; padding: 12px 16px;
        border: 2px solid var(--mist); border-radius: var(--radius-sm);
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.92rem; font-weight: 500;
        transition: var(--transition); outline: none;
    }
    .input-styled:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }
    .input-styled::placeholder { color: var(--caramel); opacity: 0.7; font-weight: 400; }
    textarea.input-styled { resize: vertical; min-height: 90px; }
    select.input-styled { cursor: pointer; }

    /* Address selector */
    .saved-address-pill {
        display: flex; align-items: center; gap: 10px;
        background: linear-gradient(135deg, rgba(201,145,42,0.1), rgba(201,145,42,0.05));
        border: 2px solid rgba(201,145,42,0.25); border-radius: 50px;
        padding: 10px 18px; cursor: pointer; transition: var(--transition);
        font-size: 0.88rem; font-weight: 600; color: var(--mahogany);
        width: 100%; margin-bottom: 10px; text-align: left;
    }
    .saved-address-pill:hover { border-color: var(--gold); background: rgba(201,145,42,0.12); }
    .saved-address-pill.active { border-color: var(--gold); background: rgba(201,145,42,0.12); }
    .saved-address-pill i { color: var(--gold); }
    .pill-default { margin-left: auto; font-size: 0.7rem; background: var(--sage-light); color: var(--sage); padding: 2px 8px; border-radius: 50px; }

    /* Payment methods */
    .payment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
    @media (max-width: 480px) { .payment-grid { grid-template-columns: 1fr; } }
    .payment-option input[type="radio"] { display: none; }
    .payment-tile {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 18px; border: 2px solid var(--mist);
        border-radius: var(--radius-sm); cursor: pointer; transition: var(--transition);
        background: var(--cream);
    }
    .payment-option input:checked + .payment-tile {
        border-color: var(--gold);
        background: rgba(201,145,42,0.06);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.08);
    }
    .payment-tile:hover { border-color: var(--honey); background: rgba(201,145,42,0.04); }
    .payment-tile-icon {
        width: 42px; height: 42px; border-radius: var(--radius-sm);
        background: var(--ivory); display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: var(--caramel); flex-shrink: 0;
        box-shadow: var(--shadow-sm);
    }
    .payment-option input:checked + .payment-tile .payment-tile-icon { color: var(--gold); }
    .payment-tile-info { flex: 1; }
    .payment-tile-name { font-weight: 700; color: var(--espresso); font-size: 0.9rem; }
    .payment-tile-desc { font-size: 0.75rem; color: var(--caramel); margin-top: 2px; }
    .payment-check { width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--mist); display: flex; align-items: center; justify-content: center; transition: var(--transition); }
    .payment-option input:checked + .payment-tile .payment-check { border-color: var(--gold); background: var(--gold); }
    .payment-option input:checked + .payment-tile .payment-check::after { content: '✓'; color: white; font-size: 0.7rem; font-weight: 700; }

    /* Credential field */
    .credential-box {
        background: linear-gradient(135deg, var(--cream), var(--mist));
        border-radius: var(--radius-sm); padding: 20px;
        border: 1px solid rgba(201,145,42,0.15);
        position: relative; overflow: hidden;
    }
    .credential-box::before {
        content: '';
        position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        background: linear-gradient(to bottom, var(--gold), var(--honey));
    }
    .credential-info {
        display: flex; align-items: center; gap: 10px;
        margin-bottom: 14px; padding-bottom: 14px;
        border-bottom: 1px solid rgba(201,145,42,0.15);
    }
    .credential-info i { color: var(--gold); font-size: 1.2rem; }
    .credential-info span { font-size: 0.85rem; color: var(--caramel); }
    .credential-info strong { color: var(--mahogany); }

    /* Save checkbox */
    .toggle-row {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px; background: rgba(122,158,126,0.08);
        border: 1px solid rgba(122,158,126,0.2); border-radius: var(--radius-sm);
        cursor: pointer;
    }
    .toggle-row input[type="checkbox"] { display: none; }
    .toggle-switch {
        width: 44px; height: 24px; border-radius: 50px;
        background: var(--mist); position: relative; flex-shrink: 0; transition: var(--transition);
    }
    .toggle-switch::after {
        content: ''; position: absolute;
        width: 18px; height: 18px; border-radius: 50%;
        background: white; top: 3px; left: 3px; transition: var(--transition);
        box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    }
    .toggle-row input:checked ~ .toggle-info .toggle-switch { background: var(--sage); }
    .toggle-row:has(input:checked) .toggle-switch { background: var(--sage); }
    .toggle-row:has(input:checked) .toggle-switch::after { transform: translateX(20px); }
    .toggle-info { flex: 1; }
    .toggle-info strong { font-size: 0.88rem; color: var(--mahogany); display: block; }
    .toggle-info span { font-size: 0.78rem; color: var(--caramel); }

    /* Action Buttons */
    .action-buttons { display: grid; grid-template-columns: auto 1fr; gap: 14px; margin-top: 28px; }
    @media (max-width: 480px) { .action-buttons { grid-template-columns: 1fr; } }
    .btn-back {
        display: flex; align-items: center; gap: 8px;
        background: transparent; border: 2px solid var(--mist);
        color: var(--mahogany); padding: 14px 24px; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.9rem;
        cursor: pointer; transition: var(--transition); text-decoration: none;
        white-space: nowrap;
    }
    .btn-back:hover { border-color: var(--caramel); background: var(--mist); color: var(--espresso); }
    .btn-pay {
        display: flex; align-items: center; justify-content: center; gap: 10px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; padding: 14px 28px; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 1rem;
        cursor: pointer; transition: var(--transition);
        box-shadow: 0 6px 20px rgba(201,145,42,0.35);
    }
    .btn-pay:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(44,26,14,0.25);
    }
    .btn-pay i { font-size: 1.1rem; }

    /* Trust badges */
    .trust-strip {
        display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
        margin-top: 20px; padding-top: 20px;
        border-top: 1px solid var(--mist);
    }
    .trust-item { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: var(--caramel); }
    .trust-item i { color: var(--sage); }

    /* ===================== ORDER SUMMARY SIDEBAR ===================== */
    .sidebar { position: sticky; top: 92px; display: flex; flex-direction: column; gap: 20px; }
    @media (max-width: 900px) { .sidebar { position: static; } }

    .summary-card {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
    }
    .summary-card-header {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        padding: 18px 24px;
        display: flex; align-items: center; gap: 10px;
    }
    .summary-card-header h4 {
        font-family: 'Playfair Display', serif;
        font-size: 1.1rem; font-weight: 700; color: var(--honey); margin: 0;
    }
    .summary-card-header i { color: var(--gold); }
    .summary-card-body { padding: 20px 24px; }

    /* Order items */
    .order-item {
        display: flex; gap: 12px; align-items: center;
        padding: 12px 0; border-bottom: 1px solid var(--mist);
    }
    .order-item:last-child { border-bottom: none; }
    .order-item-img {
        width: 60px; height: 60px; border-radius: var(--radius-sm);
        background: var(--cream); overflow: hidden; flex-shrink: 0;
        border: 2px solid var(--mist); display: flex; align-items: center; justify-content: center;
    }
    .order-item-img img { width: 100%; height: 100%; object-fit: cover; }
    .order-item-info { flex: 1; min-width: 0; }
    .order-item-name { font-weight: 700; color: var(--espresso); font-size: 0.88rem; line-height: 1.3; }
    .order-item-cat {
        display: inline-flex; align-items: center; gap: 4px;
        background: rgba(201,145,42,0.1); color: var(--caramel);
        padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 600;
        margin-top: 3px;
    }
    .order-item-price { font-weight: 700; color: var(--mahogany); font-size: 0.88rem; text-align: right; white-space: nowrap; }
    .order-item-qty { font-size: 0.75rem; color: var(--caramel); text-align: right; }

    /* Coupon */
    .coupon-box {
        background: linear-gradient(135deg, rgba(122,158,126,0.08), rgba(122,158,126,0.04));
        border: 1px dashed rgba(122,158,126,0.4); border-radius: var(--radius-sm);
        padding: 14px;
    }
    .coupon-label { font-size: 0.78rem; font-weight: 700; color: var(--sage); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .coupon-input-row { display: flex; gap: 8px; }
    .coupon-input {
        flex: 1; padding: 10px 14px;
        border: 2px solid rgba(122,158,126,0.3); border-radius: 50px;
        background: var(--ivory); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.88rem; font-weight: 600;
        outline: none; transition: var(--transition);
        letter-spacing: 1px; text-transform: uppercase;
    }
    .coupon-input:focus { border-color: var(--sage); box-shadow: 0 0 0 3px rgba(122,158,126,0.15); }
    .coupon-input::placeholder { text-transform: none; letter-spacing: 0; font-weight: 400; }
    .coupon-btn {
        background: var(--sage); color: white; border: none;
        padding: 10px 16px; border-radius: 50px; font-weight: 700;
        font-size: 0.82rem; cursor: pointer; transition: var(--transition); white-space: nowrap;
    }
    .coupon-btn:hover { background: #5e8463; transform: translateY(-1px); }
    .coupon-msg { font-size: 0.78rem; margin-top: 8px; }

    /* Totals */
    .totals-box { margin-top: 4px; }
    .total-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid rgba(201,145,42,0.1);
        font-size: 0.88rem; color: var(--caramel);
    }
    .total-row:last-child { border-bottom: none; }
    .total-row span:last-child { font-weight: 600; color: var(--espresso); }
    .total-row.discount span:last-child { color: var(--sage); }
    .total-row.grand {
        margin-top: 10px; padding-top: 14px;
        border-top: 2px solid var(--mist); border-bottom: none;
        font-size: 1.1rem; font-weight: 700; color: var(--espresso);
    }
    .total-row.grand span:last-child { color: var(--gold); font-size: 1.2rem; font-family: 'Playfair Display', serif; }

    /* Free shipping notice */
    .shipping-banner {
        background: linear-gradient(135deg, rgba(122,158,126,0.12), rgba(122,158,126,0.06));
        border: 1px solid rgba(122,158,126,0.25); border-radius: var(--radius-sm);
        padding: 12px 16px; display: flex; align-items: center; gap: 10px;
        font-size: 0.85rem; color: var(--sage);
    }
    .shipping-banner i { font-size: 1rem; }
    .shipping-banner strong { color: var(--mahogany); }

    /* Balance check card */
    .balance-card {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.1);
        padding: 18px 22px; display: flex; align-items: center; gap: 14px;
    }
    .balance-icon {
        width: 46px; height: 46px; border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        display: flex; align-items: center; justify-content: center;
        color: var(--espresso); font-size: 1.1rem; flex-shrink: 0;
    }
    .balance-info strong { display: block; font-size: 0.95rem; color: var(--mahogany); }
    .balance-value { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--gold); font-weight: 700; }
    .balance-label { font-size: 0.75rem; color: var(--caramel); }

    /* ===================== EMPTY CART ===================== */
    .empty-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(44,26,14,0.7); backdrop-filter: blur(8px);
        display: flex; align-items: center; justify-content: center; z-index: 9999;
    }
    .empty-modal {
        background: var(--ivory); border-radius: var(--radius);
        padding: 50px 40px; max-width: 440px; width: 90%;
        box-shadow: var(--shadow-lg); text-align: center;
        border: 1px solid rgba(201,145,42,0.2);
        animation: slideUp 0.4s ease;
    }
    @keyframes slideUp { from { transform:translateY(40px); opacity:0; } to { transform:translateY(0); opacity:1; } }
    .empty-modal i { font-size: 4rem; color: var(--mist); margin-bottom: 20px; display: block; }
    .empty-modal h2 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--mahogany); margin-bottom: 12px; }
    .empty-modal p { color: var(--caramel); line-height: 1.7; margin-bottom: 28px; }
    .btn-back-shop {
        display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); text-decoration: none; padding: 10px 24px;
        border-radius: 50px; font-weight: 700; font-size: 0.9rem; transition: var(--transition);
        box-shadow: 0 4px 12px rgba(201,145,42,0.25);
    }
    .btn-back-shop:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); transform: translateY(-2px); }

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

    /* ===================== SCROLL TOP ===================== */
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

    /* ===================== COUPON SUCCESS MODAL ===================== */
    .coupon-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(44,26,14,0.6); backdrop-filter: blur(6px);
        display: flex; align-items: center; justify-content: center; z-index: 9999;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    .coupon-success-box {
        background: var(--ivory); border-radius: var(--radius); padding: 36px;
        max-width: 420px; width: 90%; box-shadow: var(--shadow-lg);
        text-align: center; animation: slideUp 0.4s ease;
        border: 1px solid rgba(201,145,42,0.2);
    }
    .coupon-success-icon { font-size: 3.5rem; color: var(--sage); margin-bottom: 16px; }
    .coupon-tag {
        font-family: monospace; font-size: 1.3rem; font-weight: 700;
        color: var(--mahogany); background: var(--cream);
        padding: 8px 20px; border-radius: 8px; display: inline-block;
        border: 2px dashed var(--honey); margin-bottom: 20px; letter-spacing: 2px;
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .navbar { padding: 0 20px; }
        .nav-links a:not(.active) { display: none; }
        .checkout-hero { padding: 40px 24px 50px; }
        .hero-progress { flex-wrap: wrap; gap: 6px; }
        .checkout-main { margin: 24px auto 40px; padding: 0 16px; }
        .form-card-body { padding: 20px; }
        .summary-card-body { padding: 16px 18px; }
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

<!-- ===================== HERO BANNER ===================== -->
<section class="checkout-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="checkout-hero-inner">
        <div>
            <div class="hero-label"><i class="fas fa-lock"></i> SECURE CHECKOUT</div>
            <h1 class="hero-title">Complete Your <em>Order</em></h1>
            <p class="hero-subtitle">Your payment is protected by industry-standard SSL encryption. Fast, safe, and reliable delivery guaranteed.</p>
        </div>
        <div class="hero-progress">
            <div class="prog-step done">
                <div class="prog-num"><i class="fas fa-check" style="font-size:0.7rem;"></i></div>
                <span class="prog-label">Cart</span>
            </div>
            <div class="prog-divider"></div>
            <div class="prog-step active">
                <div class="prog-num">2</div>
                <span class="prog-label">Checkout</span>
            </div>
            <div class="prog-divider"></div>
            <div class="prog-step">
                <div class="prog-num">3</div>
                <span class="prog-label">Confirmation</span>
            </div>
        </div>
    </div>
</section>

<?php if ($is_empty_cart): ?>
<!-- ===================== EMPTY CART ===================== -->
<div class="empty-overlay">
    <div class="empty-modal">
        <i class="fas fa-shopping-bag"></i>
        <h2>Your Cart is Empty</h2>
        <p>Looks like you haven't added anything yet. Head back to the shop and pick some treats for your feline friend!</p>
        <a href="shop.php" class="btn-back-shop"><i class="fas fa-arrow-left"></i> Back to Shop</a>
    </div>
</div>
<?php else: ?>

<!-- ===================== MAIN CHECKOUT ===================== -->
<div class="checkout-main">

    <!-- LEFT: FORM -->
    <div>
        <form action="process_payment.php" method="POST" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
            <input type="hidden" name="save_delivery_info" id="save_delivery_info_flag" value="0">
            <input type="hidden" name="subtotal" value="<?= $total ?>">
            <input type="hidden" name="discount_percent" id="hiddenDiscountPercent" value="<?= $discount_percent ?>">
            <input type="hidden" name="discount_amount" id="hiddenDiscountAmt" value="<?= $discount_amount ?>">
            <input type="hidden" name="coupon_code" id="hiddenCoupon" value="<?= htmlspecialchars($coupon_code) ?>">
            <input type="hidden" name="total" id="hiddenTotal" value="<?= $grand_total ?>">
            <input type="hidden" name="buy_now" value="<?= $buy_now ? '1' : '0' ?>">
            <?php if ($buy_now): ?>
                <input type="hidden" name="product_id" value="<?= $cart_items[0]['product_id'] ?>">
                <input type="hidden" name="quantity" value="<?= $cart_items[0]['quantity'] ?>">
            <?php endif; ?>

            <!-- Delivery Section -->
            <div class="form-card" style="margin-bottom:24px;">
                <div class="form-card-header">
                    <div class="step-badge">1</div>
                    <div>
                        <h3>Delivery Information</h3>
                        <p>Where should we send your order?</p>
                    </div>
                </div>
                <div class="form-card-body">

                    <?php if (!empty($addresses)): ?>
                    <div class="form-section">
                        <div class="section-label"><i class="fas fa-bookmark" style="color:var(--gold);"></i> Saved Addresses</div>
                        <?php foreach ($addresses as $idx => $addr): ?>
                        <button type="button" class="saved-address-pill <?= $idx===0 ? 'active' : '' ?>"
                            onclick="fillAddress('<?= htmlspecialchars($addr['full_name'],ENT_QUOTES) ?>','<?= htmlspecialchars($addr['phone'],ENT_QUOTES) ?>','<?= htmlspecialchars($addr['city'],ENT_QUOTES) ?>','<?= htmlspecialchars($addr['postal_code'],ENT_QUOTES) ?>','<?= htmlspecialchars($addr['address'],ENT_QUOTES) ?>',this)">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($addr['full_name']) ?> — <?= htmlspecialchars(substr($addr['address'],0,45)) ?><?= strlen($addr['address'])>45?'…':'' ?></span>
                            <?php if ($addr['is_default']): ?><span class="pill-default">Default</span><?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                        <button type="button" class="saved-address-pill" onclick="clearAddress(this)">
                            <i class="fas fa-plus"></i> <span>Enter a new address</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="form-section">
                        <div class="section-label"><i class="fas fa-user" style="color:var(--gold);"></i> Contact Details</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label-styled" for="full_name"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="full_name" id="full_name" class="input-styled"
                                    placeholder="Your complete name"
                                    value="<?= htmlspecialchars($saved_delivery['delivery_full_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label-styled" for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="input-styled"
                                    placeholder="e.g. 0917XXXXXXX"
                                    value="<?= htmlspecialchars($saved_delivery['delivery_phone'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-label"><i class="fas fa-map-pin" style="color:var(--gold);"></i> Delivery Address</div>
                        <div class="form-row" style="margin-bottom:16px;">
                            <div class="form-group">
                                <label class="form-label-styled" for="city"><i class="fas fa-city"></i> City / Municipality</label>
                                <input type="text" name="city" id="city" class="input-styled"
                                    placeholder="e.g. Manila"
                                    value="<?= htmlspecialchars($saved_delivery['delivery_city'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label-styled" for="postal_code"><i class="fas fa-mailbox"></i> Postal Code</label>
                                <input type="text" name="postal_code" id="postal_code" class="input-styled"
                                    placeholder="e.g. 1000"
                                    value="<?= htmlspecialchars($saved_delivery['delivery_postal_code'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label-styled" for="location"><i class="fas fa-home"></i> Full Street Address</label>
                            <textarea name="location" id="location" class="input-styled"
                                placeholder="Street, Barangay, Building / Unit No., Landmark…" required><?= htmlspecialchars($saved_delivery['delivery_address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <label class="toggle-row">
                        <input type="checkbox" name="save_delivery" id="save_delivery" value="1" <?= !empty($saved_delivery['delivery_full_name']) ? 'checked' : '' ?>>
                        <div class="toggle-switch"></div>
                        <div class="toggle-info">
                            <strong>Save this address to my profile</strong>
                            <span>Use it faster on your next order</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Payment Method Section -->
            <div class="form-card" style="margin-bottom:24px;">
                <div class="form-card-header">
                    <div class="step-badge">2</div>
                    <div>
                        <h3>Payment Method</h3>
                        <p>Choose how you'd like to pay</p>
                    </div>
                </div>
                <div class="form-card-body">
                    <div class="form-section">
                        <div class="section-label"><i class="fas fa-credit-card" style="color:var(--gold);"></i> Select Method</div>
                        <div class="payment-grid">
                            <div class="payment-option">
                                <input type="radio" name="method" id="gcash" value="GCash" onchange="updateCredential()">
                                <label for="gcash" class="payment-tile">
                                    <div class="payment-tile-icon"><i class="fas fa-mobile-alt"></i></div>
                                    <div class="payment-tile-info">
                                        <div class="payment-tile-name">GCash</div>
                                        <div class="payment-tile-desc">Mobile wallet</div>
                                    </div>
                                    <div class="payment-check"></div>
                                </label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" name="method" id="paypal" value="PayPal" onchange="updateCredential()">
                                <label for="paypal" class="payment-tile">
                                    <div class="payment-tile-icon"><i class="fab fa-paypal"></i></div>
                                    <div class="payment-tile-info">
                                        <div class="payment-tile-name">PayPal</div>
                                        <div class="payment-tile-desc">Pay with email</div>
                                    </div>
                                    <div class="payment-check"></div>
                                </label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" name="method" id="mastercard" value="MasterCard" onchange="updateCredential()">
                                <label for="mastercard" class="payment-tile">
                                    <div class="payment-tile-icon"><i class="fab fa-cc-mastercard"></i></div>
                                    <div class="payment-tile-info">
                                        <div class="payment-tile-name">MasterCard</div>
                                        <div class="payment-tile-desc">Credit / Debit</div>
                                    </div>
                                    <div class="payment-check"></div>
                                </label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" name="method" id="applepay" value="Apple Pay" onchange="updateCredential()">
                                <label for="applepay" class="payment-tile">
                                    <div class="payment-tile-icon"><i class="fab fa-apple"></i></div>
                                    <div class="payment-tile-info">
                                        <div class="payment-tile-name">Apple Pay</div>
                                        <div class="payment-tile-desc">Pay with Apple ID</div>
                                    </div>
                                    <div class="payment-check"></div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-section" id="credentialSection" style="display:none;">
                        <div class="section-label"><i class="fas fa-key" style="color:var(--gold);"></i> Payment Details</div>
                        <div class="credential-box">
                            <div class="credential-info">
                                <i id="credIcon" class="fas fa-credit-card"></i>
                                <span>Enter your <strong id="credMethodName">payment credential</strong> below. Your information is fully encrypted.</span>
                            </div>
                            <label class="form-label-styled" for="credential">
                                <i id="credLabelIcon" class="fas fa-credit-card"></i>
                                <span id="credLabel">Payment Credential</span>
                            </label>
                            <input type="text" name="credential" id="credential" class="input-styled"
                                placeholder="Enter credential" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="shop.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Shop</a>
                <button type="submit" class="btn-pay" id="payBtn">
                    <i class="fas fa-lock"></i>
                    <span>Confirm & Pay ₱<span id="btnTotal"><?= number_format($grand_total, 2) ?></span></span>
                </button>
            </div>

            <!-- Trust Badges -->
            <div class="trust-strip">
                <div class="trust-item"><i class="fas fa-lock"></i> SSL Encrypted</div>
                <div class="trust-item"><i class="fas fa-shield-alt"></i> Secure Payment</div>
                <div class="trust-item"><i class="fas fa-check-circle"></i> Verified Checkout</div>
                <div class="trust-item"><i class="fas fa-undo"></i> Easy Returns</div>
            </div>
        </form>
    </div>

    <!-- RIGHT: SIDEBAR -->
    <div class="sidebar">

        <!-- Balance Card -->
        <div class="balance-card">
            <div class="balance-icon"><i class="fas fa-wallet"></i></div>
            <div class="balance-info">
                <strong>Account Balance</strong>
                <div class="balance-value">₱<?= number_format($nav_balance, 2) ?></div>
                <div class="balance-label">Available for purchases</div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="summary-card">
            <div class="summary-card-header">
                <i class="fas fa-receipt"></i>
                <h4>Order Summary</h4>
            </div>
            <div class="summary-card-body">
                <!-- Items -->
                <div style="margin-bottom:16px;">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <div class="order-item-img">
                            <?php if (!empty($item['image'])): ?>
                                <img loading="lazy" src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <?php else: ?>
                                <i class="fas fa-box" style="color:var(--mist); font-size:1.4rem;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="order-item-info">
                            <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <span class="order-item-cat"><i class="fas fa-tag"></i><?= htmlspecialchars($item['category']) ?></span>
                        </div>
                        <div>
                            <div class="order-item-price">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            <div class="order-item-qty">×<?= $item['quantity'] ?> @ ₱<?= number_format($item['price'], 2) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Coupon -->
                <div class="coupon-box" style="margin-bottom:16px;">
                    <div class="coupon-label"><i class="fas fa-tag"></i> Promo Code</div>
                    <div class="coupon-input-row">
                        <input type="text" id="couponInput" class="coupon-input" placeholder="Enter code…" autocomplete="off">
                        <button type="button" class="coupon-btn" id="applyCouponBtn">Apply</button>
                    </div>
                    <div id="couponMsg" class="coupon-msg"></div>
                </div>

                <!-- Totals -->
                <div class="totals-box">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span id="displaySubtotal">₱<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="total-row discount" id="discountRow" style="<?= $discount_percent > 0 ? '' : 'display:none;' ?>">
                        <span>Discount (<span id="discPct"><?= number_format($discount_percent, 1) ?></span>%)</span>
                        <span>−₱<span id="discAmt"><?= number_format($discount_amount, 2) ?></span></span>
                    </div>
                    <div class="total-row">
                        <span>VAT (12%)</span>
                        <span id="displayVat">₱<?= number_format($tax, 2) ?></span>
                    </div>
                    <div class="total-row">
                        <span>Shipping</span>
                        <span style="color:var(--sage); font-weight:700;">FREE</span>
                    </div>
                    <div class="total-row grand">
                        <span>Total</span>
                        <span id="displayTotal">₱<?= number_format($grand_total, 2) ?></span>
                    </div>
                </div>

                <!-- Shipping banner -->
                <div class="shipping-banner" style="margin-top:16px;">
                    <i class="fas fa-truck"></i>
                    <span><strong>Free Delivery</strong> on all Pawganic orders across the Philippines</span>
                </div>
            </div>
        </div>

    </div><!-- /sidebar -->
</div><!-- /checkout-main -->

<?php endif; ?>

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
const SUBTOTAL = <?= $total ?>;

/* ===================== PAYMENT CREDENTIAL ===================== */
const paymentDetails = {
    'GCash':      { icon: 'fas fa-mobile-alt',    label: 'GCash Number',   placeholder: 'e.g. 0917XXXXXXX',          method: 'GCash' },
    'PayPal':     { icon: 'fab fa-paypal',         label: 'PayPal Email',   placeholder: 'yourname@example.com',       method: 'PayPal' },
    'MasterCard': { icon: 'fab fa-cc-mastercard',  label: 'Card Number',    placeholder: 'e.g. 1234 5678 9012 3456',  method: 'MasterCard' },
    'Apple Pay':  { icon: 'fab fa-apple',          label: 'Apple ID Email', placeholder: 'appleid@example.com',        method: 'Apple Pay' }
};

function updateCredential() {
    const method = document.querySelector('input[name="method"]:checked');
    const section = document.getElementById('credentialSection');
    if (!method) { section.style.display = 'none'; return; }
    section.style.display = 'block';
    const d = paymentDetails[method.value];
    if (!d) return;
    document.getElementById('credIcon').className = d.icon + ' fa-lg';
    document.getElementById('credMethodName').textContent = d.method;
    document.getElementById('credLabelIcon').className = d.icon;
    document.getElementById('credLabel').textContent = d.label;
    document.getElementById('credential').placeholder = d.placeholder;
}

/* ===================== SAVED ADDRESS FILL ===================== */
function fillAddress(name, phone, city, postal, address, btn) {
    document.getElementById('full_name').value = name;
    document.getElementById('phone').value = phone;
    document.getElementById('city').value = city;
    document.getElementById('postal_code').value = postal;
    document.getElementById('location').value = address;
    document.querySelectorAll('.saved-address-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}
function clearAddress(btn) {
    ['full_name','phone','city','postal_code','location'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.querySelectorAll('.saved-address-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('full_name').focus();
}

/* ===================== COUPON ===================== */
document.getElementById('applyCouponBtn').addEventListener('click', function() {
    const code = document.getElementById('couponInput').value.trim();
    const msg  = document.getElementById('couponMsg');
    if (!code) { msg.innerHTML = '<span style="color:var(--danger); font-size:0.8rem;"><i class="fas fa-times-circle"></i> Please enter a coupon code.</span>'; return; }

    this.textContent = '…';
    this.disabled = true;

    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    fetch('validate_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&coupon_code=' + encodeURIComponent(code) + '&subtotal=' + SUBTOTAL
    })
    .then(r => r.json())
    .then(data => {
        this.textContent = 'Apply';
        this.disabled = false;
        if (data.success) {
            document.getElementById('hiddenCoupon').value = data.coupon_code;
            document.getElementById('hiddenDiscountPercent').value = data.discount_percent;
            document.getElementById('hiddenDiscountAmt').value = data.discount_amount;
            document.getElementById('hiddenTotal').value = data.final_total;
            updateTotals(SUBTOTAL, data.discount_percent, data.discount_amount, data.final_total);
            msg.innerHTML = '<span style="color:var(--sage); font-size:0.8rem;"><i class="fas fa-check-circle"></i> ' + data.discount_percent + '% discount applied!</span>';
            showCouponSuccess(data);
        } else {
            msg.innerHTML = '<span style="color:var(--danger); font-size:0.8rem;"><i class="fas fa-times-circle"></i> ' + (data.error || 'Invalid coupon.') + '</span>';
        }
    })
    .catch(() => {
        this.textContent = 'Apply';
        this.disabled = false;
        msg.innerHTML = '<span style="color:var(--danger); font-size:0.8rem;"><i class="fas fa-exclamation-circle"></i> Connection error.</span>';
    });
});

document.getElementById('couponInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('applyCouponBtn').click(); }
});

function updateTotals(subtotal, discPct, discAmt, finalTotal) {
    const vat = (subtotal - discAmt) * 0.12;
    document.getElementById('displaySubtotal').textContent = '₱' + parseFloat(subtotal).toFixed(2);
    document.getElementById('discPct').textContent = parseFloat(discPct).toFixed(1);
    document.getElementById('discAmt').textContent = parseFloat(discAmt).toFixed(2);
    document.getElementById('discountRow').style.display = discPct > 0 ? 'flex' : 'none';
    document.getElementById('displayVat').textContent = '₱' + vat.toFixed(2);
    document.getElementById('displayTotal').textContent = '₱' + parseFloat(finalTotal).toFixed(2);
    document.getElementById('btnTotal').textContent = parseFloat(finalTotal).toFixed(2);
}

function showCouponSuccess(data) {
    const vat = ((data.subtotal - data.discount_amount) * 0.12).toFixed(2);
    const overlay = document.createElement('div');
    overlay.className = 'coupon-modal-overlay';
    overlay.innerHTML = `
        <div class="coupon-success-box">
            <div class="coupon-success-icon"><i class="fas fa-check-circle"></i></div>
            <h3 style="font-family:'Playfair Display',serif; color:var(--mahogany); margin-bottom:8px;">Coupon Applied!</h3>
            <div class="coupon-tag">${data.coupon_code}</div>
            <div style="background:var(--cream); border-radius:var(--radius-sm); padding:16px; text-align:left; margin-bottom:20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem; color:var(--caramel);">
                    <span>Subtotal</span><span style="color:var(--espresso); font-weight:600;">₱${parseFloat(data.subtotal).toFixed(2)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem; color:var(--sage);">
                    <span>Discount (${data.discount_percent}%)</span><span style="font-weight:700;">−₱${parseFloat(data.discount_amount).toFixed(2)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.9rem; color:var(--caramel);">
                    <span>VAT (12%)</span><span style="color:var(--espresso); font-weight:600;">₱${vat}</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding-top:10px; border-top:2px solid var(--mist); font-size:1.05rem; font-weight:700; color:var(--espresso);">
                    <span>New Total</span><span style="color:var(--gold); font-family:'Playfair Display',serif;">₱${parseFloat(data.final_total).toFixed(2)}</span>
                </div>
            </div>
            <button onclick="this.closest('.coupon-modal-overlay').remove()" style="width:100%; padding:12px; background:linear-gradient(135deg,var(--gold),var(--honey)); color:var(--espresso); border:none; border-radius:50px; font-weight:700; cursor:pointer; font-size:0.95rem;">
                <i class="fas fa-check"></i> Great, thanks!
            </button>
        </div>
    `;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.remove(); });
}

/* ===================== FORM SUBMIT ===================== */
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const saveDelivery = document.getElementById('save_delivery').checked;
    document.getElementById('save_delivery_info_flag').value = saveDelivery ? '1' : '0';
});

/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function() {
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

    // Auto-fill first saved address
    const firstPill = document.querySelector('.saved-address-pill.active');
    if (firstPill && firstPill.onclick) firstPill.click();
});
</script>
</body>
</html>