<?php
require_once __DIR__ . '/../config/db.php';
// Session is started in db.php

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? 'Administrator';
$stmt = $conn->prepare("SELECT role, username, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    header("Location: main.php");
    exit;
}

// ============ FEATURED PRODUCTS AJAX HANDLER ============
if (isset($_POST['action']) && $_POST['action'] === 'save_featured_products') {
    header('Content-Type: application/json');
    $ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : [];
    $ids = array_slice(array_map('intval', $ids), 0, 3);

    // Clear current featured products
    $conn->query("DELETE FROM featured_products");

    $ok = true;
    foreach ($ids as $order => $pid) {
        if ($pid <= 0) continue;
        $stmt = $conn->prepare("INSERT INTO featured_products (product_id, sort_order) VALUES (?, ?)");
        $stmt->bind_param("ii", $pid, $order);
        if (!$stmt->execute()) $ok = false;
        $stmt->close();
    }
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Featured products updated!' : 'Error saving.']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_featured_products') {
    header('Content-Type: application/json');
    $result = $conn->query("
        SELECT p.id, p.name, p.category, p.price, p.stock, p.image
        FROM products p ORDER BY p.name ASC
    ");
    $products = [];
    while ($row = $result->fetch_assoc()) $products[] = $row;
    $featured = $conn->query("SELECT product_id FROM featured_products ORDER BY sort_order ASC");
    $featured_ids = [];
    while ($row = $featured->fetch_assoc()) $featured_ids[] = (int)$row['product_id'];
    echo json_encode(['products' => $products, 'featured_ids' => $featured_ids]);
    exit;
}

// ============ NOTIFICATIONS AJAX HANDLER ============
if (isset($_GET['action']) && $_GET['action'] === 'fetch_notifications') {
    header('Content-Type: application/json');
    $limit = 12;
    $last_read = $_SESSION['admin_notif_last_read'] ?? '2000-01-01 00:00:00';

    $stmt = $conn->prepare("
        SELECT t.id, t.transaction_date, t.total_price, t.status, t.quantity,
               u.username, u.profile_pic, p.name AS product_name, p.image AS product_image
        FROM transactions t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN products p ON t.product_id = p.id
        ORDER BY t.transaction_date DESC
        LIMIT ?
    ");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifs = [];
    while ($row = $res->fetch_assoc()) $notifs[] = $row;
    $stmt->close();

    // Unread = transactions newer than last_read
    $unread_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM transactions WHERE transaction_date > ?");
    $unread_stmt->bind_param('s', $last_read);
    $unread_stmt->execute();
    $unread_count = $unread_stmt->get_result()->fetch_assoc()['cnt'];
    $unread_stmt->close();

    // Latest counts for live updates
    $total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
    $low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock > 0 AND stock <= 10")->fetch_assoc()['count'];
    $total_value = $conn->query("SELECT SUM(price * stock) as value FROM products")->fetch_assoc()['value'] ?? 0;
    $total_purchases = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'] ?? 0;
    $recent_purchases = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE DATE(transaction_date) = CURDATE()")->fetch_assoc()['count'] ?? 0;
    $out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock = 0")->fetch_assoc()['count'] ?? 0;

    echo json_encode([
        'notifications' => $notifs,
        'unread' => (int)$unread_count,
        'last_read' => $last_read,
        'low_stock' => (int)$low_stock,
        'out_of_stock' => (int)$out_of_stock,
        'total_products' => (int)$total_products,
        'total_value' => (float)$total_value,
        'total_purchases' => (int)$total_purchases,
        'recent_purchases' => (int)$recent_purchases
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'mark_notifications_read') {
    header('Content-Type: application/json');
    $_SESSION['admin_notif_last_read'] = date('Y-m-d H:i:s');
    echo json_encode(['success' => true]);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_activity') {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 8;
    $offset = ($page - 1) * $limit;
    
    $total_res = $conn->query("SELECT ((SELECT COUNT(*) FROM transactions) + (SELECT COUNT(*) FROM product_reviews) + (SELECT COUNT(*) FROM coupons) + (SELECT COUNT(*) FROM users WHERE role = 'user')) as total")->fetch_assoc();
    $total_items = intval($total_res['total']);
    $total_pages = max(1, ceil($total_items / $limit));
    
    $stmt = $conn->prepare("
        (SELECT t.transaction_date AS created_at, 'purchase' AS event_type, u.username, p.name AS target_name, t.total_price AS amount_value 
         FROM transactions t 
         LEFT JOIN users u ON t.user_id = u.id 
         LEFT JOIN products p ON t.product_id = p.id)
        UNION ALL
        (SELECT r.created_at AS created_at, 'review' AS event_type, r.username, p.name AS target_name, r.rating AS amount_value 
         FROM product_reviews r 
         LEFT JOIN products p ON r.product_id = p.id)
        UNION ALL
        (SELECT c.created_at AS created_at, 'coupon' AS event_type, u.username, c.code AS target_name, c.discount_percent AS amount_value 
         FROM coupons c 
         LEFT JOIN users u ON c.created_by = u.id)
        UNION ALL
        (SELECT u.created_at AS created_at, 'register' AS event_type, u.username, NULL AS target_name, NULL AS amount_value 
         FROM users u 
         WHERE u.role = 'user')
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $activity = $stmt->get_result();
    $stmt->close();
    
    $html = '';
    if ($activity && $activity->num_rows > 0) {
        while ($act = $activity->fetch_assoc()) {
            $ts = strtotime($act['created_at'] ?? 'now');
            $diff = time() - $ts;
            $ago = $diff < 60 ? 'Just now' : ($diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : date('M d · H:i', $ts)));
            
            $dot_color = 'green';
            $icon = '<i class="fas fa-info-circle"></i>';
            $text = '';

            switch ($act['event_type']) {
                case 'purchase':
                    $dot_color = 'green';
                    $icon = '<i class="fas fa-shopping-cart" style="color:var(--sage); margin-right:4px;"></i>';
                    $text = "<strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> ordered <strong>" . htmlspecialchars($act['target_name'] ?? 'Product') . "</strong> — ₱" . number_format($act['amount_value'], 2);
                    break;
                case 'review':
                    $dot_color = 'amber';
                    $icon = '<i class="fas fa-star" style="color:var(--gold); margin-right:4px;"></i>';
                    $text = "<strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> reviewed <strong>" . htmlspecialchars($act['target_name'] ?? 'Product') . "</strong> — " . number_format($act['amount_value'], 1) . " stars";
                    break;
                case 'coupon':
                    $dot_color = 'amber';
                    $icon = '<i class="fas fa-ticket-alt" style="color:var(--gold); margin-right:4px;"></i>';
                    $text = "Admin <strong>" . htmlspecialchars($act['username'] ?? 'Admin') . "</strong> created coupon <strong>" . htmlspecialchars($act['target_name']) . "</strong> — " . number_format($act['amount_value'], 0) . "% off";
                    break;
                case 'register':
                    $dot_color = 'blue';
                    $icon = '<i class="fas fa-user-plus" style="color:#3b82f6; margin-right:4px;"></i>';
                    $text = "New customer <strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> registered an account";
                    break;
            }
            
            $html .= '<div class="timeline-item">';
            $html .= '  <div class="timeline-dot ' . $dot_color . '"></div>';
            $html .= '  <div>';
            $html .= '    <div class="timeline-text">' . $icon . ' ' . $text . '</div>';
            $html .= '    <div class="timeline-time">' . $ago . '</div>';
            $html .= '  </div>';
            $html .= '</div>';
        }
    } else {
        $html .= '<div class="timeline-item">';
        $html .= '  <div class="timeline-dot amber"></div>';
        $html .= '  <div>';
        $html .= '    <div class="timeline-text">No recent timeline activity to display.</div>';
        $html .= '    <div class="timeline-time">Check back soon</div>';
        $html .= '  </div>';
        $html .= '</div>';
    }

    if ($page === 1) {
        $low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock > 0 AND stock <= 10")->fetch_assoc()['count'];
        $html .= '<div class="timeline-item">';
        $html .= '  <div class="timeline-dot green"></div>';
        $html .= '  <div>';
        $html .= '    <div class="timeline-text"><i class="fas fa-sign-in-alt" style="color:var(--sage); margin-right:4px;"></i> Admin <strong>' . htmlspecialchars($admin_username) . '</strong> logged in</div>';
        $html .= '    <div class="timeline-time">Just now</div>';
        $html .= '  </div>';
        $html .= '</div>';
        if ($low_stock > 0) {
            $html .= '<div class="timeline-item">';
            $html .= '  <div class="timeline-dot amber"></div>';
            $html .= '  <div>';
            $html .= '    <div class="timeline-text"><i class="fas fa-exclamation-triangle" style="color:var(--gold); margin-right:4px;"></i> <strong>' . $low_stock . ' product' . ($low_stock !== 1 ? 's' : '') . '</strong> running low on stock — restock recommended</div>';
            $html .= '    <div class="timeline-time">System alert</div>';
            $html .= '  </div>';
            $html .= '</div>';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'page' => $page,
        'total_pages' => $total_pages,
        'total_items' => $total_items
    ]);
    exit;
}

$profile_pic = $user['profile_pic'] ?? 'defaultprofile.jpg';
$profile_pic_safe = htmlspecialchars($profile_pic);

// Initial load recent activity pagination
$page = 1;
$limit = 8;
$offset = 0;

$total_res = $conn->query("SELECT ((SELECT COUNT(*) FROM transactions) + (SELECT COUNT(*) FROM product_reviews) + (SELECT COUNT(*) FROM coupons) + (SELECT COUNT(*) FROM users WHERE role = 'user')) as total")->fetch_assoc();
$total_items = intval($total_res['total']);
$total_pages = max(1, ceil($total_items / $limit));

$stmt = $conn->prepare("
    (SELECT t.transaction_date AS created_at, 'purchase' AS event_type, u.username, p.name AS target_name, t.total_price AS amount_value 
     FROM transactions t 
     LEFT JOIN users u ON t.user_id = u.id 
     LEFT JOIN products p ON t.product_id = p.id)
    UNION ALL
    (SELECT r.created_at AS created_at, 'review' AS event_type, r.username, p.name AS target_name, r.rating AS amount_value 
     FROM product_reviews r 
     LEFT JOIN products p ON r.product_id = p.id)
    UNION ALL
    (SELECT c.created_at AS created_at, 'coupon' AS event_type, u.username, c.code AS target_name, c.discount_percent AS amount_value 
     FROM coupons c 
     LEFT JOIN users u ON c.created_by = u.id)
    UNION ALL
    (SELECT u.created_at AS created_at, 'register' AS event_type, u.username, NULL AS target_name, NULL AS amount_value 
     FROM users u 
     WHERE u.role = 'user')
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$activity = $stmt->get_result();
$stmt->close();

// Get metrics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock > 0 AND stock <= 10")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(price * stock) as value FROM products")->fetch_assoc()['value'] ?? 0;

// Additional metrics
$total_purchases = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'] ?? 0;
$recent_purchases = $conn->query("SELECT COUNT(*) as count FROM transactions WHERE DATE(transaction_date) = CURDATE()")->fetch_assoc()['count'] ?? 0;
$out_of_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock = 0")->fetch_assoc()['count'] ?? 0;
$active_discounts = $conn->query("SELECT COUNT(*) as count FROM coupons WHERE status = 'active'")->fetch_assoc()['count'] ?? 0;

// Fetch sales trend data for Chart.js
$sales_trend = $conn->query("
    SELECT DATE(transaction_date) as date, SUM(total_price) as revenue 
    FROM transactions 
    GROUP BY DATE(transaction_date) 
    ORDER BY DATE(transaction_date) DESC 
    LIMIT 7
");
$chart_dates = [];
$chart_revenues = [];
if ($sales_trend) {
    while ($row = $sales_trend->fetch_assoc()) {
        $chart_dates[] = date('M d', strtotime($row['date']));
        $chart_revenues[] = (float)$row['revenue'];
    }
    $chart_dates = array_reverse($chart_dates);
    $chart_revenues = array_reverse($chart_revenues);
}

// Fetch category counts for Chart.js
$category_dist = $conn->query("
    SELECT category, COUNT(*) as count 
    FROM products 
    GROUP BY category
");
$chart_categories = [];
$chart_cat_counts = [];
if ($category_dist) {
    while ($row = $category_dist->fetch_assoc()) {
        $chart_categories[] = $row['category'];
        $chart_cat_counts[] = (int)$row['count'];
    }
}

// Handle backup
$backup_message = '';
$backup_class = '';

if (isset($_GET['backup_deleted'])) {
    $backup_message = 'Backup deleted successfully.';
    $backup_class = 'success';
} elseif (isset($_GET['error'])) {
    $backup_message = 'Error deleting backup. Please try again.';
    $backup_class = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $timestamp = date('Y-m-d_H-i-s');
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) @mkdir($backup_dir, 0777, true);
    if (!is_writable($backup_dir)) {
        $backup_message = 'Backup directory is not writable.';
        $backup_class = 'warning';
    } else {
        $db_backup_file = $backup_dir . 'db_backup_' . $timestamp . '.sql';
        $backup_success = false;
        $host = DB_HOST; $user = DB_USER; $pass = DB_PASS; $dbname = DB_NAME;
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $command = "mysqldump -u $user -p$pass $dbname > " . escapeshellarg($db_backup_file);
            @exec($command, $output, $return_var);
            if ($return_var === 0 && file_exists($db_backup_file) && filesize($db_backup_file) > 0) $backup_success = true;
        }
        if (!$backup_success) {
            $tables = [];
            $res = $conn->query("SHOW TABLES");
            while ($row = $res->fetch_row()) $tables[] = $row[0];
            $sql = "-- Pawganic Supplies DB Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            foreach ($tables as $table) {
                $res = $conn->query("SHOW CREATE TABLE $table");
                $row = $res->fetch_row();
                $sql .= "\n" . $row[1] . ";\n\n";
                $res = $conn->query("SELECT * FROM $table");
                while ($data_row = $res->fetch_assoc()) {
                    $sql .= "INSERT INTO $table VALUES (";
                    $first = true;
                    foreach ($data_row as $value) {
                        if (!$first) $sql .= ", ";
                        $sql .= "'" . $conn->real_escape_string($value) . "'";
                        $first = false;
                    }
                    $sql .= ");\n";
                }
            }
            if (file_put_contents($db_backup_file, $sql)) $backup_success = true;
        }
        if ($backup_success && file_exists($db_backup_file)) {
            $backup_message = 'Database backup created — ' . date('F d, Y H:i:s');
            $backup_class = 'success';
        } else {
            $backup_message = 'Error creating backup. Check permissions.';
            $backup_class = 'warning';
        }
    }
}

$backups = [];
$backup_dir = 'backups/';
if (is_dir($backup_dir)) {
    $backup_files = array_reverse(glob($backup_dir . 'db_backup_*.sql'));
    foreach ($backup_files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'date'     => date('M d, Y · H:i', filemtime($file)),
            'size'     => round(filesize($file) / 1024, 2) . ' KB',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
        color: var(--mahogany);
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 500;
        font-size: 0.9rem;
        transition: var(--transition);
    }
    .nav-links a:hover, .nav-links a.active {
        background: var(--gold);
        color: var(--white);
    }

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

    .dropdown-content a {
        display: flex; align-items: center; gap: 10px;
        color: var(--espresso); text-decoration: none; padding: 12px 16px;
        font-size: 0.9rem; transition: var(--transition);
    }
    .dropdown-content a:hover { background: var(--cream); color: var(--mahogany); padding-left: 22px; }
    .dropdown-content a i { width: 18px; color: var(--caramel); }

    /* ===================== HERO BANNER ===================== */
    .admin-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 72px 5% 80px;
        position: relative;
        overflow: hidden;
    }

    .admin-hero::before {
        content: '';
        position: absolute; inset: 0;
        background:
            radial-gradient(ellipse at 80% 40%, rgba(201,145,42,0.22) 0%, transparent 60%),
            radial-gradient(ellipse at 5% 80%, rgba(122,158,126,0.12) 0%, transparent 50%);
    }

    .admin-hero::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }

    .hero-deco {
        position: absolute; border-radius: 50%; opacity: 0.06; background: var(--honey);
    }
    .hero-deco-1 { width: 420px; height: 420px; top: -120px; right: -100px; }
    .hero-deco-2 { width: 240px; height: 240px; bottom: 10px; left: 4%; }
    .hero-deco-3 { width: 130px; height: 130px; top: 20px; left: 35%; opacity: 0.04; }

    .hero-inner {
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
        color: rgba(255,255,255,0.62);
        font-size: 1rem;
        line-height: 1.7;
        max-width: 440px;
        margin-bottom: 28px;
    }

    .hero-quick-stats {
        display: flex; gap: 32px; flex-wrap: wrap;
    }

    .hero-qstat {
        text-align: center;
    }
    .hero-qstat-num {
        font-family: 'Playfair Display', serif;
        font-size: 2rem; font-weight: 700; color: var(--honey); line-height: 1;
    }
    .hero-qstat-label {
        font-size: 0.72rem; color: rgba(255,255,255,0.48);
        text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;
    }

    .hero-badge-group { display: flex; gap: 10px; flex-wrap: wrap; }

    .hero-badge {
        background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
        color: rgba(255,255,255,0.8); padding: 8px 16px; border-radius: 50px;
        font-size: 0.8rem; font-weight: 500; backdrop-filter: blur(8px);
        transition: var(--transition);
    }
    .hero-badge:hover { background: var(--gold); border-color: var(--gold); color: var(--white); }
    .hero-badge i { margin-right: 6px; }

    /* ===================== MAIN CONTENT ===================== */
    .content-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 48px 24px 64px;
        width: 100%;
    }

    /* ===================== SECTION HEADING ===================== */
    .section-heading {
        display: flex; align-items: center; gap: 14px;
        margin-bottom: 24px;
    }
    .section-heading-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        display: flex; align-items: center; justify-content: center;
        color: var(--honey); font-size: 1rem; flex-shrink: 0;
        box-shadow: var(--shadow-sm);
    }
    .section-heading h2 {
        font-family: 'Playfair Display', serif;
        font-size: 1.35rem; font-weight: 700; color: var(--espresso); margin: 0;
    }
    .section-heading span {
        font-size: 0.82rem; color: var(--caramel); display: block; margin-top: 2px; font-weight: 500;
    }

    /* ===================== METRICS GRID ===================== */
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 18px;
        margin-bottom: 48px;
    }

    .metric-card {
        background: var(--ivory);
        border-radius: var(--radius);
        padding: 26px 22px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.08);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        cursor: default;
    }

    .metric-card::before {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--gold), var(--honey));
        transform: scaleX(0);
        transition: transform 0.35s ease;
        transform-origin: left;
    }
    .metric-card:hover::before { transform: scaleX(1); }

    .metric-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-md);
        border-color: rgba(201,145,42,0.2);
    }

    .metric-card-deco {
        position: absolute;
        top: -16px; right: -16px;
        width: 80px; height: 80px; border-radius: 50%;
        background: rgba(201,145,42,0.06);
    }

    .metric-icon-wrap {
        width: 48px; height: 48px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; margin-bottom: 16px;
    }

    .metric-value {
        font-family: 'Playfair Display', serif;
        font-size: 2rem; font-weight: 700; color: var(--espresso);
        line-height: 1; margin-bottom: 6px;
    }
    .metric-label {
        font-size: 0.78rem; color: var(--caramel);
        text-transform: uppercase; letter-spacing: 1px; font-weight: 600;
    }
    .metric-trend {
        margin-top: 10px; font-size: 0.8rem; font-weight: 600;
        display: flex; align-items: center; gap: 4px;
    }
    .metric-trend.up { color: var(--sage); }
    .metric-trend.warn { color: var(--gold); }
    .metric-trend.danger { color: var(--danger); }

    /* Icon colors */
    .icon-blue   { background: rgba(59,130,246,0.1); color: #3b82f6; }
    .icon-green  { background: rgba(122,158,126,0.15); color: var(--sage); }
    .icon-amber  { background: rgba(201,145,42,0.12); color: var(--gold); }
    .icon-red    { background: rgba(192,57,43,0.1); color: var(--danger); }
    .icon-indigo { background: rgba(99,102,241,0.1); color: #6366f1; }
    .icon-teal   { background: rgba(20,184,166,0.1); color: #14b8a6; }
    .icon-brown  { background: rgba(90,45,12,0.1); color: var(--mahogany); }
    .icon-pink   { background: rgba(236,72,153,0.1); color: #ec4899; }

    /* ===================== TWO-COL LAYOUT ===================== */
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 48px;
    }

    /* ===================== CARD PANEL ===================== */
    .panel {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.08);
        overflow: hidden;
        transition: var(--transition);
    }

    .panel:hover {
        box-shadow: var(--shadow-md);
        border-color: rgba(201,145,42,0.15);
    }

    .panel-header {
        padding: 22px 26px 18px;
        border-bottom: 1px solid var(--mist);
        display: flex; align-items: center; justify-content: space-between;
    }

    .panel-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.05rem; font-weight: 700; color: var(--espresso);
        display: flex; align-items: center; gap: 10px;
    }
    .panel-title i { color: var(--gold); font-size: 1rem; }

    .panel-body { padding: 22px 26px; }

    /* ===================== BACKUP SECTION ===================== */
    .backup-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; border-radius: 50px;
        padding: 13px 24px; font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.95rem; cursor: pointer;
        transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
        box-shadow: 0 4px 14px rgba(44,26,14,0.25);
    }
    .backup-btn:hover {
        background: var(--gold); color: var(--white);
        transform: translateY(-2px); box-shadow: var(--shadow-md);
    }

    .backup-info-list {
        list-style: none; padding: 0; margin: 18px 0 0;
    }
    .backup-info-list li {
        padding: 8px 0;
        font-size: 0.87rem; color: var(--caramel);
        border-bottom: 1px solid var(--mist);
        display: flex; align-items: center; gap: 10px;
    }
    .backup-info-list li:last-child { border-bottom: none; }
    .backup-info-list li i { color: var(--gold); width: 16px; font-size: 0.85rem; }

    .backups-scroll { max-height: 280px; overflow-y: auto; }
    .backups-scroll::-webkit-scrollbar { width: 4px; }
    .backups-scroll::-webkit-scrollbar-track { background: var(--mist); border-radius: 4px; }
    .backups-scroll::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }

    .backup-item {
        background: var(--cream);
        border-radius: var(--radius-sm);
        padding: 14px 16px; margin-bottom: 10px;
        display: flex; justify-content: space-between; align-items: center;
        border: 1px solid rgba(201,145,42,0.1);
        transition: var(--transition);
    }
    .backup-item:hover { border-color: rgba(201,145,42,0.3); background: var(--mist); }

    .backup-item-date { font-weight: 600; color: var(--espresso); font-size: 0.85rem; margin-bottom: 3px; }
    .backup-item-meta { font-size: 0.77rem; color: var(--caramel); }

    .backup-item-actions { display: flex; gap: 8px; }

    .btn-dl {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; padding: 6px 12px;
        border-radius: 8px; font-size: 0.78rem; font-weight: 700;
        cursor: pointer; transition: var(--transition); text-decoration: none;
        display: flex; align-items: center; gap: 5px;
    }
    .btn-dl:hover { background: var(--espresso); color: var(--honey); }

    .btn-del {
        background: rgba(192,57,43,0.1); color: var(--danger);
        border: 1px solid rgba(192,57,43,0.2); padding: 6px 12px;
        border-radius: 8px; font-size: 0.78rem; font-weight: 700;
        cursor: pointer; transition: var(--transition); text-decoration: none;
        display: flex; align-items: center; gap: 5px;
    }
    .btn-del:hover { background: var(--danger); color: var(--white); }

    .no-backups {
        text-align: center; padding: 32px 16px;
        color: var(--caramel); font-size: 0.85rem;
    }
    .no-backups i { font-size: 2.5rem; color: var(--mist); display: block; margin-bottom: 12px; }

    /* ===================== QUICK ACTIONS ===================== */
    .actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .action-btn {
        display: flex; align-items: center; gap: 14px;
        padding: 18px 20px;
        background: var(--cream);
        border: 2px solid transparent;
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: var(--espresso);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .action-btn::before {
        content: '';
        position: absolute; inset: 0;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        opacity: 0; transition: opacity 0.3s ease;
        pointer-events: none;
    }

    .action-btn:hover { border-color: var(--espresso); transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .action-btn:hover::before { opacity: 1; }
    .action-btn:hover .action-icon, .action-btn:hover .action-label, .action-btn:hover .action-desc { color: var(--honey); position: relative; z-index: 1; }

    .action-icon {
        width: 42px; height: 42px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; flex-shrink: 0;
        transition: var(--transition); position: relative; z-index: 1;
    }
    .action-label {
        font-weight: 700; font-size: 0.92rem; color: var(--espresso);
        display: block; transition: var(--transition); position: relative; z-index: 1;
    }
    .action-desc {
        font-size: 0.77rem; color: var(--caramel);
        display: block; margin-top: 2px; transition: var(--transition); position: relative; z-index: 1;
    }

    .action-btn.logout {
        border-color: rgba(192,57,43,0.2);
        background: rgba(192,57,43,0.04);
        grid-column: span 2;
    }
    .action-btn.logout:hover { border-color: var(--danger); }
    .action-btn.logout:hover::before { background: linear-gradient(135deg, var(--danger), #8b1a1a); }
    .action-btn.logout:hover .action-icon, .action-btn.logout:hover .action-label, .action-btn.logout:hover .action-desc { color: var(--white); }

    /* ===================== SYSTEM STATUS ===================== */
    .status-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 0; border-bottom: 1px solid var(--mist);
    }
    .status-item:last-child { border-bottom: none; }
    .status-label { font-size: 0.87rem; color: var(--caramel); font-weight: 500; display: flex; align-items: center; gap: 8px; }
    .status-label i { color: var(--gold); width: 16px; }
    .status-badge {
        padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.5px;
    }
    .status-badge.ok     { background: rgba(122,158,126,0.15); color: var(--sage); }
    .status-badge.warn   { background: rgba(201,145,42,0.15); color: var(--gold); }
    .status-badge.error  { background: rgba(192,57,43,0.12); color: var(--danger); }

    /* ===================== ACTIVITY / TIMELINE ===================== */
    .timeline-item {
        display: flex; gap: 16px; padding: 14px 0;
        border-bottom: 1px solid var(--mist);
    }
    .timeline-item:last-child { border-bottom: none; }
    .timeline-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        margin-top: 5px;
    }
    .timeline-dot.green { background: var(--sage); box-shadow: 0 0 0 3px rgba(122,158,126,0.2); }
    .timeline-dot.amber { background: var(--gold); box-shadow: 0 0 0 3px rgba(201,145,42,0.2); }
    .timeline-dot.red   { background: var(--danger); box-shadow: 0 0 0 3px rgba(192,57,43,0.2); }
    .timeline-dot.blue  { background: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
    .timeline-text { font-size: 0.87rem; color: var(--espresso); font-weight: 500; line-height: 1.5; }
    .timeline-time { font-size: 0.75rem; color: var(--caramel); margin-top: 3px; }

    /* ===================== PAGINATION ===================== */
    .custom-pagination .page-item {
        margin: 0 3px;
    }
    .custom-pagination .page-link {
        border-radius: 50% !important;
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        background: var(--ivory);
        border: 1px solid rgba(201,145,42,0.2);
        color: var(--espresso);
        font-size: 0.85rem; font-weight: 600;
        transition: var(--transition);
        padding: 0;
    }
    .custom-pagination .page-link:hover {
        background: var(--cream);
        border-color: var(--gold);
        color: var(--mahogany);
    }
    .custom-pagination .page-item.active .page-link {
        background: var(--gold) !important;
        border-color: var(--gold) !important;
        color: var(--white) !important;
        box-shadow: 0 2px 8px rgba(201,145,42,0.3);
    }

    /* ===================== PROGRESS BARS ===================== */
    .progress-row {
        margin-bottom: 18px;
    }
    .progress-row:last-child { margin-bottom: 0; }
    .progress-meta {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 7px;
    }
    .progress-meta-label { font-size: 0.85rem; font-weight: 600; color: var(--espresso); }
    .progress-meta-val { font-size: 0.82rem; color: var(--caramel); font-weight: 500; }
    .progress-track {
        height: 8px; border-radius: 50px; background: var(--mist); overflow: hidden;
    }
    .progress-fill {
        height: 100%; border-radius: 50px;
        background: linear-gradient(90deg, var(--gold), var(--honey));
        transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .progress-fill.green { background: linear-gradient(90deg, var(--sage), var(--sage-light)); }
    .progress-fill.red { background: linear-gradient(90deg, var(--danger), #e57373); }

    /* ===================== TOAST ===================== */
    .toast-container-custom {
        position: fixed; top: 88px; right: 24px; z-index: 9999;
        display: flex; flex-direction: column; gap: 10px;
    }

    .toast-item {
        background: var(--ivory);
        border-radius: var(--radius-sm);
        padding: 16px 20px;
        box-shadow: var(--shadow-lg);
        display: flex; align-items: center; gap: 12px;
        min-width: 300px; max-width: 380px;
        animation: slideInRight 0.35s ease;
        border: 1px solid rgba(201,145,42,0.12);
    }

    @keyframes slideInRight {
        from { transform: translateX(400px); opacity: 0; }
        to   { transform: translateX(0); opacity: 1; }
    }

    .toast-item.success { border-left: 4px solid var(--sage); }
    .toast-item.warning { border-left: 4px solid var(--gold); }
    .toast-icon-t { font-size: 1.2rem; flex-shrink: 0; }
    .toast-item.success .toast-icon-t { color: var(--sage); }
    .toast-item.warning .toast-icon-t { color: var(--gold); }
    .toast-text { flex: 1; font-size: 0.88rem; color: var(--espresso); font-weight: 500; }
    .toast-close-btn {
        background: none; border: none; cursor: pointer; color: var(--caramel);
        font-size: 1rem; padding: 2px; transition: color 0.2s;
        display: flex; align-items: center;
    }
    .toast-close-btn:hover { color: var(--espresso); }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso);
        color: rgba(255,255,255,0.7);
        padding: 60px 5% 28px;
        margin-top: auto;
        position: relative;
    }
    footer::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey), var(--gold), var(--caramel));
    }
    .footer-content {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 36px; margin-bottom: 36px; max-width: 1200px; margin-left: auto; margin-right: auto;
    }
    .footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.1rem; color: var(--honey); margin-bottom: 18px; }
    .footer-section p { font-size: 0.86rem; line-height: 1.8; margin-bottom: 10px; }
    .footer-links { display: flex; flex-direction: column; gap: 8px; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.86rem; transition: var(--transition); }
    .footer-links a:hover { color: var(--honey); padding-left: 4px; }
    .social-links { display: flex; gap: 10px; margin-top: 14px; }
    .social-links a {
        width: 38px; height: 38px; border-radius: 50%;
        background: rgba(201,145,42,0.12); border: 1px solid rgba(201,145,42,0.25);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.85rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }
    .copyright { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 18px; text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.3); max-width: 1200px; margin: 0 auto; }

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
    .fade-up { animation: fadeUp 0.6s ease both; }
    .fade-up:nth-child(1) { animation-delay: 0.05s; }
    .fade-up:nth-child(2) { animation-delay: 0.10s; }
    .fade-up:nth-child(3) { animation-delay: 0.15s; }
    .fade-up:nth-child(4) { animation-delay: 0.20s; }
    .fade-up:nth-child(5) { animation-delay: 0.25s; }
    .fade-up:nth-child(6) { animation-delay: 0.30s; }
    .fade-up:nth-child(7) { animation-delay: 0.35s; }
    .fade-up:nth-child(8) { animation-delay: 0.40s; }
    @keyframes fadeUp { from { opacity:0; transform:translateY(22px); } to { opacity:1; transform:translateY(0); } }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 900px) {
        .two-col { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .navbar { padding: 0 20px; }
        .metrics-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .actions-grid { grid-template-columns: 1fr; }
        .action-btn.logout { grid-column: span 1; }
        .hero-inner { flex-direction: column; gap: 20px; }
        .admin-hero { padding: 56px 24px 68px; }
        .hero-quick-stats { gap: 20px; }
    }
    @media (max-width: 480px) {
        .metrics-grid { grid-template-columns: 1fr 1fr; }
        .content-wrapper { padding: 32px 16px 48px; }
    }

    /* ===================== NOTIFICATION BELL ===================== */
    .notif-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        margin-right: 4px;
    }

    .notif-bell-btn {
        width: 40px; height: 40px;
        border-radius: 50%;
        background: rgba(201,145,42,0.08);
        border: 1.5px solid rgba(201,145,42,0.18);
        color: var(--mahogany);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        transition: var(--transition);
        position: relative;
        outline: none;
    }
    .notif-bell-btn:hover {
        background: var(--gold);
        border-color: var(--gold);
        color: var(--white);
        transform: scale(1.08);
    }
    .notif-bell-btn.has-unread {
        background: rgba(201,145,42,0.12);
        border-color: var(--gold);
        animation: bellShake 2.4s ease infinite;
    }
    @keyframes bellShake {
        0%,100% { transform: rotate(0); }
        5%       { transform: rotate(-14deg); }
        10%      { transform: rotate(14deg); }
        15%      { transform: rotate(-10deg); }
        20%      { transform: rotate(10deg); }
        25%      { transform: rotate(0); }
    }

    .notif-badge {
        position: absolute;
        top: -4px; right: -4px;
        min-width: 18px; height: 18px;
        background: var(--danger);
        color: #fff;
        border-radius: 50px;
        font-size: 0.65rem; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        padding: 0 4px;
        border: 2px solid var(--ivory);
        line-height: 1;
        pointer-events: none;
        animation: badgePop 0.35s cubic-bezier(0.4,0,0.2,1);
    }
    @keyframes badgePop { from { transform: scale(0); } to { transform: scale(1); } }
    .notif-badge.hidden { display: none; }

    .notif-panel {
        position: absolute;
        top: calc(100% + 14px);
        right: 0;
        width: 360px;
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(201,145,42,0.15);
        z-index: 1100;
        overflow: hidden;
        display: none;
        transform-origin: top right;
    }
    .notif-panel.open {
        display: block;
        animation: notifSlideIn 0.25s cubic-bezier(0.4,0,0.2,1);
    }
    @keyframes notifSlideIn {
        from { opacity:0; transform: scale(0.94) translateY(-8px); }
        to   { opacity:1; transform: scale(1) translateY(0); }
    }

    .notif-panel-header {
        padding: 16px 20px 12px;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        display: flex; align-items: center; justify-content: space-between;
    }
    .notif-panel-title {
        font-family: 'Playfair Display', serif;
        font-size: 1rem; font-weight: 700;
        color: var(--honey);
        display: flex; align-items: center; gap: 8px;
    }
    .notif-panel-title i { font-size: 0.9rem; }
    .notif-mark-read-btn {
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.2);
        color: rgba(255,255,255,0.78);
        border-radius: 50px;
        padding: 4px 12px;
        font-size: 0.72rem; font-weight: 600;
        cursor: pointer; transition: var(--transition);
        letter-spacing: 0.3px;
    }
    .notif-mark-read-btn:hover { background: var(--gold); border-color: var(--gold); color: #fff; }

    .notif-list {
        max-height: 380px;
        overflow-y: auto;
    }
    .notif-list::-webkit-scrollbar { width: 4px; }
    .notif-list::-webkit-scrollbar-track { background: var(--mist); }
    .notif-list::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }

    .notif-item {
        display: flex; gap: 12px; align-items: flex-start;
        padding: 13px 18px;
        border-bottom: 1px solid var(--mist);
        transition: var(--transition);
        cursor: default;
        position: relative;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: var(--cream); }
    .notif-item.unread { background: rgba(201,145,42,0.05); }
    .notif-item.unread::before {
        content: '';
        position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
        background: var(--gold);
    }

    .notif-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.75rem; color: var(--espresso);
        flex-shrink: 0;
        overflow: hidden;
    }
    .notif-avatar img {
        width: 100%; height: 100%; object-fit: cover;
    }

    .notif-content { flex: 1; min-width: 0; }
    .notif-text {
        font-size: 0.82rem; color: var(--espresso); font-weight: 500;
        line-height: 1.45;
    }
    .notif-text strong { color: var(--mahogany); }
    .notif-text .notif-product {
        color: var(--gold); font-weight: 700;
    }
    .notif-meta {
        display: flex; align-items: center; gap: 8px;
        margin-top: 5px;
    }
    .notif-time {
        font-size: 0.73rem; color: var(--caramel);
    }
    .notif-status {
        font-size: 0.68rem; font-weight: 700; padding: 2px 8px;
        border-radius: 50px; letter-spacing: 0.3px;
    }
    .notif-status.pending  { background: rgba(201,145,42,0.15); color: var(--gold); }
    .notif-status.completed { background: rgba(122,158,126,0.15); color: var(--sage); }
    .notif-status.cancelled { background: rgba(192,57,43,0.12); color: var(--danger); }

    .notif-price {
        font-family: 'Playfair Display', serif;
        font-size: 0.85rem; font-weight: 700;
        color: var(--espresso); white-space: nowrap; flex-shrink: 0;
    }

    .notif-empty {
        text-align: center; padding: 40px 20px;
        color: var(--caramel);
    }
    .notif-empty i { font-size: 2rem; display: block; margin-bottom: 10px; color: var(--mist); }

    .notif-footer {
        padding: 12px 18px;
        border-top: 1px solid var(--mist);
        background: var(--cream);
        text-align: center;
    }
    .notif-footer a {
        font-size: 0.8rem; font-weight: 700;
        color: var(--caramel); text-decoration: none;
        display: inline-flex; align-items: center; gap: 6px;
        transition: var(--transition);
    }
    .notif-footer a:hover { color: var(--mahogany); }

    /* ===================== FEATURED PRODUCTS MODAL ===================== */
    .fp-modal-overlay {
        display: none;
        position: fixed; inset: 0; z-index: 9998;
        background: rgba(28, 14, 6, 0.72);
        backdrop-filter: blur(6px);
        align-items: center; justify-content: center;
    }
    .fp-modal-overlay.open { display: flex; animation: fadeInOverlay 0.3s ease; }
    @keyframes fadeInOverlay { from { opacity:0; } to { opacity:1; } }

    .fp-modal {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(201,145,42,0.15);
        width: 92%; max-width: 820px;
        max-height: 88vh;
        display: flex; flex-direction: column;
        animation: slideUpModal 0.35s cubic-bezier(0.4,0,0.2,1);
        overflow: hidden;
    }
    @keyframes slideUpModal { from { opacity:0; transform: translateY(32px); } to { opacity:1; transform: translateY(0); } }

    .fp-modal-header {
        padding: 24px 28px 18px;
        border-bottom: 1px solid var(--mist);
        display: flex; align-items: center; justify-content: space-between;
        background: linear-gradient(135deg, var(--cream), var(--ivory));
        flex-shrink: 0;
    }
    .fp-modal-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.3rem; font-weight: 700; color: var(--espresso);
        display: flex; align-items: center; gap: 10px;
    }
    .fp-modal-title i { color: var(--gold); }
    .fp-modal-subtitle { font-size: 0.82rem; color: var(--caramel); margin-top: 3px; }

    .fp-modal-close {
        background: var(--mist); border: none; color: var(--mahogany);
        width: 36px; height: 36px; border-radius: 50%; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; transition: var(--transition);
    }
    .fp-modal-close:hover { background: var(--danger); color: var(--white); }

    .fp-modal-body {
        padding: 24px 28px;
        overflow-y: auto; flex: 1;
    }
    .fp-modal-body::-webkit-scrollbar { width: 4px; }
    .fp-modal-body::-webkit-scrollbar-track { background: var(--mist); border-radius: 4px; }
    .fp-modal-body::-webkit-scrollbar-thumb { background: var(--gold); border-radius: 4px; }

    .fp-selected-strip {
        display: flex; gap: 12px; flex-wrap: wrap;
        margin-bottom: 22px; padding: 14px 16px;
        background: var(--cream); border-radius: var(--radius-sm);
        border: 2px dashed rgba(201,145,42,0.3);
        min-height: 64px; align-items: center;
    }
    .fp-selected-label {
        font-size: 0.78rem; font-weight: 700; color: var(--caramel);
        text-transform: uppercase; letter-spacing: 1px;
        width: 100%; margin-bottom: 4px;
    }
    .fp-slot {
        display: flex; align-items: center; gap: 8px;
        background: var(--ivory); border: 1px solid rgba(201,145,42,0.2);
        border-radius: 50px; padding: 6px 14px;
        font-size: 0.82rem; font-weight: 600; color: var(--espresso);
        transition: var(--transition);
    }
    .fp-slot.empty {
        border-style: dashed; color: var(--caramel); font-weight: 400; font-style: italic;
        background: transparent;
    }
    .fp-slot-remove {
        background: none; border: none; color: var(--danger);
        cursor: pointer; padding: 0 2px; font-size: 0.8rem;
        display: flex; align-items: center; transition: transform 0.2s;
    }
    .fp-slot-remove:hover { transform: scale(1.2); }

    .fp-search-bar {
        display: flex; gap: 10px; margin-bottom: 16px;
    }
    .fp-search-bar input {
        flex: 1; padding: 10px 16px;
        border: 1.5px solid var(--mist); border-radius: 50px;
        background: var(--white); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
        outline: none; transition: var(--transition);
    }
    .fp-search-bar input:focus { border-color: var(--gold); }

    .fp-products-list {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }

    .fp-product-item {
        background: var(--cream); border-radius: var(--radius-sm);
        border: 2px solid transparent;
        padding: 14px; cursor: pointer;
        transition: var(--transition); position: relative;
        display: flex; flex-direction: column; gap: 6px;
    }
    .fp-product-item:hover { border-color: var(--gold); background: var(--mist); transform: translateY(-2px); }
    .fp-product-item.selected {
        border-color: var(--sage); background: rgba(122,158,126,0.08);
    }
    .fp-product-item.disabled { opacity: 0.45; pointer-events: none; }

    .fp-product-name { font-weight: 700; font-size: 0.88rem; color: var(--espresso); line-height: 1.3; }
    .fp-product-meta { font-size: 0.76rem; color: var(--caramel); }
    .fp-product-price { font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 700; color: var(--gold); }
    .fp-product-check {
        position: absolute; top: 10px; right: 10px;
        width: 22px; height: 22px; border-radius: 50%;
        background: var(--sage); color: var(--white);
        display: none; align-items: center; justify-content: center;
        font-size: 0.7rem;
    }
    .fp-product-item.selected .fp-product-check { display: flex; }

    .fp-modal-footer {
        padding: 18px 28px;
        border-top: 1px solid var(--mist);
        display: flex; align-items: center; justify-content: space-between; gap: 14px;
        background: var(--cream); flex-shrink: 0;
    }
    .fp-count-badge {
        font-size: 0.85rem; color: var(--caramel); font-weight: 500;
    }
    .fp-count-badge strong { color: var(--espresso); }
    .fp-save-btn {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); border: none; border-radius: 50px;
        padding: 12px 28px; font-family: 'DM Sans', sans-serif;
        font-weight: 700; font-size: 0.9rem; cursor: pointer;
        transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        box-shadow: 0 4px 14px rgba(44,26,14,0.22);
    }
    .fp-save-btn:hover { background: var(--gold); color: var(--white); transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .fp-save-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .fp-spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: var(--honey); border-radius: 50%; animation: spin 0.7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
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
        <a href="admin.php" class="active">Admin</a>
        <!-- NOTIFICATION BELL -->
        <div class="notif-wrapper" id="notifWrapper">
            <button class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifPanel()" title="Purchase Notifications" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notif-badge hidden" id="notifBadge">0</span>
            </button>
            <div class="notif-panel" id="notifPanel">
                <div class="notif-panel-header">
                    <div class="notif-panel-title"><i class="fas fa-shopping-cart"></i> Purchase Alerts</div>
                    <button class="notif-mark-read-btn" onclick="markAllRead()">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        Loading...
                    </div>
                </div>
                <div class="notif-footer">
                    <a href="admin_purchases.php"><i class="fas fa-th-list"></i> View All Orders</a>
                </div>
            </div>
        </div>
        <div class="profile-dropdown">
            <img src="<?= $profile_pic_safe ?>" alt="Admin" class="profile-pic" onerror="this.src='defaultprofile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($user['username'] ?? $admin_username) ?></div>
                    <div class="dropdown-profile-role"><i class="fas fa-shield-alt" style="color:var(--gold); margin-right:5px;"></i>Administrator</div>
                </div>
                <a href="index.php"><i class="fas fa-box-open"></i>Inventory</a>
                <a href="manage_accounts.php"><i class="fas fa-users-cog"></i>Manage Users</a>
                <a href="profile.php"><i class="fas fa-user"></i>My Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Sign Out</a>
            </div>
        </div>
    </div>
</div>

<!-- ===================== HERO ===================== -->
<section class="admin-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-deco hero-deco-3"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-label"><i class="fas fa-shield-alt"></i> ADMINISTRATOR PANEL</div>
            <h1 class="hero-title">Welcome back,<br><em><?= htmlspecialchars($admin_username) ?></em></h1>
            <p class="hero-subtitle">Your store is running smoothly. Here's an overview of everything happening at Pawganic Supplies.</p>
            <div class="hero-quick-stats">
                <div class="hero-qstat">
                    <div class="hero-qstat-num" id="hero-total-products-count"><?= $total_products ?></div>
                    <div class="hero-qstat-label">Products</div>
                </div>
                <div class="hero-qstat">
                    <div class="hero-qstat-num"><?= $total_users ?></div>
                    <div class="hero-qstat-label">Users</div>
                </div>
                <div class="hero-qstat">
                    <div class="hero-qstat-num" id="hero-total-value-count">₱<?= number_format($total_value / 1000, 1) ?>k</div>
                    <div class="hero-qstat-label">Inv. Value</div>
                </div>
            </div>
        </div>
        <div class="hero-badge-group">
            <span class="hero-badge"><i class="fas fa-clock"></i><?= date('M d, Y') ?></span>
            <span class="hero-badge"><i class="fas fa-store"></i>Store Online</span>
            <span class="hero-badge"><i class="fas fa-database"></i><?= count($backups) ?> Backup<?= count($backups) !== 1 ? 's' : '' ?></span>
            <span class="hero-badge"><i class="fas fa-exclamation-triangle"></i><span id="hero-low-stock-count"><?= $low_stock ?></span> Low Stock</span>
        </div>
    </div>
</section>

<!-- ===================== TOAST ===================== -->
<div class="toast-container-custom" id="toastContainer"></div>

<?php if ($backup_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast(<?= json_encode($backup_message) ?>, <?= json_encode($backup_class) ?>);
});
</script>
<?php endif; ?>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="content-wrapper">

    <!-- METRICS -->
    <div class="section-heading fade-up">
        <div class="section-heading-icon"><i class="fas fa-chart-bar"></i></div>
        <div>
            <h2>Store Overview</h2>
            <span>Live metrics from your database</span>
        </div>
    </div>

    <div class="metrics-grid">
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-blue"><i class="fas fa-box-open"></i></div>
            <div class="metric-value" id="metric-total-products-val"><?= $total_products ?></div>
            <div class="metric-label">Total Products</div>
            <div class="metric-trend up"><i class="fas fa-arrow-up"></i> Active listings</div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-green"><i class="fas fa-users"></i></div>
            <div class="metric-value"><?= $total_users ?></div>
            <div class="metric-label">Active Users</div>
            <div class="metric-trend up"><i class="fas fa-arrow-up"></i> Registered customers</div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-amber"><i class="fas fa-peso-sign"></i></div>
            <div class="metric-value" id="metric-total-value-val">₱<?= number_format($total_value, 0) ?></div>
            <div class="metric-label">Inventory Value</div>
            <div class="metric-trend up"><i class="fas fa-coins"></i> Total stock value</div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-red"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="metric-value" id="metric-low-stock-val"><?= $low_stock ?></div>
            <div class="metric-label">Low Stock Items</div>
            <div class="metric-trend <?= $low_stock > 0 ? 'warn' : 'up' ?>" id="metric-low-stock-trend">
                <i class="fas fa-<?= $low_stock > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i>
                <span><?= $low_stock > 0 ? 'Needs attention' : 'All stocked up' ?></span>
            </div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-indigo"><i class="fas fa-shopping-bag"></i></div>
            <div class="metric-value" id="metric-total-purchases-val"><?= $total_purchases ?></div>
            <div class="metric-label">Total Orders</div>
            <div class="metric-trend up"><i class="fas fa-arrow-up"></i> All time orders</div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-teal"><i class="fas fa-calendar-day"></i></div>
            <div class="metric-value" id="metric-recent-purchases-val"><?= $recent_purchases ?></div>
            <div class="metric-label">Orders Today</div>
            <div class="metric-trend <?= $recent_purchases > 0 ? 'up' : 'warn' ?>" id="metric-recent-purchases-trend">
                <i class="fas fa-<?= $recent_purchases > 0 ? 'fire' : 'clock' ?>"></i>
                <span><?= $recent_purchases > 0 ? 'Active today' : 'No orders yet' ?></span>
            </div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-red"><i class="fas fa-ban"></i></div>
            <div class="metric-value" id="metric-out-stock-val"><?= $out_of_stock ?></div>
            <div class="metric-label">Out of Stock</div>
            <div class="metric-trend <?= $out_of_stock > 0 ? 'danger' : 'up' ?>" id="metric-out-stock-trend">
                <i class="fas fa-<?= $out_of_stock > 0 ? 'times-circle' : 'check-circle' ?>"></i>
                <span><?= $out_of_stock > 0 ? 'Restock needed' : 'All available' ?></span>
            </div>
        </div>
        <div class="metric-card fade-up">
            <div class="metric-card-deco"></div>
            <div class="metric-icon-wrap icon-pink"><i class="fas fa-ticket-alt"></i></div>
            <div class="metric-value"><?= $active_discounts ?></div>
            <div class="metric-label">Active Coupons</div>
            <div class="metric-trend up"><i class="fas fa-tag"></i> Live discounts</div>
        </div>
    </div>

    <!-- VISUAL CHARTS -->
    <div class="section-heading fade-up">
        <div class="section-heading-icon"><i class="fas fa-chart-line"></i></div>
        <div>
            <h2>Visual Analytics</h2>
            <span>Interactive sales and inventory charts</span>
        </div>
    </div>

    <div class="two-col fade-up">
        <!-- Sales Trend Chart -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-coins"></i> Revenue Trend</div>
                <span class="badge bg-success" style="font-size:0.75rem;">Last 7 Active Days</span>
            </div>
            <div class="panel-body">
                <canvas id="salesTrendChart" style="max-height: 280px; width: 100%;"></canvas>
            </div>
        </div>

        <!-- Product Categories Chart -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-tags"></i> Category Distribution</div>
                <span class="badge bg-info" style="font-size:0.75rem;">Product Count</span>
            </div>
            <div class="panel-body d-flex justify-content-center align-items-center">
                <div style="width: 80%; max-height: 280px;">
                    <canvas id="categoryDistChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- TWO COLUMN: Inventory Health + System Status -->
    <div class="two-col">
        <!-- Inventory Health -->
        <div class="panel fade-up">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-chart-pie"></i> Inventory Health</div>
            </div>
            <div class="panel-body">
                <?php
                $healthy = $total_products > 0 ? max(0, $total_products - $low_stock - $out_of_stock) : 0;
                $hp = $total_products > 0 ? round($healthy / $total_products * 100) : 0;
                $lp = $total_products > 0 ? round($low_stock / $total_products * 100) : 0;
                $op = $total_products > 0 ? round($out_of_stock / $total_products * 100) : 0;
                ?>
                <div class="progress-row">
                    <div class="progress-meta">
                        <span class="progress-meta-label"><i class="fas fa-check-circle" style="color:var(--sage); margin-right:6px;"></i>Healthy Stock</span>
                        <span class="progress-meta-val" id="health-healthy-val"><?= $healthy ?> products (<?= $hp ?>%)</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill green" id="health-healthy-bar" style="width:<?= $hp ?>%"></div></div>
                </div>
                <div class="progress-row">
                    <div class="progress-meta">
                        <span class="progress-meta-label"><i class="fas fa-exclamation-circle" style="color:var(--gold); margin-right:6px;"></i>Low Stock</span>
                        <span class="progress-meta-val" id="health-low-val"><?= $low_stock ?> products (<?= $lp ?>%)</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill" id="health-low-bar" style="width:<?= $lp ?>%"></div></div>
                </div>
                <div class="progress-row">
                    <div class="progress-meta">
                        <span class="progress-meta-label"><i class="fas fa-times-circle" style="color:var(--danger); margin-right:6px;"></i>Out of Stock</span>
                        <span class="progress-meta-val" id="health-out-val"><?= $out_of_stock ?> products (<?= $op ?>%)</span>
                    </div>
                    <div class="progress-track"><div class="progress-fill red" id="health-out-bar" style="width:<?= $op ?>%"></div></div>
                </div>
                <div style="margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--mist);">
                    <div class="progress-meta">
                        <span class="progress-meta-label" style="font-size:0.95rem;">Overall Health Score</span>
                        <span class="progress-meta-val" id="health-overall-val" style="font-size:0.95rem; font-weight:700; color: <?= $hp >= 70 ? 'var(--sage)' : ($hp >= 40 ? 'var(--gold)' : 'var(--danger)') ?>;">
                            <?= $hp ?>%
                        </span>
                    </div>
                    <div class="progress-track" style="height:12px;">
                        <div class="progress-fill <?= $hp >= 70 ? 'green' : ($hp < 40 ? 'red' : '') ?>" id="health-overall-bar" style="width:<?= $hp ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="panel fade-up">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-server"></i> System Status</div>
            </div>
            <div class="panel-body">
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-database"></i> Database Connection</div>
                    <span class="status-badge ok">● Online</span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-php"></i> PHP Version</div>
                    <span class="status-badge ok"><?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-hdd"></i> Backup Storage</div>
                    <span class="status-badge <?= is_writable('backups/') || !is_dir('backups/') ? 'ok' : 'error' ?>">
                        <?= is_writable('backups/') ? '● Writable' : (is_dir('backups/') ? '● Locked' : '● Not Created') ?>
                    </span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-image"></i> Uploads Directory</div>
                    <span class="status-badge <?= is_writable('uploads/') ? 'ok' : 'warn' ?>">
                        <?= is_writable('uploads/') ? '● Writable' : '● Check Perms' ?>
                    </span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-clock"></i> Server Time</div>
                    <span class="status-badge ok"><?= date('H:i:s') ?></span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-exclamation-triangle"></i> Critical Alerts</div>
                    <span class="status-badge <?= $low_stock > 0 || $out_of_stock > 0 ? 'warn' : 'ok' ?>" id="status-critical-alerts">
                        <?= $low_stock + $out_of_stock ?> Item<?= ($low_stock + $out_of_stock) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="status-item">
                    <div class="status-label"><i class="fas fa-shield-alt"></i> Admin Session</div>
                    <span class="status-badge ok">● Active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TWO COLUMN: Backup Manager + Quick Actions -->
    <div class="two-col">

        <!-- Backup Manager -->
        <div class="panel fade-up">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-database"></i> Database Backup</div>
                <span style="font-size:0.78rem; color:var(--caramel);"><?= count($backups) ?> backup<?= count($backups)!==1?'s':'' ?> stored</span>
            </div>
            <div class="panel-body">
                <form method="POST" style="margin-bottom:18px;">
                    <button type="submit" name="create_backup" class="backup-btn">
                        <i class="fas fa-cloud-download-alt"></i> Create Backup Now
                    </button>
                </form>
                <ul class="backup-info-list">
                    <li><i class="fas fa-table"></i> Database schema & structure</li>
                    <li><i class="fas fa-cube"></i> All product data & pricing</li>
                    <li><i class="fas fa-users"></i> User accounts & profiles</li>
                    <li><i class="fas fa-receipt"></i> Full purchase history</li>
                </ul>
            </div>
            <div style="padding: 0 26px 22px;">
                <div style="font-weight:700; color:var(--espresso); margin-bottom:14px; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.8px;">
                    <i class="fas fa-history" style="color:var(--gold); margin-right:6px;"></i> Recent Backups
                </div>
                <div class="backups-scroll">
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $backup): ?>
                        <div class="backup-item">
                            <div>
                                <div class="backup-item-date"><i class="fas fa-file-archive" style="color:var(--gold); margin-right:5px;"></i><?= $backup['date'] ?></div>
                                <div class="backup-item-meta">Size: <?= $backup['size'] ?></div>
                            </div>
                            <div class="backup-item-actions">
                                <a href="download_backup.php?file=<?= urlencode($backup['filename']) ?>" class="btn-dl"><i class="fas fa-download"></i></a>
                                <a href="delete_backup.php?file=<?= urlencode($backup['filename']) ?>" class="btn-del" onclick="return confirm('Delete this backup permanently?')"><i class="fas fa-times"></i></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-backups">
                            <i class="fas fa-inbox"></i>
                            No backups yet. Create your first one above!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel fade-up">
            <div class="panel-header">
                <div class="panel-title"><i class="fas fa-bolt"></i> Quick Actions</div>
            </div>
            <div class="panel-body">
                <div class="actions-grid">
                    <a href="index.php" class="action-btn">
                        <div class="action-icon icon-blue"><i class="fas fa-box-open"></i></div>
                        <div>
                            <span class="action-label">Inventory</span>
                            <span class="action-desc">Manage products & stock</span>
                        </div>
                    </a>
                    <a href="manage_accounts.php" class="action-btn">
                        <div class="action-icon icon-green"><i class="fas fa-users-cog"></i></div>
                        <div>
                            <span class="action-label">User Accounts</span>
                            <span class="action-desc">Manage customers</span>
                        </div>
                    </a>
                    <a href="admin_purchases.php" class="action-btn">
                        <div class="action-icon icon-indigo"><i class="fas fa-shopping-bag"></i></div>
                        <div>
                            <span class="action-label">Purchases</span>
                            <span class="action-desc">Review & manage orders</span>
                        </div>
                    </a>
                    <a href="discount_management.php" class="action-btn">
                        <div class="action-icon icon-pink"><i class="fas fa-ticket-alt"></i></div>
                        <div>
                            <span class="action-label">Coupons</span>
                            <span class="action-desc">Discount codes</span>
                        </div>
                    </a>
                    <a href="purchase_history.php" class="action-btn">
                        <div class="action-icon icon-amber"><i class="fas fa-history"></i></div>
                        <div>
                            <span class="action-label">History</span>
                            <span class="action-desc">All purchase records</span>
                        </div>
                    </a>
                    <a href="shop.php" class="action-btn">
                        <div class="action-icon icon-teal"><i class="fas fa-store"></i></div>
                        <div>
                            <span class="action-label">View Store</span>
                            <span class="action-desc">Customer-facing shop</span>
                        </div>
                    </a>
                    <a href="javascript:void(0)" id="featuredProductsBtn" class="action-btn" onclick="openFeaturedModal()" style="grid-column: span 2; cursor:pointer;">
                        <div class="action-icon" style="background:rgba(201,145,42,0.12); color:var(--gold);"><i class="fas fa-star"></i></div>
                        <div style="position:relative; z-index:1;">
                            <span class="action-label">Featured Products</span>
                            <span class="action-desc">Choose 3 products to spotlight on the homepage</span>
                        </div>
                        <i class="fas fa-chevron-right" style="margin-left:auto; color:var(--caramel); font-size:0.85rem; position:relative; z-index:1;"></i>
                    </a>
                    <a href="logout.php" class="action-btn logout">
                        <div class="action-icon icon-red"><i class="fas fa-sign-out-alt"></i></div>
                        <div>
                            <span class="action-label">Sign Out</span>
                            <span class="action-desc">End your admin session safely</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITY LOG (static placeholder — wire to real data as needed) -->
    <div class="section-heading fade-up">
        <div class="section-heading-icon"><i class="fas fa-stream"></i></div>
        <div>
            <h2>Recent Activity</h2>
            <span>Latest events in your store</span>
        </div>
    </div>

    <div class="panel fade-up" style="margin-bottom: 0;">
        <div class="panel-body">
            <div id="activityTimeline">
            <?php
            if ($activity && $activity->num_rows > 0):
                while ($act = $activity->fetch_assoc()):
                    $ts = strtotime($act['created_at'] ?? 'now');
                    $diff = time() - $ts;
                    $ago = $diff < 60 ? 'Just now' : ($diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : date('M d · H:i', $ts)));
                    
                    $dot_color = 'green';
                    $icon = '<i class="fas fa-info-circle"></i>';
                    $text = '';

                    switch ($act['event_type']) {
                        case 'purchase':
                            $dot_color = 'green';
                            $icon = '<i class="fas fa-shopping-cart" style="color:var(--sage); margin-right:4px;"></i>';
                            $text = "<strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> ordered <strong>" . htmlspecialchars($act['target_name'] ?? 'Product') . "</strong> — ₱" . number_format($act['amount_value'], 2);
                            break;
                        case 'review':
                            $dot_color = 'amber';
                            $icon = '<i class="fas fa-star" style="color:var(--gold); margin-right:4px;"></i>';
                            $text = "<strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> reviewed <strong>" . htmlspecialchars($act['target_name'] ?? 'Product') . "</strong> — " . number_format($act['amount_value'], 1) . " stars";
                            break;
                        case 'coupon':
                            $dot_color = 'amber';
                            $icon = '<i class="fas fa-ticket-alt" style="color:var(--gold); margin-right:4px;"></i>';
                            $text = "Admin <strong>" . htmlspecialchars($act['username'] ?? 'Admin') . "</strong> created coupon <strong>" . htmlspecialchars($act['target_name']) . "</strong> — " . number_format($act['amount_value'], 0) . "% off";
                            break;
                        case 'register':
                            $dot_color = 'blue';
                            $icon = '<i class="fas fa-user-plus" style="color:#3b82f6; margin-right:4px;"></i>';
                            $text = "New customer <strong>" . htmlspecialchars($act['username'] ?? 'Customer') . "</strong> registered an account";
                            break;
                    }
            ?>
            <div class="timeline-item">
                <div class="timeline-dot <?= $dot_color ?>"></div>
                <div>
                    <div class="timeline-text"><?= $icon ?> <?= $text ?></div>
                    <div class="timeline-time"><?= $ago ?></div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="timeline-item">
                <div class="timeline-dot amber"></div>
                <div>
                    <div class="timeline-text">No recent timeline activity to display.</div>
                    <div class="timeline-time">Check back soon</div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($page === 1): ?>
            <div class="timeline-item">
                <div class="timeline-dot green"></div>
                <div>
                    <div class="timeline-text"><i class="fas fa-sign-in-alt" style="color:var(--sage); margin-right:4px;"></i> Admin <strong><?= htmlspecialchars($admin_username) ?></strong> logged in</div>
                    <div class="timeline-time">Just now</div>
                </div>
            </div>
            <?php if ($low_stock > 0): ?>
            <div class="timeline-item">
                <div class="timeline-dot amber"></div>
                <div>
                    <div class="timeline-text"><i class="fas fa-exclamation-triangle" style="color:var(--gold); margin-right:4px;"></i> <strong><?= $low_stock ?> product<?= $low_stock !== 1 ? 's' : '' ?></strong> running low on stock — restock recommended</div>
                    <div class="timeline-time">System alert</div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            </div>

            <!-- Pagination Controls -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top" id="activityPagination">
                <div class="text-muted" style="font-size: 0.85rem;" id="activityCountLabel">
                    Page <span id="currentPageNum"><?= $page ?></span> of <span id="totalPageNum"><?= $total_pages ?></span> · Total <?= $total_items ?> events
                </div>
                <nav aria-label="Recent Activity Pagination">
                    <ul class="pagination pagination-sm mb-0 custom-pagination" id="activityPages">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === 1 ? 'active' : '' ?>">
                                <button type="button" class="page-link" data-page="<?= $i ?>"><?= $i ?></button>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

