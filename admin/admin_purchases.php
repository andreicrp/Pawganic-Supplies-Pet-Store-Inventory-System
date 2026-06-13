<?php
require_once __DIR__ . '/../config/db.php';
// Session is started in db.php

// Redirect to login if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'], $_POST['status'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $status = htmlspecialchars($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $transaction_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Order #" . str_pad($transaction_id, 6, '0', STR_PAD_LEFT) . " status updated to <strong>" . $status . "</strong>.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get all purchases with user details
$purchases = $conn->query("
    SELECT t.id, t.user_id, t.product_id, t.quantity, t.payment_method, t.total_price, 
           t.delivery_location, t.transaction_date, t.status,
           u.username, p.name AS product_name, p.image, p.price
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    JOIN products p ON t.product_id = p.id
    ORDER BY t.transaction_date DESC
");

// Gather stats
$all_orders = [];
while ($row = $purchases->fetch_assoc()) $all_orders[] = $row;

$total_orders   = count($all_orders);
$total_revenue  = array_sum(array_column($all_orders, 'total_price'));
$pending_count  = count(array_filter($all_orders, fn($o) => $o['status'] === 'Pending'));
$delivered_count= count(array_filter($all_orders, fn($o) => $o['status'] === 'Delivered'));
$cancelled_count= count(array_filter($all_orders, fn($o) => $o['status'] === 'Cancelled'));

// Nav info
$user_id = $_SESSION['user_id'];
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
if (!$check_column || $check_column->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}
$pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $user_id);
$pic_stmt->execute();
$pic_stmt->bind_result($profile_pic);
$pic_stmt->fetch();
$pic_stmt->close();
if (!$profile_pic) $profile_pic = 'images/profile.jpg';
$nav_username = $_SESSION['username'] ?? 'Admin';
$nav_balance  = $_SESSION['balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Purchases — Pawganic Admin</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
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
        --blue:       #2196F3;
        --purple:     #9C27B0;
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
        display: flex; flex-direction: column;
        overflow-x: hidden;
    }

    /* ===================== NAVBAR ===================== */
    .navbar {
        display: flex; justify-content: space-between; align-items: center;
        background: rgba(253,248,240,0.92);
        backdrop-filter: blur(20px);
        padding: 0 5%; height: 72px;
        position: sticky; top: 0; z-index: 1000;
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
    .nav-badge {
        background: var(--espresso); color: var(--honey);
        padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;
        margin-left: 4px; letter-spacing: 0.5px;
    }
    /* Profile dropdown */
    .profile-dropdown { position: relative; display: flex; align-items: center; cursor: pointer; }
    .profile-pic {
        width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
        border: 2.5px solid var(--gold); transition: var(--transition);
    }
    .profile-pic:hover { transform: scale(1.06); box-shadow: 0 0 0 4px rgba(201,145,42,0.18); }
    .dropdown-content {
        display: none; position: absolute; right: 0; top: calc(100% + 10px);
        background: var(--ivory); border-radius: var(--radius-sm);
        box-shadow: var(--shadow-lg); min-width: 220px; z-index: 1000;
        border: 1px solid rgba(201,145,42,0.15); overflow: hidden;
        animation: dropDown 0.25s ease;
    }
    @keyframes dropDown { from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);} }
    .profile-dropdown:hover .dropdown-content,
    .profile-dropdown.open .dropdown-content { display: block; }
    .dropdown-profile-info {
        padding: 16px; border-bottom: 1px solid var(--mist);
        background: linear-gradient(135deg,var(--cream),var(--ivory));
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

    /* ===================== HERO ===================== */
    .page-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 64px 5% 56px; position: relative; overflow: hidden;
    }
    .page-hero::before {
        content: ''; position: absolute; inset: 0;
        background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                    radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
    }
    .page-hero::after {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream); clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco { position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey); }
    .hero-deco-1 { width: 340px; height: 340px; top: -80px; right: -60px; }
    .hero-deco-2 { width: 180px; height: 180px; bottom: 20px; left: 4%; }
    .hero-inner {
        position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;
        display: flex; align-items: flex-start; justify-content: space-between; gap: 40px; flex-wrap: wrap;
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
        font-size: clamp(2.2rem, 4vw, 3.6rem); font-weight: 900;
        color: var(--white); line-height: 1.15; margin-bottom: 10px;
    }
    .hero-title em { font-style: italic; color: var(--honey); }
    .hero-sub { color: rgba(255,255,255,0.6); font-size: 0.95rem; line-height: 1.7; max-width: 420px; }

    /* Stat cards in hero */
    .hero-stats-row {
        display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;
    }
    .hero-stat-card {
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14);
        border-radius: var(--radius-sm); padding: 18px 22px; min-width: 130px;
        backdrop-filter: blur(8px); transition: var(--transition);
    }
    .hero-stat-card:hover { background: rgba(201,145,42,0.18); border-color: rgba(201,145,42,0.4); transform: translateY(-3px); }
    .hero-stat-card .stat-num {
        font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700;
        color: var(--honey); line-height: 1;
    }
    .hero-stat-card .stat-label {
        font-size: 0.72rem; color: rgba(255,255,255,0.5);
        text-transform: uppercase; letter-spacing: 1.5px; margin-top: 6px;
    }
    .hero-stat-card .stat-icon {
        font-size: 1.3rem; color: rgba(201,145,42,0.6); margin-bottom: 8px;
    }

    /* ===================== MAIN CONTENT ===================== */
    .main-content {
        max-width: 1280px; margin: 0 auto; padding: 40px 24px 60px; flex: 1;
    }

    /* ===================== FILTER / SEARCH BAR ===================== */
    .control-bar {
        background: var(--ivory); border-radius: var(--radius);
        padding: 20px 24px; box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.12);
        display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
        margin-bottom: 28px;
    }
    .search-wrap { flex: 1; min-width: 180px; position: relative; }
    .search-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--caramel); font-size: 0.9rem; }
    .control-bar input, .control-bar select {
        width: 100%; padding: 11px 16px 11px 42px;
        border: 2px solid var(--mist); border-radius: 50px;
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 500;
        transition: var(--transition); outline: none;
    }
    .control-bar select { padding-left: 16px; }
    .control-bar input:focus, .control-bar select:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }
    .control-bar input::placeholder { color: var(--caramel); opacity: 0.7; }
    .filter-tag {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 16px; border-radius: 50px; font-size: 0.82rem; font-weight: 600;
        border: 2px solid var(--mist); background: transparent; color: var(--caramel);
        cursor: pointer; transition: var(--transition); white-space: nowrap;
    }
    .filter-tag:hover, .filter-tag.active { background: var(--espresso); color: var(--honey); border-color: var(--espresso); }
    .filter-tag .dot { width: 8px; height: 8px; border-radius: 50%; }

    /* ===================== RESULTS META ===================== */
    .results-meta {
        display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
    }
    .results-count { font-size: 0.85rem; color: var(--caramel); font-weight: 600; }
    .results-divider { flex: 1; height: 1px; background: var(--mist); }
    .export-btn {
        background: transparent; border: 2px solid var(--mist); color: var(--caramel);
        padding: 7px 18px; border-radius: 50px; font-family: 'DM Sans',sans-serif;
        font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: 7px;
    }
    .export-btn:hover { background: var(--espresso); color: var(--honey); border-color: var(--espresso); }

    /* ===================== ORDER CARD ===================== */
    .order-card {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.08);
        margin-bottom: 20px; overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        animation: cardIn 0.4s ease both;
    }
    .order-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(201,145,42,0.25); }
    @keyframes cardIn { from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);} }

    /* Card top stripe by status */
    .order-card::before {
        content: ''; display: block; height: 4px;
        background: var(--stripe-color, var(--mist));
    }

    .card-grid {
        display: grid; grid-template-columns: auto 1fr auto; gap: 0;
    }

    /* Product thumbnail column */
    .card-thumb {
        width: 120px; background: linear-gradient(145deg,var(--cream),var(--mist));
        display: flex; align-items: center; justify-content: center;
        padding: 20px; flex-shrink: 0;
    }
    .card-thumb img { width: 80px; height: 80px; object-fit: cover; border-radius: var(--radius-sm); box-shadow: var(--shadow-sm); }
    .card-thumb .no-img { width: 80px; height: 80px; border-radius: var(--radius-sm); background: var(--mist); display: flex; align-items: center; justify-content: center; color: rgba(201,145,42,0.4); font-size: 2rem; }

    /* Main body */
    .card-body {
        padding: 22px 20px; display: flex; flex-direction: column; gap: 12px; min-width: 0;
    }
    .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .order-meta { display: flex; flex-direction: column; gap: 3px; }
    .order-id-tag {
        font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 700; color: var(--espresso);
    }
    .order-sub { font-size: 0.8rem; color: var(--caramel); }
    .order-sub i { margin-right: 4px; }

    .status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px; color: white;
        flex-shrink: 0;
    }
    .status-pill i { font-size: 0.7rem; }
    .pill-pending    { background: #f39c12; }
    .pill-processing { background: #2196F3; }
    .pill-shipped    { background: #9C27B0; }
    .pill-delivered  { background: #4CAF50; }
    .pill-cancelled  { background: #c0392b; }

    .product-title { font-weight: 700; color: var(--mahogany); font-size: 1rem; }
    .detail-chips { display: flex; gap: 8px; flex-wrap: wrap; }
    .chip {
        background: var(--cream); border: 1px solid var(--mist);
        border-radius: 50px; padding: 4px 12px;
        font-size: 0.78rem; color: var(--caramel); font-weight: 500;
    }
    .chip strong { color: var(--espresso); }
    .chip.highlight { background: rgba(201,145,42,0.1); border-color: rgba(201,145,42,0.3); color: var(--gold); }
    .chip.highlight strong { color: var(--mahogany); }

    .delivery-row { font-size: 0.82rem; color: var(--caramel); display: flex; align-items: flex-start; gap: 8px; }
    .delivery-row i { color: var(--gold); margin-top: 2px; flex-shrink: 0; }

    /* Status update panel */
    .card-action {
        padding: 20px; border-left: 1px solid var(--mist); background: linear-gradient(180deg,var(--cream),var(--ivory));
        display: flex; flex-direction: column; gap: 14px; justify-content: center; min-width: 220px; flex-shrink: 0;
    }
    .action-label { font-size: 0.75rem; font-weight: 700; color: var(--caramel); text-transform: uppercase; letter-spacing: 1px; }
    .status-select {
        width: 100%; padding: 10px 14px; border: 2px solid var(--mist); border-radius: var(--radius-sm);
        background: var(--ivory); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.88rem; cursor: pointer;
        transition: var(--transition); outline: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%239b6a2f' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 12px center;
        padding-right: 34px;
    }
    .status-select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,145,42,0.1); }
    .btn-update {
        width: 100%; padding: 11px 16px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border: none; border-radius: var(--radius-sm);
        color: var(--honey); font-family: 'DM Sans',sans-serif;
        font-weight: 700; font-size: 0.88rem; cursor: pointer;
        transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-update:hover { background: var(--gold); color: var(--white); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
    .view-detail-btn {
        width: 100%; padding: 9px; background: transparent;
        border: 2px solid var(--mist); border-radius: var(--radius-sm);
        color: var(--caramel); font-family: 'DM Sans',sans-serif;
        font-size: 0.82rem; font-weight: 600; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 7px;
    }
    .view-detail-btn:hover { background: var(--mist); color: var(--espresso); }

    /* ===================== EMPTY STATE ===================== */
    .empty-state {
        text-align: center; padding: 80px 24px;
        background: var(--ivory); border-radius: var(--radius);
        border: 2px dashed var(--mist);
    }
    .empty-state i { font-size: 4rem; color: var(--mist); margin-bottom: 20px; display: block; }
    .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--mahogany); margin-bottom: 10px; }
    .empty-state p { color: var(--caramel); font-size: 0.95rem; }

    /* ===================== TOAST ===================== */
    .toast-container-custom { position: fixed; bottom: 30px; left: 30px; z-index: 3000; }
    .custom-toast {
        background: linear-gradient(135deg,var(--espresso),var(--mahogany));
        border-radius: var(--radius-sm); padding: 16px 20px;
        box-shadow: var(--shadow-lg); min-width: 280px; max-width: 360px;
        color: var(--cream); border-left: 4px solid var(--gold);
        display: flex; align-items: flex-start; gap: 14px;
        animation: toastIn 0.35s cubic-bezier(0.4,0,0.2,1);
    }
    @keyframes toastIn { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
    .toast-icon { font-size: 1.2rem; color: var(--honey); flex-shrink: 0; margin-top: 1px; }
    .toast-text { flex: 1; font-size: 0.9rem; line-height: 1.5; }
    .toast-close { background: none; border: none; color: rgba(255,255,255,0.5); cursor: pointer; font-size: 1rem; padding: 0; transition: color 0.2s; }
    .toast-close:hover { color: var(--white); }

    /* ===================== ORDER DETAIL MODAL ===================== */
    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(44,26,14,0.65);
        backdrop-filter: blur(6px); z-index: 2000; animation: fadeIn 0.3s ease;
        align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    @keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
    .modal-box {
        background: var(--ivory); border-radius: var(--radius); box-shadow: var(--shadow-lg);
        width: 90%; max-width: 560px; overflow: hidden;
        animation: slideUp 0.4s ease;
    }
    @keyframes slideUp{from{transform:translateY(40px);opacity:0;}to{transform:translateY(0);opacity:1;}}
    .modal-head {
        background: linear-gradient(135deg,var(--espresso),var(--mahogany));
        color: var(--honey); padding: 24px 28px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .modal-head h2 { font-family: 'Playfair Display',serif; font-size: 1.4rem; font-weight: 700; }
    .modal-close {
        background: rgba(255,255,255,0.1); border: none; color: var(--honey);
        width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center; font-size: 1rem;
        transition: var(--transition);
    }
    .modal-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }
    .modal-body { padding: 28px; }
    .detail-row-modal { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--mist); font-size: 0.9rem; }
    .detail-row-modal:last-child { border-bottom: none; }
    .detail-key { color: var(--caramel); font-weight: 500; }
    .detail-val { color: var(--espresso); font-weight: 700; text-align: right; max-width: 60%; }
    .timeline { margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--mist); }
    .timeline-title { font-size: 0.75rem; font-weight: 700; color: var(--caramel); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
    .timeline-steps { display: flex; gap: 0; }
    .t-step { flex: 1; text-align: center; position: relative; }
    .t-step::before { content: ''; position: absolute; top: 14px; left: 50%; right: -50%; height: 2px; background: var(--mist); z-index: 0; }
    .t-step:last-child::before { display: none; }
    .t-dot {
        width: 28px; height: 28px; border-radius: 50%; border: 2px solid var(--mist);
        background: var(--ivory); margin: 0 auto 6px; position: relative; z-index: 1;
        display: flex; align-items: center; justify-content: center; font-size: 0.65rem;
        color: var(--mist); transition: var(--transition);
    }
    .t-dot.done { background: var(--sage); border-color: var(--sage); color: white; }
    .t-dot.active { background: var(--gold); border-color: var(--gold); color: white; box-shadow: 0 0 0 4px rgba(201,145,42,0.2); }
    .t-dot.cancelled-dot { background: var(--danger); border-color: var(--danger); color: white; }
    .t-label { font-size: 0.7rem; color: var(--caramel); font-weight: 500; }

    /* ===================== SCROLL TO TOP ===================== */
    .scroll-to-top {
        position: fixed; bottom: 30px; right: 30px; z-index: 999;
        width: 48px; height: 48px; border-radius: 50%;
        background: linear-gradient(135deg,var(--espresso),var(--mahogany));
        border: none; color: var(--honey); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; box-shadow: var(--shadow-md);
        opacity: 0; visibility: hidden; transition: var(--transition);
    }
    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso); color: rgba(255,255,255,0.75);
        padding: 64px 5% 28px; margin-top: auto; position: relative;
    }
    footer::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right,var(--caramel),var(--gold),var(--honey),var(--gold),var(--caramel));
    }
    .footer-content { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 40px; margin-bottom: 40px; }
    .footer-section h3 { font-family: 'Playfair Display',serif; font-size: 1.1rem; color: var(--honey); margin-bottom: 18px; }
    .footer-section p { font-size: 0.88rem; line-height: 1.8; margin-bottom: 10px; }
    .social-links { display: flex; gap: 10px; margin-top: 14px; }
    .social-links a {
        width: 38px; height: 38px; border-radius: 50%;
        background: rgba(201,145,42,0.15); border: 1px solid rgba(201,145,42,0.3);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.88rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }
    .footer-links { display: flex; flex-direction: column; gap: 9px; }
    .footer-links a { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.88rem; transition: var(--transition); }
    .footer-links a:hover { color: var(--honey); padding-left: 5px; }
    .copyright { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 18px; text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.35); }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 900px) {
        .card-grid { grid-template-columns: 1fr; }
        .card-thumb { width: 100%; height: 100px; }
        .card-action { min-width: unset; border-left: none; border-top: 1px solid var(--mist); flex-direction: row; flex-wrap: wrap; gap: 10px; }
        .card-action form { display: flex; gap: 10px; flex-wrap: wrap; flex: 1; }
        .status-select { flex: 1; min-width: 140px; }
        .btn-update, .view-detail-btn { flex: 1; }
    }
    @media (max-width: 600px) {
        .navbar { padding: 0 16px; }
        .nav-links a:not(.profile-dropdown *) { display: none; }
        .hero-stats-row { gap: 10px; }
        .hero-stat-card { padding: 14px 16px; min-width: 100px; }
        .main-content { padding: 24px 16px 40px; }
        .control-bar { flex-direction: column; }
    }

    /* Card stagger animation */
    .order-card:nth-child(1){animation-delay:.04s}
    .order-card:nth-child(2){animation-delay:.08s}
    .order-card:nth-child(3){animation-delay:.12s}
    .order-card:nth-child(4){animation-delay:.16s}
    .order-card:nth-child(5){animation-delay:.20s}
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="index.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
        </a>
    <div class="nav-links">
        <a href="admin.php"><i class="fas fa-tachometer-alt" style="margin-right:6px;font-size:0.85rem;"></i>Dashboard</a>
        <a href="admin_purchases.php" class="active">
            Purchases
            <?php if ($pending_count > 0): ?>
                <span class="nav-badge"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php"><i class="fas fa-box" style="margin-right:4px;font-size:0.82rem;"></i>Products</a>
        <div class="profile-dropdown">
            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic" onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                    <div class="dropdown-profile-role">Administrator</div>
                    <div class="dropdown-profile-balance">₱<?= number_format($nav_balance, 2) ?></div>
                </div>
                <a href="profile.php"><i class="fas fa-user"></i>My Profile</a>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ===================== HERO ===================== -->
