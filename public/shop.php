<?php
require_once __DIR__ . '/../config/db.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_balance = $is_logged_in ? $_SESSION['balance'] ?? 0 : 0;

// Render product card helper function
function renderProductCard($row, $is_logged_in) {
    $in_stock = $row['stock'] > 0;
    $low_stock = $row['stock'] > 0 && $row['stock'] <= 10;
    $is_sale = !empty($row['sale_price']) && $row['sale_price'] > 0 && $row['sale_price'] < $row['price'];
    $active_price = $is_sale ? $row['sale_price'] : $row['price'];
    
    $badges_html = '';
    if ($is_sale) {
        $discount = round((($row['price'] - $row['sale_price']) / $row['price']) * 100);
        $badges_html .= '<span class="stock-badge sale-badge" style="left: auto; right: 12px; top: 60px; background: var(--danger); font-weight: 800;">-' . $discount . '%</span>';
    }
    if (!empty($row['badge'])) {
        $badges_html .= '<span class="stock-badge promo-badge" style="left: 12px; background: var(--gold);"><i class="fas fa-award"></i> ' . htmlspecialchars($row['badge']) . '</span>';
    }

    $stock_badge = '';
    if (!$in_stock) {
        $stock_badge = '<span class="stock-badge out-stock" style="' . (!empty($row['badge']) ? 'top: 45px;' : '') . '"><i class="fas fa-times-circle"></i> Out of Stock</span>';
    } elseif ($low_stock) {
        $stock_badge = '<span class="stock-badge low-stock" style="' . (!empty($row['badge']) ? 'top: 45px;' : '') . '"><i class="fas fa-fire"></i> Only ' . $row['stock'] . ' left!</span>';
    } else {
        $stock_badge = '<span class="stock-badge in-stock" style="' . (!empty($row['badge']) ? 'top: 45px;' : '') . '"><i class="fas fa-check-circle"></i> In Stock</span>';
    }

    $img_html = '';
    if (!empty($row['image']) && file_exists("uploads/" . $row['image'])) {
        $img_html = '<img loading="lazy" src="uploads/' . htmlspecialchars($row['image']) . '" alt="' . htmlspecialchars($row['name']) . '">';
    } else {
        $img_html = '<div class="no-image-placeholder"><i class="fas fa-image"></i><span>No Image</span></div>';
    }

    $price_html = '';
    if ($is_sale) {
        $price_html = '<div class="price-ribbon sale">' .
            '₱' . number_format($row['sale_price'], 2) .
            '<span style="text-decoration: line-through; font-size: 0.75rem; opacity: 0.75; margin-left: 5px;">₱' . number_format($row['price'], 2) . '</span>' .
            '</div>';
    } else {
        $price_html = '<div class="price-ribbon">₱' . number_format($row['price'], 2) . '</div>';
    }

    $footer_html = '';
    if ($is_logged_in) {
        $footer_html .= '<button class="btn-add-cart" onclick="event.stopPropagation(); addToCart(' . $row['id'] . ');" ' . (!$in_stock ? 'disabled' : '') . '>' .
            '<i class="fas fa-cart-plus"></i> ' . ($in_stock ? 'Add to Cart' : 'Unavailable') .
            '</button>';
        if ($in_stock) {
            $footer_html .= '<button class="btn-buy-now" onclick="event.stopPropagation(); openQuantityModal(' . $row['id'] . ', \'' . addslashes(htmlspecialchars($row['name'])) . '\', ' . $row['stock'] . ');">' .
                '<i class="fas fa-bolt"></i> Buy Now' .
                '</button>';
        } else {
            $footer_html .= '<button class="btn-buy-now" disabled><i class="fas fa-ban"></i> Out of Stock</button>';
        }
    } else {
        $footer_html .= '<a href="login.php" class="btn-login" onclick="event.stopPropagation();"><i class="fas fa-sign-in-alt"></i> Login to Purchase</a>';
    }

    return '
    <div class="product-card" onclick="window.location.href=\'product.php?id=' . $row['id'] . '\'">
        ' . $badges_html . '
        ' . $stock_badge . '
        <button class="favorite-btn" data-product-id="' . $row['id'] . '" onclick="event.stopPropagation(); toggleFavorite(' . $row['id'] . ', this);" title="Save to favorites">
            <i class="far fa-heart"></i>
        </button>
        <div class="card-image">' . $img_html . '</div>
        <div class="card-body">
            ' . $price_html . '
            <span class="card-category"><i class="fas fa-tag"></i>' . htmlspecialchars($row['category']) . '</span>
            <div class="card-rating" style="margin-top: 5px; font-size: 0.82rem; color: var(--gold); display: flex; align-items: center; gap: 4px;">
                <i class="fas fa-star"></i> <span>' . number_format($row['rating'] ?? 5.0, 1) . '</span> <span style="color: var(--caramel); font-size: 0.78rem;">(' . intval($row['reviews_count'] ?? 0) . ' reviews)</span>
            </div>
            <h5 class="card-title" style="margin-top:8px;">' . htmlspecialchars($row['name']) . '</h5>
            <p class="card-desc">' . htmlspecialchars($row['description'] ?? '') . '</p>
        </div>
        <div class="card-footer">' . $footer_html . '</div>
    </div>';
}

