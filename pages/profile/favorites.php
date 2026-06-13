<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=favorites.php");
    exit;
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $product_id = intval($_POST['product_id']);
    $st = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $st->bind_param("ii", $user_id, $product_id);
    echo json_encode(['success' => (bool)$st->execute()]);
    exit;
}

$stmt = $conn->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $profile_pic);
$stmt->fetch();
$stmt->close();

$username         = $username    ?? 'User';
$balance          = $_SESSION['balance'] ?? 0;
$nav_role         = $_SESSION['role'] ?? 'customer';
$profile_pic      = $profile_pic ?: 'images/profile.jpg';
$profile_pic_safe = htmlspecialchars($profile_pic);

$fq = $conn->prepare("SELECT p.* FROM products p
                      INNER JOIN favorites f ON p.id = f.product_id
                      WHERE f.user_id = ? ORDER BY f.created_at DESC");
$fq->bind_param("i", $user_id);
$fq->execute();
$favorites   = $fq->get_result()->fetch_all(MYSQLI_ASSOC);
$fav_count   = count($favorites);
$total_value = array_sum(array_column($favorites, 'price'));
$avg_price   = $fav_count > 0 ? $total_value / $fav_count : 0;
$max_price   = $fav_count > 0 ? max(array_column($favorites, 'price')) : 0;
$min_price   = $fav_count > 0 ? min(array_column($favorites, 'price')) : 0;

// Category breakdown
$cats = [];
foreach ($favorites as $p) {
    $c = $p['category'] ?? 'Other';
    $cats[$c] = ($cats[$c] ?? 0) + 1;
}
arsort($cats);
$top_cat = count($cats) ? array_key_first($cats) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>My Favorites — Pawganic Supplies</title>
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
    text-decoration: none;
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
.dropdown-content a.active-link { background: rgba(201,145,42,0.08); color: var(--mahogany); font-weight: 600; }
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
.hero-title em { font-style: italic; color: var(--honey); }

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
.hero-stat { text-align: center; }
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
.toast-container { position: fixed; bottom: 30px; left: 30px; z-index: 2000; }

.custom-toast {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    border-radius: var(--radius-sm);
    font-size: 0.95rem; padding: 14px 20px;
    box-shadow: var(--shadow-lg);
    min-width: 260px; max-width: 320px;
    color: var(--cream); border-left: 4px solid var(--gold);
}
.custom-toast .toast-body { padding: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
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
.cart-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--mist); padding-bottom: 18px; margin-bottom: 24px; }
.cart-header h4 { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: var(--espresso); display: flex; align-items: center; gap: 10px; }
.cart-header h4 i { color: var(--gold); }
.close-cart-btn { background: var(--mist); border: none; color: var(--mahogany); width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: var(--transition); }
.close-cart-btn:hover { background: var(--gold); color: var(--white); }
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

/* ===================== FILTER BAR ===================== */
.filter-section { max-width: 1200px; margin: 40px auto 0; padding: 0 24px; }

.filter-bar {
    background: var(--ivory);
    border-radius: var(--radius);
    padding: 22px 28px;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(201,145,42,0.12);
    display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
}
.filter-bar .search-wrap { flex: 1; min-width: 200px; position: relative; }
.filter-bar .search-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--caramel); font-size: 0.9rem; }

.filter-bar input, .filter-bar select {
    width: 100%; padding: 12px 16px 12px 42px;
    border: 2px solid var(--mist); border-radius: 50px;
    background: var(--cream); color: var(--espresso);
    font-family: 'DM Sans', sans-serif; font-size: 0.92rem; font-weight: 500;
    transition: var(--transition); outline: none;
}
.filter-bar select { padding-left: 16px; }
.filter-bar input:focus, .filter-bar select:focus { border-color: var(--gold); background: var(--ivory); box-shadow: 0 0 0 4px rgba(201,145,42,0.1); }
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

.view-toggle { display: flex; border: 2px solid var(--mist); border-radius: 50px; overflow: hidden; background: var(--cream); }
.vbtn { padding: 9px 14px; background: transparent; border: none; cursor: pointer; color: var(--caramel); font-size: 0.9rem; transition: var(--transition); }
.vbtn.active { background: var(--espresso); color: var(--honey); border-radius: 50px; }
.vbtn:hover:not(.active) { background: var(--mist); color: var(--espresso); }