<section class="page-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-label"><i class="fas fa-box-open"></i> ADMIN PANEL</div>
            <h1 class="hero-title">Manage <em>Purchases</em></h1>
            <p class="hero-sub">Track every order in real time, update statuses, and keep your customers informed from placement to delivery.</p>
        </div>
        <div class="hero-stats-row">
            <div class="hero-stat-card">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-num"><?= $total_orders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="hero-stat-card">
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-num">₱<?= number_format($total_revenue, 0) ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="hero-stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-num"><?= $pending_count ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="hero-stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-num"><?= $delivered_count ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            <div class="hero-stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-num"><?= $cancelled_count ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </div>
    </div>
</section>

<!-- ===================== TOAST ===================== -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="toast-container-custom" id="toastContainer">
    <div class="custom-toast">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-text"><?= $_SESSION['success_message'] ?></div>
        <button class="toast-close" onclick="this.closest('.custom-toast').remove()"><i class="fas fa-times"></i></button>
    </div>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<!-- ===================== MAIN ===================== -->
<div class="main-content">

    <!-- Control bar -->
    <div class="control-bar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by order ID, customer, product…" oninput="filterOrders()">
        </div>
        <select id="statusFilter" onchange="filterOrders()" style="width:180px;padding:11px 14px;">
            <option value="">All Statuses</option>
            <option value="Pending">Pending</option>
            <option value="Processing">Processing</option>
            <option value="Shipped">Shipped</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
        </select>
        <select id="sortFilter" onchange="filterOrders()" style="width:180px;padding:11px 14px;">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="highest">Highest Value</option>
            <option value="lowest">Lowest Value</option>
        </select>
        <button class="export-btn" onclick="exportCSV()"><i class="fas fa-download"></i> Export CSV</button>
    </div>

    <!-- Quick filter tags -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;">
        <button class="filter-tag active" onclick="setStatusFilter('', this)"><i class="fas fa-th-large"></i> All Orders</button>
        <button class="filter-tag" onclick="setStatusFilter('Pending', this)"><span class="dot" style="background:#f39c12;"></span> Pending</button>
        <button class="filter-tag" onclick="setStatusFilter('Processing', this)"><span class="dot" style="background:#2196F3;"></span> Processing</button>
        <button class="filter-tag" onclick="setStatusFilter('Shipped', this)"><span class="dot" style="background:#9C27B0;"></span> Shipped</button>
        <button class="filter-tag" onclick="setStatusFilter('Delivered', this)"><span class="dot" style="background:#4CAF50;"></span> Delivered</button>
        <button class="filter-tag" onclick="setStatusFilter('Cancelled', this)"><span class="dot" style="background:#c0392b;"></span> Cancelled</button>
    </div>

    <!-- Results meta -->
    <div class="results-meta">
        <span class="results-count" id="resultsCount">Showing <?= $total_orders ?> order<?= $total_orders !== 1 ? 's' : '' ?></span>
        <div class="results-divider"></div>
    </div>

    <!-- Orders list -->
    <div id="ordersContainer">
    <?php if (count($all_orders) > 0):
        $stripe_map = [
            'Pending'    => '#f39c12',
            'Processing' => '#2196F3',
            'Shipped'    => '#9C27B0',
            'Delivered'  => '#4CAF50',
            'Cancelled'  => '#c0392b',
        ];
        $pill_map = [
            'Pending'    => 'pill-pending',
            'Processing' => 'pill-processing',
            'Shipped'    => 'pill-shipped',
            'Delivered'  => 'pill-delivered',
            'Cancelled'  => 'pill-cancelled',
        ];
        $status_icon = [
            'Pending'    => 'fa-clock',
            'Processing' => 'fa-cog',
            'Shipped'    => 'fa-truck',
            'Delivered'  => 'fa-check-circle',
            'Cancelled'  => 'fa-times-circle',
        ];
        foreach ($all_orders as $idx => $order):
            $stripe = $stripe_map[$order['status']] ?? '#ede4d2';
            $pill   = $pill_map[$order['status']] ?? '';
            $icon   = $status_icon[$order['status']] ?? 'fa-circle';
            $order_id_str = str_pad($order['id'], 6, '0', STR_PAD_LEFT);
    ?>
    <div class="order-card"
         style="--stripe-color: <?= $stripe ?>;"
         data-status="<?= htmlspecialchars($order['status']) ?>"
         data-customer="<?= htmlspecialchars(strtolower($order['username'])) ?>"
         data-product="<?= htmlspecialchars(strtolower($order['product_name'])) ?>"
         data-orderid="<?= $order_id_str ?>"
         data-total="<?= $order['total_price'] ?>"
         data-date="<?= $order['transaction_date'] ?>">

        <div class="card-grid">
            <!-- Thumbnail -->
            <div class="card-thumb">
                <?php if (!empty($order['image']) && file_exists('uploads/' . $order['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>">
                <?php else: ?>
                    <div class="no-img"><i class="fas fa-box"></i></div>
                <?php endif; ?>
            </div>

            <!-- Body -->
            <div class="card-body">
                <div class="card-top">
                    <div class="order-meta">
                        <div class="order-id-tag">Order #<?= $order_id_str ?></div>
                        <div class="order-sub"><i class="fas fa-user"></i> <?= htmlspecialchars($order['username']) ?> &nbsp;·&nbsp; <i class="fas fa-calendar-alt"></i> <?= date("M d, Y · h:i A", strtotime($order['transaction_date'])) ?></div>
                    </div>
                    <span class="status-pill <?= $pill ?>"><i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($order['status']) ?></span>
                </div>

                <div class="product-title"><i class="fas fa-tag" style="color:var(--gold);margin-right:7px;font-size:0.85rem;"></i><?= htmlspecialchars($order['product_name']) ?></div>

                <div class="detail-chips">
                    <span class="chip"><strong><?= $order['quantity'] ?></strong> unit<?= $order['quantity'] > 1 ? 's' : '' ?></span>
                    <span class="chip">Unit: <strong>₱<?= number_format($order['price'], 2) ?></strong></span>
                    <span class="chip highlight"><i class="fas fa-peso-sign" style="font-size:0.7rem;"></i> Total: <strong>₱<?= number_format($order['total_price'], 2) ?></strong></span>
                    <span class="chip"><i class="fas fa-credit-card" style="font-size:0.75rem;margin-right:3px;"></i><?= htmlspecialchars($order['payment_method']) ?></span>
                </div>

                <div class="delivery-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= htmlspecialchars($order['delivery_location']) ?></span>
                </div>
            </div>

            <!-- Action panel -->
            <div class="card-action">
                <form method="POST">
                    <input type="hidden" name="transaction_id" value="<?= $order['id'] ?>">
                    <div class="action-label">Update Status</div>
                    <select name="status" class="status-select">
                        <option value="Pending"    <?= $order['status'] === 'Pending'    ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $order['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped"    <?= $order['status'] === 'Shipped'    ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered"  <?= $order['status'] === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled"  <?= $order['status'] === 'Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <button type="submit" class="btn-update"><i class="fas fa-check"></i> Save Status</button>
                </form>
                <button class="view-detail-btn" onclick="openDetailModal(<?= htmlspecialchars(json_encode([
                    'id'       => $order_id_str,
                    'customer' => $order['username'],
                    'user_id'  => $order['user_id'],
                    'product'  => $order['product_name'],
                    'qty'      => $order['quantity'],
                    'unit'     => number_format($order['price'], 2),
                    'total'    => number_format($order['total_price'], 2),
                    'payment'  => $order['payment_method'],
                    'delivery' => $order['delivery_location'],
                    'date'     => date("M d, Y · h:i A", strtotime($order['transaction_date'])),
                    'status'   => $order['status'],
                ])) ?>)">
                    <i class="fas fa-expand-alt"></i> View Details
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Purchase Records Yet</h3>
        <p>New orders placed by customers will appear here. Check back soon!</p>
    </div>
    <?php endif; ?>
    </div><!-- /ordersContainer -->

    <a href="admin.php" style="display:flex;align-items:center;gap:8px;justify-content:center;margin-top:32px;padding:14px;background:var(--ivory);border-radius:var(--radius);border:2px solid var(--mist);color:var(--caramel);text-decoration:none;font-weight:600;font-size:0.9rem;transition:var(--transition);"
       onmouseover="this.style.background='var(--espresso)';this.style.color='var(--honey)';this.style.borderColor='var(--espresso)';"
       onmouseout="this.style.background='var(--ivory)';this.style.color='var(--caramel)';this.style.borderColor='var(--mist)';">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- ===================== DETAIL MODAL ===================== -->
