<?php
require_once __DIR__ . '/../config/db.php';
// Session is started in db.php

// Routing logic - redirect based on user role
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && $user['role'] === 'admin') {
        // Admin user - stay on this admin inventory page
    } else {
        header("Location: main.php");
        exit;
    }
} else {
    header("Location: main.php");
    exit;
}

// DELETE PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $productId = $_POST['id'];
    if ($productId) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        header("Location: index.php?status=deleted");
        exit;
    }
}

// UPDATE PRODUCT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $productId = $_POST['id'];
    $productName = $_POST['name'];
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'];
    $newStock = $_POST['stock'];
    $price = $_POST['price'];
    $expiryDate = $_POST['expiry_date'] ?: null;
    $salePrice = (!empty($_POST['sale_price']) && floatval($_POST['sale_price']) > 0) ? floatval($_POST['sale_price']) : null;
    $badge = !empty($_POST['badge']) ? trim($_POST['badge']) : null;
    $rating = isset($_POST['rating']) && $_POST['rating'] !== '' ? floatval($_POST['rating']) : 5.00;
    $reviewsCount = isset($_POST['reviews_count']) && $_POST['reviews_count'] !== '' ? intval($_POST['reviews_count']) : 0;
    
    if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] === 0) {
        $imagePath = 'uploads/' . basename($_FILES['new_image']['name']);
        move_uploaded_file($_FILES['new_image']['tmp_name'], $imagePath);
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category = ?, stock = ?, price = ?, expiry_date = ?, image = ?, sale_price = ?, badge = ?, rating = ?, reviews_count = ? WHERE id = ?");
        $imageFileName = $_FILES['new_image']['name'];
        $stmt->bind_param("sssidssdsdii", $productName, $description, $category, $newStock, $price, $expiryDate, $imageFileName, $salePrice, $badge, $rating, $reviewsCount, $productId);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category = ?, stock = ?, price = ?, expiry_date = ?, sale_price = ?, badge = ?, rating = ?, reviews_count = ? WHERE id = ?");
        $stmt->bind_param("sssidsdsdii", $productName, $description, $category, $newStock, $price, $expiryDate, $salePrice, $badge, $rating, $reviewsCount, $productId);
    }

    $stmt->execute();
    header("Location: index.php?status=updated");
    exit;
}

// SEARCH & FILTER PRODUCTS
$search_query = "";
$category_filter = "";

if (isset($_POST['search'])) {
    $search_query = $_POST['search'];
}

if (isset($_GET['category']) && $_GET['category'] !== 'All Products') {
    $category_filter = $_GET['category'];
}

if (!empty($search_query) && !empty($category_filter)) {
    $param1 = "%$search_query%";
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ? AND category = ?");
    $stmt->bind_param("ss", $param1, $category_filter);
} elseif (!empty($search_query)) {
    $param = "%$search_query%";
    $stmt = $conn->prepare("SELECT * FROM products WHERE name LIKE ?");
    $stmt->bind_param("s", $param);
} elseif (!empty($category_filter)) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category = ?");
    $stmt->bind_param("s", $category_filter);
} else {
    $stmt = $conn->prepare("SELECT * FROM products");
}

$stmt->execute();
$result = $stmt->get_result();
$total_products = $result->num_rows;

