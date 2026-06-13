<?php 
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// === Check if created_at column exists === //
$has_created_at = false;
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($col_check && $col_check->num_rows > 0) {
    $has_created_at = true;
}

// === Handle Updates and Deletes === //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_user_full') {
        $id = intval($_POST['user_id']);
        $new_username = trim($_POST['username']);
        $new_role = $_POST['role'];
        $new_email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $new_password = $_POST['password'];
        
        $stmt = $conn->prepare("UPDATE users SET username = ?, role = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $new_username, $new_role, $new_email, $id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User details updated successfully.";
            if ($id == $_SESSION['user_id']) {
                $_SESSION['username'] = $new_username;
                $_SESSION['role'] = $new_role;
            }
        }
        $stmt->close();
        
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User details and password updated successfully.";
            }
            $stmt->close();
        }
    }
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        if ($delete_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) $_SESSION['success_message'] = "User deleted successfully.";
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$admin_count   = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='admin'")->fetch_assoc()['t'];
$user_count    = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='user'")->fetch_assoc()['t'];
$total_users   = $admin_count + $user_count;

// Newest member — use created_at if available, else fall back to highest id
if ($has_created_at) {
    $newest = $conn->query("SELECT username, created_at FROM users ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
} else {
    $newest = $conn->query("SELECT username FROM users ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if ($newest) $newest['created_at'] = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts — Pawganic Admin</title>
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
        --info:       #2980b9;
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
        font-weight: 500; font-size: 0.9rem;
        letter-spacing: 0.3px; transition: var(--transition);
    }
    .nav-links a:hover, .nav-links a.active { background: var(--gold); color: var(--white); }
    .admin-chip {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; display: flex; align-items: center; gap: 6px;
    }
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
    .dropdown-content a {
        display: flex; align-items: center; gap: 10px;
        color: var(--espresso); text-decoration: none; padding: 12px 16px;
        font-size: 0.9rem; transition: var(--transition);
    }
    .dropdown-content a:hover { background: var(--cream); color: var(--mahogany); padding-left: 22px; }
    .dropdown-content a i { width: 18px; color: var(--caramel); }

    /* ===================== PAGE HERO ===================== */
    .page-hero {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
        padding: 64px 5% 70px;
        position: relative;
        overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 80% 50%, rgba(201,145,42,0.22) 0%, transparent 60%),
                    radial-gradient(ellipse at 5% 80%, rgba(122,158,126,0.12) 0%, transparent 50%);
    }
    .page-hero::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
        background: var(--cream);
        clip-path: ellipse(55% 100% at 50% 100%);
    }
    .hero-deco {
        position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey);
    }
    .hero-deco-1 { width: 380px; height: 380px; top: -100px; right: -80px; }
    .hero-deco-2 { width: 200px; height: 200px; bottom: 20px; left: 4%; }

    .hero-inner {
        position: relative; z-index: 2;
        max-width: 1200px; margin: 0 auto;
        display: flex; align-items: flex-end; justify-content: space-between; gap: 40px;
        flex-wrap: wrap;
    }
    .hero-label {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
        color: var(--honey); padding: 6px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 600; letter-spacing: 2px;
        text-transform: uppercase; margin-bottom: 16px;
    }
    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: clamp(2.4rem, 4vw, 3.8rem);
        font-weight: 900; color: var(--white); line-height: 1.1; margin-bottom: 14px;
    }
    .hero-title em { font-style: italic; color: var(--honey); }
    .hero-subtitle {
        color: rgba(255,255,255,0.6); font-size: 1rem; line-height: 1.7; max-width: 480px;
    }

    /* ===================== MAIN CONTENT ===================== */
    .page-content {
        max-width: 1200px; margin: 0 auto; padding: 20px 24px 80px;
        flex: 1;
    }

    /* ===================== STAT CARDS ===================== */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px; margin-bottom: 32px;
    }
    .stat-card {
        background: var(--ivory);
        border-radius: var(--radius);
        padding: 20px 22px;
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        display: flex; align-items: center; gap: 16px;
        transition: var(--transition);
        position: relative; overflow: hidden;
    }
    .stat-card::after {
        content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px;
        background: var(--gold); transition: var(--transition);
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .stat-card:hover::after { height: 5px; }

    .stat-card.admin-card::after { background: linear-gradient(90deg, var(--danger), #e74c3c); }
    .stat-card.user-card-stat::after { background: linear-gradient(90deg, var(--info), #3498db); }
    .stat-card.newest-card::after { background: linear-gradient(90deg, var(--sage), var(--sage-light)); }
    .stat-card.total-card::after { background: linear-gradient(90deg, var(--gold), var(--honey)); }

    .stat-icon-wrap {
        width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        transition: var(--transition);
    }
    .stat-card:hover .stat-icon-wrap { transform: scale(1.08); }

    .stat-card.total-card .stat-icon-wrap { background: rgba(201,145,42,0.08); color: var(--gold); }
    .stat-card.admin-card .stat-icon-wrap { background: rgba(192,57,43,0.08); color: var(--danger); }
    .stat-card.user-card-stat .stat-icon-wrap { background: rgba(41,128,185,0.08); color: var(--info); }
    .stat-card.newest-card .stat-icon-wrap { background: rgba(122,158,126,0.1); color: var(--sage); }

    .stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 1.75rem; font-weight: 700; color: var(--espresso); line-height: 1;
    }
    .stat-lbl { font-size: 0.8rem; color: var(--caramel); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-sub { font-size: 0.72rem; color: var(--espresso); opacity: 0.6; margin-top: 2px; }

    /* ===================== CONTROL BAR ===================== */
    .search-filter-bar {
        background: var(--ivory); border-radius: var(--radius);
        padding: 14px 20px; box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.12);
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
        margin-bottom: 24px; gap: 16px;
    }
    .search-wrap { flex: 1; min-width: 240px; position: relative; }
    .search-wrap i {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: var(--caramel); font-size: 0.9rem;
    }
    .search-wrap input {
        width: 100%; padding: 10px 16px 10px 42px;
        border: 2px solid var(--mist); border-radius: 50px;
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.9rem; font-weight: 500;
        transition: var(--transition); outline: none;
    }
    .search-wrap input:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.1);
    }
    .search-wrap input::placeholder { color: var(--caramel); opacity: 0.65; }
    
    .result-chip {
        font-size: 0.8rem; color: var(--caramel); font-weight: 600; white-space: nowrap;
        background: rgba(201,145,42,0.08); padding: 8px 14px; border-radius: 50px;
        border: 1px solid rgba(201,145,42,0.15); display: inline-block;
    }

    /* ===================== TAB NAV ===================== */
    .tab-nav {
        display: flex; gap: 4px;
        background: var(--cream); padding: 4px;
        border-radius: 14px;
        border: 1px solid rgba(201,145,42,0.08);
    }
    .tab-btn {
        padding: 8px 18px; border: none; border-radius: 10px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        color: var(--caramel); background: transparent;
    }
    .tab-btn:hover { background: rgba(201,145,42,0.08); color: var(--mahogany); }
    .tab-btn.active {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); box-shadow: var(--shadow-sm);
    }
    .tab-count {
        background: rgba(255,255,255,0.18); color: inherit;
        padding: 2px 7px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;
    }
    .tab-btn:not(.active) .tab-count { background: rgba(44,26,14,0.08); }

    /* ===================== TABLE LAYOUT ===================== */
    .table-container {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        border: 1px solid rgba(201,145,42,0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }
    .custom-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }
    .custom-table th {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey);
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.82rem;
        padding: 14px 18px;
        border: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .custom-table td {
        padding: 14px 18px;
        vertical-align: middle;
        border-bottom: 1px solid var(--mist);
        font-size: 0.88rem;
        color: var(--espresso);
        transition: var(--transition);
    }
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    .custom-table tr:hover td {
        background-color: rgba(201,145,42,0.03);
    }
    .btn-table-action {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        border: none; cursor: pointer; transition: var(--transition);
        font-size: 0.85rem;
    }
    .btn-table-action.edit {
        background: rgba(201,145,42,0.1); color: var(--gold);
    }
    .btn-table-action.edit:hover {
        background: var(--gold); color: var(--white);
    }
    .btn-table-action.delete {
        background: rgba(192,57,43,0.1); color: var(--danger);
    }
    .btn-table-action.delete:hover {
        background: var(--danger); color: var(--white);
    }
    .btn-table-action.disabled {
        background: var(--mist); color: var(--caramel); cursor: not-allowed; opacity: 0.6;
    }
    .table-avatar {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
        border: 2px solid var(--gold);
    }
    .table-avatar-initials {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; font-family: 'Playfair Display', serif; font-weight: 700;
        color: var(--white);
    }
    .table-avatar-initials.admin-av { background: linear-gradient(135deg, var(--danger), #e74c3c); }
    .table-avatar-initials.user-av { background: linear-gradient(135deg, var(--info), #3498db); }

    .you-chip {
        background: rgba(41,128,185,0.12); color: var(--info);
        padding: 2px 8px; border-radius: 50px; font-size: 0.68rem; font-weight: 700;
        border: 1px solid rgba(41,128,185,0.2); letter-spacing: 0.5px;
    }
    .role-pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.3px;
    }
    .role-pill.admin { background: rgba(192,57,43,0.1); color: var(--danger); border: 1px solid rgba(192,57,43,0.15); }
    .role-pill.user { background: rgba(41,128,185,0.1); color: var(--info); border: 1px solid rgba(41,128,185,0.15); }

    /* Empty state */
    .empty-state {
        text-align: center; padding: 50px 24px;
        background: var(--ivory); border-radius: var(--radius);
        border: 2px dashed var(--mist);
        box-shadow: var(--shadow-sm);
    }
    .empty-state i { font-size: 2.8rem; color: var(--mist); display: block; margin-bottom: 12px; }
    .empty-state h4 { font-family: 'Playfair Display', serif; color: var(--mahogany); margin-bottom: 6px; }
    .empty-state p { color: var(--caramel); font-size: 0.88rem; }

    /* ===================== TOAST ===================== */
    .toast-container { position: fixed; bottom: 30px; left: 30px; z-index: 2000; }
    .custom-toast {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-radius: var(--radius-sm); font-size: 0.95rem; padding: 14px 20px;
        box-shadow: var(--shadow-lg); min-width: 260px; max-width: 320px;
        color: var(--cream); border-left: 4px solid var(--sage);
    }
    .custom-toast .toast-body { padding:0; display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .custom-toast .btn-close { filter: invert(1) brightness(0.8); flex-shrink:0; }

    /* ===================== MODAL ===================== */
    .edit-modal .modal-content {
        border: none; border-radius: var(--radius);
        background: var(--ivory); box-shadow: var(--shadow-lg);
        overflow: hidden;
    }
    .edit-modal .modal-header {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); padding: 18px 24px; border: none;
    }
    .edit-modal .modal-title {
        font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 700;
    }
    .edit-modal .btn-close { filter: invert(1) brightness(0.8); }
    .edit-modal .modal-body { padding: 24px; }
    .edit-modal .modal-footer { background: var(--cream); border-top: 1px solid var(--mist); padding: 14px 24px; }

    .form-field label {
        font-weight: 600; font-size: 0.82rem; color: var(--mahogany);
        display: block; margin-bottom: 6px;
    }
    .form-field .input-group {
        border-radius: 10px; overflow: hidden;
        border: 2px solid var(--mist); transition: var(--transition);
    }
    .form-field .input-group:focus-within { border-color: var(--gold); box-shadow: 0 0 0 4px rgba(201,145,42,0.1); }
    .form-field .input-group-text {
        background: var(--cream); border: none; color: var(--caramel);
        font-size: 0.88rem; padding: 8px 12px;
    }
    .form-field .form-control, .form-field .form-select {
        border: none; background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-weight: 500; font-size: 0.9rem;
        padding: 8px 12px;
    }
    .form-field .form-control:focus, .form-field .form-select:focus { box-shadow: none; background: var(--ivory); }
    .section-divider {
        display: flex; align-items: center; gap: 10px; margin: 18px 0;
    }
    .section-divider span { font-size: 0.78rem; color: var(--caramel); font-weight: 600; white-space: nowrap; }
    .section-divider::before, .section-divider::after {
        content: ''; flex: 1; height: 1px; background: var(--mist);
    }
    .modal-action-btn {
        width: 100%; padding: 10px; border: none; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.88rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .modal-action-btn.update-name {
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); box-shadow: 0 4px 12px rgba(201,145,42,0.25);
    }
    .modal-action-btn.update-name:hover { background: linear-gradient(135deg, var(--espresso), var(--mahogany)); color: var(--honey); }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso); color: rgba(255,255,255,0.75);
        padding: 54px 5% 24px; margin-top: auto; position: relative;
    }
    footer::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey), var(--gold), var(--caramel));
    }
    .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; margin-bottom: 32px; }
    .footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.05rem; color: var(--honey); margin-bottom: 14px; }
    .footer-section p { font-size: 0.85rem; line-height: 1.8; margin-bottom: 10px; }
    .social-links { display: flex; gap: 8px; margin-top: 12px; }
    .social-links a {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(201,145,42,0.12); border: 1px solid rgba(201,145,42,0.25);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.85rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }
    .footer-links { display: flex; flex-direction: column; gap: 8px; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; transition: var(--transition); }
    .footer-links a:hover { color: var(--honey); padding-left: 5px; }
    .copyright { border-top: 1px solid rgba(255,255,255,0.08); padding-top: 18px; text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.3); }

    /* ===================== SCROLL TOP ===================== */
    .scroll-to-top {
        position: fixed; bottom: 30px; right: 30px; z-index: 999;
        width: 44px; height: 44px; border-radius: 50%;
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border: none; color: var(--honey); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; box-shadow: var(--shadow-md);
        opacity: 0; visibility: hidden; transition: var(--transition);
    }
    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .navbar { padding: 0 20px; }
        .nav-links a:not(.active) { display: none; }
        .hero-inner { flex-direction: column; }
        .tab-nav { width: 100%; }
        .tab-btn { flex: 1; justify-content: center; }
        .search-filter-bar { flex-direction: column; align-items: stretch; }
        .search-wrap { width: 100%; }
        .result-chip { text-align: center; display: block; }
    }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
    <a href="shop.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <a href="admin.php">Dashboard</a>
        <span class="admin-chip"><i class="fas fa-shield-alt"></i> Admin</span>
        <?php
        $nav_username = $_SESSION['username'] ?? 'Admin';
        $user_id = $_SESSION['user_id'];
        $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
        $pic_stmt->bind_param("i", $user_id);
        $pic_stmt->execute();
        $pic_stmt->bind_result($profile_pic);
        $pic_stmt->fetch();
        $pic_stmt->close();
        if (!$profile_pic) $profile_pic = 'images/profile.jpg';
        $profile_pic_safe = htmlspecialchars($profile_pic);
        ?>
        <div class="profile-dropdown">
            <img src="<?= $profile_pic_safe ?>" alt="Profile" class="profile-pic"
                 onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= htmlspecialchars($nav_username) ?></div>
                    <div class="dropdown-profile-role">Administrator</div>
                </div>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i>Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- ===================== TOAST ===================== -->