<div id="detailModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="modalTitle">Order Details</h2>
            <button class="modal-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalBody"></div>
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
            <h3>Admin Links</h3>
            <div class="footer-links">
                <a href="admin.php">Dashboard</a>
                <a href="admin_purchases.php">Manage Purchases</a>
                <a href="admin_products.php">Manage Products</a>
                <a href="admin_users.php">Manage Users</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey);margin-right:8px;"></i> 123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey);margin-right:8px;"></i> +1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey);margin-right:8px;"></i> meow@pawganic.com</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies — Admin Panel. All rights reserved.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script>
/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function() {
    const pd = document.querySelector('.profile-dropdown');
    if (pd) {
        pd.querySelector('.profile-pic').addEventListener('click', e => { e.stopPropagation(); pd.classList.toggle('open'); });
        document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
    }
    // Auto-dismiss toast
    const toast = document.querySelector('.custom-toast');
    if (toast) setTimeout(() => toast.remove(), 5000);

    // Scroll to top
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.pageYOffset > 300));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
});

/* ===================== FILTER / SEARCH ===================== */
function setStatusFilter(status, btn) {
    document.getElementById('statusFilter').value = status;
    document.querySelectorAll('.filter-tag').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterOrders();
}

function filterOrders() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const sort   = document.getElementById('sortFilter').value;
    const cards  = Array.from(document.querySelectorAll('.order-card'));

    let visible = 0;
    cards.forEach(card => {
        const matchSearch = !search ||
            card.dataset.orderid.includes(search) ||
            card.dataset.customer.includes(search) ||
            card.dataset.product.includes(search);
        const matchStatus = !status || card.dataset.status === status;
        const show = matchSearch && matchStatus;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // Sort
    const container = document.getElementById('ordersContainer');
    const visibleCards = cards.filter(c => c.style.display !== 'none');
    visibleCards.sort((a, b) => {
        if (sort === 'highest') return parseFloat(b.dataset.total) - parseFloat(a.dataset.total);
        if (sort === 'lowest')  return parseFloat(a.dataset.total) - parseFloat(b.dataset.total);
        if (sort === 'oldest')  return new Date(a.dataset.date) - new Date(b.dataset.date);
        return new Date(b.dataset.date) - new Date(a.dataset.date); // newest
    });
    visibleCards.forEach(c => container.appendChild(c));

    const label = visible === 1 ? '1 order' : visible + ' orders';
    document.getElementById('resultsCount').textContent = 'Showing ' + label;
}

/* ===================== DETAIL MODAL ===================== */
const statusSteps = ['Pending', 'Processing', 'Shipped', 'Delivered'];

function openDetailModal(order) {
    const statusIndex = statusSteps.indexOf(order.status);
    const isCancelled = order.status === 'Cancelled';

    const timelineHTML = statusSteps.map((s, i) => {
        let dotClass = '';
        if (isCancelled) { dotClass = i === 0 ? 'cancelled-dot' : ''; }
        else if (i < statusIndex) dotClass = 'done';
        else if (i === statusIndex) dotClass = 'active';
        const icon = { Pending: 'fa-clock', Processing: 'fa-cog', Shipped: 'fa-truck', Delivered: 'fa-check-circle' }[s];
        return `<div class="t-step">
            <div class="t-dot ${dotClass}"><i class="fas ${icon}" style="font-size:0.7rem;"></i></div>
            <div class="t-label">${s}</div>
        </div>`;
    }).join('');

    document.getElementById('modalTitle').textContent = 'Order #' + order.id;
    document.getElementById('modalBody').innerHTML = `
        <div class="detail-row-modal"><span class="detail-key">Customer</span><span class="detail-val">${order.customer} <small style="color:var(--caramel)">(ID: ${order.user_id})</small></span></div>
        <div class="detail-row-modal"><span class="detail-key">Product</span><span class="detail-val">${order.product}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Quantity</span><span class="detail-val">${order.qty} unit${order.qty > 1 ? 's' : ''}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Unit Price</span><span class="detail-val">₱${order.unit}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Total Paid</span><span class="detail-val" style="color:var(--gold)">₱${order.total}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Payment Method</span><span class="detail-val">${order.payment}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Delivery Location</span><span class="detail-val">${order.delivery}</span></div>
        <div class="detail-row-modal"><span class="detail-key">Order Date</span><span class="detail-val">${order.date}</span></div>
        <div class="timeline">
            <div class="timeline-title">${isCancelled ? 'Order was Cancelled' : 'Order Progress'}</div>
            <div class="timeline-steps">${timelineHTML}</div>
        </div>`;
    document.getElementById('detailModal').classList.add('active');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('active');
}

window.addEventListener('click', e => {
    if (e.target === document.getElementById('detailModal')) closeDetailModal();
});

/* ===================== CSV EXPORT ===================== */
function exportCSV() {
    const rows = [['Order ID','Customer','Product','Qty','Unit Price','Total','Payment','Delivery Location','Date','Status']];
    document.querySelectorAll('.order-card').forEach(card => {
        const cells = card.querySelectorAll('.chip');
        rows.push([
            '#' + card.dataset.orderid,
            card.dataset.customer,
            card.dataset.product,
            cells[0]?.textContent.trim() || '',
            cells[1]?.textContent.replace('Unit: ','').trim() || '',
            '₱' + card.dataset.total,
            cells[3]?.textContent.trim() || '',
            card.querySelector('.delivery-row span')?.textContent.trim() || '',
            card.dataset.date,
            card.dataset.status,
        ]);
    });
    const csv = rows.map(r => r.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'pawganic_orders_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}
</script>
</body>
</html>