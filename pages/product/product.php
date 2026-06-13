<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: shop.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Handle POST review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    if ($rating < 1) $rating = 1;
    if ($rating > 5) $rating = 5;
    
    $user_id = $_SESSION['user_id'];
    $is_anonymous = isset($_POST['post_anonymous']) && $_POST['post_anonymous'] == '1';
    $username = $is_anonymous ? 'Anonymous' : ($_SESSION['username'] ?? 'Anonymous');
    
    // Insert review
    $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, username, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisis", $product_id, $user_id, $username, $rating, $comment);
    $stmt->execute();
    $stmt->close();
    
    // Recalculate average rating and reviews count
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM product_reviews WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $new_rating = $res['avg_rating'] !== null ? floatval($res['avg_rating']) : 5.00;
    $new_count = intval($res['cnt']);
    
    // Update products table
    $stmt = $conn->prepare("UPDATE products SET rating = ?, reviews_count = ? WHERE id = ?");
    $stmt->bind_param("dii", $new_rating, $new_count, $product_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: product.php?id=" . $product_id . "&review_submitted=1");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Fetch reviews list
$reviews_stmt = $conn->prepare("SELECT * FROM product_reviews WHERE product_id = ? ORDER BY created_at DESC");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews = [];
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}
$reviews_stmt->close();

$user_balance  = $_SESSION['balance'] ?? 0;
$nav_username  = $_SESSION['username'] ?? 'User';
$nav_role      = $_SESSION['role'] ?? 'customer';

$user_id = $_SESSION['user_id'];
$pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $user_id);
$pic_stmt->execute();
$pic_stmt->bind_result($profile_pic);
$pic_stmt->fetch();
$pic_stmt->close();