.result-meta { max-width: 1200px; margin: 18px auto 0; padding: 0 24px; display: flex; align-items: center; gap: 10px; }
.result-count { font-size: 0.85rem; color: var(--caramel); font-weight: 500; }
.result-divider { height: 1px; flex: 1; background: var(--mist); }

/* ===================== STATS DASHBOARD ===================== */
.stats-section { max-width: 1200px; margin: 32px auto 0; padding: 0 24px; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 12px;
}

.stat-card {
    background: var(--ivory);
    border-radius: var(--radius);
    padding: 22px 20px;
    border: 1px solid rgba(201,145,42,0.1);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(to right, var(--gold), var(--honey));
}
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(201,145,42,0.25); }

.stat-card-icon {
    width: 40px; height: 40px; border-radius: 50%;
    background: rgba(201,145,42,0.1);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 12px;
}
.stat-card-icon i { color: var(--gold); font-size: 1rem; }
.stat-card-label { font-size: 0.72rem; color: var(--caramel); text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; margin-bottom: 6px; }
.stat-card-value { font-family: 'Playfair Display', serif; font-size: 1.7rem; font-weight: 700; color: var(--espresso); line-height: 1; }
.stat-card-value.gold { color: var(--gold); }
.stat-card-sub { font-size: 0.78rem; color: var(--caramel); margin-top: 4px; opacity: 0.7; }

/* Category chips row */
.cat-breakdown { max-width: 1200px; margin: 16px auto 0; padding: 0 24px; }
.cat-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.cat-label { font-size: 0.8rem; color: var(--caramel); font-weight: 600; white-space: nowrap; }
.cat-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--ivory); border: 1.5px solid rgba(201,145,42,0.2);
    color: var(--mahogany); padding: 5px 14px; border-radius: 50px;
    font-size: 0.78rem; font-weight: 600; cursor: pointer;
    transition: var(--transition);
}
.cat-chip:hover, .cat-chip.active { background: var(--gold); color: var(--white); border-color: var(--gold); }
.cat-chip .count { background: rgba(0,0,0,0.12); border-radius: 50px; padding: 1px 6px; font-size: 0.7rem; }

/* ===================== PRODUCT GRID ===================== */
.products-section { max-width: 1200px; margin: 28px auto 60px; padding: 0 24px; }

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 28px;
}
.products-grid.list-view {
    grid-template-columns: 1fr;
    gap: 16px;
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
    animation: cardIn 0.5s ease both;
}
@keyframes cardIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

.product-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); border-color: rgba(201,145,42,0.3); }

.products-grid.list-view .product-card { flex-direction: row; border-radius: var(--radius-sm); }
.products-grid.list-view .card-image { width: 200px; min-width: 200px; height: auto; border-radius: 0; }
.products-grid.list-view .card-body { padding: 20px; }
.products-grid.list-view .price-ribbon { position: static; margin-bottom: 8px; display: inline-block; }
.products-grid.list-view .card-footer { border-top: none; padding-top: 0; flex-direction: row; flex-wrap: wrap; }
.products-grid.list-view:hover .product-card { transform: translateX(6px) translateY(0); }

.stock-badge {
    position: absolute; top: 14px; left: 14px; z-index: 5;
    padding: 4px 12px; border-radius: 50px; font-size: 0.75rem;
    font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
}
.stock-badge.in-stock { background: rgba(122,158,126,0.15); color: var(--sage); border: 1px solid rgba(122,158,126,0.3); }
.stock-badge.low-stock { background: rgba(201,145,42,0.15); color: var(--gold); border: 1px solid rgba(201,145,42,0.3); }
.stock-badge.out-stock { background: rgba(192,57,43,0.12); color: var(--danger); border: 1px solid rgba(192,57,43,0.25); }

