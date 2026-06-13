<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id      = $_SESSION['user_id'];
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role     = $_SESSION['role'] ?? 'customer';
$nav_balance  = $_SESSION['balance'] ?? 0;

$pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $user_id);
$pic_stmt->execute();
$pic_stmt->bind_result($profile_pic);
$pic_stmt->fetch();
$pic_stmt->close();
if (!$profile_pic) $profile_pic = 'images/profile.jpg';

// Fetch cart items
$stmt = $conn->prepare("SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.stock, p.image, p.category FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_items = [];
$subtotal = 0;
while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
    $subtotal += $row['price'] * $row['quantity'];
}
$stmt->close();

// Fetch 3 recommended products (not already in cart)
$rec_stmt = $conn->prepare("SELECT id, name, price, image, category FROM products WHERE id NOT IN (SELECT product_id FROM cart WHERE user_id = ?) LIMIT 3");
$rec_stmt->bind_param("i", $user_id);
$rec_stmt->execute();
$recommended_products = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rec_stmt->close();

// Shipping logic: Free for orders over ₱1500, otherwise ₱100
$shipping_fee = ($subtotal > 1500 || $subtotal == 0) ? 0 : 100;
$grand_total = $subtotal + $shipping_fee;
$tax_included = $subtotal * 0.12; // 12% VAT included
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Bag — Pawganic Supplies</title>
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
            --danger:     #c0392b;
            --white:      #ffffff;
            --shadow-sm:  0 2px 12px rgba(44,26,14,0.10);
            --shadow-md:  0 8px 32px rgba(44,26,14,0.16);
            --shadow-lg:  0 20px 60px rgba(44,26,14,0.22);
            --radius:     18px;
            --radius-sm:  10px;
            --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background-color: var(--cream);
            font-family: 'DM Sans', sans-serif;
            color: var(--espresso);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===================== NAVBAR ===================== */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(253,248,240,0.92);
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

        .logo-img { height: 40px; width: auto; }

        .nav-links { display: flex; align-items: center; gap: 6px; }

        .nav-links a {
            color: var(--mahogany);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }
        .nav-links a:hover { background: var(--gold); color: var(--white); }

        .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
        .profile-pic {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
            border: 2.5px solid var(--gold); transition: var(--transition);
        }

        .dropdown-content {
            display: none;
            position: absolute; right: 0; top: calc(100% + 10px);
            background: var(--ivory); border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg); min-width: 220px; z-index: 1000;
            border: 1px solid rgba(201,145,42,0.15);
            overflow: hidden;
            animation: dropDown 0.25s ease;
        }
        @keyframes dropDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .profile-dropdown:hover .dropdown-content { display: block; }

        .dropdown-profile-info {
            padding: 16px; border-bottom: 1px solid var(--mist);
            background: linear-gradient(135deg, var(--cream), var(--ivory));
        }
        .dropdown-profile-name { font-weight: 700; color: var(--mahogany); font-size: 0.95rem; }
        .dropdown-profile-role { font-size: 0.78rem; color: var(--caramel); margin-top: 2px; }
        .dropdown-profile-balance { font-size: 0.85rem; color: var(--gold); font-weight: 600; margin-top: 5px; }

        .dropdown-content a {
            display: flex; align-items: center; gap: 10px;
            color: var(--espresso); text-decoration: none; padding: 12px 16px;
            font-size: 0.9rem; transition: var(--transition);
        }
        .dropdown-content a:hover { background: var(--cream); color: var(--mahogany); padding-left: 22px; }
        .dropdown-content a i { width: 18px; color: var(--caramel); }

        /* ===================== LAYOUT CONTAINER ===================== */
        .cart-section {
            flex: 1;
            max-width: 1200px;
            width: 100%;
            margin: 40px auto;
            padding: 0 24px;
        }

        .cart-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 900;
            color: var(--espresso);
            margin-bottom: 30px;
        }

        .cart-title em {
            font-style: italic;
            color: var(--gold);
        }

        /* Split columns */
        .cart-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 40px;
            align-items: start;
        }

        /* ===================== LEFT: ITEM CARDS ===================== */
        .cart-item-card {
            background: var(--ivory);
            border: 1px solid rgba(201, 145, 42, 0.12);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .cart-item-card:hover {
            box-shadow: var(--shadow-md);
            border-color: rgba(201, 145, 42, 0.25);
        }

        .item-image {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid rgba(201, 145, 42, 0.08);
            flex-shrink: 0;
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .item-category {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--caramel);
            font-weight: 600;
        }

        .item-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--espresso);
            line-height: 1.3;
        }

        .item-stock {
            font-size: 0.78rem;
            font-weight: 500;
        }
        .item-stock.in { color: var(--sage); }
        .item-stock.low { color: var(--gold); }

        .item-price-each {
            font-size: 0.85rem;
            color: rgba(44, 26, 14, 0.65);
        }

        /* Quantity controls */
        .item-actions-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 12px;
        }

        .quantity-selector {
            display: inline-flex;
            align-items: center;
            background: var(--cream);
            border: 1px solid var(--mist);
            border-radius: 50px;
            padding: 4px;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            color: var(--mahogany);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .qty-btn:hover {
            background: var(--gold);
            color: var(--white);
        }

        .qty-input {
            width: 35px;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--espresso);
            outline: none;
        }

        .item-price-total {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--mahogany);
            transition: transform 0.2s ease;
        }

        .pulse-text {
            transform: scale(1.08);
            color: var(--gold);
        }

        /* Remove button */
        .btn-remove-item {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: rgba(44, 26, 14, 0.35);
            cursor: pointer;
            font-size: 1.1rem;
            transition: var(--transition);
            padding: 5px;
        }

        .btn-remove-item:hover {
            color: var(--danger);
            transform: scale(1.1);
        }

        /* ===================== RIGHT: RECEIPT SUMMARY ===================== */
        .summary-card {
            background: var(--ivory);
            border: 2px solid var(--gold);
            border-radius: var(--radius);
            padding: 32px;
            position: sticky;
            top: 100px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--gold), var(--honey));
        }

        .summary-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--espresso);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--mist);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.92rem;
            margin-bottom: 16px;
        }

        .summary-row.total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--mahogany);
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px dashed var(--mist);
        }

        .summary-label {
            color: rgba(44, 26, 14, 0.7);
        }

        .summary-value {
            font-weight: 600;
        }

        .summary-value.grand {
            color: var(--gold);
        }

        /* Promo code input */
        .promo-section {
            margin: 22px 0;
            padding: 18px 0;
            border-top: 1px solid rgba(201, 145, 42, 0.1);
            border-bottom: 1px solid rgba(201, 145, 42, 0.1);
        }

        .promo-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--caramel);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .promo-input-group {
            display: flex;
            gap: 8px;
        }

        .promo-input {
            flex: 1;
            border-radius: 50px;
            border: 1px solid var(--mist);
            background: var(--cream);
            padding: 8px 16px;
            font-size: 0.88rem;
            color: var(--espresso);
            outline: none;
            transition: var(--transition);
        }

        .promo-input:focus {
            border-color: var(--gold);
            background: var(--white);
        }

        .btn-promo-apply {
            background: var(--espresso);
            color: var(--honey);
            border: none;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-promo-apply:hover {
            background: var(--gold);
            color: var(--white);
        }

        .btn-checkout {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 100%);
            color: var(--honey);
            border: none;
            border-radius: 50px;
            width: 100%;
            padding: 14px;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--white);
        }

        .btn-checkout:active {
            transform: translateY(1px);
        }

        /* ===================== CROSS SELL SECTION ===================== */
        .cross-sell-section {
            margin-top: 64px;
            border-top: 1px solid rgba(201, 145, 42, 0.16);
            padding-top: 40px;
        }

        .cross-sell-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--espresso);
            margin-bottom: 24px;
        }

        .cross-sell-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .cross-card {
            background: var(--ivory);
            border: 1px solid rgba(201, 145, 42, 0.12);
            border-radius: var(--radius);
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .cross-card:hover {
            border-color: var(--gold);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .cross-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(201, 145, 42, 0.08);
            flex-shrink: 0;
            transition: var(--transition);
        }

        .cross-card:hover .cross-img {
            transform: scale(1.04);
        }

        .cross-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .cross-name {
            font-weight: 700;
            font-size: 0.92rem;
            color: var(--espresso);
            line-height: 1.3;
        }

        .cross-price {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--gold);
        }

        .btn-cross-add {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--cream);
            border: none;
            color: var(--mahogany);
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .cross-card:hover .btn-cross-add {
            background: var(--gold);
            color: var(--white);
        }

        /* ===================== EMPTY STATE ===================== */
        .empty-state {
            text-align: center;
            padding: 80px 24px;
            background: var(--ivory);
            border: 1px solid rgba(201, 145, 42, 0.12);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--honey);
            margin-bottom: 20px;
        }

        .empty-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--espresso);
        }

        .empty-sub {
            font-size: 0.95rem;
            color: var(--caramel);
            margin-bottom: 28px;
        }

        .btn-empty-shop {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 100%);
            color: var(--honey);
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .btn-empty-shop:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--white);
        }

        /* ===================== TOAST NOTIFICATION ===================== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
        }

        #toastNotification {
            background: linear-gradient(135deg, var(--espresso) 0%, #1e1108 100%);
            border: 1px solid rgba(201, 145, 42, 0.35);
            border-radius: 14px;
            box-shadow: var(--shadow-lg);
            min-width: 300px;
            display: none;
            animation: slideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #toastNotification.show { display: block !important; }
        #toastNotification.hide { display: none !important; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toast-content {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toast-icon { font-size: 1.15rem; }
        #toastNotification.success .toast-icon { color: var(--sage); }
        #toastNotification.error .toast-icon { color: var(--danger); }

        .toast-text {
            flex: 1;
            color: var(--white);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .toast-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.45);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 2px;
            transition: var(--transition);
        }

        .toast-close:hover { color: var(--white); }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            .cart-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .summary-card {
                position: static;
            }
        }

        @media (max-width: 480px) {
            .cart-item-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 18px;
            }
            .item-image {
                width: 100%;
                height: 180px;
            }
            .item-actions-row {
                width: 100%;
            }
            .btn-remove-item {
                top: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>

    <!-- Custom Toast Notification -->
    <div class="toast-container">
        <div id="toastNotification" class="toast hide" role="alert" aria-live="polite" aria-atomic="true">
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas" id="toastIcon"></i>
                </div>
                <div class="toast-text" id="toastBody"></div>
                <button type="button" class="toast-close" onclick="closeToast()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== NAVBAR ===================== -->
    <div class="navbar">
        <a href="main.php" style="text-decoration:none;">
            <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" class="logo-img">
        </a>
        <div class="nav-links">
            <a href="main.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="about.php">About</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            
            <div class="profile-dropdown">
                <img src="/petv10/<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic" onerror="this.src='/petv10/defaultprofile.jpg'">
                <div class="dropdown-content">
                    <div class="dropdown-profile-info">
                        <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                        <div class="dropdown-profile-role"><?= htmlspecialchars($nav_role) ?></div>
                        <div class="dropdown-profile-balance">₱<?= number_format($nav_balance,2) ?></div>
                    </div>
                    <a href="favorites.php"><i class="fas fa-heart"></i>My Favorites</a>
                    <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                    <a href="purchase_history.php"><i class="fas fa-history"></i>Purchase History</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MAIN SECTION ===================== -->
    <div class="cart-section">
        <h1 class="cart-title">Your Shopping <em>Bag</em></h1>

        <?php if (empty($cart_items)): ?>
            <!-- Empty state -->
            <div class="empty-state">
                <i class="fas fa-shopping-basket empty-icon"></i>
                <div class="empty-text">Your Shopping Bag is Empty</div>
                <p class="empty-sub">Add premium organic pet supplies to give your companion the best wellness care.</p>
                <a href="shop.php" class="btn-empty-shop">
                    <i class="fas fa-arrow-left"></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Split content grid -->
            <div class="cart-grid">
                
                <!-- Left: Cards list -->
                <div class="cart-list">
                    <?php foreach ($cart_items as $item): ?>
                        <?php
                            $item_total = $item['price'] * $item['quantity'];
                            $low_stock = $item['stock'] <= 5;
                            $out_of_stock = $item['stock'] <= 0;
                        ?>
                        <div class="cart-item-card" data-product-id="<?= $item['product_id'] ?>">
                            <!-- Delete button -->
                            <button class="btn-remove-item" onclick="removeItem(<?= $item['product_id'] ?>, this.closest('.cart-item-card'))" aria-label="Remove item">
                                <i class="fas fa-trash-alt"></i>
                            </button>

                            <?php if ($item['image']): ?>
                                <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center item-image">
                                    <i class="fas fa-box text-muted" style="font-size: 1.5rem;"></i>
                                </div>
                            <?php endif; ?>

                            <div class="item-details">
                                <span class="item-category"><?= htmlspecialchars($item['category']) ?></span>
                                <h3 class="item-name"><?= htmlspecialchars($item['name']) ?></h3>
                                
                                <?php if ($out_of_stock): ?>
                                    <span class="item-stock out-of-stock" style="color: var(--danger); font-size: 0.78rem; font-weight: 600;">Out of Stock</span>
                                <?php elseif ($low_stock): ?>
                                    <span class="item-stock low" style="color: var(--gold); font-size: 0.78rem; font-weight: 600;">Only <?= $item['stock'] ?> left!</span>
                                <?php else: ?>
                                    <span class="item-stock in" style="color: var(--sage); font-size: 0.78rem; font-weight: 600;">In Stock</span>
                                <?php endif; ?>

                                <span class="item-price-each">₱<?= number_format($item['price'], 2) ?> each</span>

                                <div class="item-actions-row">
                                    <div class="quantity-selector">
                                        <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, -1, this)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="text" class="qty-input" value="<?= $item['quantity'] ?>" readonly data-price="<?= $item['price'] ?>" data-stock="<?= $item['stock'] ?>">
                                        <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, 1, this)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>

                                    <div class="item-price-total">
                                        ₱<span class="item-price-total-val"><?= number_format($item_total, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Right: Summary receipt panel -->
                <div class="cart-summary">
                    <div class="summary-card">
                        <h2 class="summary-title">Receipt Summary</h2>
                        
                        <div class="summary-row">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value" id="summary-subtotal">₱<?= number_format($subtotal, 2) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span class="summary-label">Shipping</span>
                            <span class="summary-value" id="summary-shipping">
                                <?= $shipping_fee > 0 ? '₱' . number_format($shipping_fee, 2) : 'Free' ?>
                            </span>
                        </div>

                        <div class="summary-row">
                            <span class="summary-label">VAT (12% Included)</span>
                            <span class="summary-value" id="summary-tax" style="font-weight: 300; font-size: 0.85rem;">
                                ₱<?= number_format($tax_included, 2) ?>
                            </span>
                        </div>

                        <!-- Coupon Code -->
                        <div class="promo-section">
                            <div class="promo-title">Promo / Coupon Code</div>
                            <div class="promo-input-group">
                                <input type="text" class="promo-input" id="couponCode" placeholder="Enter coupon code">
                                <button class="btn-promo-apply" onclick="applyCoupon()">Apply</button>
                            </div>
                            <div id="couponFeedback" style="font-size: 0.78rem; margin-top: 6px; font-weight: 500;"></div>
                        </div>

                        <div class="summary-row total">
                            <span class="summary-label">Grand Total</span>
                            <span class="summary-value grand" id="summary-grandtotal">₱<?= number_format($grand_total, 2) ?></span>
                        </div>

                        <a href="checkout.php" class="btn-checkout mt-4">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                    </div>
                </div>

            </div>

            <!-- Recommended Cross-Sell Products -->
            <?php if (!empty($recommended_products)): ?>
                <div class="cross-sell-section">
                    <h2 class="cross-sell-title">Customers also bought...</h2>
                    <div class="cross-sell-grid">
                        <?php foreach ($recommended_products as $rec): ?>
                            <div class="cross-card">
                                <?php if ($rec['image']): ?>
                                    <img src="uploads/<?= htmlspecialchars($rec['image']) ?>" alt="<?= htmlspecialchars($rec['name']) ?>" class="cross-img">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center cross-img">
                                        <i class="fas fa-box text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="cross-info">
                                    <div class="cross-name"><?= htmlspecialchars($rec['name']) ?></div>
                                    <div class="cross-price">₱<?= number_format($rec['price'], 2) ?></div>
                                </div>

                                <button class="btn-cross-add" onclick="quickAdd(<?= $rec['id'] ?>, this)" aria-label="Add product to bag">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- ===================== JAVASCRIPT LOGIC ===================== -->
    <script>
        // Custom Toast Notification functions
        function showToast(message, type = 'success') {
            const toastElement = document.getElementById('toastNotification');
            const toastBody = document.getElementById('toastBody');
            const toastIcon = document.getElementById('toastIcon');
            
            toastBody.textContent = message;
            toastElement.classList.remove('success', 'error');
            toastElement.classList.add(type);
            
            if (type === 'success') {
                toastIcon.className = 'fas fa-check-circle';
            } else {
                toastIcon.className = 'fas fa-exclamation-circle';
            }
            
            toastElement.classList.remove('hide');
            toastElement.classList.add('show');
            
            setTimeout(() => {
                closeToast();
            }, 3000);
        }

        function closeToast() {
            const toastElement = document.getElementById('toastNotification');
            toastElement.classList.remove('show');
            toastElement.classList.add('hide');
        }

        // Change quantity function
        async function changeQty(productId, change, btnEl) {
            const cardEl = btnEl.closest('.cart-item-card');
            const inputEl = cardEl.querySelector('.qty-input');
            const priceEach = parseFloat(inputEl.getAttribute('data-price'));
            const maxStock = parseInt(inputEl.getAttribute('data-stock'));
            const priceTotalEl = cardEl.querySelector('.item-price-total-val');
            
            const currentQty = parseInt(inputEl.value);
            const targetQty = currentQty + change;
            
            if (targetQty <= 0) {
                // Remove item if user decreases below 1
                removeItem(productId, cardEl);
                return;
            }
            
            if (targetQty > maxStock) {
                showToast(`Not enough stock. Only ${maxStock} available.`, 'error');
                return;
            }
            
            btnEl.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('product_id', productId);
                formData.append('quantity', targetQty);

                const response = await fetch('cart_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    inputEl.value = result.data.quantity;
                    const newTotal = result.data.quantity * priceEach;
                    priceTotalEl.textContent = newTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    
                    // Trigger pulse animation
                    priceTotalEl.parentElement.classList.add('pulse-text');
                    setTimeout(() => priceTotalEl.parentElement.classList.remove('pulse-text'), 300);
                    
                    recalculateSummary();
                } else {
                    showToast(result.message || 'Error updating quantity', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Connection error', 'error');
            } finally {
                btnEl.disabled = false;
            }
        }

        // Remove item from cart
        async function removeItem(productId, cardEl) {
            if (!confirm('Are you sure you want to remove this item?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove');
                formData.append('product_id', productId);

                const response = await fetch('cart_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    cardEl.style.opacity = '0';
                    cardEl.style.transform = 'translateY(15px)';
                    setTimeout(() => {
                        cardEl.remove();
                        recalculateSummary();
                        showToast('Item removed from cart', 'success');
                    }, 300);
                } else {
                    showToast(result.message || 'Error removing item', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Connection error', 'error');
            }
        }

        // Recalculate summary totals client-side
        function recalculateSummary() {
            let subtotal = 0;
            const items = document.querySelectorAll('.cart-item-card');
            
            items.forEach(card => {
                const priceText = card.querySelector('.item-price-total-val').textContent;
                const price = parseFloat(priceText.replace(/[₱,]/g, ''));
                subtotal += price;
            });
            
            const subtotalEl = document.getElementById('summary-subtotal');
            const taxEl = document.getElementById('summary-tax');
            const grandTotalEl = document.getElementById('summary-grandtotal');
            const shippingEl = document.getElementById('summary-shipping');
            
            if (subtotal === 0) {
                // If cart is empty, reload to render PHP empty state
                location.reload();
                return;
            }

            subtotalEl.textContent = '₱' + subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // 12% VAT included
            const tax = subtotal * 0.12;
            taxEl.textContent = '₱' + tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Shipping calculation: Free over ₱1500, otherwise ₱100
            let shipping = 0;
            if (subtotal < 1500) {
                shipping = 100;
                shippingEl.textContent = '₱100.00';
            } else {
                shippingEl.textContent = 'Free';
            }
            
            const grandTotal = subtotal + shipping;
            grandTotalEl.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Quick add recommended product
        async function quickAdd(productId, btnEl) {
            btnEl.disabled = true;
            btnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'add');
                formData.append('product_id', productId);
                formData.append('quantity', 1);

                const response = await fetch('cart_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showToast(result.message || 'Added to cart!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(result.message || 'Error adding item', 'error');
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<i class="fas fa-plus"></i>';
                }
            } catch (error) {
                console.error(error);
                showToast('Connection error', 'error');
                btnEl.disabled = false;
                btnEl.innerHTML = '<i class="fas fa-plus"></i>';
            }
        }

        // Coupon code validation
        async function applyCoupon() {
            const coupon = document.getElementById('couponCode').value.trim();
            const feedback = document.getElementById('couponFeedback');
            
            if (!coupon) {
                feedback.textContent = 'Please enter a coupon code.';
                feedback.style.color = 'var(--danger)';
                return;
            }
            
            feedback.textContent = 'Verifying...';
            feedback.style.color = 'var(--caramel)';
            
            try {
                const formData = new FormData();
                formData.append('coupon_code', coupon);

                const response = await fetch('validate_coupon.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    feedback.textContent = `Coupon applied: ${result.discount_percent}% Discount!`;
                    feedback.style.color = 'var(--sage)';
                    showToast('Coupon code applied!', 'success');
                    
                    // If validation succeeds, recalculate totals with discount
                    // Note: validate_coupon saves the active discount rate in the session or updates db calculations.
                    // Reloading keeps the numbers in sync with the database checkout state.
                    setTimeout(() => location.reload(), 1200);
                } else {
                    feedback.textContent = result.message || 'Invalid coupon code.';
                    feedback.style.color = 'var(--danger)';
                    showToast(result.message || 'Invalid coupon code', 'error');
                }
            } catch (error) {
                console.error(error);
                feedback.textContent = 'Error verifying coupon.';
                feedback.style.color = 'var(--danger)';
            }
        }
    </script>
</body>
</html>
