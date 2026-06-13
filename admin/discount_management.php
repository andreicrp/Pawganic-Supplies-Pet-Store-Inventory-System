<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    header("Location: main.php");
    exit;
}

// Create coupons table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,
    expiry_date DATETIME NOT NULL,
    status ENUM('active', 'expired', 'disabled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    usage_limit INT DEFAULT NULL,
    usage_count INT DEFAULT 0,
    description VARCHAR(255),
    FOREIGN KEY (created_by) REFERENCES users(id)
)";
$conn->query($create_table);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'generate') {
        $discount = floatval($_POST['discount_percent']);
        $expiry = $_POST['expiry_date'];
        $description = $_POST['description'] ?? '';
        if ($discount > 0 && $discount <= 100) {
            $code = strtoupper('PAWG' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            $stmt = $conn->prepare("INSERT INTO coupons (code, discount_percent, expiry_date, created_by, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsds", $code, $discount, $expiry, $user_id, $description);
            if ($stmt->execute()) {
                $message = "Coupon generated successfully: <strong>$code</strong>";
                $messageType = 'success';
            } else {
                $message = "Error generating coupon. Please try again.";
                $messageType = 'error';
            }
        } else {
            $message = "Discount must be between 1 and 100 percent.";
            $messageType = 'error';
        }
    } elseif ($action === 'create_custom') {
        $code = strtoupper(trim($_POST['coupon_code']));
        $discount = floatval($_POST['discount_percent']);
        $expiry = $_POST['expiry_date'];
        $description = $_POST['description'] ?? '';
        if (empty($code) || $discount <= 0 || $discount > 100) {
            $message = "Invalid coupon details. Please check and try again.";
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO coupons (code, discount_percent, expiry_date, created_by, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsds", $code, $discount, $expiry, $user_id, $description);
            if ($stmt->execute()) {
                $message = "Custom coupon created: <strong>$code</strong>";
                $messageType = 'success';
            } else {
                $message = "Coupon code already exists or an error occurred.";
                $messageType = 'error';
            }
        }
    } elseif ($action === 'edit') {
        $coupon_id = intval($_POST['coupon_id']);
        $discount = floatval($_POST['discount_percent']);
        $expiry = $_POST['expiry_date'];
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'];
        if ($discount > 0 && $discount <= 100) {
            $stmt = $conn->prepare("UPDATE coupons SET discount_percent = ?, expiry_date = ?, description = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ddssi", $discount, $expiry, $description, $status, $coupon_id);
            if ($stmt->execute()) {
                $message = "Coupon updated successfully.";
                $messageType = 'success';
            } else {
                $message = "Error updating coupon.";
                $messageType = 'error';
            }
        } else {
            $message = "Discount must be between 1 and 100 percent.";
            $messageType = 'error';
        }
    } elseif ($action === 'delete') {
        $coupon_id = intval($_POST['coupon_id']);
        $stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->bind_param("i", $coupon_id);
        if ($stmt->execute()) {
            $message = "Coupon deleted successfully.";
            $messageType = 'success';
        } else {
            $message = "Error deleting coupon.";
            $messageType = 'error';
        }
    }
}

// Fetch all coupons
$coupons = [];
$result = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $now = new DateTime();
        $expiry = new DateTime($row['expiry_date']);
        if ($expiry < $now && $row['status'] === 'active') {
            $row['status'] = 'expired';
            $conn->query("UPDATE coupons SET status = 'expired' WHERE id = " . $row['id']);
        }
        $coupons[] = $row;
    }
}

// Stats
$total_coupons   = count($coupons);
$active_coupons  = count(array_filter($coupons, fn($c) => $c['status'] === 'active'));
$expired_coupons = count(array_filter($coupons, fn($c) => $c['status'] === 'expired'));
$total_uses      = array_sum(array_column($coupons, 'usage_count'));