.favorite-btn {
    position: absolute; top: 12px; right: 12px; z-index: 10;
    width: 40px; height: 40px; border: none; border-radius: 50%;
    background: rgba(253,248,240,0.9); backdrop-filter: blur(6px);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 12px rgba(44,26,14,0.12); transition: var(--transition);
}
.favorite-btn:hover { transform: scale(1.12); background: #e74c3c; }
.favorite-btn i { color: #e74c3c; font-size: 1.1rem; transition: var(--transition); }
.favorite-btn:hover i { color: var(--white); }

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

.no-image-placeholder { display: flex; flex-direction: column; align-items: center; gap: 10px; color: var(--mist); }
.no-image-placeholder i { font-size: 2.5rem; }
.no-image-placeholder span { font-size: 0.85rem; }

.card-body { padding: 22px 22px 10px; flex: 1; display: flex; flex-direction: column; gap: 8px; position: relative; }

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

.card-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 700; color: var(--espresso); line-height: 1.3; margin: 0; }
.card-desc { font-size: 0.84rem; color: var(--caramel); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.card-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.7rem; font-weight: 700; padding: 3px 9px;
    border-radius: 50px; letter-spacing: 0.3px;
}
.chip-org { background: rgba(22,163,74,.1); color: #166534; }
.chip-vet { background: rgba(8,145,178,.1); color: #075985; }
.chip-pop { background: rgba(201,145,42,0.12); color: #7a4f08; }

.card-stars { display: flex; align-items: center; gap: 2px; font-size: 0.78rem; color: var(--gold); }
.card-stars em { color: var(--caramel); font-style: normal; font-size: 0.75rem; margin-left: 4px; }

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
    text-decoration: none;
}
.btn-add-cart:hover { background: var(--espresso); color: var(--honey); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
.btn-add-cart:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

.btn-view-product {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, var(--gold), var(--honey));
    color: var(--espresso); border: none; border-radius: 50px;
    font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.9rem;
    cursor: pointer; transition: var(--transition);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    text-decoration: none;
}
.btn-view-product:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(44,26,14,0.25); }

/* ===================== EMPTY STATE ===================== */
.empty-state {
    text-align: center; padding: 100px 24px;
    background: rgba(253,248,240,0.7); backdrop-filter: blur(16px);
    border-radius: 24px; border: 2px dashed rgba(201,145,42,0.2);
}
.empty-icon { font-size: 5rem; display: block; margin-bottom: 8px; animation: floatIcon 3s ease-in-out infinite; }
@keyframes floatIcon { 0%,100% { transform: translateY(0) rotate(-5deg); } 50% { transform: translateY(-14px) rotate(5deg); } }
.empty-state h3 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--mahogany); margin-bottom: 12px; }
.empty-state p { color: var(--caramel); font-size: 0.95rem; max-width: 360px; margin: 0 auto 34px; font-weight: 300; }
.btn-shop-now {
    display: inline-flex; align-items: center; gap: 10px;
    background: var(--espresso); color: var(--honey);
    padding: 14px 36px; border-radius: 50px; text-decoration: none;
    font-weight: 700; font-size: 0.95rem; transition: var(--transition);
    box-shadow: var(--shadow-md);
}
.btn-shop-now:hover { background: var(--gold); color: var(--white); transform: translateY(-4px); box-shadow: 0 14px 36px rgba(44,26,14,0.28); }