if (!$profile_pic) $profile_pic = 'images/profile.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — Pawganic Supplies</title>
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

    .logo-img { height: 46px; width: auto; transition: transform 0.3s ease; }
    .logo-img:hover { transform: scale(1.05); }

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
    .nav-links a:hover, .nav-links a.active {
        background: var(--gold); color: var(--white);
    }

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
        border: 1px solid rgba(201,145,42,0.15); overflow: hidden;
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

    /* ===================== HERO BREADCRUMB BANNER ===================== */
    .page-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 40px 5% 48px;
        position: relative;
        overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 80% 50%, rgba(201,145,42,0.2) 0%, transparent 60%);
    }
    .page-hero::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 48px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco {
        position: absolute; border-radius: 50%; opacity: 0.06; background: var(--honey);
    }
    .hero-deco-1 { width: 280px; height: 280px; top: -80px; right: -60px; }
    .hero-deco-2 { width: 150px; height: 150px; bottom: 10px; left: 8%; }

    .page-hero-inner {
        position: relative; z-index: 2;
        max-width: 1100px; margin: 0 auto;
    }

    .breadcrumb-nav {
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .breadcrumb-nav a {
        color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.82rem;
        font-weight: 500; transition: var(--transition); display: flex; align-items: center; gap: 4px;
    }
    .breadcrumb-nav a:hover { color: var(--honey); }
    .breadcrumb-nav .sep { color: rgba(255,255,255,0.3); font-size: 0.7rem; }
    .breadcrumb-nav .current { color: var(--honey); font-size: 0.82rem; font-weight: 600; }

    .page-hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.6rem, 3vw, 2.4rem);
        font-weight: 700;
        color: var(--white);
        line-height: 1.2;
    }
    .page-hero-title em { font-style: italic; color: var(--honey); }

    .back-link {
        display: inline-flex; align-items: center; gap: 8px; margin-top: 16px;
        color: rgba(255,255,255,0.65); font-size: 0.88rem; text-decoration: none;
        transition: var(--transition); font-weight: 500;
    }
    .back-link:hover { color: var(--honey); gap: 12px; }

    /* ===================== MAIN CONTENT ===================== */
    .main-content {
        flex: 1;
        max-width: 1100px; margin: 40px auto 60px;
        padding: 0 24px; width: 100%;
    }

    /* ===================== PRODUCT CARD ===================== */
    .product-detail {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
    }

    .product-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
    }

    /* ---- Left: Image pane ---- */
    .product-image-pane {
        background: linear-gradient(145deg, var(--cream), var(--mist));
        position: relative;
        display: flex; flex-direction: column;
        min-height: 500px;
    }

    .image-wrap {
        flex: 1; display: flex; align-items: center; justify-content: center;
        padding: 50px 40px; position: relative;
    }

    .image-wrap img {
        max-width: 100%; max-height: 400px; object-fit: contain;
        filter: drop-shadow(0 16px 40px rgba(44,26,14,0.18));
        transition: transform 0.5s cubic-bezier(0.4,0,0.2,1);
        animation: floatIn 0.6s ease both;
    }
    @keyframes floatIn { from { opacity:0; transform:translateY(20px) scale(0.96); } to { opacity:1; transform:translateY(0) scale(1); } }

    .product-image-pane:hover .image-wrap img { transform: scale(1.04) translateY(-4px); }

    .no-image-placeholder {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 12px; color: rgba(44,26,14,0.25); padding: 60px;
    }
    .no-image-placeholder i { font-size: 3.5rem; }
    .no-image-placeholder span { font-size: 0.9rem; }

    /* Favorite btn */
    .fav-btn {
        position: absolute; top: 20px; right: 20px; z-index: 10;
        width: 46px; height: 46px; border: none; border-radius: 50%;
        background: rgba(253,248,240,0.92); backdrop-filter: blur(8px);
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        box-shadow: var(--shadow-sm); transition: var(--transition);
    }
    .fav-btn:hover { transform: scale(1.12); background: var(--white); }
    .fav-btn i { font-size: 1.2rem; color: #ccc; transition: var(--transition); }
    .fav-btn.active i { color: #e74c3c; animation: heartPop 0.35s ease; }

    @keyframes heartPop {
        0%,100% { transform:scale(1); }
        40% { transform:scale(1.4); }
        70% { transform:scale(0.9); }
    }

    /* Description panel at bottom of image pane */
    .desc-panel {
        background: rgba(44,26,14,0.04); border-top: 1px solid rgba(201,145,42,0.12);
        padding: 24px 32px;
    }
    .desc-panel h6 {
        font-family: 'Playfair Display', serif;
        font-size: 0.9rem; font-weight: 700; color: var(--caramel);
        text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;
    }
    .desc-panel p {
        font-size: 0.92rem; color: var(--caramel); line-height: 1.75;
    }

    /* ---- Right: Info pane ---- */
    .product-info-pane {
        padding: 44px 44px 40px;
        display: flex; flex-direction: column; gap: 20px;
        border-left: 1px solid rgba(201,145,42,0.1);
    }

    .category-chip {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(201,145,42,0.1); color: var(--caramel);
        padding: 4px 14px; border-radius: 50px; font-size: 0.78rem; font-weight: 600;
        border: 1px solid rgba(201,145,42,0.22); width: fit-content; letter-spacing: 0.3px;
    }

    .product-name {
        font-family: 'Playfair Display', serif;
        font-size: clamp(1.6rem, 2.5vw, 2.2rem);
        font-weight: 900; color: var(--espresso); line-height: 1.2;
    }

    /* Price block */
    .price-block {
        display: flex; align-items: baseline; gap: 10px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-radius: var(--radius-sm);
        padding: 18px 24px;
    }
    .price-label {
        font-size: 0.8rem; color: rgba(255,255,255,0.5);
        text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600;
        align-self: center;
    }
    .price-value {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem; font-weight: 700; color: var(--honey);
    }

    /* Stock indicator */
    .stock-row { display: flex; align-items: center; gap: 10px; }
    .stock-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 50px;
        font-size: 0.82rem; font-weight: 700;
    }
    .stock-chip.in-stock   { background: rgba(122,158,126,0.14); color: var(--sage);  border: 1px solid rgba(122,158,126,0.3); }
    .stock-chip.low-stock  { background: rgba(201,145,42,0.14);  color: var(--gold);  border: 1px solid rgba(201,145,42,0.3); }
    .stock-chip.out-stock  { background: rgba(192,57,43,0.12);   color: var(--danger); border: 1px solid rgba(192,57,43,0.25); }

    /* Divider */
    .divider { height: 1px; background: var(--mist); }

    /* Quantity row */
    .qty-row {
        display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
    }
    .qty-label {
        font-weight: 700; color: var(--espresso); font-size: 0.95rem; white-space: nowrap;
    }
    .qty-control {
        display: flex; align-items: center;
        border: 2px solid var(--mist); border-radius: 50px; overflow: hidden;
        background: var(--ivory);
    }
    .qty-control button {
        background: transparent; border: none;
        padding: 10px 18px; cursor: pointer;
        font-size: 1rem; color: var(--mahogany); font-weight: 700;
        transition: var(--transition);
    }
    .qty-control button:hover { background: var(--mist); }
    .qty-control input {
        border: none; width: 60px; text-align: center;
        font-weight: 700; font-size: 1rem; color: var(--espresso);
        background: transparent; font-family: 'DM Sans', sans-serif;
        outline: none;
    }
    .qty-max { font-size: 0.8rem; color: var(--caramel); }

    /* Action buttons */
    .action-row {
        display: flex; flex-direction: column; gap: 12px;
    }

    .btn-add-cart {
        width: 100%; padding: 14px;
        background: transparent; border: 2.5px solid var(--espresso);
        color: var(--espresso); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.95rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .btn-add-cart:hover:not(:disabled) {
        background: var(--espresso); color: var(--honey);
        box-shadow: var(--shadow-sm); transform: translateY(-2px);
    }
    .btn-add-cart:disabled { opacity: 0.4; cursor: not-allowed; }

    .btn-buy-now {
        width: 100%; padding: 15px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.95rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
        box-shadow: 0 6px 20px rgba(201,145,42,0.3);
    }
    .btn-buy-now:hover:not(:disabled) {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(44,26,14,0.25);
    }
    .btn-buy-now:disabled { opacity: 0.4; cursor: not-allowed; background: var(--mist); color: var(--caramel); box-shadow: none; }

    .btn-continue {
        width: 100%; padding: 12px;
        background: transparent; border: 2px solid var(--mist);
        color: var(--caramel); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.88rem;
        cursor: pointer; transition: var(--transition); text-decoration: none;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-continue:hover { background: var(--mist); color: var(--espresso); }

    /* ===================== QUANTITY MODAL ===================== */
    .quantity-modal {
        display: none; position: fixed; z-index: 2000; inset: 0;
        background: rgba(44,26,14,0.65); backdrop-filter: blur(6px);
        animation: fadeIn 0.3s ease;
    }
    .quantity-modal.active { display: flex; align-items: center; justify-content: center; }

    @keyframes fadeIn  { from { opacity:0; }              to { opacity:1; } }
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
    .modal-product-info {
        background: var(--cream); border-left: 4px solid var(--gold);
        padding: 14px 18px; border-radius: var(--radius-sm);
        color: var(--mahogany); font-weight: 600; margin-bottom: 24px;
        font-family: 'DM Sans', sans-serif; font-size: 0.95rem;
    }
    .quantity-selector label {
        font-weight: 700; color: var(--espresso); display: block; margin-bottom: 14px;
    }
    .quantity-input-group { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }

    .qty-btn {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; width: 44px; height: 44px;
        border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition); box-shadow: var(--shadow-sm);
    }
    .qty-btn:hover { background: var(--gold); color: var(--white); transform: scale(1.1); }
    .qty-btn:active { transform: scale(0.95); }

    #quantityInput {
        width: 80px; height: 44px; text-align: center; font-size: 1.2rem;
        font-weight: 700; border: 2px solid var(--mist); border-radius: var(--radius-sm);
        background: var(--ivory); color: var(--espresso);
        font-family: 'Playfair Display', serif; outline: none;
    }
    #maxQuantityInfo { font-size: 0.82rem; color: var(--caramel); margin-top: 6px; display: block; }

    .quantity-modal-footer {
        padding: 20px 28px; border-top: 1px solid var(--mist);
        display: flex; gap: 12px; background: var(--cream);
    }
    .btn-cancel {
        background: var(--mist); border: 2px solid transparent; color: var(--mahogany);
        padding: 12px 24px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; cursor: pointer; transition: var(--transition);
    }
    .btn-cancel:hover { background: var(--cream); }
    .btn-confirm {
        flex: 1; background: linear-gradient(135deg, var(--gold), var(--honey));
        border: none; color: var(--espresso); padding: 12px 24px; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; cursor: pointer;
        transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }
    .btn-confirm:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); }

    /* ===================== TOAST ===================== */
    .toast-container { position: fixed; bottom: 30px; left: 30px; z-index: 2000; }
    .custom-toast {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--cream); padding: 14px 20px; border-radius: var(--radius-sm);
        box-shadow: var(--shadow-lg); min-width: 260px; max-width: 320px;
        border-left: 4px solid var(--gold); font-size: 0.92rem;
        display: none; animation: slideInToast 0.35s ease;
    }
    .custom-toast.show { display: block; }
    @keyframes slideInToast {
        from { transform: translateX(-40px); opacity: 0; }
        to   { transform: translateX(0);      opacity: 1; }
    }

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
    .footer-section p { font-size: 0.88rem; line-height: 1.8; margin-bottom: 10px; }
    .footer-section p i { color: var(--honey); margin-right: 8px; }
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

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 860px) {
        .product-grid { grid-template-columns: 1fr; }
        .product-image-pane { min-height: 320px; }
        .product-info-pane { padding: 30px 24px 28px; border-left: none; border-top: 1px solid rgba(201,145,42,0.1); }
        .image-wrap { padding: 36px 28px; }
    }

    @media (max-width: 600px) {
        .navbar { padding: 0 16px; }
        .nav-links a:not(.active) { display: none; }
        .main-content { padding: 0 14px; margin: 24px auto 40px; }
        .page-hero { padding: 30px 18px 42px; }
        .action-row { gap: 10px; }
        .slide-cart { width: 92vw; right: -92vw; }
    }
    
    /* Reviews custom styling */
    .star-rating-select i {
        transition: var(--transition);
    }
    .star-rating-select i.active, .star-rating-select i:hover {
        color: var(--gold) !important;
    }
    .reviews-list::-webkit-scrollbar {
        width: 6px;
    }
    .reviews-list::-webkit-scrollbar-track {
        background: transparent;
    }
    .reviews-list::-webkit-scrollbar-thumb {
        background-color: var(--mist);
        border-radius: 10px;
    }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none; display:flex; align-items:center;">
           <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php" class="active">Shop</a>
        <a href="about.php">About</a>
        <?php
        if (isset($_SESSION['user_id'])) {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') echo '<a href="admin.php">Admin</a>';
            $check_col = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
            if (!$check_col || $check_col->num_rows === 0) $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");

            $uid = $_SESSION['user_id'];
            $st2 = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
            $st2->bind_param("i", $uid);
            $st2->execute();
            $st2->bind_result($db_uname, $db_pic);
            $st2->fetch();
            $st2->close();

            $db_uname = $db_uname ?? 'User';
            if (!$db_pic) $db_pic = 'images/profile.jpg';
            $role    = htmlspecialchars($_SESSION['role'] ?? 'customer');
            $balance = number_format($_SESSION['balance'] ?? 0, 2);
            $pic     = htmlspecialchars($db_pic);
            $uname   = htmlspecialchars($db_uname);

            echo '
            <div class="profile-dropdown">
                <img src="'.$pic.'" alt="Profile" class="profile-pic" onerror="this.src=\'images/profile.jpg\'">
                <div class="dropdown-content">
                    <div class="dropdown-profile-info">
                        <div class="dropdown-profile-name">'.$uname.'</div>
                        <div class="dropdown-profile-role">'.$role.'</div>
                        <div class="dropdown-profile-balance">₱'.$balance.'</div>
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

<!-- ===================== HERO / BREADCRUMB ===================== -->
<section class="page-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="page-hero-inner">
        <nav class="breadcrumb-nav">
            <a href="main.php"><i class="fas fa-home"></i> Home</a>
            <span class="sep"><i class="fas fa-chevron-right"></i></span>
            <a href="shop.php">Shop</a>
            <span class="sep"><i class="fas fa-chevron-right"></i></span>
            <span class="current"><?= htmlspecialchars($product['name']) ?></span>
        </nav>
        <h1 class="page-hero-title"><em><?= htmlspecialchars($product['name']) ?></em></h1>
        <a href="shop.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Shop</a>
    </div>
</section>

<!-- ===================== MAIN ===================== -->
<main class="main-content">
    <div class="product-detail">
        <div class="product-grid">

            <!-- ---- IMAGE PANE ---- -->
            <div class="product-image-pane">
                <div class="image-wrap">
                    <button class="fav-btn" onclick="toggleFavorite(<?= $product_id ?>, this)" title="Save to favorites">
                        <i class="far fa-heart"></i>
                    </button>
                    <?php if (!empty($product['image']) && file_exists("uploads/".$product['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div class="no-image-placeholder">
                            <i class="fas fa-image"></i>
                            <span>No Image Available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($product['description'])): ?>
                <div class="desc-panel">
                    <h6><i class="fas fa-align-left" style="margin-right:6px;"></i>Description</h6>
                    <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- ---- INFO PANE ---- -->
            <div class="product-info-pane">

                <span class="category-chip">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($product['category']) ?>
                </span>

                <h1 class="product-name"><?= htmlspecialchars($product['name']) ?></h1>

                <!-- Price -->
                <div class="price-block">
                    <span class="price-label">Price</span>
                    <?php if (!empty($product['sale_price']) && $product['sale_price'] > 0 && $product['sale_price'] < $product['price']): ?>
                        <span class="price-value sale">₱<?= number_format($product['sale_price'], 2) ?></span>
                        <span class="original-price" style="text-decoration: line-through; font-size: 1.1rem; color: var(--caramel); margin-left: 10px; opacity: 0.75;">₱<?= number_format($product['price'], 2) ?></span>
                    <?php else: ?>
                        <span class="price-value">₱<?= number_format($product['price'], 2) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Stock -->
                <div class="stock-row">
                    <?php
                    $stock = $product['stock'];
                    if ($stock <= 0):
                    ?>
                        <span class="stock-chip out-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
                    <?php elseif ($stock <= 10): ?>
                        <span class="stock-chip low-stock"><i class="fas fa-fire"></i> Only <?= $stock ?> left!</span>
                    <?php else: ?>
                        <span class="stock-chip in-stock"><i class="fas fa-check-circle"></i> <?= $stock ?> in stock</span>
                    <?php endif; ?>
                </div>

                <div class="divider"></div>

                <!-- Quantity -->
                <?php if ($stock > 0): ?>
                <div class="qty-row">
                    <span class="qty-label">Quantity:</span>
                    <div class="qty-control">
                        <button type="button" onclick="decreaseQty()">−</button>
                        <input type="number" id="quantity" value="1" min="1" max="<?= $stock ?>" readonly>
                        <button type="button" onclick="increaseQty()">+</button>
                    </div>
                    <span class="qty-max">Max <?= $stock ?></span>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="action-row">
                    <?php if ($stock > 0): ?>
                        <button class="btn-add-cart" onclick="addToCart()">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button class="btn-buy-now" onclick="buyNow()">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled>
                            <i class="fas fa-ban"></i> Out of Stock
                        </button>
                        <button class="btn-buy-now" disabled>
                            <i class="fas fa-ban"></i> Unavailable
                        </button>
                    <?php endif; ?>
                    <a href="shop.php" class="btn-continue">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>

            </div><!-- /info pane -->
        </div><!-- /product-grid -->
    </div><!-- /product-detail -->
    
    <!-- ===================== REVIEWS SECTION ===================== -->
    <div class="reviews-section" style="margin-top: 50px; background: var(--ivory); border-radius: var(--radius); padding: 40px; border: 1px solid rgba(201,145,42,0.1); box-shadow: var(--shadow-md);">
        <h3 style="font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 700; color: var(--espresso); margin-bottom: 25px;">
            Customer <span style="font-style: italic; color: var(--caramel);">Reviews</span>
        </h3>
        
        <div class="row g-4">
            <!-- Left Column: Submit a Review -->
            <div class="col-md-5">
                <form method="POST" action="product.php?id=<?= $product_id ?>" style="background: var(--cream); padding: 25px; border-radius: var(--radius-sm); border: 1px solid var(--mist);">
                    <input type="hidden" name="submit_review" value="1">
                    <h4 style="font-size: 1.15rem; font-weight: 700; color: var(--mahogany); margin-bottom: 18px;"><i class="fas fa-edit"></i> Write a Review</h4>
                    
                    <!-- Star Selection -->
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: var(--espresso); display: block;">Your Rating</label>
                        <div class="star-rating-select" style="display: flex; gap: 8px; font-size: 1.5rem; color: var(--mist); cursor: pointer;">
                            <i class="fas fa-star" data-rating="1"></i>
                            <i class="fas fa-star" data-rating="2"></i>
                            <i class="fas fa-star" data-rating="3"></i>
                            <i class="fas fa-star" data-rating="4"></i>
                            <i class="fas fa-star" data-rating="5"></i>
                        </div>
                        <input type="hidden" name="rating" id="reviewRatingInput" value="5">
                    </div>
                    
                    <!-- Comment Textarea -->
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: var(--espresso);">Your Review</label>
                        <textarea name="comment" class="form-control" rows="4" placeholder="What did your cat think about this product? Is it delicious? Highly recommended?" required style="border-radius: 12px; border: 2px solid var(--mist); background: var(--ivory); font-size: 0.92rem; outline: none;"></textarea>
                    </div>
                    
                    <!-- Anonymous Checkbox -->
                    <div class="form-check mb-3" style="display: flex; align-items: center; gap: 8px;">
                        <input class="form-check-input" type="checkbox" name="post_anonymous" id="postAnonymous" value="1" style="width: 16px; height: 16px; cursor: pointer;">
                        <label class="form-check-label" for="postAnonymous" style="font-size: 0.88rem; color: var(--espresso); cursor: pointer; user-select: none;">
                            Post review anonymously
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-confirm" style="width: 100%; border-radius: 50px; padding: 12px; font-weight: 600; border: none; background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); cursor: pointer; transition: var(--transition);">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </form>
            </div>
            
            <!-- Right Column: Reviews List -->
            <div class="col-md-7">
                <div class="reviews-list" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <?php if (empty($reviews)): ?>
                        <div style="text-align: center; padding: 40px 20px; color: var(--caramel);">
                            <i class="far fa-comments" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.6;"></i>
                            <h5 style="margin: 0; font-weight: 600;">No reviews yet</h5>
                            <p style="font-size: 0.88rem; margin-top: 5px; opacity: 0.8;">Be the first to share your experience with this product!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="review-item" style="background: var(--ivory); border-bottom: 1px solid var(--mist); padding: 18px 0; display: flex; flex-direction: column; gap: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: var(--mahogany); font-size: 0.95rem;"><?= htmlspecialchars($rev['username']) ?></strong>
                                    <span style="font-size: 0.78rem; color: var(--caramel);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <div style="color: var(--gold); font-size: 0.85rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= $rev['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <p style="font-size: 0.9rem; color: var(--espresso); margin: 0; line-height: 1.5;"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

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
            <p><i class="fas fa-map-marker-alt"></i> 123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone"></i> +1 234 567 8900</p>
            <p><i class="fas fa-envelope"></i> meow@pawganic.com</p>
            <p><i class="fas fa-clock"></i> Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
    </div>
</footer>

<!-- ===================== TOAST ===================== -->
<div class="toast-container">
    <div id="toastMessage" class="custom-toast"></div>
</div>

<!-- ===================== QUANTITY MODAL ===================== -->
<div id="quantityModal" class="quantity-modal">
    <div class="quantity-modal-content">
        <div class="quantity-modal-header">
            <h2>Select Quantity</h2>
            <button class="quantity-modal-close" onclick="closeQuantityModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="quantity-modal-body">
            <div class="modal-product-info" id="productInfo"></div>
            <div class="quantity-selector">
                <label>How many would you like?</label>
                <div class="quantity-input-group">
                    <button type="button" class="qty-btn" onclick="decreaseModalQuantity()"><i class="fas fa-minus"></i></button>
                    <input type="number" id="quantityInput" min="1" value="1" readonly>
                    <button type="button" class="qty-btn" onclick="increaseModalQuantity()"><i class="fas fa-plus"></i></button>
                </div>
                <small id="maxQuantityInfo"></small>
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

<script>
const productId = <?= $product_id ?>;
const maxStock  = <?= $product['stock'] ?>;
let currentMaxStock = maxStock;

/* ===================== QTY CONTROLS (main) ===================== */
function decreaseQty() {
    const q = document.getElementById('quantity');
    if (parseInt(q.value) > 1) q.value = parseInt(q.value) - 1;
}
function increaseQty() {
    const q = document.getElementById('quantity');
    if (parseInt(q.value) < maxStock) q.value = parseInt(q.value) + 1;
}

/* ===================== TOAST ===================== */
function showToast(message, type = 'success') {
    const t = document.getElementById('toastMessage');
    t.textContent = message;
    t.classList.add('show');
    t.style.borderLeftColor = type === 'success' ? 'var(--sage)' : 'var(--danger)';
    setTimeout(() => t.classList.remove('show'), 3200);
}

/* ===================== ADD TO CART ===================== */
function addToCart() {
    const quantity = parseInt(document.getElementById('quantity').value);
    const btn = event.target.closest('button');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding…';

    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=add&product_id=' + productId + '&quantity=' + quantity
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.success ? '✓ Added to cart!' : (data.message || 'Error adding to cart'), data.success ? 'success' : 'danger');
        if (data.success) {
            document.getElementById('quantity').value = 1;
            updateCartDisplay();
        }
    })
    .catch(() => showToast('Error adding to cart', 'danger'))
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

/* ===================== BUY NOW ===================== */
function buyNow() {
    const name = '<?= addslashes(htmlspecialchars($product['name'])) ?>';
    openQuantityModal(productId, name, maxStock);
}

/* ===================== QUANTITY MODAL ===================== */
function openQuantityModal(pid, name, stock) {
    currentMaxStock = stock;
    document.getElementById('productInfo').textContent      = '🛒 ' + name;
    document.getElementById('quantityInput').value          = '1';
    document.getElementById('quantityInput').max            = stock;
    document.getElementById('maxQuantityInfo').textContent  = stock + ' units available';
    document.getElementById('productIdInput').value         = pid;
    document.getElementById('quantityHiddenInput').value    = '1';
    document.getElementById('quantityModal').classList.add('active');
}
function closeQuantityModal() {
    document.getElementById('quantityModal').classList.remove('active');
}
function increaseModalQuantity() {
    const inp = document.getElementById('quantityInput');
    let v = parseInt(inp.value) || 1;
    if (v < currentMaxStock) { inp.value = ++v; document.getElementById('quantityHiddenInput').value = v; }
}
function decreaseModalQuantity() {
    const inp = document.getElementById('quantityInput');
    let v = parseInt(inp.value) || 1;
    if (v > 1) { inp.value = --v; document.getElementById('quantityHiddenInput').value = v; }
}

/* ===================== FAVORITES ===================== */
function toggleFavorite(pid, btn) {
    const action = btn.classList.contains('active') ? 'remove' : 'add';
    fetch('favorites_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=' + action + '&product_id=' + pid
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.is_favorite) {
                btn.classList.add('active');
                icon.classList.replace('far', 'fas');
                showToast('❤️ Added to favorites');
            } else {
                btn.classList.remove('active');
                icon.classList.replace('fas', 'far');
                showToast('Removed from favorites');
            }
        }
    });
}