// Aggregate stats
$stats_stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(stock) as total_stock, COUNT(CASE WHEN stock <= 10 AND stock > 0 THEN 1 END) as low_stock, COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock FROM products");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$statusMessage = '';
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'deleted': $statusMessage = 'Product successfully deleted!'; break;
        case 'updated': $statusMessage = 'Product successfully updated!'; break;
        case 'added':   $statusMessage = 'New product successfully added!'; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management — Pawganic Supplies</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
    /* ===================== ROOT ===================== */
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
        --danger-bg:  #fdecea;
        --warning:    #e9a320;
        --warning-bg: #fef6e4;
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
        background: rgba(253, 248, 240, 0.95);
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
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .nav-links a:hover, .nav-links a.active {
        background: var(--gold);
        color: var(--white);
    }

    .nav-links a.active {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey);
    }

    .nav-badge {
        background: var(--danger);
        color: white;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 50px;
        line-height: 1;
    }

    /* ===================== HERO BANNER ===================== */
    .inventory-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 55%, #7a3a10 100%);
        padding: 72px 5% 80px;
        position: relative;
        overflow: hidden;
    }

    .inventory-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 80% 40%, rgba(201,145,42,0.22) 0%, transparent 60%),
                    radial-gradient(ellipse at 5% 80%, rgba(122,158,126,0.12) 0%, transparent 50%);
    }

    .inventory-hero::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }

    .hero-deco { position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey); }
    .hero-deco-1 { width: 420px; height: 420px; top: -120px; right: -100px; }
    .hero-deco-2 { width: 200px; height: 200px; bottom: 10px; left: 3%; }
    .hero-deco-3 { width: 80px; height: 80px; top: 40px; left: 28%; opacity: 0.05; }

    .hero-inner {
        position: relative; z-index: 2;
        max-width: 1300px; margin: 0 auto;
        display: flex; align-items: flex-end; justify-content: space-between; gap: 40px; flex-wrap: wrap;
    }

    .hero-label {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(201,145,42,0.18); border: 1px solid rgba(201,145,42,0.38);
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
        margin-bottom: 18px;
    }

    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.4rem, 4vw, 3.8rem);
        font-weight: 900;
        color: var(--white);
        line-height: 1.1;
        margin-bottom: 14px;
    }

    .hero-title em { font-style: italic; color: var(--honey); }

    .hero-subtitle {
        color: rgba(255,255,255,0.6);
        font-size: 1rem;
        line-height: 1.7;
        max-width: 460px;
    }

    /* Hero Stats */
    .hero-stats-row {
        display: flex; gap: 0;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: var(--radius);
        overflow: hidden;
        backdrop-filter: blur(12px);
        flex-shrink: 0;
    }

    .hero-stat-card {
        padding: 22px 32px;
        text-align: center;
        border-right: 1px solid rgba(255,255,255,0.08);
        min-width: 120px;
        transition: var(--transition);
        cursor: default;
    }

    .hero-stat-card:last-child { border-right: none; }
    .hero-stat-card:hover { background: rgba(201,145,42,0.15); }

    .stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 2rem; font-weight: 900; color: var(--honey); line-height: 1;
        display: block;
    }

    .stat-label {
        font-size: 0.7rem; color: rgba(255,255,255,0.45);
        text-transform: uppercase; letter-spacing: 1.5px; margin-top: 5px;
        display: block;
    }

    .stat-icon {
        font-size: 0.85rem; margin-bottom: 8px; display: block;
    }

    .stat-icon.sage { color: var(--sage-light); }
    .stat-icon.gold { color: var(--honey); }
    .stat-icon.danger { color: #f08080; }
    .stat-icon.warning { color: #f0c060; }

    /* ===================== MAIN CONTENT ===================== */
    .main-content {
        max-width: 1300px;
        margin: 0 auto;
        padding: 40px 24px 80px;
        flex: 1;
        width: 100%;
    }

    /* ===================== TOOLBAR ===================== */
    .toolbar {
        background: var(--ivory);
        border-radius: var(--radius);
        padding: 22px 28px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.12);
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
        margin-bottom: 30px;
    }

    .toolbar .search-wrap {
        flex: 1; min-width: 200px; position: relative;
    }

    .toolbar .search-wrap i {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: var(--caramel); font-size: 0.9rem; pointer-events: none;
    }

    .toolbar input[type="text"] {
        width: 100%; padding: 12px 16px 12px 42px;
        border: 2px solid var(--mist); border-radius: 50px;
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.92rem; font-weight: 500;
        transition: var(--transition); outline: none;
    }

    .toolbar input[type="text"]:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }

    .toolbar input[type="text"]::placeholder { color: var(--caramel); opacity: 0.7; }

    .filter-pills {
        display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
    }

    .filter-pill {
        padding: 9px 20px; border: 2px solid var(--mist);
        border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.82rem; cursor: pointer;
        transition: var(--transition); background: var(--cream);
        color: var(--caramel); white-space: nowrap;
    }

    .filter-pill:hover {
        border-color: var(--gold); color: var(--gold);
    }

    .filter-pill.active {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-color: var(--espresso); color: var(--honey);
        box-shadow: var(--shadow-sm);
    }

    .btn-search {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; border-radius: 50px;
        padding: 12px 26px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.9rem; cursor: pointer;
        transition: var(--transition); white-space: nowrap;
        display: flex; align-items: center; gap: 8px;
    }

    .btn-search:hover { background: var(--gold); color: var(--white); transform: translateY(-1px); box-shadow: var(--shadow-sm); }

    /* ===================== RESULT META ===================== */
    .result-meta {
        display: flex; align-items: center; gap: 12px; margin-bottom: 24px;
    }

    .result-count {
        font-size: 0.88rem; color: var(--caramel); font-weight: 600;
        background: var(--ivory); border: 1px solid var(--mist);
        padding: 6px 16px; border-radius: 50px;
    }

    .result-divider { height: 1px; flex: 1; background: var(--mist); }

    .action-btns {
        display: flex; gap: 10px; align-items: center;
    }

    .btn-add-product {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; border-radius: 50px;
        padding: 10px 22px; font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.88rem; cursor: pointer;
        transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        text-decoration: none; box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }

    .btn-add-product:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px); box-shadow: var(--shadow-md);
    }

    .btn-dashboard {
        background: var(--ivory); color: var(--mahogany);
        border: 2px solid var(--mist); border-radius: 50px;
        padding: 10px 22px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.88rem; cursor: pointer;
        transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        text-decoration: none;
    }

    .btn-dashboard:hover { background: var(--mist); border-color: var(--caramel); color: var(--espresso); }

    /* ===================== PRODUCT GRID ===================== */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
        gap: 24px;
    }

    /* ===================== PRODUCT CARD ===================== */
    .product-card {
        background: var(--ivory);
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.08);
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    box-shadow 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                    border-color 0.35s ease;
        display: flex; flex-direction: column;
        position: relative;
        animation: cardIn 0.5s ease both;
    }

    @keyframes cardIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

    .product-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-lg);
        border-color: rgba(201,145,42,0.3);
    }

    /* Stock badge */
    .stock-badge {
        position: absolute; top: 14px; left: 14px; z-index: 5;
        padding: 4px 12px; border-radius: 50px; font-size: 0.72rem;
        font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .stock-badge.in-stock  { background: rgba(122,158,126,0.15); color: var(--sage); border: 1px solid rgba(122,158,126,0.3); }
    .stock-badge.low-stock { background: rgba(201,145,42,0.15); color: var(--gold); border: 1px solid rgba(201,145,42,0.3); }
    .stock-badge.out-stock { background: rgba(192,57,43,0.12); color: var(--danger); border: 1px solid rgba(192,57,43,0.25); }

    /* Image */
    .card-image {
        height: 220px;
        background: linear-gradient(145deg, var(--cream), var(--mist));
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; position: relative;
    }

    .card-image::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 50px;
        background: linear-gradient(to top, rgba(253,248,240,0.5), transparent);
        pointer-events: none;
    }

    .card-image img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .product-card:hover .card-image img { transform: scale(1.06); }

    .no-image-placeholder {
        display: flex; flex-direction: column; align-items: center;
        gap: 10px; color: var(--mist);
    }
    .no-image-placeholder i { font-size: 2.5rem; }
    .no-image-placeholder span { font-size: 0.82rem; color: var(--caramel); opacity: 0.5; }

    /* Body */
    .card-body {
        padding: 20px 22px 8px;
        flex: 1; display: flex; flex-direction: column; gap: 8px;
        position: relative;
    }

    /* Price ribbon */
    .price-ribbon {
        position: absolute;
        top: -18px; right: 18px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey);
        padding: 7px 16px; border-radius: 50px;
        font-family: 'Playfair Display', serif;
        font-size: 1rem; font-weight: 700;
        box-shadow: 0 4px 16px rgba(44,26,14,0.25);
        letter-spacing: 0.3px;
        z-index: 3;
    }

    .card-category {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(201,145,42,0.1); color: var(--caramel);
        padding: 3px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 600;
        border: 1px solid rgba(201,145,42,0.2); width: fit-content;
        letter-spacing: 0.3px;
    }

    .card-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.15rem; font-weight: 700; color: var(--espresso);
        line-height: 1.3; margin: 0;
    }

    .card-desc {
        font-size: 0.82rem; color: var(--caramel); line-height: 1.6;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; margin: 0;
    }

    /* Meta row */
    .card-meta {
        display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
        padding: 10px 0 4px;
        border-top: 1px solid var(--mist);
    }

    .meta-chip {
        display: flex; align-items: center; gap: 5px;
        font-size: 0.78rem; color: var(--caramel); font-weight: 500;
    }

    .meta-chip i { color: var(--gold); font-size: 0.72rem; }

    /* Stock bar */
    .stock-section {
        padding: 4px 0 8px;
    }

    .stock-row {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 7px;
    }

    .stock-label { font-size: 0.75rem; font-weight: 600; color: var(--caramel); text-transform: uppercase; letter-spacing: 0.8px; }

    .stock-value {
        font-size: 0.8rem; font-weight: 700; 
    }

    .stock-value.high { color: var(--sage); }
    .stock-value.medium { color: var(--warning); }
    .stock-value.low { color: var(--danger); }

    .stock-bar {
        height: 6px;
        background: var(--mist);
        border-radius: 10px;
        overflow: hidden;
    }

    .stock-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stock-fill.high { background: linear-gradient(to right, var(--sage), var(--sage-light)); }
    .stock-fill.medium { background: linear-gradient(to right, var(--warning), #f5d078); }
    .stock-fill.low { background: linear-gradient(to right, var(--danger), #e07070); }

    /* Card footer (actions) */
    .card-footer {
        padding: 14px 22px 20px;
        background: transparent; border-top: 1px solid var(--mist);
        display: flex; gap: 10px;
    }

    .btn-edit {
        flex: 1; padding: 11px;
        background: rgba(201,145,42,0.1); border: 2px solid rgba(201,145,42,0.25);
        color: var(--caramel); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 7px;
    }

    .btn-edit:hover {
        background: var(--gold); border-color: var(--gold); color: var(--white);
        transform: translateY(-2px); box-shadow: var(--shadow-sm);
    }

    .btn-delete {
        flex: 1; padding: 11px;
        background: rgba(192,57,43,0.08); border: 2px solid rgba(192,57,43,0.2);
        color: var(--danger); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 7px;
    }

    .btn-delete:hover {
        background: var(--danger); border-color: var(--danger); color: var(--white);
        transform: translateY(-2px); box-shadow: var(--shadow-sm);
    }

    /* ===================== QUICK STATS STRIP ===================== */
    .stats-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 30px;
    }

    .stat-strip-card {
        background: var(--ivory);
        border-radius: var(--radius-sm);
        padding: 20px 22px;
        border: 1px solid rgba(201,145,42,0.1);
        box-shadow: var(--shadow-sm);
        display: flex; align-items: center; gap: 16px;
        transition: var(--transition);
        cursor: default;
    }

    .stat-strip-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(201,145,42,0.25); }

    .stat-strip-icon {
        width: 48px; height: 48px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0;
    }

    .stat-strip-icon.green { background: rgba(122,158,126,0.15); color: var(--sage); }
    .stat-strip-icon.gold  { background: rgba(201,145,42,0.12); color: var(--gold); }
    .stat-strip-icon.red   { background: rgba(192,57,43,0.1); color: var(--danger); }
    .stat-strip-icon.orange{ background: rgba(233,163,32,0.12); color: var(--warning); }

    .stat-strip-info { flex: 1; }
    .stat-strip-num { font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 800; color: var(--espresso); line-height: 1; }
    .stat-strip-label { font-size: 0.75rem; color: var(--caramel); font-weight: 500; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.8px; }

    /* ===================== EMPTY STATE ===================== */
    .empty-state {
        text-align: center; padding: 80px 24px;
        background: var(--ivory); border-radius: var(--radius);
        border: 1px solid var(--mist);
    }

    .empty-state i { font-size: 3.5rem; color: var(--mist); margin-bottom: 20px; display: block; }
    .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--mahogany); margin-bottom: 10px; }
    .empty-state p { color: var(--caramel); font-size: 0.9rem; }

    /* ===================== MODAL ===================== */
    .modal-content {
        border-radius: var(--radius);
        border: none;
        box-shadow: var(--shadow-lg);
        font-family: 'DM Sans', sans-serif;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-bottom: none;
        padding: 22px 28px;
    }

    .modal-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        color: var(--honey);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.25rem;
    }

    .modal-title i { color: var(--gold); }

    .btn-close { filter: invert(1) brightness(0.8); }

    .modal-body { padding: 28px; background: var(--ivory); }
    .modal-footer { border-top: 1px solid var(--mist); padding: 16px 28px 24px; background: var(--cream); }

    .form-label {
        font-weight: 600; font-size: 0.82rem; text-transform: uppercase;
        letter-spacing: 0.8px; color: var(--caramel); margin-bottom: 8px;
        display: block;
    }

    .form-control, .form-select {
        border-radius: var(--radius-sm);
        padding: 12px 15px;
        border: 2px solid var(--mist);
        background: var(--cream);
        color: var(--espresso);
        font-family: 'DM Sans', sans-serif;
        transition: var(--transition);
        font-size: 0.92rem;
    }

    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 0 4px rgba(201,145,42,0.12);
        border-color: var(--gold);
        background: var(--ivory);
        outline: none;
    }

    .input-group .input-group-text {
        background-color: var(--cream);
        border: 2px solid var(--mist);
        border-right: none;
        color: var(--caramel);
        font-weight: 700;
        border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    }

    .input-group .form-control {
        border-left: none;
        border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    }

    .image-preview {
        background: var(--cream); border: 2px dashed var(--mist);
        border-radius: var(--radius-sm); padding: 16px; text-align: center;
        min-height: 100px; display: flex; align-items: center; justify-content: center;
    }

    .image-preview img { max-height: 140px; max-width: 100%; border-radius: var(--radius-sm); }

    .btn-modal-cancel {
        background: var(--mist); color: var(--mahogany); border: none;
        padding: 12px 24px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; cursor: pointer; transition: var(--transition);
    }

    .btn-modal-cancel:hover { background: var(--cream); }

    .btn-modal-save {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none;
        padding: 12px 28px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 700; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }

    .btn-modal-save:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); }

    /* ===================== TOAST ===================== */
    .toast-container {
        position: fixed; top: 20px; right: 20px; z-index: 9999;
        display: flex; flex-direction: column; gap: 10px;
    }

    .toast-item {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 20px; border-radius: var(--radius-sm);
        box-shadow: var(--shadow-lg);
        animation: toastIn 0.35s ease;
        min-width: 280px; max-width: 380px;
        border-left: 4px solid;
    }

    .toast-item.success { background: rgba(52,168,83,0.96); border-left-color: #34a853; color: white; }
    .toast-item.error   { background: rgba(192,57,43,0.96); border-left-color: var(--danger); color: white; }

    @keyframes toastIn { from { transform: translateX(400px); opacity:0; } to { transform: translateX(0); opacity:1; } }
    @keyframes toastOut { from { transform: translateX(0); opacity:1; } to { transform: translateX(400px); opacity:0; } }

    .toast-close-btn { background: none; border: none; color: inherit; cursor: pointer; padding: 4px; opacity: 0.7; font-size: 1rem; }
    .toast-close-btn:hover { opacity: 1; }

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

    .footer-content {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 40px; margin-bottom: 40px;
        max-width: 1300px; margin-left: auto; margin-right: auto;
    }

    .footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--honey); margin-bottom: 20px; }
    .footer-section p  { font-size: 0.88rem; line-height: 1.8; margin-bottom: 10px; }

    .footer-links { display: flex; flex-direction: column; gap: 10px; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.88rem; transition: var(--transition); display: flex; align-items: center; gap: 8px; }
    .footer-links a:hover { color: var(--honey); padding-left: 6px; }
    .footer-links a i { width: 14px; color: var(--caramel); }

    .social-links { display: flex; gap: 10px; margin-top: 16px; }
    .social-links a {
        width: 38px; height: 38px; border-radius: 50%;
        background: rgba(201,145,42,0.12); border: 1px solid rgba(201,145,42,0.25);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.88rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }

    .copyright {
        border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px;
        text-align: center; font-size: 0.82rem; color: rgba(255,255,255,0.3);
        max-width: 1300px; margin: 0 auto;
    }

    /* ===================== SCROLL TO TOP ===================== */
    .scroll-to-top {
        position: fixed; bottom: 30px; right: 30px; z-index: 999;
        width: 46px; height: 46px; border-radius: 50%;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border: none; color: var(--honey); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; box-shadow: var(--shadow-md);
        opacity: 0; visibility: hidden; transition: var(--transition);
    }

    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 1024px) {
        .stats-strip { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .navbar { padding: 0 20px; }
        .hero-stats-row { display: none; }
        .hero-inner { flex-direction: column; gap: 24px; }
        .stats-strip { grid-template-columns: repeat(2, 1fr); }
        .products-grid { grid-template-columns: 1fr; }
        .result-meta { flex-wrap: wrap; }
        .action-btns { width: 100%; }
        .btn-add-product, .btn-dashboard { flex: 1; justify-content: center; }
    }

    @media (max-width: 480px) {
        .stats-strip { grid-template-columns: 1fr; }
        .toolbar { flex-direction: column; }
        .filter-pills { justify-content: center; }
    }

    /* ===================== STAGGER ANIMATION ===================== */
    .product-card:nth-child(1)  { animation-delay: 0.04s; }
    .product-card:nth-child(2)  { animation-delay: 0.08s; }
    .product-card:nth-child(3)  { animation-delay: 0.12s; }
    .product-card:nth-child(4)  { animation-delay: 0.16s; }
    .product-card:nth-child(5)  { animation-delay: 0.20s; }
    .product-card:nth-child(6)  { animation-delay: 0.24s; }
    .product-card:nth-child(7)  { animation-delay: 0.28s; }
    .product-card:nth-child(8)  { animation-delay: 0.32s; }
    .product-card:nth-child(9)  { animation-delay: 0.36s; }

    /* Delete confirm overlay */
    .delete-confirm-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(44,26,14,0.65); backdrop-filter: blur(6px);
        z-index: 3000; align-items: center; justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    .delete-confirm-overlay.active { display: flex; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }

    .delete-confirm-box {
        background: var(--ivory); border-radius: var(--radius);
        padding: 40px 36px; max-width: 380px; width: 90%;
        text-align: center; box-shadow: var(--shadow-lg);
        animation: slideUp 0.35s ease;
        border-top: 4px solid var(--danger);
    }
    @keyframes slideUp { from{transform:translateY(30px);opacity:0;} to{transform:translateY(0);opacity:1;} }

    .delete-confirm-icon { font-size: 3rem; color: var(--danger); margin-bottom: 18px; }
    .delete-confirm-box h3 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--espresso); margin-bottom: 10px; }
    .delete-confirm-box p { color: var(--caramel); font-size: 0.9rem; margin-bottom: 28px; line-height: 1.6; }

    .confirm-btns { display: flex; gap: 12px; }

    .btn-confirm-cancel {
        flex: 1; padding: 12px; background: var(--mist);
        border: none; border-radius: 50px; color: var(--mahogany);
        font-family: 'DM Sans', sans-serif; font-weight: 600; cursor: pointer;
        transition: var(--transition);
    }
    .btn-confirm-cancel:hover { background: var(--cream); }

    .btn-confirm-delete {
        flex: 1; padding: 12px;
        background: linear-gradient(135deg, var(--danger), #e06050);
        border: none; border-radius: 50px; color: white;
        font-family: 'DM Sans', sans-serif; font-weight: 700; cursor: pointer;
        transition: var(--transition); box-shadow: 0 4px 14px rgba(192,57,43,0.3);
    }
    .btn-confirm-delete:hover { background: #a02020; transform: translateY(-1px); }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="navbar">
    <a href="index.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" class="logo-img">
    </a>
    <div class="nav-links">
        <a href="index.php" class="active"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="add.php"><i class="fas fa-plus-circle"></i> Add Product</a>
        <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
    </div>
</nav>

<!-- ===================== HERO ===================== -->
<section class="inventory-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-deco hero-deco-3"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-label"><i class="fas fa-shield-alt"></i> ADMIN PANEL</div>
            <h1 class="hero-title">Inventory <em>Control</em><br>Center</h1>
            <p class="hero-subtitle">Manage your entire product catalog with precision. Track stock levels, update listings, and keep your store running at its best.</p>
        </div>
        <div class="hero-stats-row">
            <div class="hero-stat-card">
                <span class="stat-icon sage"><i class="fas fa-boxes"></i></span>
                <span class="stat-num"><?= $stats['total'] ?? 0 ?></span>
                <span class="stat-label">Products</span>
            </div>
            <div class="hero-stat-card">
                <span class="stat-icon gold"><i class="fas fa-layer-group"></i></span>
                <span class="stat-num"><?= $stats['total_stock'] ?? 0 ?></span>
                <span class="stat-label">Total Units</span>
            </div>
            <div class="hero-stat-card">
                <span class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></span>
                <span class="stat-num"><?= $stats['low_stock'] ?? 0 ?></span>
                <span class="stat-label">Low Stock</span>
            </div>
            <div class="hero-stat-card">
                <span class="stat-icon danger"><i class="fas fa-times-circle"></i></span>
                <span class="stat-num"><?= $stats['out_of_stock'] ?? 0 ?></span>
                <span class="stat-label">Out of Stock</span>
            </div>
        </div>
    </div>
</section>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="main-content">

    <!-- Stats Strip -->
    <div class="stats-strip">
        <div class="stat-strip-card">
            <div class="stat-strip-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-strip-info">
                <div class="stat-strip-num"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-strip-label">Total Products</div>
            </div>
        </div>
        <div class="stat-strip-card">
            <div class="stat-strip-icon gold"><i class="fas fa-cubes"></i></div>
            <div class="stat-strip-info">
                <div class="stat-strip-num"><?= number_format($stats['total_stock'] ?? 0) ?></div>
                <div class="stat-strip-label">Units in Stock</div>
            </div>
        </div>
        <div class="stat-strip-card">
            <div class="stat-strip-icon orange"><i class="fas fa-fire"></i></div>
            <div class="stat-strip-info">
                <div class="stat-strip-num"><?= $stats['low_stock'] ?? 0 ?></div>
                <div class="stat-strip-label">Low Stock Alerts</div>
            </div>
        </div>
        <div class="stat-strip-card">
            <div class="stat-strip-icon red"><i class="fas fa-ban"></i></div>
            <div class="stat-strip-info">
                <div class="stat-strip-num"><?= $stats['out_of_stock'] ?? 0 ?></div>
                <div class="stat-strip-label">Out of Stock</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <form method="POST" class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search products by name…" value="<?= htmlspecialchars($search_query) ?>">
        </div>
        <div class="filter-pills">
            <button type="button" class="filter-pill active" data-category="all">All</button>
            <button type="button" class="filter-pill" data-category="Food"><i class="fas fa-bowl-food"></i> Food</button>
            <button type="button" class="filter-pill" data-category="Toys">Toys</button>
            <button type="button" class="filter-pill" data-category="Accessories">Accessories</button>
            <button type="button" class="filter-pill" data-category="Medicine">Medicine</button>
        </div>
        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
    </form>

    <!-- Result meta + actions -->
    <div class="result-meta">
        <span class="result-count"><i class="fas fa-box" style="margin-right:6px; color:var(--gold);"></i><?= $total_products ?> product<?= $total_products !== 1 ? 's' : '' ?> <?= !empty($search_query) ? 'found' : 'total' ?></span>
        <div class="result-divider"></div>
        <div class="action-btns">
            <a href="add.php" class="btn-add-product"><i class="fas fa-plus"></i> Add Product</a>
            <a href="admin.php" class="btn-dashboard"><i class="fas fa-chart-pie"></i> Dashboard</a>
        </div>
    </div>

    <!-- Product Grid -->
    <?php if ($total_products > 0): ?>
    <div class="products-grid" id="productsGrid">
        <?php
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()):
            $stock = (int)$row['stock'];
            $stockClass = $stock === 0 ? 'out-stock' : ($stock <= 10 ? 'low-stock' : 'in-stock');
            $stockBarClass = $stock === 0 ? 'low' : ($stock <= 10 ? 'low' : ($stock <= 20 ? 'medium' : 'high'));
            $stockValueClass = $stock === 0 ? 'low' : ($stock <= 10 ? 'low' : ($stock <= 20 ? 'medium' : 'high'));
            $stockPercentage = min(100, ($stock / 30) * 100);
            $stockBadgeText = $stock === 0 ? '<i class="fas fa-times-circle"></i> Out of Stock' : ($stock <= 10 ? '<i class="fas fa-fire"></i> Only '.$stock.' left' : '<i class="fas fa-check-circle"></i> In Stock');
        ?>
        <div class="product-card" data-category="<?= htmlspecialchars($row['category']) ?>">
            <span class="stock-badge <?= $stockClass ?>" style="<?= !empty($row['badge']) ? 'top: 46px;' : '' ?>"><?= $stockBadgeText ?></span>

            <div class="card-image">
                <?php if (!empty($row['badge'])): ?>
                    <span class="badge-pill-promo" style="position: absolute; top: 12px; left: 12px; background: var(--gold); color: var(--white); padding: 5px 12px; border-radius: 50px; font-size: 0.72rem; font-weight: 700; z-index: 2; box-shadow: var(--shadow-sm);"><i class="fas fa-award"></i> <?= htmlspecialchars($row['badge']) ?></span>
                <?php endif; ?>
                <?php if (!empty($row['image']) && file_exists('uploads/' . $row['image'])): ?>
                    <img loading="lazy" src="uploads/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
                <?php else: ?>
                    <div class="no-image-placeholder">
                        <i class="fas fa-image"></i>
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card-body">
                <?php if (!empty($row['sale_price']) && $row['sale_price'] > 0 && $row['sale_price'] < $row['price']): ?>
                    <div class="price-ribbon sale">
                        ₱<?= number_format($row['sale_price'], 2) ?>
                        <span style="text-decoration: line-through; font-size: 0.75rem; opacity: 0.75; margin-left: 5px;">₱<?= number_format($row['price'], 2) ?></span>
                    </div>
                <?php else: ?>
                    <div class="price-ribbon">₱<?= number_format($row['price'], 2) ?></div>
                <?php endif; ?>
                
                <span class="card-category"><i class="fas fa-tag"></i><?= htmlspecialchars($row['category']) ?></span>
                <div class="card-rating" style="margin-top: 5px; font-size: 0.82rem; color: var(--gold);">
                    <i class="fas fa-star"></i> <?= number_format($row['rating'] ?? 5.0, 1) ?> <span style="color: var(--caramel);">(<?= intval($row['reviews_count'] ?? 0) ?> reviews)</span>
                </div>
                <h5 class="card-title" style="margin-top:8px;"><?= htmlspecialchars($row['name']) ?></h5>
                <?php if (!empty($row['description'])): ?>
                    <p class="card-desc"><?= htmlspecialchars($row['description']) ?></p>
                <?php endif; ?>

                <div class="card-meta">
                    <div class="meta-chip">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= $row['expiry_date'] ?: 'No Expiry' ?></span>
                    </div>
                    <div class="meta-chip">
                        <i class="fas fa-hashtag"></i>
                        <span>ID: <?= $row['id'] ?></span>
                    </div>
                </div>

                <div class="stock-section">
                    <div class="stock-row">
                        <span class="stock-label">Stock Level</span>
                        <span class="stock-value <?= $stockValueClass ?>"><?= $stock ?> units</span>
                    </div>
                    <div class="stock-bar">
                        <div class="stock-fill <?= $stockBarClass ?>" style="width:<?= $stockPercentage ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <button class="btn-edit"
                    data-bs-toggle="modal" data-bs-target="#editModal"
                    data-id="<?= $row['id'] ?>"
                    data-name="<?= htmlspecialchars($row['name']) ?>"
                    data-description="<?= htmlspecialchars($row['description'] ?? '') ?>"
                    data-category="<?= htmlspecialchars($row['category']) ?>"
                    data-stock="<?= $row['stock'] ?>"
                    data-price="<?= $row['price'] ?>"
                    data-expiry="<?= $row['expiry_date'] ?>"
                    data-image="<?= htmlspecialchars($row['image'] ?? '') ?>"
                    data-sale-price="<?= $row['sale_price'] ?? '' ?>"
                    data-badge="<?= htmlspecialchars($row['badge'] ?? '') ?>"
                    data-rating="<?= $row['rating'] ?? '5.00' ?>"
                    data-reviews-count="<?= $row['reviews_count'] ?? '0' ?>">
                    <i class="fas fa-pen"></i> Edit
                </button>
                <button class="btn-delete" onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['name'])) ?>')">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Empty filter message -->
    <div id="emptyFilterMsg" class="empty-state" style="display:none;">
        <i class="fas fa-filter"></i>
        <h3>No products in this category</h3>
        <p>Try selecting a different category filter.</p>
    </div>

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <h3>No products found</h3>
        <p>Try adjusting your search or <a href="add.php" style="color:var(--gold); font-weight:600;">add your first product</a>.</p>
    </div>
    <?php endif; ?>