/* ===================== CONFIRM MODAL ===================== */
.overlay {
    position: fixed; inset: 0; background: rgba(44,26,14,0.65);
    backdrop-filter: blur(8px); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; visibility: hidden; transition: all 0.28s;
}
.overlay.show { opacity: 1; visibility: visible; }
.modal {
    background: var(--ivory); border-radius: 24px; padding: 38px 42px;
    text-align: center; max-width: 390px; width: 92%;
    box-shadow: var(--shadow-lg);
    transform: scale(0.9) translateY(16px);
    transition: all 0.34s cubic-bezier(0.22,1,.36,1);
    border: 1px solid rgba(201,145,42,0.2);
}
.overlay.show .modal { transform: scale(1) translateY(0); }
.modal-ico { width: 66px; height: 66px; background: rgba(192,57,43,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
.modal-ico i { color: var(--danger); font-size: 26px; }
.modal h3 { font-family: 'Playfair Display', serif; font-size: 1.45rem; color: var(--espresso); margin-bottom: 9px; }
.modal p { color: var(--caramel); font-size: 0.9rem; font-weight: 300; margin-bottom: 28px; line-height: 1.6; }
.modal-btns { display: flex; gap: 12px; }
.m-keep { flex: 1; padding: 13px; border: 2px solid var(--mist); border-radius: 50px; background: transparent; color: var(--mahogany); font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: var(--transition); }
.m-keep:hover { background: var(--mist); }
.m-del { flex: 1; padding: 13px; border: none; border-radius: 50px; background: var(--danger); color: #fff; font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: var(--transition); }
.m-del:hover { background: #a93226; transform: translateY(-1px); }

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

/* ===================== SECTION HEADER ===================== */
.section-header {
    display: flex; align-items: center; gap: 16px;
    margin: 32px 0 24px;
}
.section-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; font-weight: 700; color: var(--espresso); }
.section-title em { font-style: italic; color: var(--gold); }
.section-line { flex: 1; height: 1px; background: linear-gradient(to right, var(--mist), transparent); }
.section-link { font-size: 0.85rem; font-weight: 700; color: var(--gold); text-decoration: none; white-space: nowrap; transition: var(--transition); }
.section-link:hover { color: var(--caramel); }

/* ===================== NO RESULTS ===================== */
.no-results-msg { text-align: center; padding: 60px 24px; display: none; }
.no-results-msg.show { display: block; }
.no-results-msg i { font-size: 3rem; color: var(--mist); margin-bottom: 16px; display: block; }
.no-results-msg h3 { font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--mahogany); margin-bottom: 8px; }
.no-results-msg p { color: var(--caramel); font-size: 0.9rem; }

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
.footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 40px; margin-bottom: 40px; max-width: 1200px; margin-left: auto; margin-right: auto; }
.footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--honey); margin-bottom: 20px; padding-bottom: 12px; position: relative; }
.footer-section h3::after { content: ''; position: absolute; bottom: 0; left: 0; width: 26px; height: 2px; background: var(--gold); border-radius: 2px; }
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
.footer-links a { color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.88rem; transition: var(--transition); padding-left: 0; }
.footer-links a:hover { color: var(--honey); padding-left: 6px; }
.footer-links li { list-style: none; }
.footer-contact li { display: flex; align-items: flex-start; gap: 9px; margin-bottom: 11px; font-size: 0.85rem; color: rgba(255,255,255,0.55); list-style: none; }
.footer-contact i { color: var(--honey); width: 14px; margin-top: 2px; flex-shrink: 0; }
.footer-tags { display: flex; gap: 7px; }
.footer-tag { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; padding: 4px 12px; border: 1px solid rgba(255,255,255,0.14); border-radius: 50px; color: rgba(255,255,255,0.35); }
.copyright { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 0.82rem; color: rgba(255,255,255,0.35); }

/* ===================== STAGGER ANIMATIONS ===================== */
.product-card:nth-child(1) { animation-delay: 0.05s; }
.product-card:nth-child(2) { animation-delay: 0.10s; }
.product-card:nth-child(3) { animation-delay: 0.15s; }
.product-card:nth-child(4) { animation-delay: 0.20s; }
.product-card:nth-child(5) { animation-delay: 0.25s; }
.product-card:nth-child(6) { animation-delay: 0.30s; }
.product-card:nth-child(7) { animation-delay: 0.35s; }
.product-card:nth-child(8) { animation-delay: 0.40s; }
.product-card:nth-child(9) { animation-delay: 0.45s; }
.product-card:nth-child(10) { animation-delay: 0.50s; }