</div><!-- /content-wrapper -->

<!-- ===================== FEATURED PRODUCTS MODAL ===================== -->
<div class="fp-modal-overlay" id="fpModalOverlay" onclick="closeFeaturedModalOutside(event)">
  <div class="fp-modal" id="fpModal">
    <div class="fp-modal-header">
      <div>
        <div class="fp-modal-title"><i class="fas fa-star"></i> Featured Products</div>
        <div class="fp-modal-subtitle">Select exactly 3 products to feature on the homepage</div>
      </div>
      <button class="fp-modal-close" onclick="closeFeaturedModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="fp-modal-body">
      <!-- Selected Strip -->
      <div id="fpSelectedStrip" class="fp-selected-strip">
        <div class="fp-selected-label"><i class="fas fa-check-circle" style="color:var(--sage); margin-right:5px;"></i> Selected (0/3)</div>
        <div class="fp-slot empty" id="fpSlot0"><i class="fas fa-plus-circle"></i> Slot 1</div>
        <div class="fp-slot empty" id="fpSlot1"><i class="fas fa-plus-circle"></i> Slot 2</div>
        <div class="fp-slot empty" id="fpSlot2"><i class="fas fa-plus-circle"></i> Slot 3</div>
      </div>

      <!-- Search -->
      <div class="fp-search-bar">
        <input type="text" id="fpSearch" placeholder="Search products by name or category..." oninput="filterFPProducts()">
      </div>

      <!-- Product List -->
      <div class="fp-products-list" id="fpProductsList">
        <div style="text-align:center; padding:32px; color:var(--caramel);">
          <i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem; display:block; margin-bottom:10px;"></i>
          Loading products...
        </div>
      </div>
    </div>

    <div class="fp-modal-footer">
      <div class="fp-count-badge">Select <strong id="fpCountDisplay">0</strong> / 3 products</div>
      <button class="fp-save-btn" id="fpSaveBtn" onclick="saveFeaturedProducts()" disabled>
        <div class="fp-spinner" id="fpSpinner"></div>
        <i class="fas fa-save" id="fpSaveIcon"></i> Save Featured Products
      </button>
    </div>
  </div>