function checkFavoriteStatus(pid) {
    fetch('favorites_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check&product_id=' + pid
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.is_favorite) {
            document.querySelectorAll('.fav-btn, .product-favorite-btn').forEach(btn => {
                btn.classList.add('active');
                btn.querySelector('i').classList.replace('far', 'fas');
            });
        }
    });
}

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

/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function() {
    const pd = document.querySelector('.profile-dropdown');
    if (pd) {
        pd.querySelector('.profile-pic').addEventListener('click', function(e) {
            e.stopPropagation();
            pd.classList.toggle('open');
        });
        document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
    }

    // Close modal on outside click
    const modal = document.getElementById('quantityModal');
    window.addEventListener('click', e => { if (e.target === modal) closeQuantityModal(); });

    checkFavoriteStatus(productId);
    updateCartDisplay();

    // Save to recently viewed
    const viewedProduct = {
        id: <?= intval($product['id']) ?>,
        name: <?= json_encode($product['name']) ?>,
        price: <?= floatval($product['price']) ?>,
        sale_price: <?= isset($product['sale_price']) ? floatval($product['sale_price']) : 'null' ?>,
        image: <?= json_encode($product['image']) ?>,
        badge: <?= json_encode($product['badge']) ?>,
        rating: <?= isset($product['rating']) ? floatval($product['rating']) : '5.00' ?>,
        reviews_count: <?= isset($product['reviews_count']) ? intval($product['reviews_count']) : '0' ?>,
        category: <?= json_encode($product['category']) ?>
    };
    try {
        let list = JSON.parse(localStorage.getItem('recently_viewed')) || [];
        list = list.filter(p => p.id !== viewedProduct.id);
        list.unshift(viewedProduct);
        list = list.slice(0, 10);
        localStorage.setItem('recently_viewed', JSON.stringify(list));
    } catch (e) {
        console.error("Failed to update recently viewed: ", e);
    }

    // Star Rating Selection
    const stars = document.querySelectorAll('.star-rating-select i');
    const ratingInput = document.getElementById('reviewRatingInput');
    
    if (stars.length && ratingInput) {
        function highlightStars(rating) {
            stars.forEach((star, idx) => {
                if (idx < rating) {
                    star.style.color = 'var(--gold)';
                } else {
                    star.style.color = 'var(--mist)';
                }
            });
        }

        highlightStars(5);

        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                highlightStars(rating);
            });
            
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.dataset.rating);
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating);
            });
        });
    }

    <?php if (isset($_GET['review_submitted'])): ?>
        showToast('⭐ Thank you! Your review has been submitted.');
    <?php endif; ?>
});
</script>
</body>
</html>