<div class="toast-container">
    <div id="toastMessage" class="toast text-white border-0 custom-toast" role="alert"
         aria-live="assertive" aria-atomic="true" data-bs-delay="3500">
        <div class="toast-body">
            <span id="toastText">Action completed.</span>
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
            <div class="hero-label"><i class="fas fa-shield-alt"></i> ADMIN PANEL</div>
            <h1 class="hero-title">Manage <em>Accounts</em></h1>
            <p class="hero-subtitle">View, update, and control all user accounts and permissions across the Pawganic platform.</p>
        </div>
    </div>
</section>

<!-- ===================== MAIN ===================== -->
<div class="page-content">

    <!-- Stat Cards -->
    <div class="stats-row">
        <div class="stat-card total-card">
            <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-num"><?= $total_users ?></div>
                <div class="stat-lbl">Total Accounts</div>
                <div class="stat-sub">All roles combined</div>
            </div>
        </div>
        <div class="stat-card admin-card">
            <div class="stat-icon-wrap"><i class="fas fa-user-shield"></i></div>
            <div>
                <div class="stat-num"><?= $admin_count ?></div>
                <div class="stat-lbl">Administrators</div>
                <div class="stat-sub">Full platform access</div>
            </div>
        </div>
        <div class="stat-card user-card-stat">
            <div class="stat-icon-wrap"><i class="fas fa-user"></i></div>
            <div>
                <div class="stat-num"><?= $user_count ?></div>
                <div class="stat-lbl">Regular Users</div>
                <div class="stat-sub">Shop customers</div>
            </div>
        </div>
        <div class="stat-card newest-card">
            <div class="stat-icon-wrap"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="stat-num" style="font-size:1.05rem; line-height:1.2; font-weight:700;">
                    <?= $newest ? htmlspecialchars($newest['username']) : '—' ?>
                </div>
                <div class="stat-lbl">Newest Member</div>
                <div class="stat-sub">
                    <?php
                        if ($newest && !empty($newest['created_at'])) {
                            echo date('M d, Y', strtotime($newest['created_at']));
                        } else {
                            echo 'Recently joined';
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Consolidated Search & Tab Controls -->
    <div class="search-filter-bar">
        <div class="tab-nav" id="tabNav">
            <button type="button" class="tab-btn active" data-tab="all">
                <i class="fas fa-layer-group"></i> All
                <span class="tab-count"><?= $total_users ?></span>
            </button>
            <button type="button" class="tab-btn" data-tab="admin">
                <i class="fas fa-user-shield"></i> Admins
                <span class="tab-count"><?= $admin_count ?></span>
            </button>
            <button type="button" class="tab-btn" data-tab="user">
                <i class="fas fa-user"></i> Customers
                <span class="tab-count"><?= $user_count ?></span>
            </button>
        </div>
        
        <div class="d-flex align-items-center gap-3 flex-grow-1 flex-md-grow-0 justify-content-end">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="liveSearch" placeholder="Search by name, email, or ID…">
            </div>
            <span class="result-chip" id="resultCount">Showing <?= $total_users ?> accounts</span>
        </div>
    </div>

    <!-- User Table -->
    <div class="table-container fade-up" id="userListTable">
        <div class="table-responsive">
            <table class="table custom-table align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="d-none d-md-table-cell">Email</th>
                        <th>Role</th>
                        <th class="d-none d-sm-table-cell">Balance</th>
                        <th class="d-none d-lg-table-cell">Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                <?php
                $all_users = $conn->query("SELECT * FROM users ORDER BY role ASC, username ASC");
                $card_idx = 0;
                while ($u = $all_users->fetch_assoc()):
                    $id        = $u['id'];
                    $username  = htmlspecialchars($u['username']);
                    $role      = $u['role'];
                    $is_me     = ($id == $_SESSION['user_id']);
                    $initials  = strtoupper(mb_substr($u['username'], 0, 1));
                    $joined    = ($has_created_at && !empty($u['created_at']))
                                 ? date('M d, Y', strtotime($u['created_at']))
                                 : null;
                    $card_idx++;
                ?>
                <tr class="user-row" 
                    data-role="<?= $role ?>" 
                    data-username="<?= strtolower($u['username']) ?>" 
                    data-email="<?= strtolower(htmlspecialchars($u['email'] ?? '')) ?>" 
                    data-id="<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>"
                    style="animation-delay: <?= $card_idx * 0.03 ?>s;">
                    <!-- User Info -->
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <?php
                            $user_pic = !empty($u['profile_pic']) ? htmlspecialchars($u['profile_pic']) : 'images/profile.jpg';
                            ?>
                            <img src="<?= $user_pic ?>" class="table-avatar" onerror="this.src='images/profile.jpg'" alt="Profile">
                            <div>
                                <div class="fw-bold d-flex align-items-center gap-2">
                                    <?= $username ?>
                                    <?php if ($is_me): ?><span class="you-chip">YOU</span><?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:0.75rem;">ID #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
                            </div>
                        </div>
                    </td>
                    
                    <!-- Email -->
                    <td class="d-none d-md-table-cell">
                        <div class="text-muted" style="font-size:0.88rem;"><?= htmlspecialchars($u['email'] ?? 'No email set') ?></div>
                    </td>
                    
                    <!-- Role -->
                    <td>
                        <span class="role-pill <?= $role ?>">
                            <i class="fas fa-<?= $role === 'admin' ? 'shield-alt' : 'user' ?>"></i>
                            <?= $role === 'admin' ? 'Admin' : 'Customer' ?>
                        </span>
                    </td>
                    
                    <!-- Balance -->
                    <td class="d-none d-sm-table-cell fw-bold">
                        ₱<?= number_format($u['balance'], 2) ?>
                    </td>
                    
                    <!-- Joined -->
                    <td class="d-none d-lg-table-cell text-muted" style="font-size:0.85rem;">
                        <?= $joined ? $joined : 'Recently joined' ?>
                    </td>
                    
                    <!-- Actions -->
                    <td>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-table-action edit edit-user-btn" 
                                    data-id="<?= $id ?>" 
                                    data-username="<?= $username ?>" 
                                    data-email="<?= htmlspecialchars($u['email'] ?? '') ?>" 
                                    data-role="<?= $role ?>"
                                    data-is-me="<?= $is_me ? 'true' : 'false' ?>"
                                    title="Edit details">
                                <i class="fas fa-pen"></i>
                            </button>
                            <?php if (!$is_me): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete <?= $username ?>? This cannot be undone.')">
                                    <input type="hidden" name="delete_id" value="<?= $id ?>">
                                    <button type="submit" class="btn-table-action delete" title="Delete account">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn-table-action disabled" disabled title="Protected (Current User)">
                                    <i class="fas fa-lock"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Empty state (hidden by default) -->
    <div id="emptyState" class="empty-state" style="display:none;">
        <i class="fas fa-search"></i>
        <h4>No accounts found</h4>
        <p>Try adjusting your search or filter.</p>
    </div>

    <!-- Back button -->
    <div style="text-align:center; margin-top:40px;">
        <a href="admin.php" style="display:inline-flex; align-items:center; gap:10px; padding:13px 30px;
            background:linear-gradient(135deg, var(--espresso), var(--mahogany)); color:var(--honey);
            border-radius:50px; text-decoration:none; font-weight:700; font-size:0.95rem;
            box-shadow:var(--shadow-md); transition:var(--transition);"
            onmouseover="this.style.background='var(--gold)'; this.style.color='var(--white)'"
            onmouseout="this.style.background='linear-gradient(135deg, var(--espresso), var(--mahogany))'; this.style.color='var(--honey)'">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Single Shared Edit Modal -->
<div class="modal fade edit-modal" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-2"></i>Edit Details — <span id="modalTitleUsername">User</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user_full">
                    <input type="hidden" name="user_id" id="modalUserId">
                    
                    <!-- Username -->
                    <div class="form-field mb-3">
                        <label><i class="fas fa-user" style="color:var(--gold); margin-right:6px;"></i>Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <input type="text" name="username" id="modalUsername" class="form-control" required autocomplete="off">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-field mb-3">
                        <label><i class="fas fa-envelope" style="color:var(--gold); margin-right:6px;"></i>Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="email" name="email" id="modalEmail" class="form-control" placeholder="Enter email address" autocomplete="off">
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-field mb-3" id="modalRoleFieldContainer">
                        <label><i class="fas fa-shield-alt" style="color:var(--gold); margin-right:6px;"></i>Role</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-shield"></i></span>
                            <select name="role" id="modalRole" class="form-select" style="border:none; background:var(--cream); color:var(--espresso); font-family:'DM Sans'; font-weight:500;">
                                <option value="admin">Administrator</option>
                                <option value="user">Customer</option>
                            </select>
                        </div>
                        <div id="modalSelfAlert" class="text-muted mt-2 d-none" style="font-size:0.75rem;">
                            <i class="fas fa-info-circle"></i> You cannot change your own role.
                        </div>
                    </div>

                    <div class="section-divider">
                        <span><i class="fas fa-key" style="color:var(--gold);"></i> Password (Optional)</span>
                    </div>

                    <!-- Password -->
                    <div class="form-field mb-2">
                        <label><i class="fas fa-lock" style="color:var(--gold); margin-right:6px;"></i>New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" name="password" id="modalPassword" class="form-control" placeholder="Leave blank to keep current password" autocomplete="new-password">
                            <button type="button" class="btn" style="border:none; background:var(--cream); color:var(--caramel);" onclick="togglePw('modalPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <small style="color:var(--caramel); font-size:0.78rem;">
                        <i class="fas fa-info-circle"></i> Immediate effect
                    </small>
                    <button type="submit" class="modal-action-btn update-name" style="width: auto; padding: 10px 24px;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Since 2020, crafting premium, health-conscious treats for feline wellness — by cat lovers, for cat lovers.</p>
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
            <h3>Admin Panel</h3>
            <div class="footer-links">
                <a href="manage_accounts.php"><i class="fas fa-users" style="width:16px;"></i> Manage Accounts</a>
                <a href="index.php"><i class="fas fa-box" style="width:16px;"></i> Manage Products</a>
                <a href="admin_purchases.php"><i class="fas fa-receipt" style="width:16px;"></i> Orders</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt" style="width:16px;"></i> Logout</a>
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
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ---- Toast on page load (PHP message) ---- */
<?php if (isset($_SESSION['success_message'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('toastText').textContent = "<?= addslashes($_SESSION['success_message']) ?>";
    new bootstrap.Toast(document.getElementById('toastMessage')).show();
});
<?php unset($_SESSION['success_message']); endif; ?>

/* ---- Password toggle ---- */
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* ---- Live search + filter ---- */
const searchInput  = document.getElementById('liveSearch');
const resultCount  = document.getElementById('resultCount');
const emptyState   = document.getElementById('emptyState');
const rows         = document.querySelectorAll('#userTableBody .user-row');

let currentTab = 'all';

function filterCards() {
    const q = searchInput.value.toLowerCase().trim();
    let visible = 0;
    rows.forEach(row => {
        const name     = row.dataset.username || '';
        const email    = row.dataset.email || '';
        const id       = row.dataset.id || '';
        const rowRole  = row.dataset.role || '';
        
        const matchQ    = !q || name.includes(q) || email.includes(q) || id.includes(q);
        const matchRole = currentTab === 'all' || rowRole === currentTab;
        const show = matchQ && matchRole;
        
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    resultCount.textContent = 'Showing ' + visible + ' account' + (visible !== 1 ? 's' : '');
    emptyState.style.display = visible === 0 ? '' : 'none';
}

searchInput.addEventListener('input', filterCards);

/* ---- Tab buttons ---- */
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentTab = btn.dataset.tab;
        filterCards();
    });
});