// ----------------- AJAX ACTIONS -----------------
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'autocomplete') {
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        if ($query === '') {
            echo json_encode([]);
            exit;
        }
        $search = '%' . $query . '%';
        $stmt = $conn->prepare("SELECT id, name, price, sale_price, image, badge FROM products WHERE name LIKE ? LIMIT 5");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $res = $stmt->get_result();
        $suggestions = [];
        while ($row = $res->fetch_assoc()) {
            $is_sale = !empty($row['sale_price']) && $row['sale_price'] > 0 && $row['sale_price'] < $row['price'];
            $suggestions[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => (float)$row['price'],
                'sale_price' => $is_sale ? (float)$row['sale_price'] : null,
                'image' => $row['image'],
                'badge' => $row['badge']
            ];
        }
        echo json_encode($suggestions);
        exit;
    }

    if ($action === 'fetch_products') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : '';
        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;

        $limit = 8;
        $offset = ($page - 1) * $limit;

        // Base Query
        $sql = " FROM products WHERE 1=1";
        $params = [];
        $types = "";

        if ($search !== "") {
            $sql .= " AND name LIKE ?";
            $params[] = '%' . $search . '%';
            $types .= "s";
        }

        if ($category !== "" && $category !== "all") {
            $sql .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }

        // Get total matching count
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt" . $sql);
        if ($types !== "") {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $total_count = $count_stmt->get_result()->fetch_assoc()['cnt'];
        $count_stmt->close();

        // Add sorting
        switch ($sort_by) {
            case "price_asc":
                $sql .= " ORDER BY IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) ASC";
                break;
            case "price_desc":
                $sql .= " ORDER BY IF(sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price, sale_price, price) DESC";
                break;
            case "stock_asc":
                $sql .= " ORDER BY stock ASC";
                break;
            case "stock_desc":
                $sql .= " ORDER BY stock DESC";
                break;
            default:
                $sql .= " ORDER BY id DESC";
                break;
        }

        // Add limit and offset
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $conn->prepare("SELECT *" . $sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $html = "";
        while ($row = $res->fetch_assoc()) {
            $html .= renderProductCard($row, $is_logged_in);
        }

        $has_more = ($offset + $limit) < $total_count;

        echo json_encode([
            'success' => true,
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total_count
        ]);
        exit;
    }
}