</div><!-- /main-content -->

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Admin inventory management portal. Keep your store stocked, organized, and ready to delight every customer.</p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Admin Links</h3>
            <div class="footer-links">
                <a href="index.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="add.php"><i class="fas fa-plus-circle"></i> Add Product</a>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="shop.php"><i class="fas fa-store"></i> View Shop</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey); margin-right:8px;"></i>123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey); margin-right:8px;"></i>+1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey); margin-right:8px;"></i>meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey); margin-right:8px;"></i>Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved. Admin Portal v2.0</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<!-- ===================== EDIT MODAL ===================== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="index.php" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pen-to-square"></i> Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="productId" name="id">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="productName" name="name" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select class="form-control" id="productCategory" name="category" required>
                            <option value="Food">Food</option>
                            <option value="Toys">Toys</option>
                            <option value="Accessories">Accessories</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="3" style="resize:vertical;"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" class="form-control" id="productPrice" name="price" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" class="form-control" id="newStock" name="stock" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" id="expiryDate" name="expiry_date">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Current Image</label>
                        <div class="image-preview" id="currentImage">
                            <span style="color:var(--caramel); font-size:0.85rem;">No image</span>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Upload New Image</label>
                        <input type="file" class="form-control" name="new_image" accept="image/*">
                    </div>
                    <!-- New fields for badge/sale/social proof -->
                    <div class="col-md-6">
                        <label class="form-label">Sale Price (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" min="0" class="form-control" id="productSalePrice" name="sale_price" placeholder="Leave empty if not on sale">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product Badge</label>
                        <input type="text" class="form-control" id="productBadge" name="badge" placeholder="e.g. SALE, NEW, HOT, LIMITED">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Initial Rating</label>
                        <input type="number" step="0.01" min="1" max="5" class="form-control" id="productRating" name="rating">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reviews Count</label>
                        <input type="number" min="0" class="form-control" id="productReviewsCount" name="reviews_count">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_stock" class="btn-modal-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== DELETE CONFIRM ===================== -->
<div class="delete-confirm-overlay" id="deleteOverlay">
    <div class="delete-confirm-box">
        <div class="delete-confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <h3>Delete Product?</h3>
        <p id="deleteProductName">This action cannot be undone. The product will be permanently removed from your inventory.</p>
        <div class="confirm-btns">
            <button class="btn-confirm-cancel" onclick="closeDeleteConfirm()">Cancel</button>
            <form id="deleteForm" method="POST" action="index.php" style="flex:1;">
                <input type="hidden" name="delete_product" value="true">
                <input type="hidden" id="deleteProductId" name="id">
                <button type="submit" class="btn-confirm-delete" style="width:100%;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="toastContainer" class="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== EDIT MODAL =====
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('productId').value       = btn.dataset.id;
    document.getElementById('productName').value     = btn.dataset.name;
    document.getElementById('productDescription').value = btn.dataset.description || '';
    document.getElementById('productPrice').value    = btn.dataset.price;
    document.getElementById('newStock').value        = btn.dataset.stock;
    document.getElementById('expiryDate').value      = btn.dataset.expiry || '';
    document.getElementById('productSalePrice').value = btn.dataset.salePrice || '';
    document.getElementById('productBadge').value     = btn.dataset.badge || '';
    document.getElementById('productRating').value    = btn.dataset.rating || '5.00';
    document.getElementById('productReviewsCount').value = btn.dataset.reviewsCount || '0';

    const cat = btn.dataset.category;
    const sel = document.getElementById('productCategory');
    for (let opt of sel.options) { if (opt.value === cat) { opt.selected = true; break; } }

    const img = btn.dataset.image;
    const preview = document.getElementById('currentImage');
    if (img && img.trim()) {
        preview.innerHTML = `<img src="uploads/${img}" alt="Current Image">`;
    } else {
        preview.innerHTML = `<span style="color:var(--caramel); font-size:0.85rem;"><i class="fas fa-image" style="margin-right:6px;"></i>No image uploaded</span>`;
    }
});