/* ===================== RESPONSIVE ===================== */
@media (max-width: 768px) {
    .navbar { padding: 0 20px; }
    .nav-links a:not(.active) { display: none; }
    .hero-stats { display: none; }
    .hero-inner { flex-direction: column; gap: 24px; }
    .shop-hero { padding: 56px 24px 60px; }
    .hero-title { font-size: 2.4rem; }
    .products-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
    .products-grid.list-view .product-card { flex-direction: column; }
    .products-grid.list-view .card-image { width: 100%; min-width: unset; height: 220px; }
    .stats-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 480px) {
    .products-grid { grid-template-columns: 1fr; }
    .filter-bar { flex-direction: column; }
    .filter-bar .apply-btn { width: 100%; justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Logo" class="logo-img">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <div class="profile-dropdown" id="profileDropdown">
            <img src="<?= $profile_pic_safe ?>" alt="Profile" class="profile-pic" onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($username) ?></div>
                    <div class="dropdown-profile-role"><?= htmlspecialchars($nav_role) ?></div>
                    <div class="dropdown-profile-balance">₱<?= number_format($balance, 2) ?></div>
                </div>
                <a href="favorites.php" class="active-link"><i class="fas fa-heart"></i> My Favorites</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="purchase_history.php"><i class="fas fa-history"></i> Purchase History</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        <button onclick="toggleCart()" class="cart-btn">
            <i class="fas fa-shopping-cart"></i>
        </button>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast-container">
    <div id="toastMessage" class="toast text-white border-0 custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3200">
        <div class="toast-body">
            Done!
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
            <div class="hero-label"><i class="fas fa-heart"></i> PAWGANIC SUPPLIES</div>
            <h1 class="hero-title">My <em>Favorites</em><br>Collection</h1>
            <p class="hero-subtitle">Hand-picked treats and supplies your cat keeps asking for — saved in one cozy place.</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <div class="hero-stat-num" id="heroCount"><?= $fav_count ?>+</div>
                    <div class="hero-stat-label">Saved Items</div>
                </div>
                <?php if ($fav_count > 0): ?>
                <div class="hero-stat">
                    <div class="hero-stat-num">₱<?= number_format($total_value, 0) ?></div>
                    <div class="hero-stat-label">Total Value</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-num">★ 4.9</div>
                    <div class="hero-stat-label">Avg Rating</div>
                </div>
                <?php endif; ?>
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

<?php if ($fav_count > 0): ?>

<!-- ===================== STATS DASHBOARD ===================== -->
<div class="stats-section">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-heart"></i></div>
            <div class="stat-card-label">Saved Items</div>
            <div class="stat-card-value" id="statCount"><?= $fav_count ?></div>
            <div class="stat-card-sub">in your wishlist</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-card-label">Total Value</div>
            <div class="stat-card-value gold">₱<?= number_format($total_value, 2) ?></div>
            <div class="stat-card-sub">combined price</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-card-label">Average Price</div>
            <div class="stat-card-value gold">₱<?= number_format($avg_price, 2) ?></div>
            <div class="stat-card-sub">per item</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-crown"></i></div>
            <div class="stat-card-label">Top Pick</div>
            <div class="stat-card-value">₱<?= number_format($max_price, 2) ?></div>
            <div class="stat-card-sub">most premium</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-tag"></i></div>
            <div class="stat-card-label">Best Value</div>
            <div class="stat-card-value">₱<?= number_format($min_price, 2) ?></div>
            <div class="stat-card-sub">lowest priced</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon"><i class="fas fa-th-large"></i></div>
            <div class="stat-card-label">Top Category</div>
            <div class="stat-card-value" style="font-size:1.1rem; margin-top:4px;"><?= htmlspecialchars($top_cat) ?></div>
            <div class="stat-card-sub"><?= count($cats) ?> categor<?= count($cats) === 1 ? 'y' : 'ies' ?></div>
        </div>
    </div>
</div>

<!-- Category Filter Chips -->
<div class="cat-breakdown">
    <div class="cat-row">
        <span class="cat-label"><i class="fas fa-filter" style="margin-right:5px;color:var(--gold)"></i>Filter:</span>
        <span class="cat-chip active" data-cat="all" onclick="filterByCategory('all', this)">
            All <span class="count"><?= $fav_count ?></span>
        </span>
        <?php foreach ($cats as $cat => $count): ?>
        <span class="cat-chip" data-cat="<?= htmlspecialchars(strtolower($cat)) ?>" onclick="filterByCategory('<?= htmlspecialchars(addslashes(strtolower($cat))) ?>', this)">
            <?= htmlspecialchars($cat) ?> <span class="count"><?= $count ?></span>
        </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================== FILTER BAR ===================== -->
<div class="filter-section">
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search your saved items…" oninput="doFilter()">
        </div>
        <select id="sortSel" style="width:200px; padding:12px 16px; border-radius:50px; border:2px solid var(--mist); background:var(--cream); color:var(--espresso); font-family:'DM Sans',sans-serif; font-weight:500; font-size:0.92rem; outline:none; cursor:pointer;" onchange="doSort()">
            <option value="">Sort by…</option>
            <option value="name-asc">Name A–Z</option>
            <option value="name-desc">Name Z–A</option>
            <option value="price-asc">Price: Low → High</option>
            <option value="price-desc">Price: High → Low</option>
        </select>
        <div class="view-toggle">
            <button class="vbtn active" id="gridBtn" onclick="setView('grid')" title="Grid View"><i class="fas fa-th-large"></i></button>
            <button class="vbtn" id="listBtn" onclick="setView('list')" title="List View"><i class="fas fa-list"></i></button>
        </div>
        <button class="apply-btn" onclick="doFilter()"><i class="fas fa-sliders-h"></i> Apply</button>
    </div>
</div>

<div class="result-meta">
    <span class="result-count">Showing <b id="visCnt"><?= $fav_count ?></b> saved item<?= $fav_count !== 1 ? 's' : '' ?></span>
    <div class="result-divider"></div>
</div>

<!-- ===================== PRODUCT GRID ===================== -->
<div class="products-section">
    <div class="section-header">
        <h2 class="section-title">Your <em>Saved</em> Treats</h2>
        <div class="section-line"></div>
        <a href="shop.php" class="section-link"><i class="fas fa-plus"></i> Browse More</a>
    </div>

    <div class="products-grid" id="prodGrid">
        <?php foreach ($favorites as $i => $p):
            $in_stock  = ($p['stock'] ?? 0) > 0;
            $low_stock = $in_stock && ($p['stock'] ?? 0) <= 5;
            $img_path  = !empty($p['image']) && file_exists("uploads/".$p['image'])
                         ? "uploads/".htmlspecialchars($p['image'])
                         : null;
            $rating    = number_format($p['rating'] ?? 4.5, 1);
            $revs      = $p['review_count'] ?? rand(18, 160);
        ?>
        <div class="product-card"
             data-id="<?= $p['id'] ?>"
             data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
             data-price="<?= floatval($p['price']) ?>"
             data-cat="<?= strtolower(htmlspecialchars($p['category'] ?? '')) ?>"
             data-desc="<?= strtolower(htmlspecialchars($p['description'] ?? '')) ?>">

            <!-- Stock badge -->
            <?php if (!$in_stock): ?>
                <span class="stock-badge out-stock"><i class="fas fa-times-circle"></i> Out of Stock</span>
            <?php elseif ($low_stock): ?>
                <span class="stock-badge low-stock"><i class="fas fa-fire"></i> Only <?= $p['stock'] ?> left!</span>
            <?php else: ?>
                <span class="stock-badge in-stock"><i class="fas fa-check-circle"></i> In Stock</span>
            <?php endif; ?>

            <!-- Remove favorite -->
            <button class="favorite-btn"
                    onclick="event.stopPropagation(); openConfirm(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>');"
                    title="Remove from favorites">
                <i class="fas fa-heart"></i>
            </button>

            <!-- Image -->
            <div class="card-image" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
                <?php if ($img_path): ?>
                    <img loading="lazy" src="<?= $img_path ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <div class="no-image-placeholder">
                        <i class="fas fa-paw"></i>
                        <span>No Image</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="card-body" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
                <div class="price-ribbon">₱<?= number_format($p['price'], 2) ?></div>
                <span class="card-category"><i class="fas fa-tag"></i> <?= htmlspecialchars($p['category'] ?? '') ?></span>
                <h5 class="card-title"><?= htmlspecialchars($p['name']) ?></h5>
                <div class="card-stars">
                    <?php for ($s = 0; $s < 5; $s++): ?>
                        <i class="<?= $s < floor($rating) ? 'fas' : 'far' ?> fa-star"></i>
                    <?php endfor; ?>
                    <em><?= $rating ?> (<?= $revs ?>)</em>
                </div>
                <p class="card-desc"><?= htmlspecialchars($p['description'] ?? 'Premium quality product crafted for your pet.') ?></p>
                <div class="card-chips">
                    <?php if (!empty($p['is_organic'])): ?><span class="chip chip-org"><i class="fas fa-leaf"></i> Organic</span><?php endif; ?>
                    <?php if (!empty($p['is_vet_approved'])): ?><span class="chip chip-vet"><i class="fas fa-shield-alt"></i> Vet Approved</span><?php endif; ?>
                    <?php if ($i < 2): ?><span class="chip chip-pop"><i class="fas fa-fire"></i> Popular</span><?php endif; ?>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="card-footer">
                <button class="btn-add-cart"
                        onclick="event.stopPropagation(); addToCart(<?= $p['id'] ?>);"
                        <?= !$in_stock ? 'disabled' : '' ?>>
                    <i class="fas fa-cart-plus"></i>
                    <?= $in_stock ? 'Add to Cart' : 'Unavailable' ?>
                </button>
                <a href="product.php?id=<?= $p['id'] ?>" class="btn-view-product" onclick="event.stopPropagation();">
                    <i class="fas fa-eye"></i> View Details
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="no-results-msg" id="noResults">
        <i class="fas fa-search"></i>
        <h3>Nothing found</h3>
        <p>Try a different search or category filter.</p>
    </div>
</div>

<?php else: ?>
<!-- ===================== EMPTY STATE ===================== -->
<div class="products-section">
    <div class="empty-state">
        <span class="empty-icon">🐾</span>
        <h3>Nothing saved yet</h3>
        <p>Browse our premium treats and tap the heart on anything your cat deserves most.</p>
        <a href="shop.php" class="btn-shop-now"><i class="fas fa-shopping-bag"></i> Explore the Shop</a>
    </div>
</div>
<?php endif; ?>

<!-- ===================== CONFIRM MODAL ===================== -->
<div class="overlay" id="confirmOverlay">
    <div class="modal">
        <div class="modal-ico"><i class="fas fa-heart-broken"></i></div>
        <h3>Remove from Favorites?</h3>
        <p id="confirmMsg">This will remove the item from your collection.</p>
        <div class="modal-btns">
            <button class="m-keep" onclick="closeConfirm()">Keep It</button>
            <button class="m-del" onclick="doRemove()"><i class="fas fa-trash-alt"></i> Yes, Remove</button>
        </div>
    </div>
</div>

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
                <a href="favorites.php">My Favorites</a>
                <a href="main.php#faq">FAQs</a>
                <a href="cat_care_tips.php">Cat Care Tips</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>My Account</h3>
            <div class="footer-links">
                <a href="profile.php">Profile</a>
                <a href="purchase_history.php">Purchase History</a>
                <a href="cart.php">My Cart</a>
                <a href="favorites.php">Wishlist</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <ul class="footer-contact">
                <li><i class="fas fa-map-marker-alt"></i> 123 Feline Street, Purrville, PH</li>
                <li><i class="fas fa-phone"></i> +1 234 567 8900</li>
                <li><i class="fas fa-envelope"></i> meow@pawganic.com</li>
                <li><i class="fas fa-clock"></i> Mon–Fri: 9AM–6PM</li>
            </ul>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
        <div class="footer-tags">
            <span class="footer-tag">100% Organic</span>
            <span class="footer-tag">Vet Approved</span>
            <span class="footer-tag">PH Owned</span>
        </div>
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
    toastBody.textContent = message;
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'toast');
    closeBtn.setAttribute('aria-label', 'Close');
    toastBody.appendChild(closeBtn);
    toastEl.style.borderLeftColor = type === 'success' ? 'var(--sage)' : 'var(--danger)';
    new bootstrap.Toast(toastEl, { delay: 3200 }).show();
}