// Default page load: Page 1 (limit 8)
$limit = 8;
$stmt = $conn->prepare("SELECT * FROM products ORDER BY id DESC LIMIT ?");
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop — Pawganic Supplies</title>
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

    .nav-links {
        display: flex;
        align-items: center;
        gap: 6px;
    }

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

    .nav-links a:hover, .nav-links a.active {
        background: var(--gold);
        color: var(--white);
    }

    .cart-btn {
        background: var(--espresso);
        border: none;
        color: var(--honey);
        width: 42px;
        height: 42px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
    }
    .cart-btn:hover { background: var(--gold); color: var(--white); transform: scale(1.08); }

    /* Profile Dropdown */
    .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
    .profile-pic {
        width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        border: 2.5px solid var(--gold); transition: var(--transition);
    }
    .profile-pic:hover { transform: scale(1.06); box-shadow: 0 0 0 4px rgba(201,145,42,0.18); }

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

    .profile-dropdown:hover .dropdown-content,
    .profile-dropdown.open .dropdown-content { display: block; }

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

    /* ===================== HERO BANNER ===================== */
    .shop-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 80px 5% 70px;
        position: relative;
        overflow: hidden;
    }

    .shop-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                    radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
    }

    .shop-hero::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }

    /* Decorative circles */
    .hero-deco {
        position: absolute;
        border-radius: 50%;
        opacity: 0.07;
        background: var(--honey);
    }
    .hero-deco-1 { width: 380px; height: 380px; top: -100px; right: -80px; }
    .hero-deco-2 { width: 220px; height: 220px; bottom: 20px; left: 5%; }
    .hero-deco-3 { width: 120px; height: 120px; top: 30px; left: 30%; opacity: 0.05; }

    .hero-inner {
        position: relative; z-index: 2;
        max-width: 1200px; margin: 0 auto;
        display: flex; align-items: center; justify-content: space-between; gap: 40px;
    }

    .hero-label {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
        margin-bottom: 18px;
    }

    .hero-label i { font-size: 0.8rem; }

    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.8rem, 5vw, 4.5rem);
        font-weight: 900;
        color: var(--white);
        line-height: 1.1;
        margin-bottom: 18px;
    }

    .hero-title em {
        font-style: italic;
        color: var(--honey);
    }

    .hero-subtitle {
        color: rgba(255,255,255,0.65);
        font-size: 1.05rem;
        line-height: 1.7;
        max-width: 480px;
        margin-bottom: 32px;
    }

    .hero-stats {
        display: flex; gap: 36px;
    }

    .hero-stat {
        text-align: center;
    }

    .hero-stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem; font-weight: 700; color: var(--honey); line-height: 1;
    }

    .hero-stat-label {
        font-size: 0.75rem; color: rgba(255,255,255,0.5);
        text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;
    }

    .hero-badge-group {
        display: flex; gap: 12px; flex-wrap: wrap;
    }

    .hero-badge {
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
        color: rgba(255,255,255,0.8); padding: 8px 18px; border-radius: 50px;
        font-size: 0.82rem; font-weight: 500;
        backdrop-filter: blur(8px);
        transition: var(--transition);
    }
    .hero-badge:hover { background: var(--gold); border-color: var(--gold); color: var(--white); }
    .hero-badge i { margin-right: 6px; }

    /* ===================== TOAST ===================== */
    .toast-container {
        position: fixed; bottom: 30px; left: 30px; z-index: 2000;
    }

    .custom-toast {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-radius: var(--radius-sm);
        font-size: 0.95rem; padding: 14px 20px;
        box-shadow: var(--shadow-lg);
        min-width: 260px; max-width: 320px;
        color: var(--cream); border-left: 4px solid var(--gold);
    }

    .custom-toast .toast-body {
        padding: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px;
    }

    .custom-toast .btn-close { filter: invert(1) brightness(0.8); flex-shrink: 0; }

    /* ===================== SLIDE CART ===================== */
    .slide-cart {
        position: fixed; top: 0; right: -480px; width: 480px; height: 100%;
        background: var(--ivory);
        box-shadow: -12px 0 40px rgba(44,26,14,0.2);
        transition: right 0.45s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 998; padding: 32px 28px; overflow-y: auto;
        border-left: 3px solid var(--gold);
    }

    .cart-header {
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 2px solid var(--mist); padding-bottom: 18px; margin-bottom: 24px;
    }

    .cart-header h4 {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem; font-weight: 700; color: var(--espresso);
        display: flex; align-items: center; gap: 10px;
    }

    .cart-header h4 i { color: var(--gold); }

    .close-cart-btn {
        background: var(--mist); border: none; color: var(--mahogany);
        width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition);
    }
    .close-cart-btn:hover { background: var(--gold); color: var(--white); }

    .cart-item {
        background: var(--cream); border-radius: var(--radius-sm);
        padding: 14px; margin-bottom: 12px; display: flex; gap: 12px;
        align-items: center; transition: var(--transition);
        border: 1px solid rgba(201,145,42,0.1);
    }
    .cart-item:hover { border-color: rgba(201,145,42,0.3); box-shadow: var(--shadow-sm); }
    .cart-item input[type='number'] {
        width: 68px; border: 2px solid var(--mist); border-radius: var(--radius-sm);
        padding: 7px 10px; background: var(--ivory); color: var(--espresso); font-weight: 600;
    }
    .remove-btn {
        background: none; border: none; color: var(--danger);
        cursor: pointer; padding: 6px 10px; border-radius: var(--radius-sm);
        font-size: 0.85rem; transition: var(--transition);
    }
    .remove-btn:hover { background: #fdecea; }

    .checkout-btn {
        width: 100%; margin-top: 24px; padding: 16px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; border-radius: var(--radius);
        font-weight: 700; font-size: 1rem; letter-spacing: 0.5px;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
        text-decoration: none;
    }
    .checkout-btn:hover { background: var(--gold); color: var(--white); transform: translateY(-2px); box-shadow: var(--shadow-md); }

    /* ===================== SEARCH / FILTER BAR ===================== */
    .filter-section {
        max-width: 1200px; margin: 40px auto 0;
        padding: 0 24px;
    }

    .filter-bar {
        background: var(--ivory);
        border-radius: var(--radius);
        padding: 22px 28px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.12);
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
    }

    .filter-bar .search-wrap {
        flex: 1; min-width: 200px; position: relative;
    }

    .filter-bar .search-wrap i {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: var(--caramel); font-size: 0.9rem;
    }

    .filter-bar input, .filter-bar select {
        width: 100%; padding: 12px 16px 12px 42px;
        border: 2px solid var(--mist); border-radius: 50px;
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.92rem; font-weight: 500;
        transition: var(--transition); outline: none;
    }
    .filter-bar select { padding-left: 16px; }

    .filter-bar input:focus, .filter-bar select:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }

    .filter-bar input::placeholder { color: var(--caramel); opacity: 0.7; }

    .filter-bar .apply-btn {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; border-radius: 50px;
        padding: 12px 28px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.9rem; cursor: pointer;
        transition: var(--transition); white-space: nowrap;
        display: flex; align-items: center; gap: 8px;
    }
    .filter-bar .apply-btn:hover { background: var(--gold); color: var(--white); transform: translateY(-1px); box-shadow: var(--shadow-sm); }

    .result-meta {
        max-width: 1200px; margin: 18px auto 0; padding: 0 24px;
        display: flex; align-items: center; gap: 10px;
    }
    .result-count {
        font-size: 0.85rem; color: var(--caramel); font-weight: 500;
    }
    .result-divider { height: 1px; flex: 1; background: var(--mist); }

    /* ===================== PRODUCT GRID ===================== */
    .products-section {
        max-width: 1200px; margin: 28px auto 60px;
        padding: 0 24px;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 28px;
    }

    /* ===================== PRODUCT CARD ===================== */
    .product-card {
        background: var(--ivory);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.08);
        cursor: pointer;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    border-color 0.35s ease;
        display: flex; flex-direction: column;
        position: relative;
    }

    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-lg);
        border-color: rgba(201,145,42,0.3);
    }

    /* Stock badge */
    .stock-badge {
        position: absolute; top: 14px; left: 14px; z-index: 5;
        padding: 4px 12px; border-radius: 50px; font-size: 0.75rem;
        font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .stock-badge.in-stock { background: rgba(122,158,126,0.15); color: var(--sage); border: 1px solid rgba(122,158,126,0.3); }
    .stock-badge.low-stock { background: rgba(201,145,42,0.15); color: var(--gold); border: 1px solid rgba(201,145,42,0.3); }
    .stock-badge.out-stock { background: rgba(192,57,43,0.12); color: var(--danger); border: 1px solid rgba(192,57,43,0.25); }

    /* Favorite btn */
    .favorite-btn {
        position: absolute; top: 12px; right: 12px; z-index: 10;
        width: 40px; height: 40px; border: none; border-radius: 50%;
        background: rgba(253,248,240,0.9); backdrop-filter: blur(6px);
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(44,26,14,0.12); transition: var(--transition);
    }
    .favorite-btn:hover { transform: scale(1.12); background: var(--white); }
    .favorite-btn i { font-size: 1.1rem; color: #ccc; transition: var(--transition); }
    .favorite-btn.active i { color: #e74c3c; animation: heartPop 0.35s ease; }

    @keyframes heartPop {
        0%, 100% { transform: scale(1); }
        40% { transform: scale(1.4); }
        70% { transform: scale(0.9); }
    }

    /* Image container */
    .card-image {
        height: 240px;
        background: linear-gradient(145deg, var(--cream), var(--mist));
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; position: relative;
    }

    .card-image::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
        background: linear-gradient(to top, rgba(253,248,240,0.6), transparent);
        pointer-events: none;
    }

    .card-image img {
        max-width: 78%; max-height: 78%; object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        filter: drop-shadow(0 8px 20px rgba(44,26,14,0.15));
    }

    .product-card:hover .card-image img { transform: scale(1.09) translateY(-4px); }

    .no-image-placeholder {
        display: flex; flex-direction: column; align-items: center;
        gap: 10px; color: var(--mist);
    }
    .no-image-placeholder i { font-size: 2.5rem; }
    .no-image-placeholder span { font-size: 0.85rem; }

    /* Card body */
    .card-body {
        padding: 22px 22px 10px;
        flex: 1; display: flex; flex-direction: column; gap: 8px;
        position: relative;
    }

    /* Price ribbon */
    .price-ribbon {
        position: absolute;
        top: -20px; right: 20px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey);
        padding: 8px 18px; border-radius: 50px;
        font-family: 'Playfair Display', serif;
        font-size: 1.05rem; font-weight: 700;
        box-shadow: 0 4px 16px rgba(44,26,14,0.25);
        letter-spacing: 0.3px;
    }

    .card-category {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(201,145,42,0.1); color: var(--caramel);
        padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600;
        border: 1px solid rgba(201,145,42,0.2); width: fit-content;
        letter-spacing: 0.3px;
    }

    .card-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem; font-weight: 700; color: var(--espresso);
        line-height: 1.3; margin: 0;
    }

    .card-desc {
        font-size: 0.84rem; color: var(--caramel); line-height: 1.6;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Card footer */
    .card-footer {
        padding: 16px 22px 22px;
        background: transparent; border-top: 1px solid var(--mist);
        display: flex; flex-direction: column; gap: 10px;
    }

    .btn-add-cart {
        width: 100%; padding: 11px;
        background: transparent; border: 2px solid var(--espresso);
        color: var(--espresso); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.88rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-add-cart:hover {
        background: var(--espresso); color: var(--honey);
        transform: translateY(-1px); box-shadow: var(--shadow-sm);
    }
    .btn-add-cart:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

    .btn-buy-now {
        width: 100%; padding: 12px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.9rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }
    .btn-buy-now:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(44,26,14,0.25);
    }
    .btn-buy-now:disabled { opacity: 0.45; cursor: not-allowed; transform: none; background: var(--mist); color: var(--caramel); box-shadow: none; }

    .btn-login {
        width: 100%; padding: 12px; text-align: center;
        background: var(--cream); color: var(--mahogany); border: 2px solid var(--mist);
        border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.88rem; text-decoration: none;
        transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-login:hover { background: var(--mist); color: var(--espresso); }

    /* ===================== NO PRODUCTS ===================== */
    .empty-state {
        text-align: center; padding: 80px 24px;
    }
    .empty-state i { font-size: 4rem; color: var(--mist); margin-bottom: 20px; display: block; }
    .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--mahogany); margin-bottom: 10px; }
    .empty-state p { color: var(--caramel); font-size: 0.95rem; }

    /* ===================== QUANTITY MODAL ===================== */
    .quantity-modal {
        display: none; position: fixed; z-index: 2000;
        inset: 0; background: rgba(44,26,14,0.65);
        backdrop-filter: blur(6px); animation: fadeIn 0.3s ease;
    }
    .quantity-modal.active { display: flex; align-items: center; justify-content: center; }

    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { transform:translateY(40px); opacity:0; } to { transform:translateY(0); opacity:1; } }

    .quantity-modal-content {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-lg); width: 90%; max-width: 440px;
        overflow: hidden; animation: slideUp 0.4s ease;
        border: 1px solid rgba(201,145,42,0.2);
    }

    .quantity-modal-header {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); padding: 24px 28px;
        display: flex; justify-content: space-between; align-items: center;
    }

    .quantity-modal-header h2 {
        font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700;
    }

    .quantity-modal-close {
        background: rgba(255,255,255,0.1); border: none; color: var(--honey);
        width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition);
    }
    .quantity-modal-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }

    .quantity-modal-body { padding: 28px; }

    .product-info {
        background: var(--cream); border-left: 4px solid var(--gold);
        padding: 14px 18px; border-radius: var(--radius-sm);
        color: var(--mahogany); font-weight: 600; margin-bottom: 24px;
    }

    .quantity-selector label {
        font-weight: 700; color: var(--espresso); display: block; margin-bottom: 14px;
    }

    .quantity-input-group {
        display: flex; align-items: center; gap: 14px; margin-bottom: 10px;
    }

    .qty-btn {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; width: 44px; height: 44px;
        border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition); box-shadow: var(--shadow-sm);
    }
    .qty-btn:hover { background: var(--gold); color: var(--white); transform: scale(1.1); }

    #quantityInput {
        width: 80px; height: 44px; text-align: center; font-size: 1.2rem;
        font-weight: 700; border: 2px solid var(--mist); border-radius: var(--radius-sm);
        background: var(--ivory); color: var(--espresso); font-family: 'Playfair Display', serif;
    }

    #maxQuantityInfo { font-size: 0.82rem; color: var(--caramel); }

    .quantity-modal-footer {
        padding: 20px 28px; border-top: 1px solid var(--mist);
        display: flex; gap: 12px; background: var(--cream);
    }

    .btn-cancel {
        background: var(--mist); border: 2px solid transparent; color: var(--mahogany);
        padding: 12px 24px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; cursor: pointer; transition: var(--transition);
    }
    .btn-cancel:hover { background: var(--cream); border-color: var(--mist); }

    .btn-confirm {
        flex: 1; background: linear-gradient(135deg, var(--gold), var(--honey));
        border: none; color: var(--espresso); padding: 12px 24px; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; cursor: pointer;
        transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }
    .btn-confirm:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso); color: rgba(255,255,255,0.75);
        padding: 64px 5% 28px; margin-top: auto;
        position: relative;
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

    /* ===================== SCROLL TO TOP ===================== */
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

    /* ===================== ANIMATIONS ===================== */
    .product-card { animation: cardIn 0.5s ease both; }
    @keyframes cardIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

    .product-card:nth-child(1) { animation-delay: 0.05s; }
    .product-card:nth-child(2) { animation-delay: 0.10s; }
    .product-card:nth-child(3) { animation-delay: 0.15s; }
    .product-card:nth-child(4) { animation-delay: 0.20s; }
    .product-card:nth-child(5) { animation-delay: 0.25s; }
    .product-card:nth-child(6) { animation-delay: 0.30s; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .navbar { padding: 0 20px; flex-wrap: nowrap; }
        .nav-links a:not(.active) { display: none; }
        .products-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
        .slide-cart { width: 92vw; right: -92vw; }
        .hero-stats { display: none; }
        .hero-inner { flex-direction: column; gap: 24px; }
        .shop-hero { padding: 56px 24px 60px; }
        .hero-title { font-size: 2.4rem; }
    }

    @media (max-width: 480px) {
        .products-grid { grid-template-columns: 1fr; }
        .filter-bar { flex-direction: column; }
        .filter-bar .apply-btn { width: 100%; justify-content: center; }
    }
    
    /* Category Chips Styling */
    .category-chips {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin: 0 auto 30px;
        flex-wrap: wrap;
        max-width: 800px;
    }
    .category-chip-btn {
        background: var(--ivory);
        border: 2px solid var(--mist);
        color: var(--espresso);
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.9rem;
        padding: 10px 24px;
        border-radius: 50px;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .category-chip-btn:hover {
        background: var(--cream);
        border-color: var(--gold);
        transform: translateY(-2px);
    }
    .category-chip-btn.active {
        background: var(--espresso);
        border-color: var(--gold);
        color: var(--honey);
    }

    /* Autocomplete dropdown styling */
    .search-wrap {
        position: relative;
    }
    .autocomplete-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: var(--ivory);
        border: 1px solid rgba(201,145,42,0.18);
        border-radius: 12px;
        box-shadow: var(--shadow-lg);
        z-index: 1010;
        overflow: hidden;
        display: none;
        max-height: 350px;
        overflow-y: auto;
    }
    .autocomplete-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 1px solid var(--mist);
    }
    .autocomplete-item:last-child {
        border-bottom: none;
    }
    .autocomplete-item:hover {
        background: var(--cream);
    }
    .autocomplete-thumb {
        width: 40px;
        height: 40px;
        border-radius: 6px;
        object-fit: cover;
        background: var(--mist);
    }
    .autocomplete-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .autocomplete-name {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--espresso);
    }
    .autocomplete-price {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--caramel);
    }
    .autocomplete-badge {
        background: var(--gold);
        color: var(--white);
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        margin-left: 6px;
        display: inline-block;
    }

    /* Breadcrumbs styling */
    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .breadcrumb-nav a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
    }
    .breadcrumb-nav a:hover {
        color: var(--honey);
    }
    .breadcrumb-nav .sep {
        color: rgba(255, 255, 255, 0.3);
        font-size: 0.75rem;
    }
    .breadcrumb-nav .current {
        color: var(--honey);
        font-size: 0.85rem;
        font-weight: 600;
    }

    /* Recently Viewed Navigation styles */
    .rv-nav-btn:hover {
        background: var(--gold) !important;
        color: var(--white) !important;
        border-color: var(--gold) !important;
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
        <a href="shop.php" class="active">Shop</a>
        <a href="about.php">About</a>
        <?php
        if (isset($_SESSION['user_id'])) {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                echo '<a href="admin.php">Admin</a>';
            }
            $check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
            if (!$check_column || $check_column->num_rows === 0) {
                $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
            }
            $nav_username = $_SESSION['username'] ?? 'User';
            $nav_role     = $_SESSION['role'] ?? 'customer';
            $nav_balance  = $_SESSION['balance'] ?? 0;
            $user_id      = $_SESSION['user_id'];
            $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $pic_stmt->bind_param("i", $user_id);
            $pic_stmt->execute();
            $pic_stmt->bind_result($profile_pic);
            $pic_stmt->fetch();
            $pic_stmt->close();
            if (!$profile_pic) $profile_pic = 'images/profile.jpg';
            $profile_pic_safe = htmlspecialchars($profile_pic);
            echo '
            <div class="profile-dropdown">
                <img src="'.$profile_pic_safe.'" alt="Profile" class="profile-pic" onerror="this.src=\'images/profile.jpg\'">
                <div class="dropdown-content">
                    <div class="dropdown-profile-info">
                        <div class="dropdown-profile-name">'.htmlspecialchars($nav_username).'</div>
                        <div class="dropdown-profile-role">'.htmlspecialchars($nav_role).'</div>
                        <div class="dropdown-profile-balance">₱'.number_format($nav_balance,2).'</div>
                    </div>
                    <a href="favorites.php"><i class="fas fa-heart"></i>My Favorites</a>
                    <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                    <a href="purchase_history.php"><i class="fas fa-history"></i>Purchase History</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </div>
            </div>';
        } else {
            echo '<a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>';
        }
        ?>
        <button onclick="toggleCart()" class="cart-btn">
            <i class="fas fa-shopping-cart"></i>
        </button>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast-container">
    <div id="toastMessage" class="toast text-white border-0 custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-body">
            Product added to cart!
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ===================== SLIDE CART ===================== -->
<div id="cart-panel" class="slide-cart">
    <div class="cart-header">
        <h4><i class="fas fa-shopping-bag"></i> Your Cart</h4>
        <button class="close-cart-btn" onclick="toggleCart()"><i class="fas fa-times"></i></button>
    </div>
    <div id="cart-items"></div>
    <a href="checkout.php" class="checkout-btn">
        <i class="fas fa-check-circle"></i> Proceed to Checkout
    </a>