/* ---- Single Shared Modal Populating ---- */
document.addEventListener('DOMContentLoaded', () => {
    const editModalEl = document.getElementById('editUserModal');
    if (editModalEl) {
        const editModal = new bootstrap.Modal(editModalEl);
        
        document.querySelectorAll('.edit-user-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const username = btn.dataset.username;
                const email = btn.dataset.email;
                const role = btn.dataset.role;
                const isMe = btn.dataset.isMe === 'true';
                
                document.getElementById('modalTitleUsername').textContent = username;
                document.getElementById('modalUserId').value = id;
                document.getElementById('modalUsername').value = username;
                document.getElementById('modalEmail').value = email;
                document.getElementById('modalRole').value = role;
                document.getElementById('modalPassword').value = ''; // clear password field
                
                const roleSelect = document.getElementById('modalRole');
                const selfAlert = document.getElementById('modalSelfAlert');
                
                if (isMe) {
                    roleSelect.disabled = true;
                    selfAlert.classList.remove('d-none');
                    
                    // Add a hidden input to submit the role if disabled, since disabled elements aren't sent in POST
                    let hiddenRole = document.getElementById('modalRoleHidden');
                    if (!hiddenRole) {
                        hiddenRole = document.createElement('input');
                        hiddenRole.type = 'hidden';
                        hiddenRole.name = 'role';
                        hiddenRole.id = 'modalRoleHidden';
                        document.getElementById('editUserForm').appendChild(hiddenRole);
                    }
                    hiddenRole.value = role;
                } else {
                    roleSelect.disabled = false;
                    selfAlert.classList.add('d-none');
                    
                    const hiddenRole = document.getElementById('modalRoleHidden');
                    if (hiddenRole) {
                        hiddenRole.remove();
                    }
                }
                
                editModal.show();
            });
        });
    }

    /* ---- Profile dropdown ---- */
    const pd = document.querySelector('.profile-dropdown');
    if (pd) {
        pd.querySelector('.profile-pic')?.addEventListener('click', e => {
            e.stopPropagation();
            pd.classList.toggle('open');
        });
        document.addEventListener('click', e => {
            if (!pd.contains(e.target)) pd.classList.remove('open');
        });
    }

    /* Scroll to top */
    const btn = document.getElementById('scrollToTopBtn');
    window.addEventListener('scroll', () => btn.classList.toggle('show', window.pageYOffset > 300));
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
});
</script>
</body>
</html>