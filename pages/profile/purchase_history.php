<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'], $_POST['status']) && $is_admin) {
    $transaction_id = intval($_POST['transaction_id']);
    $status = htmlspecialchars($_POST['status']);
    $stmt = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $transaction_id);
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Order status updated successfully!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$stmt = $conn->prepare("
    SELECT t.id, t.product_id, t.quantity, t.payment_method, t.total_price, t.delivery_location, t.transaction_date, t.status,
           p.name AS product_name, p.image, p.price
    FROM transactions t
    JOIN products p ON t.product_id = p.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC
");
if (!$stmt) { die("Query error: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$stmt_stats = $conn->prepare("
    SELECT COUNT(*) as total_orders, SUM(total_price) as total_spent, SUM(quantity) as total_items,
           MAX(transaction_date) as last_order
    FROM transactions WHERE user_id = ?
");
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$total_orders = $stats['total_orders'] ?? 0;
$total_spent  = $stats['total_spent']  ?? 0;
$total_items  = $stats['total_items']  ?? 0;
$last_order   = $stats['last_order']   ?? null;

// Nav profile
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role     = $_SESSION['role']     ?? 'customer';
$nav_balance  = $_SESSION['balance']  ?? 0;
$profile_pic  = 'images/profile.jpg';
if (isset($_SESSION['user_id'])) {
    $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $pic_stmt->bind_param("i", $user_id);
    $pic_stmt->execute();
    $pic_stmt->bind_result($db_pic);
    $pic_stmt->fetch();
    $pic_stmt->close();
    if ($db_pic) $profile_pic = $db_pic;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    /* =================== ROOT =================== */
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
        --transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
    }
    *, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
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

    /* =================== NAVBAR =================== */
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
        color: var(--mahogany); text-decoration: none;
        padding: 8px 16px; border-radius: 50px;
        font-weight: 500; font-size: 0.9rem; letter-spacing: 0.3px;
        transition: var(--transition);
    }
    .nav-links a:hover, .nav-links a.active { background: var(--gold); color: var(--white); }
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
    @keyframes dropDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .profile-dropdown:hover .dropdown-content,
    .profile-dropdown.open .dropdown-content { display: block; }
    .dropdown-profile-info {
        padding: 16px; border-bottom: 1px solid var(--mist);
        background: linear-gradient(135deg, var(--cream), var(--ivory));
    }
    .dropdown-profile-name  { font-weight: 700; color: var(--mahogany); font-size: 0.95rem; }
    .dropdown-profile-role  { font-size: 0.78rem; color: var(--caramel); margin-top: 2px; }
    .dropdown-profile-balance { font-size: 0.85rem; color: var(--gold); font-weight: 600; margin-top: 5px; }
    .dropdown-content a {
        display: flex; align-items: center; gap: 10px;
        color: var(--espresso); text-decoration: none; padding: 12px 16px;
        font-size: 0.9rem; transition: var(--transition);
    }
    .dropdown-content a:hover { background: var(--cream); color: var(--mahogany); padding-left: 22px; }
    .dropdown-content a i { width: 18px; color: var(--caramel); }

    /* =================== HERO =================== */
    .page-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 72px 5% 64px;
        position: relative; overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                    radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
    }
    .page-hero::after {
        content: ''; position: absolute;
        bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco { position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey); }
    .hero-deco-1 { width: 380px; height: 380px; top: -100px; right: -80px; }
    .hero-deco-2 { width: 220px; height: 220px; bottom: 20px; left: 5%; }
    .hero-inner {
        position: relative; z-index: 2;
        max-width: 1200px; margin: 0 auto;
    }
    .hero-label {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
        margin-bottom: 18px;
    }
    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.4rem,4vw,3.8rem);
        font-weight: 900; color: var(--white); line-height: 1.1; margin-bottom: 12px;
    }
    .hero-title em { font-style: italic; color: var(--honey); }
    .hero-subtitle { color: rgba(255,255,255,0.65); font-size: 1rem; line-height: 1.7; max-width: 480px; }

    /* =================== MAIN LAYOUT =================== */
    .main-content {
        max-width: 1200px; margin: 0 auto;
        padding: 48px 24px 80px;
        flex: 1;
    }

    /* =================== STATS STRIP =================== */
    .stats-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 48px;
    }
    .stat-card {
        background: var(--ivory);
        border-radius: var(--radius);
        padding: 28px 24px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        display: flex; align-items: center; gap: 18px;
        transition: var(--transition);
        position: relative; overflow: hidden;
    }
    .stat-card::before {
        content: '';
        position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(to right, var(--gold), var(--honey));
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .stat-icon-wrap {
        width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: var(--honey);
    }
    .stat-text {}
    .stat-value {
        font-family: 'Playfair Display', serif;
        font-size: 1.7rem; font-weight: 700; color: var(--espresso); line-height: 1;
    }
    .stat-label { font-size: 0.8rem; color: var(--caramel); font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.8px; }

    /* =================== FILTER ROW =================== */
    .filter-row {
        display: flex; gap: 12px; align-items: center; margin-bottom: 32px; flex-wrap: wrap;
    }
    .filter-btn {
        padding: 9px 20px; border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition);
        border: 2px solid var(--mist); background: var(--ivory); color: var(--caramel);
    }
    .filter-btn.active, .filter-btn:hover {
        background: var(--espresso); color: var(--honey); border-color: var(--espresso);
    }
    .filter-btn .dot {
        display: inline-block; width: 8px; height: 8px; border-radius: 50%;
        margin-right: 6px;
    }
    .search-filter {
        flex: 1; min-width: 220px; position: relative;
    }
    .search-filter i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--caramel); }
    .search-filter input {
        width: 100%; padding: 10px 16px 10px 42px;
        border: 2px solid var(--mist); border-radius: 50px;
        background: var(--ivory); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.9rem; outline: none;
        transition: var(--transition);
    }
    .search-filter input:focus { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,145,42,0.1); }
    .result-count-bar {
        display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
    }
    .result-count-txt { font-size: 0.85rem; color: var(--caramel); font-weight: 500; white-space: nowrap; }
    .result-divider { flex: 1; height: 1px; background: var(--mist); }

    /* =================== ORDER CARDS =================== */
    .orders-list { display: flex; flex-direction: column; gap: 24px; }

    .order-card {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.08);
        overflow: hidden; transition: var(--transition);
        animation: cardIn 0.5s ease both;
    }
    .order-card:hover { box-shadow: var(--shadow-md); border-color: rgba(201,145,42,0.25); transform: translateY(-2px); }
    @keyframes cardIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

    /* Order top bar */
    .order-topbar {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        padding: 16px 24px;
        display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    }
    .order-id {
        font-family: 'Playfair Display', serif;
        font-size: 1rem; font-weight: 700; color: var(--honey);
        display: flex; align-items: center; gap: 8px;
    }
    .order-id i { font-size: 0.9rem; opacity: 0.7; }
    .order-meta { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .order-date-txt { font-size: 0.8rem; color: rgba(255,255,255,0.6); display: flex; align-items: center; gap: 6px; }

    /* Status badge */
    .status-badge {
        padding: 5px 14px; border-radius: 50px;
        font-size: 0.74rem; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
        display: flex; align-items: center; gap: 6px;
    }
    .status-pending    { background: rgba(201,145,42,0.2);  color: var(--honey);     border: 1px solid rgba(201,145,42,0.4); }
    .status-processing { background: rgba(52,152,219,0.2);  color: #5dade2;          border: 1px solid rgba(52,152,219,0.4); }
    .status-shipped    { background: rgba(156,39,176,0.2);  color: #ce93d8;          border: 1px solid rgba(156,39,176,0.4); }
    .status-delivered  { background: rgba(122,158,126,0.2); color: var(--sage-light);border: 1px solid rgba(122,158,126,0.4); }
    .status-cancelled  { background: rgba(192,57,43,0.2);   color: #e57373;          border: 1px solid rgba(192,57,43,0.4); }

    /* Order body */
    .order-body { padding: 24px; }
    .order-product-row {
        display: flex; gap: 20px; align-items: center;
        margin-bottom: 20px; padding-bottom: 20px;
        border-bottom: 1px solid var(--mist);
    }
    .product-thumb {
        width: 90px; height: 90px; border-radius: var(--radius-sm);
        overflow: hidden; flex-shrink: 0;
        background: linear-gradient(145deg, var(--cream), var(--mist));
        border: 1px solid rgba(201,145,42,0.15);
        display: flex; align-items: center; justify-content: center;
    }
    .product-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .product-thumb .no-img { font-size: 2rem; color: var(--mist); }
    .product-info-col { flex: 1; }
    .product-name-txt {
        font-family: 'Playfair Display', serif;
        font-size: 1.15rem; font-weight: 700; color: var(--espresso); margin-bottom: 6px;
    }
    .product-meta-row { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 8px; }
    .product-meta-item { font-size: 0.84rem; color: var(--caramel); }
    .product-meta-item strong { color: var(--espresso); }
    .price-display {
        font-family: 'Playfair Display', serif;
        font-size: 1.4rem; font-weight: 700;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Info grid */
    .order-info-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 16px;
    }
    .info-chip {
        background: var(--cream); border-radius: var(--radius-sm);
        padding: 14px 16px; border: 1px solid rgba(201,145,42,0.1);
    }
    .info-chip-label {
        font-size: 0.72rem; color: var(--caramel); font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 5px;
        display: flex; align-items: center; gap: 6px;
    }
    .info-chip-value { font-size: 0.9rem; color: var(--espresso); font-weight: 600; }

    /* Delivery */
    .delivery-row {
        background: linear-gradient(135deg, rgba(122,158,126,0.08), rgba(122,158,126,0.04));
        border: 1px solid rgba(122,158,126,0.25);
        border-radius: var(--radius-sm);
        padding: 14px 18px;
        display: flex; align-items: flex-start; gap: 12px;
    }
    .delivery-row .icon-wrap {
        width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
        background: rgba(122,158,126,0.2);
        display: flex; align-items: center; justify-content: center;
        color: var(--sage); font-size: 1rem;
    }
    .delivery-label { font-size: 0.75rem; color: var(--sage); font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px; }
    .delivery-addr { font-size: 0.9rem; color: var(--espresso); font-weight: 500; line-height: 1.5; }

    /* Admin status update */
    .admin-update-form {
        display: flex; gap: 10px; align-items: center; margin-top: 10px;
    }
    .admin-update-form select {
        padding: 7px 14px; border-radius: 50px; border: 2px solid var(--mist);
        background: var(--ivory); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.88rem; outline: none;
        transition: var(--transition);
    }
    .admin-update-form select:focus { border-color: var(--gold); }
    .admin-update-form button {
        padding: 7px 18px; border-radius: 50px; border: none;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: var(--transition);
    }
    .admin-update-form button:hover { background: var(--espresso); color: var(--honey); }

    /* Progress tracker */
    .progress-tracker {
        display: flex; align-items: center; margin-top: 16px; padding-top: 16px;
        border-top: 1px solid var(--mist);
    }
    .progress-step {
        display: flex; flex-direction: column; align-items: center; flex: 1; position: relative;
    }
    .progress-step:not(:last-child)::after {
        content: '';
        position: absolute; top: 14px; left: 50%; width: 100%; height: 2px;
        background: var(--mist); z-index: 0;
    }
    .progress-step.done:not(:last-child)::after { background: var(--sage); }
    .step-dot {
        width: 28px; height: 28px; border-radius: 50%; position: relative; z-index: 1;
        border: 2px solid var(--mist); background: var(--ivory);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; color: var(--mist); transition: var(--transition);
    }
    .progress-step.done .step-dot { background: var(--sage); border-color: var(--sage); color: var(--white); }
    .progress-step.active .step-dot { background: var(--gold); border-color: var(--gold); color: var(--white); box-shadow: 0 0 0 4px rgba(201,145,42,0.2); }
    .step-label { font-size: 0.68rem; color: var(--caramel); margin-top: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; }
    .progress-step.done .step-label, .progress-step.active .step-label { color: var(--espresso); }

    /* =================== EMPTY STATE =================== */
    .empty-state {
        text-align: center; padding: 80px 24px;
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.08);
    }
    .empty-icon { font-size: 4rem; color: var(--mist); margin-bottom: 20px; display: block; }
    .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--mahogany); margin-bottom: 10px; }
    .empty-state p { color: var(--caramel); font-size: 0.95rem; margin-bottom: 30px; }
    .btn-shop-now {
        display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); text-decoration: none;
        padding: 14px 32px; border-radius: 50px; font-weight: 700; font-size: 0.95rem;
        box-shadow: 0 4px 14px rgba(201,145,42,0.35); transition: var(--transition);
    }
    .btn-shop-now:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px);
    }

    /* =================== SECTION HEADER =================== */
    .section-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 28px; gap: 16px; flex-wrap: wrap;
    }
    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.6rem; font-weight: 700; color: var(--espresso);
        display: flex; align-items: center; gap: 10px;
    }
    .section-title i { color: var(--gold); }
    .section-count {
        background: rgba(201,145,42,0.12); color: var(--gold);
        border: 1px solid rgba(201,145,42,0.25);
        padding: 3px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 700;
    }

    /* =================== FOOTER =================== */
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

    /* =================== SCROLL TO TOP =================== */
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

    /* =================== ALERT =================== */
    .alert-success-custom {
        background: linear-gradient(135deg, rgba(122,158,126,0.15), rgba(122,158,126,0.08));
        border: 1px solid rgba(122,158,126,0.35);
        border-left: 4px solid var(--sage);
        border-radius: var(--radius-sm); padding: 14px 20px; margin-bottom: 28px;
        color: var(--espresso); display: flex; align-items: center; gap: 12px;
    }
    .alert-success-custom i { color: var(--sage); font-size: 1.1rem; }

    /* =================== HIDDEN =================== */
    .order-card.hidden { display: none; }

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

    /* =================== RESPONSIVE =================== */
    @media (max-width: 900px) {
        .stats-strip { grid-template-columns: repeat(2, 1fr); }
        .order-info-grid { grid-template-columns: repeat(2,1fr); }
    }
    @media (max-width: 640px) {
        .stats-strip { grid-template-columns: 1fr 1fr; gap: 12px; }
        .order-body { padding: 16px; }
        .order-topbar { padding: 14px 16px; }
        .order-product-row { flex-direction: column; align-items: flex-start; }
        .product-thumb { width: 70px; height: 70px; }
        .order-info-grid { grid-template-columns: 1fr; }
        .progress-tracker { display: none; }
        .navbar { padding: 0 20px; }
        .nav-links a:not(.active):not([href="login.php"]) { display: none; }
        .slide-cart { width: 92vw; right: -92vw; }
    }
    @media (max-width: 400px) {
        .stats-strip { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>

<!-- =================== NAVBAR =================== -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none;">
   <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($is_admin): ?><a href="admin.php">Admin</a><?php endif; ?>
            <div class="profile-dropdown">
                <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile" class="profile-pic"
                     onerror="this.src='images/profile.jpg'">
                <div class="dropdown-content">
                    <div class="dropdown-profile-info">
                        <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                        <div class="dropdown-profile-role"><?= htmlspecialchars($nav_role) ?></div>
                        <div class="dropdown-profile-balance">₱<?= number_format($nav_balance, 2) ?></div>
                    </div>
                    <a href="favorites.php"><i class="fas fa-heart"></i> My Favorites</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="purchase_history.php" class="active"><i class="fas fa-history"></i> Purchase History</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
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

<!-- =================== HERO =================== -->
<section class="page-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-inner">
        <div class="hero-label"><i class="fas fa-history"></i> ACCOUNT</div>
        <h1 class="hero-title">Your <em>Order</em><br>History</h1>
        <p class="hero-subtitle">Every purchase, neatly tracked. Review your orders, check delivery status, and revisit your favorites.</p>
    </div>
</section>

<!-- =================== MAIN =================== -->
<div class="main-content">

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert-success-custom">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['success_message']); ?>
        <?php unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Stats Strip -->
    <?php if ($total_orders > 0): ?>
    <div class="stats-strip">
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-text">
                <div class="stat-value"><?= $total_orders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-boxes"></i></div>
            <div class="stat-text">
                <div class="stat-value"><?= $total_items ?></div>
                <div class="stat-label">Items Bought</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-text">
                <div class="stat-value">₱<?= number_format($total_spent, 0) ?></div>
                <div class="stat-label">Total Spent</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-text">
                <div class="stat-value" style="font-size:1.1rem;"><?= $last_order ? date('M d', strtotime($last_order)) : '—' ?></div>
                <div class="stat-label">Last Order</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section Header + Filters -->
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-receipt"></i> Your Orders <span class="section-count"><?= $total_orders ?></span></h2>
    </div>

    <?php if ($total_orders > 0): ?>
    <!-- Filter Row -->
    <div class="filter-row">
        <div class="search-filter">
            <i class="fas fa-search"></i>
            <input type="text" id="orderSearch" placeholder="Search by product or order…">
        </div>
        <button class="filter-btn active" data-filter="all">All Orders</button>
        <button class="filter-btn" data-filter="Pending"><span class="dot" style="background:#e8b86d;"></span>Pending</button>
        <button class="filter-btn" data-filter="Processing"><span class="dot" style="background:#5dade2;"></span>Processing</button>
        <button class="filter-btn" data-filter="Shipped"><span class="dot" style="background:#ce93d8;"></span>Shipped</button>
        <button class="filter-btn" data-filter="Delivered"><span class="dot" style="background:#7a9e7e;"></span>Delivered</button>
        <button class="filter-btn" data-filter="Cancelled"><span class="dot" style="background:#e57373;"></span>Cancelled</button>
    </div>

    <div class="result-count-bar">
        <span class="result-count-txt" id="resultCountTxt">Showing <?= $total_orders ?> order<?= $total_orders !== 1 ? 's' : '' ?></span>
        <div class="result-divider"></div>
    </div>
    <?php endif; ?>

    <!-- Orders List -->
    <div class="orders-list" id="ordersList">
    <?php if ($result->num_rows > 0):
        $order_steps = ['Pending' => 0, 'Processing' => 1, 'Shipped' => 2, 'Delivered' => 3, 'Cancelled' => -1];
        $step_labels  = ['Pending', 'Processing', 'Shipped', 'Delivered'];
        $step_icons   = ['clock', 'cogs', 'truck', 'check-circle'];
        $i = 0;
        while ($order = $result->fetch_assoc()):
            $i++;
            $status       = $order['status'];
            $current_step = $order_steps[$status] ?? 0;
            $status_class = 'status-' . strtolower($status);
    ?>
    <div class="order-card" data-status="<?= htmlspecialchars($status) ?>"
         data-name="<?= htmlspecialchars(strtolower($order['product_name'])) ?>"
         data-orderid="<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>"
         style="animation-delay: <?= $i * 0.07 ?>s;">

        <!-- Top bar -->
        <div class="order-topbar">
            <div class="order-id">
                <i class="fas fa-hashtag"></i>
                Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
            </div>
            <div class="order-meta">
                <span class="order-date-txt"><i class="fas fa-calendar-alt"></i><?= date("M d, Y · h:i A", strtotime($order['transaction_date'])) ?></span>
                <span class="status-badge <?= $status_class ?>">
                    <i class="fas fa-<?php
                        if ($status === 'Pending')    echo 'clock';
                        elseif ($status === 'Processing') echo 'cogs';
                        elseif ($status === 'Shipped')    echo 'truck';
                        elseif ($status === 'Delivered')  echo 'check-circle';
                        elseif ($status === 'Cancelled')  echo 'times-circle';
                        else echo 'info-circle';
                    ?>"></i><?= htmlspecialchars($status) ?>
                </span>
            </div>
        </div>

        <!-- Body -->
        <div class="order-body">

            <!-- Product Row -->
            <div class="order-product-row">
                <div class="product-thumb">
                    <?php if (!empty($order['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($order['image']) ?>" alt="<?= htmlspecialchars($order['product_name']) ?>">
                    <?php else: ?>
                        <i class="fas fa-box no-img"></i>
                    <?php endif; ?>
                </div>
                <div class="product-info-col">
                    <div class="product-name-txt"><?= htmlspecialchars($order['product_name']) ?></div>
                    <div class="product-meta-row">
                        <span class="product-meta-item"><i class="fas fa-cube" style="margin-right:4px;color:var(--caramel);"></i>Qty: <strong><?= $order['quantity'] ?></strong></span>
                        <span class="product-meta-item"><i class="fas fa-tag" style="margin-right:4px;color:var(--caramel);"></i>Unit: <strong>₱<?= number_format($order['price'], 2) ?></strong></span>
                    </div>
                    <div class="price-display">₱<?= number_format($order['total_price'], 2) ?></div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="order-info-grid">
                <div class="info-chip">
                    <div class="info-chip-label"><i class="fas fa-credit-card"></i> Payment</div>
                    <div class="info-chip-value"><?= htmlspecialchars($order['payment_method']) ?></div>
                </div>
                <div class="info-chip">
                    <div class="info-chip-label"><i class="fas fa-receipt"></i> Total Amount</div>
                    <div class="info-chip-value">₱<?= number_format($order['total_price'], 2) ?></div>
                </div>
                <div class="info-chip">
                    <div class="info-chip-label"><i class="fas fa-truck"></i> Status</div>
                    <div class="info-chip-value">
                        <?php if ($is_admin): ?>
                            <form class="admin-update-form" method="POST">
                                <input type="hidden" name="transaction_id" value="<?= $order['id'] ?>">
                                <select name="status">
                                    <?php foreach (['Pending','Processing','Shipped','Delivered','Cancelled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit"><i class="fas fa-check"></i> Update</button>
                            </form>
                        <?php else: ?>
                            <?= htmlspecialchars($status) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Delivery -->
            <div class="delivery-row">
                <div class="icon-wrap"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <div class="delivery-label">Delivery Address</div>
                    <div class="delivery-addr"><?= htmlspecialchars($order['delivery_location']) ?></div>
                </div>
            </div>

            <!-- Progress Tracker (only for non-cancelled) -->
            <?php if ($status !== 'Cancelled'): ?>
            <div class="progress-tracker">
                <?php foreach ($step_labels as $si => $slabel): ?>
                <div class="progress-step <?= ($si < $current_step) ? 'done' : (($si === $current_step) ? 'active' : '') ?>">
                    <div class="step-dot">
                        <?php if ($si < $current_step): ?>
                            <i class="fas fa-check"></i>
                        <?php elseif ($si === $current_step): ?>
                            <i class="fas fa-<?= $step_icons[$si] ?>"></i>
                        <?php else: ?>
                            <i class="fas fa-<?= $step_icons[$si] ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="step-label"><?= $slabel ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endwhile; ?>

    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-shopping-bag empty-icon"></i>
        <h3>No Orders Yet</h3>
        <p>You haven't placed any orders. Explore our premium cat supplies and find something your feline will love!</p>
        <a href="shop.php" class="btn-shop-now"><i class="fas fa-store"></i> Browse the Shop</a>
    </div>
    <?php endif; ?>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex; gap:14px; flex-wrap:wrap; margin-top:40px;">
        <a href="shop.php" style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; border-radius:50px; background:linear-gradient(135deg,var(--espresso),var(--mahogany)); color:var(--honey); text-decoration:none; font-weight:700; font-size:0.9rem; transition:var(--transition); box-shadow:var(--shadow-sm);">
            <i class="fas fa-shopping-bag"></i> Continue Shopping
        </a>
        <a href="main.php" style="display:inline-flex; align-items:center; gap:8px; padding:12px 28px; border-radius:50px; background:var(--ivory); border:2px solid var(--mist); color:var(--mahogany); text-decoration:none; font-weight:600; font-size:0.9rem; transition:var(--transition);">
            <i class="fas fa-home"></i> Back to Home
        </a>
    </div>

</div>

<!-- =================== FOOTER =================== -->
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
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey);margin-right:8px;"></i>123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey);margin-right:8px;"></i>+1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey);margin-right:8px;"></i>meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey);margin-right:8px;"></i>Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* Profile dropdown */
const profileDropdown = document.querySelector('.profile-dropdown');
if (profileDropdown) {
    profileDropdown.querySelector('.profile-pic').addEventListener('click', e => {
        e.stopPropagation();
        profileDropdown.classList.toggle('open');
    });
    document.addEventListener('click', e => {
        if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('open');
    });
}

/* Scroll to top */
const topBtn = document.getElementById('scrollToTopBtn');
window.addEventListener('scroll', () => topBtn.classList.toggle('show', scrollY > 300));
topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* Filter buttons */
const filterBtns = document.querySelectorAll('.filter-btn');
const orderCards  = document.querySelectorAll('.order-card');
const resultTxt   = document.getElementById('resultCountTxt');
let activeFilter  = 'all';

function applyFilters() {
    const searchVal = (document.getElementById('orderSearch')?.value || '').toLowerCase().trim();
    const cleanSearchVal = searchVal.replace('#', '');
    let visible = 0;
    orderCards.forEach(card => {
        const matchStatus = activeFilter === 'all' || card.dataset.status === activeFilter;
        const matchSearch = !searchVal 
                         || card.dataset.name.includes(searchVal)
                         || card.dataset.orderid.includes(searchVal)
                         || card.dataset.orderid.includes(cleanSearchVal)
                         || card.querySelector('.order-id').textContent.toLowerCase().includes(searchVal);
        const show = matchStatus && matchSearch;
        card.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    if (resultTxt) resultTxt.textContent = `Showing ${visible} order${visible !== 1 ? 's' : ''}`;
}

filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        applyFilters();
    });
});

document.getElementById('orderSearch')?.addEventListener('input', applyFilters);

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

updateCartDisplay();
</script>
</body>
</html>