</div>

<!-- ===================== HERO ===================== -->
<section class="shop-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-deco hero-deco-3"></div>
    <div class="hero-inner">
        <div>
            <!-- Luxury Breadcrumbs -->
            <div class="breadcrumb-nav" style="margin-bottom: 20px;">
                <a href="main.php">Home</a>
                <span class="sep">/</span>
                <a href="shop.php">Shop</a>
                <span id="bc-sep" class="sep" style="display:none;">/</span>
                <span id="bc-category" class="current" style="display:none;"></span>
            </div>
            <div class="hero-label"><i class="fas fa-paw"></i> PAWGANIC SUPPLIES</div>
            <h1 class="hero-title">Our <em>Premium</em><br>Cat Shop</h1>
            <p class="hero-subtitle">Hand-picked, vet-approved treats and supplies crafted with love for the felines in your life.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-num"><?= $total_products ?>+</div>
                    <div class="hero-stat-label">Products</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">100%</div>
                    <div class="hero-stat-label">Organic</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">★ 4.9</div>
                    <div class="hero-stat-label">Rating</div>
                </div>
            </div>
        </div>
        <div class="hero-badge-group">
            <span class="hero-badge"><i class="fas fa-leaf"></i> Natural Ingredients</span>
            <span class="hero-badge"><i class="fas fa-shield-alt"></i> Vet Approved</span>
            <span class="hero-badge"><i class="fas fa-truck"></i> Fast Delivery</span>
            <span class="hero-badge"><i class="fas fa-heart"></i> Made with Love</span>
        </div>
    </div>