// Nav helpers (same pattern as shop.php)
$nav_username = $_SESSION['username'] ?? 'Admin';
$nav_role     = $_SESSION['role'] ?? 'admin';
$nav_balance  = $_SESSION['balance'] ?? 0;
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
$profile_pic_safe = htmlspecialchars($profile_pic);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discount Management — Pawganic Admin</title>
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
        color: var(--mahogany); text-decoration: none; padding: 8px 16px;
        border-radius: 50px; font-weight: 500; font-size: 0.9rem;
        letter-spacing: 0.3px; transition: var(--transition);
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
    .page-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 72px 5% 80px;
        position: relative;
        overflow: hidden;
    }

    .page-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                    radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
    }

    .page-hero::after {
        content: '';
        position: absolute;
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
        display: flex; align-items: flex-start; justify-content: space-between; gap: 40px;
        flex-wrap: wrap;
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
        font-size: clamp(2.4rem, 4vw, 3.6rem);
        font-weight: 900; color: var(--white); line-height: 1.1; margin-bottom: 14px;
    }
    .hero-title em { font-style: italic; color: var(--honey); }

    .hero-subtitle {
        color: rgba(255,255,255,0.65); font-size: 1rem; line-height: 1.7;
        max-width: 460px; margin-bottom: 0;
    }

    .hero-back-btn {
        display: inline-flex; align-items: center; gap: 9px;
        background: rgba(255,255,255,0.1); border: 1.5px solid rgba(255,255,255,0.25);
        color: rgba(255,255,255,0.85); padding: 10px 22px; border-radius: 50px;
        text-decoration: none; font-size: 0.88rem; font-weight: 600;
        transition: var(--transition); backdrop-filter: blur(8px);
        align-self: flex-start; margin-top: 10px;
    }
    .hero-back-btn:hover { background: var(--gold); border-color: var(--gold); color: var(--white); }

    /* ===================== STATS ROW ===================== */
    .stats-row {
        max-width: 1200px; margin: 40px auto 0; padding: 0 24px;
        display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
    }

    .stat-card {
        background: var(--ivory); border-radius: var(--radius);
        padding: 24px 22px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        display: flex; align-items: center; gap: 16px;
        transition: var(--transition);
        position: relative; overflow: hidden;
    }
    .stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 4px;
        background: linear-gradient(to bottom, var(--gold), var(--honey));
        border-radius: 4px 0 0 4px;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }

    .stat-icon {
        width: 52px; height: 52px; border-radius: var(--radius-sm); flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
    }
    .stat-icon.gold   { background: rgba(201,145,42,0.12); color: var(--gold); }
    .stat-icon.sage   { background: rgba(122,158,126,0.12); color: var(--sage); }
    .stat-icon.danger { background: rgba(192,57,43,0.1);   color: var(--danger); }
    .stat-icon.maho   { background: rgba(90,45,12,0.1);    color: var(--mahogany); }

    .stat-info {}
    .stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 2rem; font-weight: 700; color: var(--espresso); line-height: 1;
    }
    .stat-label { font-size: 0.8rem; color: var(--caramel); font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; }

    /* ===================== ALERT / MESSAGE ===================== */
    .alert-bar {
        max-width: 1200px; margin: 28px auto 0; padding: 0 24px;
    }
    .alert-msg {
        display: flex; align-items: center; gap: 12px;
        padding: 16px 20px; border-radius: var(--radius-sm);
        font-size: 0.95rem; font-weight: 500;
        animation: slideDown 0.4s ease;
    }
    @keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
    .alert-msg.success {
        background: rgba(122,158,126,0.12); color: #1a5c2e;
        border: 1px solid rgba(122,158,126,0.35); border-left: 4px solid var(--sage);
    }
    .alert-msg.error {
        background: rgba(192,57,43,0.08); color: var(--danger);
        border: 1px solid rgba(192,57,43,0.25); border-left: 4px solid var(--danger);
    }
    .alert-msg i { font-size: 1.1rem; flex-shrink: 0; }

    /* ===================== MAIN CONTENT ===================== */
    .main-content {
        max-width: 1200px; margin: 32px auto 60px; padding: 0 24px;
    }

    /* ===================== TABS ===================== */
    .tab-nav {
        display: flex; gap: 6px; margin-bottom: 28px; flex-wrap: wrap;
    }
    .tab-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 11px 24px; border-radius: 50px; border: 2px solid var(--mist);
        background: var(--ivory); color: var(--caramel); font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.88rem; cursor: pointer;
        transition: var(--transition); white-space: nowrap;
    }
    .tab-btn:hover { border-color: var(--gold); color: var(--gold); }
    .tab-btn.active {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-color: var(--espresso); color: var(--honey);
        box-shadow: var(--shadow-sm);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeTabIn 0.35s ease; }
    @keyframes fadeTabIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

    /* ===================== PANEL CARD ===================== */
    .panel {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
    }

    .panel-header {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 100%);
        padding: 22px 28px;
        display: flex; align-items: center; gap: 14px;
    }
    .panel-header-icon {
        width: 44px; height: 44px; border-radius: var(--radius-sm);
        background: rgba(255,255,255,0.12);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; color: var(--honey);
    }
    .panel-header-text h2 {
        font-family: 'Playfair Display', serif;
        font-size: 1.35rem; font-weight: 700; color: var(--white); margin: 0;
    }
    .panel-header-text p {
        font-size: 0.82rem; color: rgba(255,255,255,0.55); margin: 3px 0 0;
    }

    .panel-body { padding: 32px 28px; }

    /* ===================== FORM ELEMENTS ===================== */
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 22px; }

    .form-group { display: flex; flex-direction: column; gap: 8px; }

    .form-group label {
        font-weight: 600; font-size: 0.85rem; color: var(--espresso);
        text-transform: uppercase; letter-spacing: 0.6px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px 16px; border: 2px solid var(--mist);
        border-radius: var(--radius-sm); background: var(--cream);
        color: var(--espresso); font-family: 'DM Sans', sans-serif;
        font-size: 0.94rem; font-weight: 500; transition: var(--transition);
        outline: none;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }
    .form-group input::placeholder,
    .form-group textarea::placeholder { color: var(--caramel); opacity: 0.6; }

    .form-group textarea { resize: vertical; min-height: 80px; }

    .input-hint { font-size: 0.78rem; color: var(--caramel); }

    /* Discount preview badge */
    .discount-preview {
        display: flex; align-items: center; gap: 10px; margin-top: 6px;
    }
    .discount-preview-badge {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); padding: 4px 14px; border-radius: 50px;
        font-weight: 700; font-size: 0.85rem; opacity: 0;
        transition: opacity 0.3s ease;
    }
    .discount-preview-badge.visible { opacity: 1; }

    /* Form divider */
    .form-divider {
        border: none; border-top: 1px solid var(--mist); margin: 24px 0;
    }

    /* Submit btn */
    .btn-submit {
        display: inline-flex; align-items: center; gap: 10px;
        padding: 14px 32px; border: none; border-radius: 50px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.95rem; cursor: pointer;
        transition: var(--transition);
        box-shadow: 0 4px 14px rgba(201,145,42,0.3);
    }
    .btn-submit:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(44,26,14,0.25);
    }

    /* ===================== COUPON CARDS ===================== */
    .coupons-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;
    }

    .coupon-card {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden; transition: var(--transition);
        position: relative;
        animation: cardIn 0.4s ease both;
    }
    .coupon-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-md); border-color: rgba(201,145,42,0.3); }
    .coupon-card.expired { opacity: 0.7; }

    @keyframes cardIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

    /* Coupon top strip */
    .coupon-strip {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 100%);
        padding: 18px 22px;
        display: flex; align-items: center; justify-content: space-between;
        position: relative; overflow: hidden;
    }
    .coupon-strip::after {
        content: '';
        position: absolute; right: 0; top: 50%; transform: translateY(-50%);
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--cream); margin-right: -14px;
    }
    .coupon-strip::before {
        content: '';
        position: absolute; left: 0; top: 50%; transform: translateY(-50%);
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--cream); margin-left: -14px;
    }

    .coupon-code-display {
        font-family: 'Courier New', monospace; font-weight: 700;
        font-size: 1.35rem; color: var(--honey); letter-spacing: 3px;
        text-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .coupon-discount-display {
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem; font-weight: 900; color: var(--white);
        line-height: 1;
    }
    .coupon-discount-display span { font-size: 0.9rem; font-weight: 400; color: rgba(255,255,255,0.6); display: block; text-align: right; font-family: 'DM Sans', sans-serif; }

    .coupon-deco {
        position: absolute; top: -30px; left: 40%; opacity: 0.05;
        font-size: 6rem; color: var(--white); pointer-events: none;
    }

    /* Coupon body */
    .coupon-body { padding: 18px 22px; }

    .coupon-meta-row {
        display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;
        margin-bottom: 14px;
    }

    .meta-item {}
    .meta-label { font-size: 0.72rem; color: var(--caramel); font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 3px; }
    .meta-value { font-size: 0.88rem; font-weight: 700; color: var(--espresso); }

    .status-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-pill.active   { background: rgba(122,158,126,0.15); color: #1a5c2e; border: 1px solid rgba(122,158,126,0.3); }
    .status-pill.expired  { background: rgba(192,57,43,0.1);   color: var(--danger); border: 1px solid rgba(192,57,43,0.2); }
    .status-pill.disabled { background: rgba(90,45,12,0.08);   color: var(--caramel); border: 1px solid rgba(90,45,12,0.15); }

    .coupon-desc {
        font-size: 0.83rem; color: var(--caramel); line-height: 1.5;
        padding: 10px 14px; background: var(--cream); border-radius: var(--radius-sm);
        border-left: 3px solid var(--honey); margin-bottom: 14px;
    }

    /* Coupon dashed divider */
    .coupon-divider {
        border: none;
        border-top: 2px dashed var(--mist);
        margin: 12px 0;
    }

    .coupon-actions {
        display: flex; gap: 10px;
    }

    .btn-edit {
        flex: 1; padding: 10px;
        background: transparent; border: 2px solid var(--espresso); color: var(--espresso);
        border-radius: 50px; font-family: 'DM Sans', sans-serif; font-weight: 600;
        font-size: 0.85rem; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 7px;
    }
    .btn-edit:hover { background: var(--espresso); color: var(--honey); }

    .btn-delete {
        flex: 1; padding: 10px;
        background: transparent; border: 2px solid var(--danger); color: var(--danger);
        border-radius: 50px; font-family: 'DM Sans', sans-serif; font-weight: 600;
        font-size: 0.85rem; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 7px;
    }
    .btn-delete:hover { background: var(--danger); color: var(--white); }

    .btn-copy {
        padding: 10px 16px;
        background: rgba(201,145,42,0.1); border: 2px solid rgba(201,145,42,0.25);
        color: var(--gold); border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center;
        white-space: nowrap;
    }
    .btn-copy:hover { background: var(--gold); color: var(--white); border-color: var(--gold); }

    /* ===================== EMPTY STATE ===================== */
    .empty-state {
        text-align: center; padding: 64px 24px;
        background: var(--ivory); border-radius: var(--radius);
        border: 2px dashed var(--mist);
    }
    .empty-state i { font-size: 3.5rem; color: var(--mist); display: block; margin-bottom: 18px; }
    .empty-state h3 { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--mahogany); margin-bottom: 8px; }
    .empty-state p { color: var(--caramel); font-size: 0.92rem; }

    /* ===================== FILTER TOOLBAR (manage tab) ===================== */
    .manage-toolbar {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        margin-bottom: 24px;
    }
    .filter-pill {
        padding: 8px 18px; border-radius: 50px; border: 2px solid var(--mist);
        background: var(--ivory); color: var(--caramel); font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.82rem; cursor: pointer; transition: var(--transition);
    }
    .filter-pill:hover, .filter-pill.active { border-color: var(--gold); color: var(--gold); background: rgba(201,145,42,0.07); }
    .filter-count {
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--gold); color: var(--white);
        width: 20px; height: 20px; border-radius: 50%;
        font-size: 0.72rem; font-weight: 700; margin-left: 4px;
    }

    .manage-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .sort-select {
        padding: 8px 16px; border: 2px solid var(--mist); border-radius: 50px;
        background: var(--ivory); color: var(--espresso); font-family: 'DM Sans', sans-serif;
        font-weight: 500; font-size: 0.85rem; outline: none; cursor: pointer;
        transition: var(--transition);
    }
    .sort-select:focus { border-color: var(--gold); }

    /* ===================== EDIT MODAL ===================== */
    .modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(44,26,14,0.65); backdrop-filter: blur(6px);
        z-index: 2000; align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
    @keyframes slideUp { from { transform:translateY(40px); opacity:0; } to { transform:translateY(0); opacity:1; } }

    .modal-box {
        background: var(--ivory); border-radius: var(--radius);
        box-shadow: var(--shadow-lg); width: 90%; max-width: 480px;
        overflow: hidden; animation: slideUp 0.4s ease;
        border: 1px solid rgba(201,145,42,0.2);
        max-height: 90vh; overflow-y: auto;
    }

    .modal-head {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        padding: 24px 28px; display: flex; align-items: center; justify-content: space-between;
    }
    .modal-head h2 {
        font-family: 'Playfair Display', serif; font-size: 1.4rem;
        font-weight: 700; color: var(--white); margin: 0; display: flex; align-items: center; gap: 12px;
    }
    .modal-head h2 i { color: var(--honey); }

    .modal-close {
        background: rgba(255,255,255,0.1); border: none; color: var(--honey);
        width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition);
    }
    .modal-close:hover { background: rgba(255,255,255,0.2); transform: rotate(90deg); }

    .modal-body { padding: 28px; }

    .modal-footer {
        padding: 18px 28px; border-top: 1px solid var(--mist);
        background: var(--cream); display: flex; gap: 12px;
    }

    .btn-cancel {
        padding: 12px 24px; background: var(--mist); border: 2px solid transparent;
        color: var(--mahogany); border-radius: 50px; font-family: 'DM Sans', sans-serif;
        font-weight: 600; cursor: pointer; transition: var(--transition);
    }
    .btn-cancel:hover { background: var(--cream); border-color: var(--mist); }

    .btn-save {
        flex: 1; padding: 12px 24px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        border: none; color: var(--espresso); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; cursor: pointer;
        transition: var(--transition); display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-save:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); }

    /* ===================== TOAST ===================== */
    .toast-container { position: fixed; bottom: 30px; left: 30px; z-index: 3000; }
    .custom-toast {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-radius: var(--radius-sm); font-size: 0.95rem; padding: 14px 20px;
        box-shadow: var(--shadow-lg); min-width: 260px; max-width: 320px;
        color: var(--cream); border-left: 4px solid var(--gold);
    }
    .custom-toast .toast-body { padding: 0; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .custom-toast .btn-close { filter: invert(1) brightness(0.8); flex-shrink: 0; }

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

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 900px) {
        .stats-row { grid-template-columns: repeat(2, 1fr); }
        .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        .coupon-meta-row { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
        .stats-row { grid-template-columns: 1fr 1fr; gap: 12px; }
        .coupons-grid { grid-template-columns: 1fr; }
        .navbar { padding: 0 20px; }
        .nav-links a:not(.active) { display: none; }
        .panel-body { padding: 20px 16px; }
        .main-content { padding: 0 16px; }
    }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40" class="logo-img">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <a href="admin.php" class="active">Admin</a>
        <div class="profile-dropdown">
            <img src="<?= $profile_pic_safe ?>" alt="Profile" class="profile-pic" onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                    <div class="dropdown-profile-role"><?= htmlspecialchars($nav_role) ?></div>
                    <div class="dropdown-profile-balance">₱<?= number_format($nav_balance, 2) ?></div>
                </div>
                <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast-container">
    <div id="toastMessage" class="toast text-white border-0 custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-body">
            Done!
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ===================== HERO ===================== -->
<section class="page-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-label"><i class="fas fa-ticket-alt"></i> Admin · Discount Management</div>
            <h1 class="hero-title">Coupon <em>Control</em><br>Center</h1>
            <p class="hero-subtitle">Create, schedule, and manage promotional coupons for your Pawganic storefront. Track redemptions and keep your offers fresh.</p>
        </div>
        <a href="admin.php" class="hero-back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</section>

<!-- ===================== STATS ROW ===================== -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-ticket-alt"></i></div>
        <div class="stat-info">
            <div class="stat-num"><?= $total_coupons ?></div>
            <div class="stat-label">Total Coupons</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon sage"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <div class="stat-num"><?= $active_coupons ?></div>
            <div class="stat-label">Active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-num"><?= $expired_coupons ?></div>
            <div class="stat-label">Expired</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon maho"><i class="fas fa-fire"></i></div>
        <div class="stat-info">
            <div class="stat-num"><?= $total_uses ?></div>
            <div class="stat-label">Total Uses</div>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert-bar">
    <div class="alert-msg <?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <span><?= $message ?></span>
    </div>
</div>
<?php endif; ?>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="main-content">

    <!-- Tab Nav -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('generate', this)">
            <i class="fas fa-magic"></i> Auto-Generate
        </button>
        <button class="tab-btn" onclick="switchTab('custom', this)">
            <i class="fas fa-pen"></i> Custom Coupon
        </button>
        <button class="tab-btn" onclick="switchTab('manage', this)">
            <i class="fas fa-layer-group"></i> Manage All
            <?php if ($total_coupons > 0): ?><span class="filter-count" style="margin-left:4px;"><?= $total_coupons ?></span><?php endif; ?>
        </button>
    </div>

    <!-- ── TAB 1: AUTO-GENERATE ── -->
    <div id="tab-generate" class="tab-content active">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fas fa-magic"></i></div>
                <div class="panel-header-text">
                    <h2>Auto-Generate Coupon</h2>
                    <p>Pawganic creates a unique code automatically — you set the rules.</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="discount_percent">Discount Amount</label>
                            <input type="number" id="discount_percent" name="discount_percent" min="1" max="100" step="0.01" placeholder="e.g., 15" required oninput="updatePreview('preview1', this.value)">
                            <div class="discount-preview">
                                <span class="input-hint">% off the cart total</span>
                                <span id="preview1" class="discount-preview-badge">15% OFF</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="expiry_date">Expires On</label>
                            <input type="datetime-local" id="expiry_date" name="expiry_date" required>
                            <span class="input-hint">Coupon auto-expires after this date</span>
                        </div>
                        <div class="form-group">
                            <label for="gen_desc">Label <span style="font-weight:400;opacity:.6;">(optional)</span></label>
                            <input type="text" id="gen_desc" name="description" placeholder="e.g., Summer Sale">
                            <span class="input-hint">Internal note for your reference</span>
                        </div>
                    </div>
                    <hr class="form-divider">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-magic"></i> Generate Coupon Code
                        </button>
                        <span style="font-size:0.83rem;color:var(--caramel);">Code will begin with <strong>PAWG</strong> followed by 8 random characters.</span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ── TAB 2: CUSTOM ── -->
    <div id="tab-custom" class="tab-content">
        <div class="panel">
            <div class="panel-header">
                <div class="panel-header-icon"><i class="fas fa-pen"></i></div>
                <div class="panel-header-text">
                    <h2>Create Custom Coupon</h2>
                    <p>Design your own memorable code — great for campaigns and partnerships.</p>
                </div>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create_custom">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="coupon_code">Coupon Code</label>
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="e.g., PAWSUMMER20" required
                                   style="text-transform:uppercase; font-family:'Courier New',monospace; letter-spacing:2px; font-weight:700;"
                                   oninput="this.value=this.value.toUpperCase()">
                            <span class="input-hint">Letters and numbers only. Will be uppercased automatically.</span>
                        </div>
                        <div class="form-group">
                            <label for="discount_custom">Discount Amount</label>
                            <input type="number" id="discount_custom" name="discount_percent" min="1" max="100" step="0.01" placeholder="e.g., 20" required oninput="updatePreview('preview2', this.value)">
                            <div class="discount-preview">
                                <span class="input-hint">% off the cart total</span>
                                <span id="preview2" class="discount-preview-badge">20% OFF</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="expiry_custom">Expires On</label>
                            <input type="datetime-local" id="expiry_custom" name="expiry_date" required>
                        </div>
                        <div class="form-group">
                            <label for="desc_custom">Label <span style="font-weight:400;opacity:.6;">(optional)</span></label>
                            <input type="text" id="desc_custom" name="description" placeholder="e.g., New customer welcome offer">
                        </div>
                    </div>
                    <hr class="form-divider">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> Create Coupon
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: MANAGE ── -->
    <div id="tab-manage" class="tab-content">

        <!-- Toolbar -->
        <div class="manage-toolbar">
            <button class="filter-pill active" onclick="filterCoupons('all', this)">All <span class="filter-count"><?= $total_coupons ?></span></button>
            <button class="filter-pill" onclick="filterCoupons('active', this)">Active <span class="filter-count"><?= $active_coupons ?></span></button>
            <button class="filter-pill" onclick="filterCoupons('expired', this)">Expired <span class="filter-count"><?= $expired_coupons ?></span></button>
            <button class="filter-pill" onclick="filterCoupons('disabled', this)">Disabled</button>
            <div class="manage-right">
                <select class="sort-select" onchange="sortCoupons(this.value)">
                    <option value="newest">Newest first</option>
                    <option value="discount_high">Highest discount</option>
                    <option value="discount_low">Lowest discount</option>
                    <option value="uses">Most used</option>
                </select>
            </div>
        </div>

        <?php if (empty($coupons)): ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <h3>No coupons yet</h3>
            <p>Switch to the Generate or Custom tab to create your first coupon.</p>
        </div>
        <?php else: ?>
        <div class="coupons-grid" id="couponsGrid">
            <?php foreach ($coupons as $c):
                $expiry_dt = new DateTime($c['expiry_date']);
                $now_dt    = new DateTime();
                $diff      = $now_dt->diff($expiry_dt);
                $expiry_label = $c['status'] === 'expired'
                    ? 'Expired ' . $diff->days . 'd ago'
                    : ($diff->days === 0 ? 'Expires today' : 'Expires in ' . $diff->days . 'd');
            ?>
            <div class="coupon-card <?= $c['status'] ?>"
                 data-status="<?= $c['status'] ?>"
                 data-discount="<?= $c['discount_percent'] ?>"
                 data-uses="<?= $c['usage_count'] ?>"
                 data-created="<?= strtotime($c['created_at']) ?>">

                <div class="coupon-strip">
                    <div class="coupon-deco"><i class="fas fa-paw"></i></div>
                    <div class="coupon-code-display"><?= htmlspecialchars($c['code']) ?></div>
                    <div class="coupon-discount-display">
                        <?= number_format($c['discount_percent'], 0) ?>%
                        <span>DISCOUNT</span>
                    </div>
                </div>

                <div class="coupon-body">
                    <div class="coupon-meta-row">
                        <div class="meta-item">
                            <div class="meta-label">Status</div>
                            <div class="meta-value">
                                <span class="status-pill <?= $c['status'] ?>">
                                    <i class="fas fa-<?= $c['status'] === 'active' ? 'check' : ($c['status'] === 'expired' ? 'times' : 'ban') ?>"></i>
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Used</div>
                            <div class="meta-value"><?= $c['usage_count'] ?> times</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Expiry</div>
                            <div class="meta-value" style="font-size:0.8rem;"><?= $expiry_label ?></div>
                        </div>
                    </div>

                    <?php if (!empty($c['description'])): ?>
                    <div class="coupon-desc">
                        <i class="fas fa-sticky-note" style="color:var(--honey);margin-right:6px;"></i><?= htmlspecialchars($c['description']) ?>
                    </div>
                    <?php endif; ?>

                    <div style="font-size:0.75rem;color:var(--caramel);margin-bottom:10px;">
                        <i class="fas fa-calendar-plus" style="margin-right:5px;"></i>Created <?= date('M d, Y', strtotime($c['created_at'])) ?>
                        &nbsp;·&nbsp;
                        <i class="fas fa-hourglass-end" style="margin-right:5px;"></i><?= date('M d, Y H:i', strtotime($c['expiry_date'])) ?>
                    </div>

                    <hr class="coupon-divider">

                    <div class="coupon-actions">
                        <button class="btn-copy" onclick="copyCode('<?= htmlspecialchars($c['code']) ?>', this)" title="Copy code">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn-edit" onclick="openEditModal(<?= $c['id'] ?>, '<?= addslashes($c['code']) ?>', <?= $c['discount_percent'] ?>, '<?= $c['expiry_date'] ?>', '<?= $c['status'] ?>', '<?= addslashes(htmlspecialchars($c['description'])) ?>')">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <form method="POST" style="flex:1;display:contents;" onsubmit="return confirm('Delete coupon <?= htmlspecialchars($c['code']) ?>? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="coupon_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-delete"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== EDIT MODAL ===================== -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="fas fa-pen"></i> Edit Coupon</h2>
            <button class="modal-close" onclick="closeEditModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_coupon_id" name="coupon_id">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:18px;">
                    <label>Coupon Code</label>
                    <input type="text" id="edit_code" readonly style="background:var(--mist);cursor:not-allowed;font-family:'Courier New',monospace;letter-spacing:2px;font-weight:700;">
                </div>
                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label for="edit_discount">Discount (%)</label>
                        <input type="number" id="edit_discount" name="discount_percent" min="1" max="100" step="0.01" required oninput="updatePreview('previewEdit', this.value)">
                        <div class="discount-preview">
                            <span id="previewEdit" class="discount-preview-badge">10% OFF</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="edit_expiry">Expires On</label>
                    <input type="datetime-local" id="edit_expiry" name="expiry_date" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Label</label>
                    <input type="text" id="edit_description" name="description" placeholder="Optional note">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
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
                <a href="admin.php">Admin Dashboard</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Admin Tools</h3>
            <div class="footer-links">
                <a href="discount_management.php">Discount Management</a>
                <a href="admin.php">Dashboard</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey);margin-right:8px;"></i>123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-envelope" style="color:var(--honey);margin-right:8px;"></i>meow@pawganic.com</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===================== TABS ===================== */
function switchTab(name, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

/* ===================== DISCOUNT PREVIEW ===================== */
function updatePreview(id, val) {
    const badge = document.getElementById(id);
    if (!badge) return;
    const v = parseFloat(val);
    if (v > 0 && v <= 100) {
        badge.textContent = v % 1 === 0 ? v + '% OFF' : v.toFixed(2) + '% OFF';
        badge.classList.add('visible');
    } else {
        badge.classList.remove('visible');
    }
}

/* ===================== EDIT MODAL ===================== */
function openEditModal(id, code, discount, expiry, status, description) {
    document.getElementById('edit_coupon_id').value = id;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_discount').value = discount;
    document.getElementById('edit_expiry').value = expiry.replace(' ', 'T').substring(0, 16);
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_description').value = description;
    updatePreview('previewEdit', discount);
    document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

/* ===================== COPY CODE ===================== */
function copyCode(code, btn) {
    navigator.clipboard.writeText(code).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = 'var(--sage)';
        btn.style.color = 'var(--white)';
        btn.style.borderColor = 'var(--sage)';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 1800);
    });
}