</div>

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Premium, health-conscious treats and supplies crafted with love for felines everywhere.</p>
            <div class="social-links">
                <a href="https://www.facebook.com/"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/"><i class="fab fa-twitter"></i></a>
                <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                <a href="https://www.tiktok.com/"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Admin Links</h3>
            <div class="footer-links">
                <a href="index.php">Inventory Management</a>
                <a href="manage_accounts.php">User Accounts</a>
                <a href="admin_purchases.php">Purchase Orders</a>
                <a href="discount_management.php">Discount Coupons</a>
                <a href="purchase_history.php">Purchase Records</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Store Links</h3>
            <div class="footer-links">
                <a href="main.php">Home</a>
                <a href="shop.php">Shop</a>
                <a href="about.php">About Us</a>
                <a href="main.php#faq">FAQs</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey); margin-right:8px;"></i>123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-envelope" style="color:var(--honey); margin-right:8px;"></i>meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey); margin-right:8px;"></i>Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved. · Admin Panel v2.0</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* ============ TOAST ============ */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = 'toast-item ' + type;
    const iconMap = { success: 'fa-check-circle', warning: 'fa-exclamation-circle', error: 'fa-times-circle' };
    toast.innerHTML = `
        <i class="fas ${iconMap[type] || iconMap.success} toast-icon-t"></i>
        <span class="toast-text">${message}</span>
        <button class="toast-close-btn"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(toast);
    toast.querySelector('.toast-close-btn').addEventListener('click', () => toast.remove());
    setTimeout(() => { toast.style.animation = 'none'; toast.style.opacity = '0'; toast.style.transform = 'translateX(400px)'; toast.style.transition = 'all 0.35s ease'; setTimeout(() => toast.remove(), 360); }, 5000);
}

/* ============ PROFILE DROPDOWN ============ */
document.addEventListener('DOMContentLoaded', function() {
    const profileDropdown = document.querySelector('.profile-dropdown');
    if (profileDropdown) {
        profileDropdown.querySelector('.profile-pic').addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function(e) {
            if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('open');
        });
    }

    /* ============ RECENT ACTIVITY PAGINATION ============ */
    const timelineContainer = document.getElementById('activityTimeline');
    const paginationContainer = document.getElementById('activityPages');
    const currentPageNum = document.getElementById('currentPageNum');
    const totalPageNum = document.getElementById('totalPageNum');
    const activityCountLabel = document.getElementById('activityCountLabel');

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            const btn = e.target.closest('.page-link');
            if (!btn) return;
            e.preventDefault();
            
            const page = parseInt(btn.getAttribute('data-page'));
            if (isNaN(page)) return;
            
            // Show fade transition
            timelineContainer.style.opacity = '0.4';
            timelineContainer.style.transition = 'opacity 0.25s ease';
            
            fetch(`?action=fetch_activity&page=${page}`)
                .then(res => res.json())
                .then(data => {
                    timelineContainer.innerHTML = data.html;
                    timelineContainer.style.opacity = '1';
                    
                    // Update count label and page numbers
                    activityCountLabel.innerHTML = `Page <span id="currentPageNum">${data.page}</span> of <span id="totalPageNum">${data.total_pages}</span> · Total ${data.total_items} events`;
                    
                    // Re-render pagination links
                    renderPagination(data.page, data.total_pages);
                })
                .catch(err => {
                    console.error('Error fetching activity:', err);
                    timelineContainer.style.opacity = '1';
                });
        });
    }

    function renderPagination(activePage, totalPages) {
        if (!paginationContainer) return;
        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `
                <li class="page-item ${i == activePage ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${i}">${i}</button>
                </li>
            `;
        }
        paginationContainer.innerHTML = html;
    }

    /* Scroll to top */
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.pageYOffset > 300));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    /* Animate progress bars on load */
    document.querySelectorAll('.progress-fill').forEach(bar => {
        const target = bar.style.width;
        bar.style.width = '0%';
        requestAnimationFrame(() => { setTimeout(() => bar.style.width = target, 100); });
    });

    // ============ CHART.JS INITIALIZATION ============
    // Sales Trend Chart
    const salesCtx = document.getElementById('salesTrendChart')?.getContext('2d');
    if (salesCtx) {
        const salesGradient = salesCtx.createLinearGradient(0, 0, 0, 280);
        salesGradient.addColorStop(0, 'rgba(201, 145, 42, 0.4)');
        salesGradient.addColorStop(1, 'rgba(201, 145, 42, 0.0)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_dates) ?>,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: <?= json_encode($chart_revenues) ?>,
                    borderColor: '#c9912a',
                    borderWidth: 3,
                    backgroundColor: salesGradient,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#2c1a0e',
                    pointBorderColor: '#c9912a',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(44, 26, 14, 0.05)' },
                        ticks: { color: '#5a2d0c', font: { family: 'DM Sans' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#5a2d0c', font: { family: 'DM Sans' } }
                    }
                }
            }
        });
    }

    // Category Distribution Chart
    const catCtx = document.getElementById('categoryDistChart')?.getContext('2d');
    if (catCtx) {
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chart_categories) ?>,
                datasets: [{
                    data: <?= json_encode($chart_cat_counts) ?>,
                    backgroundColor: ['#2c1a0e', '#9b6a2f', '#7a9e7e', '#c9912a', '#e8b86d'],
                    borderWidth: 2,
                    borderColor: '#fdf8f0'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#2c1a0e', font: { family: 'DM Sans', weight: 'bold' } }
                    }
                },
                cutout: '65%'
            }
        });
    }
});

/* ============ FEATURED PRODUCTS MODAL ============ */
let fpAllProducts = [];
let fpSelectedIds = [];

function openFeaturedModal() {
    document.getElementById('fpModalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('fpSearch').value = '';
    loadFeaturedProducts();
}

function closeFeaturedModal() {
    document.getElementById('fpModalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function closeFeaturedModalOutside(e) {
    if (e.target === document.getElementById('fpModalOverlay')) closeFeaturedModal();
}

function loadFeaturedProducts() {
    document.getElementById('fpProductsList').innerHTML = `
        <div style="text-align:center; padding:32px; color:var(--caramel); grid-column:1/-1;">
            <i class="fas fa-circle-notch fa-spin" style="font-size:1.8rem; display:block; margin-bottom:10px;"></i>
            Loading products...
        </div>`;

    fetch('?action=fetch_featured_products')
        .then(r => r.json())
        .then(data => {
            fpAllProducts = data.products;
            fpSelectedIds = data.featured_ids.map(Number);
            renderFPProducts(fpAllProducts);
            updateFPUI();
        })
        .catch(() => {
            document.getElementById('fpProductsList').innerHTML =
                '<div style="text-align:center; padding:32px; color:var(--danger); grid-column:1/-1;"><i class="fas fa-exclamation-triangle"></i> Failed to load products.</div>';
        });
}

function filterFPProducts() {
    const q = document.getElementById('fpSearch').value.toLowerCase();
    const filtered = fpAllProducts.filter(p =>
        p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q)
    );
    renderFPProducts(filtered);
}

function renderFPProducts(products) {
    const list = document.getElementById('fpProductsList');
    if (!products.length) {
        list.innerHTML = '<div style="text-align:center; padding:32px; color:var(--caramel); grid-column:1/-1;"><i class="fas fa-search" style="font-size:1.5rem; display:block; margin-bottom:8px;"></i> No products found.</div>';
        return;
    }
    list.innerHTML = products.map(p => {
        const id = parseInt(p.id);
        const isSelected = fpSelectedIds.includes(id);
        const isDisabled = !isSelected && fpSelectedIds.length >= 3;
        const isOOS = parseInt(p.stock) === 0;
        return `
        <div class="fp-product-item ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}"
             data-id="${id}" onclick="toggleFPProduct(${id})">
            <div class="fp-product-check"><i class="fas fa-check"></i></div>
            <div class="fp-product-name">${escHtml(p.name)}</div>
            <div class="fp-product-meta">
                <i class="fas fa-tag" style="margin-right:4px;"></i>${escHtml(p.category)}
                &nbsp;&middot;&nbsp;
                <i class="fas fa-boxes" style="margin-right:4px;"></i>Stock: ${p.stock}
                ${isOOS ? '<span style="color:var(--danger); margin-left:4px;">(Out of stock)</span>' : ''}
            </div>
            <div class="fp-product-price">&#x20B1;${parseFloat(p.price).toLocaleString('en-PH', {minimumFractionDigits:2})}</div>
        </div>`;
    }).join('');
}

function toggleFPProduct(id) {
    id = parseInt(id);
    const idx = fpSelectedIds.indexOf(id);
    if (idx > -1) {
        fpSelectedIds.splice(idx, 1);
    } else {
        if (fpSelectedIds.length >= 3) return;
        fpSelectedIds.push(id);
    }
    const q = document.getElementById('fpSearch').value.toLowerCase();
    const filtered = fpAllProducts.filter(p =>
        !q || p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q)
    );
    renderFPProducts(filtered);
    updateFPUI();
}

function updateFPUI() {
    const count = fpSelectedIds.length;
    document.getElementById('fpCountDisplay').textContent = count;
    const saveBtn = document.getElementById('fpSaveBtn');
    saveBtn.disabled = count !== 3;

    const label = document.querySelector('#fpSelectedStrip .fp-selected-label');
    label.innerHTML = '<i class="fas fa-check-circle" style="color:var(--sage); margin-right:5px;"></i> Selected (' + count + '/3)';

    for (let i = 0; i < 3; i++) {
        const slot = document.getElementById('fpSlot' + i);
        if (fpSelectedIds[i] !== undefined) {
            const p = fpAllProducts.find(x => parseInt(x.id) === fpSelectedIds[i]);
            const name = p ? (p.name.length > 24 ? p.name.substring(0,24)+'...' : p.name) : 'Product';
            slot.className = 'fp-slot';
            slot.innerHTML =
                '<i class="fas fa-star" style="color:var(--gold); font-size:0.75rem;"></i> ' +
                escHtml(name) +
                ' <button class="fp-slot-remove" onclick="event.stopPropagation(); toggleFPProduct(' + fpSelectedIds[i] + ')" title="Remove"><i class="fas fa-times"></i></button>';
        } else {
            slot.className = 'fp-slot empty';
            slot.innerHTML = '<i class="fas fa-plus-circle"></i> Slot ' + (i+1);
        }
    }
}

function saveFeaturedProducts() {
    if (fpSelectedIds.length !== 3) return;
    const btn = document.getElementById('fpSaveBtn');
    const icon = document.getElementById('fpSaveIcon');
    const spinner = document.getElementById('fpSpinner');
    btn.disabled = true;
    icon.style.display = 'none';
    spinner.style.display = 'block';

    const form = new FormData();
    form.append('action', 'save_featured_products');
    fpSelectedIds.forEach(id => form.append('product_ids[]', id));

    fetch(window.location.pathname, { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            icon.style.display = '';
            spinner.style.display = 'none';
            btn.disabled = false;
            if (data.success) {
                showToast('Featured products saved! Homepage updated.', 'success');
                closeFeaturedModal();
            } else {
                showToast(data.message || 'Could not save. Try again.', 'warning');
            }
        })
        .catch(() => {
            icon.style.display = '';
            spinner.style.display = 'none';
            btn.disabled = false;
            showToast('Network error. Please try again.', 'warning');
        });
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

/* ============ NOTIFICATIONS ============ */
let notifOpen = false;
let notifLastRead = null;
let notifPollTimer = null;

function toggleNotifPanel() {
    notifOpen = !notifOpen;
    const panel = document.getElementById('notifPanel');
    const btn   = document.getElementById('notifBellBtn');
    if (notifOpen) {
        panel.classList.add('open');
        btn.style.background = 'var(--gold)';
        btn.style.color = '#fff';
        btn.style.borderColor = 'var(--gold)';
        loadNotifications();
    } else {
        panel.classList.remove('open');
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
    }
}

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notifWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        const panel = document.getElementById('notifPanel');
        const btn   = document.getElementById('notifBellBtn');
        if (notifOpen) {
            panel.classList.remove('open');
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
            notifOpen = false;
        }
    }
});

function loadNotifications() {
    fetch('?action=fetch_notifications')
        .then(r => r.json())
        .then(data => {
            notifLastRead = data.last_read;
            renderNotifications(data.notifications, data.unread, data.last_read);
            updateBadge(data.unread);
            updateDashboardMetrics(data);
        })
        .catch(() => {
            document.getElementById('notifList').innerHTML =
                '<div class="notif-empty"><i class="fas fa-exclamation-triangle"></i> Failed to load.</div>';
        });
}

function renderNotifications(items, unread, lastRead) {
    const list = document.getElementById('notifList');
    if (!items || !items.length) {
        list.innerHTML = '<div class="notif-empty"><i class="fas fa-shopping-cart"></i> No purchase orders yet.</div>';
        return;
    }

    const lastReadTime = new Date(lastRead.replace(' ', 'T')).getTime();

    list.innerHTML = items.map(n => {
        const txTime = new Date(n.transaction_date.replace(' ', 'T')).getTime();
        const isUnread = txTime > lastReadTime;

        const diff = Math.floor((Date.now() - txTime) / 1000);
        const ago  = diff < 60 ? 'Just now'
                   : diff < 3600 ? Math.floor(diff/60) + 'm ago'
                   : diff < 86400 ? Math.floor(diff/3600) + 'h ago'
                   : new Date(n.transaction_date).toLocaleDateString('en-PH', {month:'short', day:'numeric'}) +
                     ' · ' + new Date(n.transaction_date).toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit'});

        const initials = (n.username || 'U').substring(0,2).toUpperCase();
        const statusKey = (n.status || 'pending').toLowerCase();
        const statusLabel = n.status || 'Pending';
        const productName = n.product_name || 'Unknown Product';
        const price = parseFloat(n.total_price || 0).toLocaleString('en-PH', {minimumFractionDigits:2});
        const qty   = parseInt(n.quantity || 1);

        const profilePicUrl = n.profile_pic && n.profile_pic.trim() !== '' ? n.profile_pic : 'images/profile.jpg';
        const avatarImgHtml = `<img src="${escHtml(profilePicUrl)}" alt="${escHtml(n.username || 'User')}" onerror="this.src='images/profile.jpg';">`;

        return `
        <div class="notif-item ${isUnread ? 'unread' : ''}">
            <div class="notif-avatar">${avatarImgHtml}</div>
            <div class="notif-content">
                <div class="notif-text">
                    <strong>${escHtml(n.username || 'Customer')}</strong> purchased
                    <span class="notif-product">${escHtml(productName)}</span>
                    ${qty > 1 ? '<span style="color:var(--caramel);">×'+qty+'</span>' : ''}
                </div>
                <div class="notif-meta">
                    <span class="notif-time"><i class="fas fa-clock" style="margin-right:3px;"></i>${ago}</span>
                    <span class="notif-status ${statusKey}">${escHtml(statusLabel)}</span>
                </div>
            </div>
            <div class="notif-price">₱${price}</div>
        </div>`;
    }).join('');
}

function updateBadge(count) {
    const badge = document.getElementById('notifBadge');
    const btn   = document.getElementById('notifBellBtn');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
        btn.classList.add('has-unread');
    } else {
        badge.classList.add('hidden');
        btn.classList.remove('has-unread');
    }
}

function updateDashboardMetrics(data) {
    const total = data.total_products;
    if (total <= 0) return;
    
    // Update metric cards
    const mProducts = document.getElementById('metric-total-products-val');
    if (mProducts) mProducts.textContent = data.total_products;
    const mLowStock = document.getElementById('metric-low-stock-val');
    if (mLowStock) mLowStock.textContent = data.low_stock;
    const mOutStock = document.getElementById('metric-out-stock-val');
    if (mOutStock) mOutStock.textContent = data.out_of_stock;
    const mPurchases = document.getElementById('metric-total-purchases-val');
    if (mPurchases) mPurchases.textContent = data.total_purchases;
    const mRecent = document.getElementById('metric-recent-purchases-val');
    if (mRecent) mRecent.textContent = data.recent_purchases;
    
    const mValue = document.getElementById('metric-total-value-val');
    if (mValue) {
        mValue.textContent = '₱' + data.total_value.toLocaleString('en-PH', {maximumFractionDigits:0});
    }
    
    // Update hero section
    const hProducts = document.getElementById('hero-total-products-count');
    if (hProducts) hProducts.textContent = data.total_products;
    const hValue = document.getElementById('hero-total-value-count');
    if (hValue) hValue.textContent = '₱' + (data.total_value / 1000).toFixed(1) + 'k';
    const hLowStock = document.getElementById('hero-low-stock-count');
    if (hLowStock) hLowStock.textContent = data.low_stock;
    
    // Low Stock trend and label
    const trendEl = document.getElementById('metric-low-stock-trend');
    if (trendEl) {
        trendEl.className = 'metric-trend ' + (data.low_stock > 0 ? 'warn' : 'up');
        trendEl.innerHTML = `<i class="fas fa-${data.low_stock > 0 ? 'exclamation-circle' : 'check-circle'}"></i> <span>${data.low_stock > 0 ? 'Needs attention' : 'All stocked up'}</span>`;
    }
    
    // Out of Stock trend and label
    const oosTrendEl = document.getElementById('metric-out-stock-trend');
    if (oosTrendEl) {
        oosTrendEl.className = 'metric-trend ' + (data.out_of_stock > 0 ? 'danger' : 'up');
        oosTrendEl.innerHTML = `<i class="fas fa-${data.out_of_stock > 0 ? 'times-circle' : 'check-circle'}"></i> <span>${data.out_of_stock > 0 ? 'Restock needed' : 'All available'}</span>`;
    }
    
    // Recent Purchases trend and label
    const recentTrendEl = document.getElementById('metric-recent-purchases-trend');
    if (recentTrendEl) {
        recentTrendEl.className = 'metric-trend ' + (data.recent_purchases > 0 ? 'up' : 'warn');
        recentTrendEl.innerHTML = `<i class="fas fa-${data.recent_purchases > 0 ? 'fire' : 'clock'}"></i> <span>${data.recent_purchases > 0 ? 'Active today' : 'No orders yet'}</span>`;
    }
    
    // System Status Critical Alerts
    const critEl = document.getElementById('status-critical-alerts');
    if (critEl) {
        const totalAlerts = data.low_stock + data.out_of_stock;
        critEl.className = 'status-badge ' + (totalAlerts > 0 ? 'warn' : 'ok');
        critEl.textContent = `${totalAlerts} Item${totalAlerts !== 1 ? 's' : ''}`;
    }
    
    // Inventory Health
    const healthy = Math.max(0, total - data.low_stock - data.out_of_stock);
    const hp = Math.round((healthy / total) * 100);
    const lp = Math.round((data.low_stock / total) * 100);
    const op = Math.round((data.out_of_stock / total) * 100);
    
    const hHealthyVal = document.getElementById('health-healthy-val');
    if (hHealthyVal) hHealthyVal.textContent = `${healthy} products (${hp}%)`;
    const hHealthyBar = document.getElementById('health-healthy-bar');
    if (hHealthyBar) hHealthyBar.style.width = hp + '%';
    
    const hLowVal = document.getElementById('health-low-val');
    if (hLowVal) hLowVal.textContent = `${data.low_stock} products (${lp}%)`;
    const hLowBar = document.getElementById('health-low-bar');
    if (hLowBar) hLowBar.style.width = lp + '%';
    
    const hOutVal = document.getElementById('health-out-val');
    if (hOutVal) hOutVal.textContent = `${data.out_of_stock} products (${op}%)`;
    const hOutBar = document.getElementById('health-out-bar');
    if (hOutBar) hOutBar.style.width = op + '%';
    
    const overallValEl = document.getElementById('health-overall-val');
    if (overallValEl) {
        overallValEl.textContent = hp + '%';
        overallValEl.style.color = hp >= 70 ? 'var(--sage)' : (hp >= 40 ? 'var(--gold)' : 'var(--danger)');
    }
    
    const overallBarEl = document.getElementById('health-overall-bar');
    if (overallBarEl) {
        overallBarEl.className = 'progress-fill ' + (hp >= 70 ? 'green' : (hp < 40 ? 'red' : ''));
        overallBarEl.style.width = hp + '%';
    }
}

function markAllRead() {
    const form = new FormData();
    form.append('action', 'mark_notifications_read');
    fetch(window.location.pathname, { method:'POST', body: form })
        .then(r => r.json())
        .then(() => {
            updateBadge(0);
            // Re-render items without unread highlight
            loadNotifications();
        });
}

// Initial load badge count on page load
fetch('?action=fetch_notifications')
    .then(r => r.json())
    .then(data => {
        updateBadge(data.unread);
        updateDashboardMetrics(data);
    })
    .catch(() => {});

// Poll every 30 seconds for new orders
setInterval(() => {
    fetch('?action=fetch_notifications')
        .then(r => r.json())
        .then(data => {
            updateBadge(data.unread);
            if (notifOpen) renderNotifications(data.notifications, data.unread, data.last_read);
            updateDashboardMetrics(data);
        })
        .catch(() => {});
}, 30000);
</script>
</body>
</html>