</section>

<!-- ===================== FILTER BAR ===================== -->
<div class="filter-section">
    <!-- Category Chips -->
    <div class="category-chips">
        <button type="button" class="category-chip-btn active" data-category="all">All</button>
        <button type="button" class="category-chip-btn" data-category="Food">Food 🍖</button>
        <button type="button" class="category-chip-btn" data-category="Toys">Toys 🧶</button>
        <button type="button" class="category-chip-btn" data-category="Accessories">Accessories 💎</button>
    </div>
    
    <div class="filter-bar">
        <form method="GET" action="shop.php" style="display:contents;" onsubmit="event.preventDefault();">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search for treats, toys, supplies…" autocomplete="off"
                       value="">
            </div>
            <select name="sort_by" id="sortBySelect" style="width:200px; padding: 12px 16px; border-radius:50px; border:2px solid var(--mist); background:var(--cream); color:var(--espresso); font-family:'DM Sans',sans-serif; font-weight:500; font-size:0.92rem; outline:none; cursor:pointer;">
                <option value="">Sort by…</option>
                <option value="price_asc">Price: Low → High</option>
                <option value="price_desc">Price: High → Low</option>
                <option value="stock_asc">Stock: Low → High</option>
                <option value="stock_desc">Stock: High → Low</option>
            </select>
            <button type="submit" class="apply-btn"><i class="fas fa-sliders-h"></i> Apply</button>
        </form>
    </div>