/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function () {
    const pd = document.getElementById('profileDropdown');
    if (pd) {
        pd.querySelector('.profile-pic').addEventListener('click', e => {
            e.stopPropagation();
            pd.classList.toggle('open');
        });
        document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
    }

    updateCartDisplay();

    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.pageYOffset > 300));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    document.getElementById('confirmOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });
});

/* ===================== VIEW TOGGLE ===================== */
function setView(mode) {
    const grid = document.getElementById('prodGrid');
    if (!grid) return;
    if (mode === 'list') {
        grid.classList.add('list-view');
        document.getElementById('listBtn').classList.add('active');
        document.getElementById('gridBtn').classList.remove('active');
    } else {
        grid.classList.remove('list-view');
        document.getElementById('gridBtn').classList.add('active');
        document.getElementById('listBtn').classList.remove('active');
    }
}

/* ===================== FILTER & SORT ===================== */
let activeCat = 'all';

function doFilter() {
    const q = document.getElementById('searchInput')?.value.toLowerCase().trim() || '';
    const cards = document.querySelectorAll('.pcard, .product-card');
    let visible = 0;
    cards.forEach(c => {
        const nameMatch = c.dataset.name?.includes(q);
        const descMatch = c.dataset.desc?.includes(q);
        const catMatch  = activeCat === 'all' || c.dataset.cat === activeCat;
        const show = (nameMatch || descMatch) && catMatch;
        c.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const vc = document.getElementById('visCnt');
    if (vc) vc.textContent = visible;
    const nr = document.getElementById('noResults');
    if (nr) nr.classList.toggle('show', visible === 0);
}

function filterByCategory(cat, el) {
    activeCat = cat;
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    doFilter();
}

function doSort() {
    const grid = document.getElementById('prodGrid');
    if (!grid) return;
    const val = document.getElementById('sortSel').value;
    const cards = [...grid.querySelectorAll('.pcard, .product-card')];
    cards.sort((a, b) => {
        if (val === 'name-asc')   return a.dataset.name.localeCompare(b.dataset.name);
        if (val === 'name-desc')  return b.dataset.name.localeCompare(a.dataset.name);
        if (val === 'price-asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        if (val === 'price-desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
        return 0;
    });
    cards.forEach(c => {
        c.style.animation = 'none';
        grid.appendChild(c);
        requestAnimationFrame(() => { c.style.animation = ''; });
    });
}

/* ===================== CONFIRM REMOVE ===================== */
let pendingId = null, pendingCard = null;

function openConfirm(id, name) {
    pendingId   = id;
    pendingCard = document.querySelector(`.product-card[data-id="${id}"]`);
    document.getElementById('confirmMsg').textContent = `Remove "${name}" from your favorites?`;
    document.getElementById('confirmOverlay').classList.add('show');
}
function closeConfirm() {
    document.getElementById('confirmOverlay').classList.remove('show');
    pendingId = pendingCard = null;
}

function doRemove() {
    if (!pendingId) return;
    const id = pendingId, card = pendingCard;
    closeConfirm();
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove_favorite&product_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (card) {
                card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity    = '0';
                card.style.transform  = 'scale(0.8) translateY(-16px)';
                setTimeout(() => { card.remove(); updateFavStats(); }, 420);
            }
            showToast('❤️‍🩹 Removed from favorites', 'success');
        } else {
            showToast(data.message || 'Failed to remove', 'danger');
        }
    })
    .catch(() => showToast('Something went wrong', 'danger'));
}

function updateFavStats() {
    const cards  = document.querySelectorAll('.product-card');
    const n      = cards.length;
    let total    = 0;
    cards.forEach(c => total += parseFloat(c.dataset.price) || 0);

    const heroCount = document.getElementById('heroCount');
    const statCount = document.getElementById('statCount');
    const visCnt    = document.getElementById('visCnt');
    if (heroCount) heroCount.textContent = n + '+';
    if (statCount) statCount.textContent = n;
    if (visCnt)    visCnt.textContent    = n;

    if (n === 0) setTimeout(() => location.reload(), 600);
}
</script>
</body>
</html>