// ===== DELETE CONFIRM =====
function confirmDelete(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
    document.getElementById('deleteOverlay').classList.add('active');
}

function closeDeleteConfirm() {
    document.getElementById('deleteOverlay').classList.remove('active');
}

document.getElementById('deleteOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteConfirm();
});

// ===== TOAST =====
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast-item ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'times-circle'}"></i>
        <span style="flex:1;">${message}</span>
        <button class="toast-close-btn"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(toast);
    toast.querySelector('.toast-close-btn').onclick = () => removeToast(toast);
    setTimeout(() => removeToast(toast), 4000);
}

function removeToast(toast) {
    toast.style.animation = 'toastOut 0.35s ease forwards';
    setTimeout(() => toast.remove(), 340);
}

// ===== CLIENT-SIDE CATEGORY FILTER =====
document.querySelectorAll('.filter-pill').forEach(pill => {
    pill.addEventListener('click', function() {
        document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.category;
        const cards = document.querySelectorAll('.product-card');
        let visible = 0;
        cards.forEach(card => {
            const show = cat === 'all' || card.dataset.category === cat;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const emptyMsg = document.getElementById('emptyFilterMsg');
        if (emptyMsg) emptyMsg.style.display = visible === 0 ? 'block' : 'none';
    });
});

// ===== STATUS TOAST FROM PHP =====
<?php if ($statusMessage): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes($statusMessage) ?>', 'success'));
<?php endif; ?>

// ===== SCROLL TO TOP =====
const scrollBtn = document.getElementById('scrollToTopBtn');
window.addEventListener('scroll', () => scrollBtn.classList.toggle('show', window.pageYOffset > 300));
scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

// ===== ANIMATE STOCK BARS ON LOAD =====
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.stock-fill').forEach(bar => {
        const target = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => { bar.style.width = target; }, 200);
    });
});
</script>
</body>
</html>