</div>

<div class="result-meta">
    <span class="result-count">Showing <?= $total_products ?> product<?= $total_products !== 1 ? 's' : '' ?></span>
    <div class="result-divider"></div>
</div>

<!-- ===================== PRODUCT GRID ===================== -->
<div class="products-section">
    <div class="products-grid">
        <?php
        if ($total_products > 0) {
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) {
                echo renderProductCard($row, $is_logged_in);
            }
        } else {
            echo '<div class="empty-state" id="emptyStateEl" style="width: 100%; grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                <i class="fas fa-search" style="font-size: 3rem; color: var(--caramel); margin-bottom: 20px; display: block;"></i>
                <h3>No products found</h3>
                <p>Try adjusting your search or filters to find what you\'re looking for.</p>
            </div>';
        }
        ?>
    </div>
    
    <!-- Sentinel Loader for Infinite Scroll -->
    <div id="infinite-scroll-sentinel" style="height: 20px; margin-top: 20px;"></div>
    <div id="infinite-scroll-loader" style="display: none; text-align: center; margin: 20px 0;">
        <div class="spinner-border" style="color: var(--gold);" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<!-- ===================== QUANTITY MODAL ===================== -->
<div id="quantityModal" class="quantity-modal">
    <div class="quantity-modal-content">
        <div class="quantity-modal-header">
            <h2>Select Quantity</h2>
            <button class="quantity-modal-close" onclick="closeQuantityModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="quantity-modal-body">
            <div class="product-info" id="productInfo"></div>
            <div class="quantity-selector">
                <label>How many would you like?</label>
                <div class="quantity-input-group">
                    <button type="button" class="qty-btn" onclick="decreaseQuantity()"><i class="fas fa-minus"></i></button>
                    <input type="number" id="quantityInput" min="1" value="1" readonly>
                    <button type="button" class="qty-btn" onclick="increaseQuantity()"><i class="fas fa-plus"></i></button>
                </div>
                <small id="maxQuantityInfo" class="text-muted"></small>
            </div>
        </div>
        <div class="quantity-modal-footer">
            <button class="btn-cancel" onclick="closeQuantityModal()">Cancel</button>
            <form id="buyNowForm" method="POST" action="checkout.php" style="flex:1; display:contents;">
                <input type="hidden" name="buy_now" value="1">
                <input type="hidden" id="productIdInput" name="product_id">
                <input type="hidden" id="quantityHiddenInput" name="quantity" value="1">
                <button type="submit" class="btn-confirm">
                    <i class="fas fa-bolt"></i> Confirm Purchase
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ===================== RECENTLY VIEWED PRODUCTS ===================== -->
<section class="recently-viewed-section" id="recentlyViewedSection" style="display:none; background:var(--ivory); padding: 60px 5%; border-top:1px solid rgba(201,145,42,0.18); position:relative; overflow:hidden; z-index:10;">
    <div style="max-width:1200px; margin:0 auto; width:100%;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h3 style="font-family:'Playfair Display',serif; font-size:1.6rem; font-weight:700; color:var(--espresso); margin:0;">
                Recently <span style="font-style:italic; color:var(--caramel);">Viewed Items</span>
            </h3>
            <div style="display:flex; gap:10px;">
                <button class="rv-nav-btn rv-prev" style="width:40px; height:40px; border-radius:50%; border:1px solid var(--mist); background:var(--white); color:var(--espresso); cursor:pointer; transition:var(--transition);"><i class="fas fa-chevron-left"></i></button>
                <button class="rv-nav-btn rv-next" style="width:40px; height:40px; border-radius:50%; border:1px solid var(--mist); background:var(--white); color:var(--espresso); cursor:pointer; transition:var(--transition);"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="rv-carousel-container" style="overflow:hidden; width:100%;">
            <div class="rv-carousel-track" id="rvCarouselTrack" style="display:flex; gap:20px; transition:transform 0.5s ease; width:max-content; padding: 10px 0;">
                <!-- Populate dynamically via JS -->
            </div>
        </div>
    </div>
</section>

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
/* ===================== CART ===================== */
function toggleCart() {
    const panel = document.getElementById('cart-panel');
    panel.style.right = panel.style.right === '0px' ? '-480px' : '0px';
}

function updateCartDisplay() {
    fetch('cart_contents.php?sidebar=1')
        .then(r => r.text())
        .then(html => { document.getElementById('cart-items').innerHTML = html; });
}

function addToCart(productId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) updateCartDisplay();
    })
    .catch(() => showToast('Error adding to cart', 'danger'));
}

function removeFromCart(productId) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${productId}`
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) updateCartDisplay();
    });
}

function updateQuantity(productId, quantity) {
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&product_id=${productId}&quantity=${quantity}`
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, data.success ? 'success' : 'danger');
        if (data.success) updateCartDisplay();
    });
}