/* ===================== FILTER & SORT ===================== */
function filterCoupons(status, btn) {
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.coupon-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

function sortCoupons(val) {
    const grid = document.getElementById('couponsGrid');
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.coupon-card'));
    cards.sort((a, b) => {
        if (val === 'discount_high') return parseFloat(b.dataset.discount) - parseFloat(a.dataset.discount);
        if (val === 'discount_low')  return parseFloat(a.dataset.discount) - parseFloat(b.dataset.discount);
        if (val === 'uses')          return parseInt(b.dataset.uses) - parseInt(a.dataset.uses);
        return parseInt(b.dataset.created) - parseInt(a.dataset.created); // newest
    });
    cards.forEach(c => grid.appendChild(c));
}

/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function() {
    const pd = document.querySelector('.profile-dropdown');
    if (pd) {
        pd.querySelector('.profile-pic').addEventListener('click', e => {
            e.stopPropagation();
            pd.classList.toggle('open');
        });
        document.addEventListener('click', e => {
            if (!pd.contains(e.target)) pd.classList.remove('open');
        });
    }

    // Set min datetime
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const iso = now.toISOString().slice(0, 16);
    ['expiry_date', 'expiry_custom'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.min = iso;
    });

    // Scroll to top
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.pageYOffset > 300));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // Stagger coupon card animations
    document.querySelectorAll('.coupon-card').forEach((c, i) => {
        c.style.animationDelay = (i * 0.07) + 's';
    });
});
</script>
</body>
</html>