/* ===================== TOAST ===================== */
function showToast(message, type = 'success') {
    const toastEl = document.getElementById('toastMessage');
    if (!toastEl) return;
    const toastBody = toastEl.querySelector('.toast-body');
    if (!toastBody) return;
    toastBody.textContent = message;
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Close');
    toastBody.appendChild(closeBtn);

    toastEl.style.borderLeftColor = type === 'success' ? 'var(--sage)' : 'var(--danger)';
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
}

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

    updateCartDisplay();

    /* Scroll to top */
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => {
        btn.classList.toggle('show', window.pageYOffset > 300);
    });
    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* Favorites on load */
    document.querySelectorAll('.favorite-btn').forEach(btn => {
        const pid = btn.getAttribute('data-product-id');
        if (pid) checkFavoriteStatus(pid, btn);
    });

    /* Quantity modal outside click */
    const modal = document.getElementById('quantityModal');
    if (modal) {
        window.addEventListener('click', e => { if (e.target === modal) closeQuantityModal(); });
    }

    const quantityInput = document.getElementById('quantityInput');
    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            let v = parseInt(this.value) || 1;
            v = Math.max(1, Math.min(v, currentMaxStock));
            this.value = v;
            document.getElementById('quantityHiddenInput').value = v;
        });
    }

    // ----------------- CUSTOM SHOP CATALOG LOGIC -----------------
    let currentPage = 1;
    let hasMore = true;
    let isLoading = false;
    let activeCategory = 'all';
    let searchQuery = '';
    let sortBy = '';

    // Load products via AJAX function
    function loadProducts(append = false) {
        if (isLoading || (!hasMore && append)) return;
        isLoading = true;
        document.getElementById('infinite-scroll-loader').style.display = 'block';

        const url = `shop.php?action=fetch_products&search=${encodeURIComponent(searchQuery)}&sort_by=${encodeURIComponent(sortBy)}&category=${encodeURIComponent(activeCategory)}&page=${currentPage}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const grid = document.querySelector('.products-grid');
                    if (append) {
                        grid.insertAdjacentHTML('beforeend', data.html);
                    } else {
                        grid.innerHTML = data.html || `
                            <div class="empty-state" id="emptyStateEl" style="width: 100%; grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                                <i class="fas fa-search" style="font-size: 3rem; color: var(--caramel); margin-bottom: 20px; display: block;"></i>
                                <h3>No products found</h3>
                                <p>Try adjusting your search or filters to find what you're looking for.</p>
                            </div>
                        `;
                    }
                    hasMore = data.has_more;
                    document.querySelector('.result-count').textContent = `Showing ${data.total} product${data.total !== 1 ? 's' : ''}`;
                    
                    // Initialize newly loaded favorites
                    document.querySelectorAll('.favorite-btn').forEach(btn => {
                        const pid = btn.getAttribute('data-product-id');
                        if (pid && !btn.classList.contains('initialized')) {
                            btn.classList.add('initialized');
                            checkFavoriteStatus(pid, btn);
                        }
                    });
                }
                isLoading = false;
                document.getElementById('infinite-scroll-loader').style.display = 'none';
            })
            .catch(() => {
                isLoading = false;
                document.getElementById('infinite-scroll-loader').style.display = 'none';
            });
    }

    // IntersectionObserver for infinite scroll
    const sentinel = document.getElementById('infinite-scroll-sentinel');
    if (sentinel) {
        const observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && hasMore && !isLoading) {
                currentPage++;
                loadProducts(true);
            }
        }, { rootMargin: '100px' });
        observer.observe(sentinel);
    }

    // Category chips click handler
    document.querySelectorAll('.category-chip-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-chip-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeCategory = this.dataset.category;
            
            // Update breadcrumbs
            const bcSep = document.getElementById('bc-sep');
            const bcCat = document.getElementById('bc-category');
            if (activeCategory === 'all') {
                if (bcSep) bcSep.style.display = 'none';
                if (bcCat) bcCat.style.display = 'none';
            } else {
                if (bcSep) bcSep.style.display = 'inline';
                if (bcCat) {
                    bcCat.style.display = 'inline';
                    bcCat.textContent = activeCategory;
                }
            }

            currentPage = 1;
            hasMore = true;
            loadProducts(false);
        });
    });

    // Sorting select handler
    const sortSelect = document.getElementById('sortBySelect');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortBy = this.value;
            currentPage = 1;
            hasMore = true;
            loadProducts(false);
        });
    }

    // Debounced search logic & form override
    const filterForm = document.querySelector('.filter-bar form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            searchQuery = this.querySelector('input[name="search"]').value;
            currentPage = 1;
            hasMore = true;
            loadProducts(false);
            closeAutocomplete();
        });
    }

    // Debounce keyup search input
    let debounceTimer;
    const searchInput = document.querySelector('.search-wrap input[name="search"]');
    if (searchInput) {
        // Create autocomplete list wrapper dynamically
        const autocompleteDropdown = document.createElement('div');
        autocompleteDropdown.className = 'autocomplete-dropdown';
        searchInput.parentNode.appendChild(autocompleteDropdown);

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const val = this.value.trim();
            if (val.length < 2) {
                autocompleteDropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`shop.php?action=autocomplete&query=${encodeURIComponent(val)}`)
                    .then(r => r.json())
                    .then(suggestions => {
                        if (suggestions.length === 0) {
                            autocompleteDropdown.style.display = 'none';
                            return;
                        }
                        let html = '';
                        suggestions.forEach(item => {
                            const priceStr = item.sale_price 
                                ? `₱${item.sale_price.toFixed(2)} <span style="text-decoration:line-through; font-size:0.75rem; opacity:0.6; margin-left:5px;">₱${item.price.toFixed(2)}</span>`
                                : `₱${item.price.toFixed(2)}`;
                            const imgUrl = item.image ? `uploads/${item.image}` : '';
                            const imgHtml = imgUrl 
                                ? `<img class="autocomplete-thumb" src="${imgUrl}" alt="${item.name}">`
                                : `<div class="autocomplete-thumb" style="display:flex;align-items:center;justify-content:center;background:var(--mist);"><i class="fas fa-box text-muted" style="font-size:0.8rem;"></i></div>`;
                            const badgeHtml = item.badge ? `<span class="autocomplete-badge">${item.badge}</span>` : '';

                            html += `
                                <div class="autocomplete-item" data-id="${item.id}">
                                    ${imgHtml}
                                    <div class="autocomplete-info">
                                        <div class="autocomplete-name">${item.name} ${badgeHtml}</div>
                                        <div class="autocomplete-price">${priceStr}</div>
                                    </div>
                                </div>
                            `;
                        });
                        autocompleteDropdown.innerHTML = html;
                        autocompleteDropdown.style.display = 'block';

                        // Bind click on suggestions
                        autocompleteDropdown.querySelectorAll('.autocomplete-item').forEach(el => {
                            el.addEventListener('click', function() {
                                window.location.href = `product.php?id=${this.dataset.id}`;
                            });
                        });
                    });
            }, 300);
        });

        function closeAutocomplete() {
            autocompleteDropdown.style.display = 'none';
        }

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
                closeAutocomplete();
            }
        });
    }

    // Recently viewed products carousel init
    function initRecentlyViewed() {
        const track = document.getElementById('rvCarouselTrack');
        const section = document.getElementById('recentlyViewedSection');
        if (!track || !section) return;

        const items = JSON.parse(localStorage.getItem('recently_viewed')) || [];

        if (items.length === 0) {
            section.style.display = 'none';
            return;
        }
        section.style.display = 'block';

        let html = '';
        items.forEach(item => {
            const priceStr = item.sale_price && item.sale_price < item.price
                ? `<span style="color:var(--gold); font-weight:700;">₱${Number(item.sale_price).toFixed(2)}</span> <span style="text-decoration:line-through; font-size:0.75rem; opacity:0.6; margin-left:5px;">₱${Number(item.price).toFixed(2)}</span>`
                : `₱${Number(item.price).toFixed(2)}`;
            const badgeHtml = item.badge ? `<span style="position:absolute; top:8px; left:8px; background:var(--gold); color:var(--white); font-size:0.6rem; font-weight:700; padding:2px 6px; border-radius:4px; z-index:2;">${item.badge}</span>` : '';
            const imgUrl = item.image ? `uploads/${item.image}` : '';
            const imgHtml = imgUrl 
                ? `<img src="${imgUrl}" alt="${item.name}" style="max-height:100%; max-width:100%; object-fit:cover;">`
                : `<i class="fas fa-image text-muted" style="font-size: 2rem;"></i>`;

            html += `
                <div class="product-card" style="width:220px; flex-shrink:0; cursor:pointer; background: var(--ivory); border: 1px solid rgba(201,145,42,0.1); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm);" onclick="window.location.href='product.php?id=${item.id}'">
                    <div style="position:relative; width:100%; height:150px; background:var(--mist); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                        ${badgeHtml}
                        ${imgHtml}
                    </div>
                    <div style="padding:12px; display:flex; flex-direction:column; gap:4px;">
                        <span style="font-size:0.75rem; font-weight:600; color:var(--caramel); text-transform:uppercase;"><i class="fas fa-tag"></i> ${item.category}</span>
                        <h6 style="font-size:0.88rem; font-weight:700; color:var(--espresso); margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${item.name}</h6>
                        <div style="font-size:0.85rem; font-weight:600; margin-top:2px;">${priceStr}</div>
                        <div style="font-size:0.75rem; color:var(--gold); display:flex; align-items:center; gap:3px; margin-top:2px;">
                           <i class="fas fa-star"></i> ${Number(item.rating || 5.0).toFixed(1)} <span style="color:var(--caramel);">(${item.reviews_count || 0})</span>
                        </div>
                    </div>
                </div>
            `;
        });
        track.innerHTML = html;

        // Carousel sliding logic
        let currentSlide = 0;
        const cardWidth = 240; // Card width 220px + gap 20px
        
        const nextBtn = document.querySelector('.rv-next');
        const prevBtn = document.querySelector('.rv-prev');

        function updateCarouselNav() {
            const containerWidth = document.querySelector('.rv-carousel-container').clientWidth;
            const maxSlide = Math.max(0, items.length - Math.floor(containerWidth / cardWidth));
            
            if (currentSlide > maxSlide) {
                currentSlide = maxSlide;
                track.style.transform = `translateX(-${currentSlide * cardWidth}px)`;
            }
        }
        window.addEventListener('resize', updateCarouselNav);

        if (nextBtn && prevBtn) {
            nextBtn.onclick = () => {
                const containerWidth = document.querySelector('.rv-carousel-container').clientWidth;
                const maxSlide = Math.max(0, items.length - Math.floor(containerWidth / cardWidth));
                if (currentSlide < maxSlide) {
                    currentSlide++;
                    track.style.transform = `translateX(-${currentSlide * cardWidth}px)`;
                }
            };
            prevBtn.onclick = () => {
                if (currentSlide > 0) {
                    currentSlide--;
                    track.style.transform = `translateX(-${currentSlide * cardWidth}px)`;
                }
            };
        }
    }

    initRecentlyViewed();
});

/* ===================== QUANTITY MODAL ===================== */
let currentProductId = null;
let currentMaxStock = 1;

function openQuantityModal(productId, productName, maxStock) {
    currentProductId = productId;
    currentMaxStock  = maxStock;
    document.getElementById('productInfo').textContent = '🛒 ' + productName;
    document.getElementById('quantityInput').value = '1';
    document.getElementById('quantityInput').max   = maxStock;
    document.getElementById('maxQuantityInfo').textContent = maxStock + ' units available';
    document.getElementById('productIdInput').value = productId;
    document.getElementById('quantityModal').classList.add('active');
}

function closeQuantityModal() {
    document.getElementById('quantityModal').classList.remove('active');
}

function increaseQuantity() {
    const inp = document.getElementById('quantityInput');
    let v = parseInt(inp.value) || 1;
    if (v < currentMaxStock) { inp.value = ++v; document.getElementById('quantityHiddenInput').value = v; }
}

function decreaseQuantity() {
    const inp = document.getElementById('quantityInput');
    let v = parseInt(inp.value) || 1;
    if (v > 1) { inp.value = --v; document.getElementById('quantityHiddenInput').value = v; }
}

/* ===================== FAVORITES ===================== */
function toggleFavorite(productId, button) {
    const action = button.classList.contains('active') ? 'remove' : 'add';
    fetch('favorites_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = button.querySelector('i');
            if (data.is_favorite) {
                button.classList.add('active');
                icon.classList.replace('far', 'fas');
                showToast('❤️ Added to favorites', 'success');
            } else {
                button.classList.remove('active');
                icon.classList.replace('fas', 'far');
                showToast('Removed from favorites', 'success');
            }
        }
    });
}

function checkFavoriteStatus(productId, button) {
    fetch('favorites_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check&product_id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.is_favorite) {
            button.classList.add('active');
            button.querySelector('i').classList.replace('far', 'fas');
        }
    });
}
</script>
</body>
</html>