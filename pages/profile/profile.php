<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// ── column guards ──────────────────────────────────────────────────────────────
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
if (!$check_column || $check_column->num_rows === 0)
    $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");

$delivery_cols = ['delivery_full_name','delivery_phone','delivery_city','delivery_postal_code','delivery_address'];
foreach ($delivery_cols as $col) {
    $chk = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='$col'");
    if (!$chk || $chk->num_rows === 0)
        $conn->query("ALTER TABLE users ADD COLUMN $col " . ($col==='delivery_address'?'LONGTEXT':'VARCHAR(255)'));
}

$conn->query("CREATE TABLE IF NOT EXISTS user_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    address LONGTEXT NOT NULL,
    is_default BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// ── column guards: balance, created_at ────────────────────────────────────────
$chk_bal = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='balance'");
if (!$chk_bal || $chk_bal->num_rows === 0)
    $conn->query("ALTER TABLE users ADD COLUMN balance DECIMAL(10,2) DEFAULT 0");

$chk_cat = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='created_at'");
$has_created_at = ($chk_cat && $chk_cat->num_rows > 0);
if (!$has_created_at)
    $conn->query("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// ── fetch user (guard against missing created_at) ────────────────────────────
$member_since = null;

// Try with created_at first, fall back without if it doesn't exist
try {
    $stmt = $conn->prepare("SELECT username, email, role, balance, profile_pic, delivery_full_name, delivery_phone, delivery_city, delivery_postal_code, delivery_address, created_at FROM users WHERE id = ?");
    if ($stmt === false) throw new Exception("Prepare failed");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $email, $role, $balance, $profile_pic, $delivery_full_name, $delivery_phone, $delivery_city, $delivery_postal_code, $delivery_address, $member_since);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    // Fallback: select without created_at if column doesn't exist
    $stmt = $conn->prepare("SELECT username, email, role, balance, profile_pic, delivery_full_name, delivery_phone, delivery_city, delivery_postal_code, delivery_address FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $email, $role, $balance, $profile_pic, $delivery_full_name, $delivery_phone, $delivery_city, $delivery_postal_code, $delivery_address);
    $stmt->fetch();
    $stmt->close();
}
if (!$profile_pic) $profile_pic = 'images/profile.jpg';

// ── fetch addresses ────────────────────────────────────────────────────────────
$addresses = [];
$addr_stmt = $conn->prepare("SELECT id, full_name, phone, city, postal_code, address, is_default FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
$addr_stmt->bind_param("i", $user_id);
$addr_stmt->execute();
$addr_result = $addr_stmt->get_result();
while ($row = $addr_result->fetch_assoc()) $addresses[] = $row;
$addr_stmt->close();

// ── fetch order count + total spent ───────────────────────────────────────────
$order_count = 0; $total_spent = 0; $last_order = null;
$transaction_check = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($transaction_check && $transaction_check->num_rows > 0) {
    $os = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(total_price),0), MAX(transaction_date) FROM transactions WHERE user_id=?");
    $os->bind_param("i",$user_id); $os->execute();
    $os->bind_result($order_count, $total_spent, $last_order); $os->fetch(); $os->close();
}

// ── fetch favorites count ──────────────────────────────────────────────────────
$fav_count = 0;
$fav_check = $conn->query("SHOW TABLES LIKE 'favorites'");
if ($fav_check && $fav_check->num_rows > 0) {
    $fs = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=?");
    $fs->bind_param("i",$user_id); $fs->execute();
    $fs->bind_result($fav_count); $fs->fetch(); $fs->close();
}

// ── POST handler ───────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'update_email') {
        $new_email = filter_var($_POST['new_email']??'', FILTER_VALIDATE_EMAIL);
        if (!$new_email) { $message='Invalid email format!'; $messageType='error'; }
        else {
            try {
                $u=$conn->prepare("UPDATE users SET email=? WHERE id=?");
                $u->bind_param("si",$new_email,$user_id);
                if($u->execute()){$email=$new_email;$message='Email updated successfully!';$messageType='success';}
                else{$message='Email already in use!';$messageType='error';}
                $u->close();
            } catch(Exception $e){ $message='Email already in use!';$messageType='error'; }
        }
    }
    elseif ($action == 'update_username') {
        $new_username = trim($_POST['new_username']??'');
        if(strlen($new_username)<3){$message='Min 3 characters.';$messageType='error';}
        else {
            try {
                $u=$conn->prepare("UPDATE users SET username=? WHERE id=?");
                $u->bind_param("si",$new_username,$user_id);
                if($u->execute()){$username=$new_username;$_SESSION['username']=$new_username;$message='Username updated!';$messageType='success';}
                else{$message='Username already taken!';$messageType='error';}
                $u->close();
            } catch(Exception $e){ $message='Username already taken!';$messageType='error'; }
        }
    }
    elseif ($action == 'update_password') {
        $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $con=$_POST['confirm_password']??'';
        $chk=$conn->prepare("SELECT password FROM users WHERE id=?"); $chk->bind_param("i",$user_id); $chk->execute(); $chk->bind_result($hp); $chk->fetch(); $chk->close();
        if(!password_verify($cur,$hp)){$message='Current password is incorrect!';$messageType='error';}
        elseif(strlen($new)<6){$message='New password must be at least 6 characters.';$messageType='error';}
        elseif($new!==$con){$message='Passwords don\'t match!';$messageType='error';}
        else {
            $u=$conn->prepare("UPDATE users SET password=? WHERE id=?"); $nh=password_hash($new,PASSWORD_DEFAULT); $u->bind_param("si",$nh,$user_id);
            if($u->execute()){$message='Password changed successfully!';$messageType='success';}else{$message='Error updating password.';$messageType='error';} $u->close();
        }
    }
    elseif ($action == 'delete_account') {
        $pw=$_POST['delete_password']??'';
        $chk=$conn->prepare("SELECT password FROM users WHERE id=?"); $chk->bind_param("i",$user_id); $chk->execute(); $chk->bind_result($hp); $chk->fetch(); $chk->close();
        if(!password_verify($pw,$hp)){$message='Incorrect password!';$messageType='error';}
        else {
            $d=$conn->prepare("DELETE FROM users WHERE id=?"); $d->bind_param("i",$user_id);
            if($d->execute()){$d->close();session_destroy();header("Location: index.php?account_deleted=true");exit;}
            else{$message='Error deleting account.';$messageType='error';} $d->close();
        }
    }
    elseif ($action == 'add_address') {
        $fn=$_POST['address_full_name']??''; $ph=$_POST['address_phone']??''; $ct=$_POST['address_city']??''; $pc=$_POST['address_postal_code']??''; $ad=$_POST['address_address']??'';
        $is_default_input = isset($_POST['address_is_default']) && $_POST['address_is_default'] == '1';
        if(empty($fn)||empty($ph)||empty($ct)||empty($pc)||empty($ad)){$message='All fields are required!';$messageType='error';}
        else {
            // Check if user has any existing addresses
            $chk_exist = $conn->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $chk_exist->bind_param("i", $user_id);
            $chk_exist->execute();
            $chk_exist->bind_result($count);
            $chk_exist->fetch();
            $chk_exist->close();

            $is_default = ($count == 0 || $is_default_input) ? 1 : 0;

            if ($is_default == 1) {
                // Clear all defaults for this user first
                $cl=$conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=? AND is_default=1");
                $cl->bind_param("i",$user_id);
                $cl->execute();
                $cl->close();
            }

            $i=$conn->prepare("INSERT INTO user_addresses (user_id,full_name,phone,city,postal_code,address,is_default) VALUES(?,?,?,?,?,?,?)");
            $i->bind_param("isssssi",$user_id,$fn,$ph,$ct,$pc,$ad,$is_default);
            if($i->execute()){
                // If it is default, sync it to users table
                if ($is_default == 1) {
                    $u_sync = $conn->prepare("UPDATE users SET delivery_full_name = ?, delivery_phone = ?, delivery_city = ?, delivery_postal_code = ?, delivery_address = ? WHERE id = ?");
                    $u_sync->bind_param("sssssi", $fn, $ph, $ct, $pc, $ad, $user_id);
                    $u_sync->execute();
                    $u_sync->close();
                }
                $message='Address added successfully!';$messageType='success';header("Refresh:0");
            }
            else{$message='Error adding address!';$messageType='error';} $i->close();
        }
    }
    elseif ($action == 'edit_address') {
        $aid=intval($_POST['address_id']??0); $fn=$_POST['address_full_name']??''; $ph=$_POST['address_phone']??''; $ct=$_POST['address_city']??''; $pc=$_POST['address_postal_code']??''; $ad=$_POST['address_address']??'';
        $is_default_input = isset($_POST['address_is_default']) && $_POST['address_is_default'] == '1';
        if(empty($fn)||empty($ph)||empty($ct)||empty($pc)||empty($ad)){$message='All fields are required!';$messageType='error';}
        else {
            // Check if the edited address is default
            $chk_default = $conn->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
            $chk_default->bind_param("ii", $aid, $user_id);
            $chk_default->execute();
            $chk_default->bind_result($is_default);
            $chk_default->fetch();
            $chk_default->close();

            $new_is_default = ($is_default == 1 || $is_default_input) ? 1 : 0;

            if ($new_is_default == 1 && $is_default == 0) {
                // Promotion to default: clear other defaults
                $cl=$conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=? AND is_default=1");
                $cl->bind_param("i",$user_id);
                $cl->execute();
                $cl->close();
            }

            $u=$conn->prepare("UPDATE user_addresses SET full_name=?,phone=?,city=?,postal_code=?,address=?,is_default=? WHERE id=? AND user_id=?");
            $u->bind_param("sssssiii",$fn,$ph,$ct,$pc,$ad,$new_is_default,$aid,$user_id);
            if($u->execute()){
                // If it was/is the default address, sync changes to users table
                if ($new_is_default == 1) {
                    $u_sync = $conn->prepare("UPDATE users SET delivery_full_name = ?, delivery_phone = ?, delivery_city = ?, delivery_postal_code = ?, delivery_address = ? WHERE id = ?");
                    $u_sync->bind_param("sssssi", $fn, $ph, $ct, $pc, $ad, $user_id);
                    $u_sync->execute();
                    $u_sync->close();
                }
                $message='Address updated successfully!';$messageType='success';header("Refresh:0");
            }
            else{$message='Error updating address.';$messageType='error';} $u->close();
        }
    }
    elseif ($action == 'delete_address') {
        $aid=intval($_POST['address_id']??0);
        
        // Check if the deleted address is default
        $chk_default = $conn->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND user_id = ?");
        $chk_default->bind_param("ii", $aid, $user_id);
        $chk_default->execute();
        $chk_default->bind_result($is_default);
        $chk_default->fetch();
        $chk_default->close();

        $d=$conn->prepare("DELETE FROM user_addresses WHERE id=? AND user_id=?"); $d->bind_param("ii",$aid,$user_id);
        if($d->execute()){
            // If the deleted address was the default one, look for the next one
            if ($is_default == 1) {
                $next_addr = $conn->prepare("SELECT id, full_name, phone, city, postal_code, address FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                $next_addr->bind_param("i", $user_id);
                $next_addr->execute();
                $next_res = $next_addr->get_result();
                if ($next_row = $next_res->fetch_assoc()) {
                    // Make this next address default
                    $new_aid = $next_row['id'];
                    $up_def = $conn->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ?");
                    $up_def->bind_param("i", $new_aid);
                    $up_def->execute();
                    $up_def->close();

                    // Sync to users table
                    $u_sync = $conn->prepare("UPDATE users SET delivery_full_name = ?, delivery_phone = ?, delivery_city = ?, delivery_postal_code = ?, delivery_address = ? WHERE id = ?");
                    $u_sync->bind_param("sssssi", $next_row['full_name'], $next_row['phone'], $next_row['city'], $next_row['postal_code'], $next_row['address'], $user_id);
                    $u_sync->execute();
                    $u_sync->close();
                } else {
                    // No other addresses left, set users table delivery columns to NULL
                    $u_sync = $conn->prepare("UPDATE users SET delivery_full_name = NULL, delivery_phone = NULL, delivery_city = NULL, delivery_postal_code = NULL, delivery_address = NULL WHERE id = ?");
                    $u_sync->bind_param("i", $user_id);
                    $u_sync->execute();
                    $u_sync->close();
                }
                $next_addr->close();
            }
            $message='Address removed.';$messageType='success';header("Refresh:0");
        }
        else{$message='Error removing address.';$messageType='error';} $d->close();
    }
    elseif ($action == 'set_default_address') {
        $aid=intval($_POST['address_id']??0);
        if ($aid <= 0) {
            $message='Invalid address ID!';
            $messageType='error';
        } else {
            // First verify the address belongs to this user and fetch details
            $verify = $conn->prepare("SELECT id, full_name, phone, city, postal_code, address FROM user_addresses WHERE id=? AND user_id=?");
            $verify->bind_param("ii", $aid, $user_id);
            $verify->execute();
            $verify_result = $verify->get_result();
            
            if ($verify_result->num_rows === 0) {
                $message='Address not found or does not belong to you!';
                $messageType='error';
                $verify->close();
            } else {
                $row = $verify_result->fetch_assoc();
                $verify->close();
                
                // Clear all defaults for this user first
                $cl=$conn->prepare("UPDATE user_addresses SET is_default=0 WHERE user_id=? AND is_default=1");
                $cl->bind_param("i",$user_id);
                $cl->execute();
                $cl->close();
                
                // Set the selected address as default
                $sd=$conn->prepare("UPDATE user_addresses SET is_default=1 WHERE id=? AND user_id=?");
                $sd->bind_param("ii",$aid,$user_id);
                if($sd->execute()){
                    // Sync default address details to users table
                    $u_sync = $conn->prepare("UPDATE users SET delivery_full_name = ?, delivery_phone = ?, delivery_city = ?, delivery_postal_code = ?, delivery_address = ? WHERE id = ?");
                    $u_sync->bind_param("sssssi", $row['full_name'], $row['phone'], $row['city'], $row['postal_code'], $row['address'], $user_id);
                    $u_sync->execute();
                    $u_sync->close();

                    $message='Default address updated!';$messageType='success';header("Refresh:0");
                }
                else{$message='Error setting default.';$messageType='error';}
                $sd->close();
            }
        }
    }
}

$member_date = $member_since ? date('F Y', strtotime($member_since)) : 'June 2026';
$years_member = $member_since ? max(1, date('Y') - date('Y', strtotime($member_since))) : 1;
$profile_pic_safe = htmlspecialchars($profile_pic);
$nav_username = htmlspecialchars($_SESSION['username'] ?? $username);
$nav_balance  = number_format($_SESSION['balance'] ?? $balance, 2);
$nav_role     = htmlspecialchars($_SESSION['role'] ?? $role);

// Loyalty tier
$loyalty_tier = 'Kitten';
$loyalty_color = '#7a9e7e';
$loyalty_next = 'Cat Person';
if ($order_count >= 20) { $loyalty_tier = 'Apex Pawrent'; $loyalty_color = '#c9912a'; $loyalty_next = 'Max tier!'; }
elseif ($order_count >= 10) { $loyalty_tier = 'Purrfect'; $loyalty_color = '#9b6a2f'; $loyalty_next = 'Apex Pawrent'; }
elseif ($order_count >= 5) { $loyalty_tier = 'Cat Person'; $loyalty_color = '#5a2d0c'; $loyalty_next = 'Purrfect'; }
$loyalty_pct = min(100, ($order_count / 20) * 100);

// Open correct tab after POST
$openTab = 'overview';
if ($message) {
    if (stripos($message,'email')!==false) $openTab='account';
    elseif (stripos($message,'username')!==false||stripos($message,'Username')!==false) $openTab='account';
    elseif (stripos($message,'password')!==false||stripos($message,'Password')!==false) $openTab='security';
    elseif (stripos($message,'address')!==false||stripos($message,'Address')!==false) $openTab='addresses';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Pawganic Supplies</title>
<link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
<link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
<link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════ ROOT ═══════════════════════════════ */
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
    --shadow-sm:  0 2px 12px rgba(44,26,14,.10);
    --shadow-md:  0 8px 32px rgba(44,26,14,.16);
    --shadow-lg:  0 20px 60px rgba(44,26,14,.22);
    --radius:     18px;
    --radius-sm:  10px;
    --transition: all .35s cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--cream);font-family:'DM Sans',sans-serif;color:var(--espresso);min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden}

/* ═══════════════════════════════ NAVBAR ═══════════════════════════════ */
.navbar{
    display:flex;justify-content:space-between;align-items:center;
    background:rgba(253,248,240,.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    padding:0 5%;height:72px;position:sticky;top:0;z-index:1000;
    border-bottom:1px solid rgba(201,145,42,.18);box-shadow:0 2px 24px rgba(44,26,14,.08);
}
.logo-img{height:46px;width:auto;transition:transform .3s ease}
.logo-img:hover{transform:scale(1.05)}
.nav-links{display:flex;align-items:center;gap:6px}
.nav-links a{color:var(--mahogany);text-decoration:none;padding:8px 16px;border-radius:50px;font-weight:500;font-size:.9rem;letter-spacing:.3px;transition:var(--transition)}
.nav-links a:hover,.nav-links a.active{background:var(--gold);color:var(--white)}

.profile-dropdown{position:relative;display:flex;align-items:center;cursor:pointer}
.profile-pic{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2.5px solid var(--gold);transition:var(--transition)}
.profile-pic:hover{transform:scale(1.06);box-shadow:0 0 0 4px rgba(201,145,42,.18)}
.dropdown-content{display:none;position:absolute;right:0;top:calc(100% + 10px);background:var(--ivory);border-radius:var(--radius-sm);box-shadow:var(--shadow-lg);min-width:220px;z-index:1000;border:1px solid rgba(201,145,42,.15);overflow:hidden;animation:dropDown .25s ease}
@keyframes dropDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.profile-dropdown:hover .dropdown-content,.profile-dropdown.open .dropdown-content{display:block}
.dropdown-profile-info{padding:16px;border-bottom:1px solid var(--mist);background:linear-gradient(135deg,var(--cream),var(--ivory))}
.dropdown-profile-name{font-weight:700;color:var(--mahogany);font-size:.95rem}
.dropdown-profile-role{font-size:.78rem;color:var(--caramel);margin-top:2px}
.dropdown-profile-balance{font-size:.85rem;color:var(--gold);font-weight:600;margin-top:5px}
.dropdown-content a{display:flex;align-items:center;gap:10px;color:var(--espresso);text-decoration:none;padding:12px 16px;font-size:.9rem;transition:var(--transition)}
.dropdown-content a:hover{background:var(--cream);color:var(--mahogany);padding-left:22px}
.dropdown-content a i{width:18px;color:var(--caramel)}

/* ═══════════════════════════════ HERO ═══════════════════════════════ */
.profile-hero{
    background:linear-gradient(135deg,var(--espresso) 0%,var(--mahogany) 60%,#8b4513 100%);
    padding:80px 5% 100px;position:relative;overflow:hidden;
}
.profile-hero::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 75% 50%,rgba(201,145,42,.25) 0%,transparent 65%),
               radial-gradient(ellipse at 10% 80%,rgba(122,158,126,.15) 0%,transparent 50%);
}
.profile-hero::after{
    content:'';position:absolute;bottom:0;left:0;right:0;height:60px;
    background:var(--cream);clip-path:ellipse(55% 100% at 50% 100%);
}
.hero-deco{position:absolute;border-radius:50%;opacity:.07;background:var(--honey)}
.hero-deco-1{width:400px;height:400px;top:-120px;right:-80px}
.hero-deco-2{width:240px;height:240px;bottom:20px;left:4%}
.hero-deco-3{width:130px;height:130px;top:40px;left:35%;opacity:.05}

.hero-content{position:relative;z-index:2;max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:52px;flex-wrap:wrap}

.hero-avatar-wrap{position:relative;flex-shrink:0}
.hero-avatar{
    width:128px;height:128px;border-radius:50%;object-fit:cover;
    border:4px solid var(--gold);box-shadow:0 0 0 10px rgba(201,145,42,.18),var(--shadow-lg);
    transition:var(--transition);cursor:pointer;display:block;
}
.hero-avatar:hover{transform:scale(1.04)}
.avatar-edit-badge{
    position:absolute;bottom:4px;right:4px;
    width:34px;height:34px;background:var(--gold);border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    color:var(--espresso);font-size:.8rem;cursor:pointer;
    box-shadow:var(--shadow-sm);transition:var(--transition);border:2.5px solid var(--ivory);
}
.avatar-edit-badge:hover{background:var(--honey);transform:scale(1.12)}
.avatar-tier-badge{
    position:absolute;top:-10px;left:50%;transform:translateX(-50%);
    background:linear-gradient(135deg,var(--gold),var(--honey));
    color:var(--espresso);font-size:.65rem;font-weight:800;letter-spacing:.5px;
    text-transform:uppercase;padding:3px 10px;border-radius:50px;white-space:nowrap;
    box-shadow:0 2px 8px rgba(201,145,42,.4);
}

.hero-text{flex:1;min-width:260px}
.hero-label{
    display:inline-flex;align-items:center;gap:8px;
    background:rgba(201,145,42,.2);border:1px solid rgba(201,145,42,.4);
    color:var(--honey);padding:6px 14px;border-radius:50px;
    font-size:.78rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;margin-bottom:18px;
}
.hero-name{
    font-family:'Playfair Display',serif;font-size:clamp(2.2rem,4.5vw,3.6rem);
    font-weight:900;color:var(--white);line-height:1.1;margin-bottom:10px;
}
.hero-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:24px}
.hero-role-badge{
    display:inline-flex;align-items:center;gap:6px;
    background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
    color:rgba(255,255,255,.85);padding:6px 16px;border-radius:50px;
    font-size:.83rem;font-weight:600;text-transform:capitalize;letter-spacing:.5px;
    backdrop-filter:blur(8px);
}
.hero-since{
    font-size:.8rem;color:rgba(255,255,255,.45);display:flex;align-items:center;gap:5px;
}

.hero-stats-row{display:flex;gap:0;flex-wrap:wrap;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:var(--radius);overflow:hidden;backdrop-filter:blur(8px)}
.hero-stat{
    flex:1;min-width:90px;text-align:center;padding:14px 20px;
    border-right:1px solid rgba(255,255,255,.08);position:relative;
    transition:var(--transition);cursor:default;
}
.hero-stat:last-child{border-right:none}
.hero-stat:hover{background:rgba(255,255,255,.05)}
.hero-stat-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--honey);line-height:1}
.hero-stat-label{font-size:.68rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.8px;margin-top:5px}

/* Loyalty bar in hero */
.hero-loyalty{
    margin-top:20px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
    border-radius:var(--radius-sm);padding:14px 20px;backdrop-filter:blur(8px);
}
.hero-loyalty-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.hero-loyalty-label{font-size:.75rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:1.5px;font-weight:600}
.hero-loyalty-tier{font-size:.82rem;color:var(--honey);font-weight:700}
.hero-loyalty-bar{height:5px;background:rgba(255,255,255,.12);border-radius:3px;overflow:hidden}
.hero-loyalty-fill{height:100%;border-radius:3px;background:linear-gradient(to right,var(--caramel),var(--gold),var(--honey));transition:width 1.2s cubic-bezier(.4,0,.2,1)}

/* ═══════════════════════════════ LAYOUT ═══════════════════════════════ */
.page-wrapper{max-width:1200px;margin:-42px auto 80px;padding:0 24px;position:relative;z-index:5}

/* Sticky tab nav */
.tab-nav{
    background:var(--ivory);border-radius:var(--radius);box-shadow:var(--shadow-md);
    padding:6px;display:flex;gap:4px;margin-bottom:32px;
    border:1px solid rgba(201,145,42,.12);flex-wrap:wrap;
    position:sticky;top:80px;z-index:50;
}
.tab-btn{
    flex:1;min-width:110px;padding:12px 14px;border:none;background:transparent;
    color:var(--caramel);border-radius:var(--radius-sm);font-family:'DM Sans',sans-serif;
    font-weight:600;font-size:.85rem;cursor:pointer;transition:var(--transition);
    display:flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap;
}
.tab-btn:hover{background:var(--cream);color:var(--mahogany)}
.tab-btn.active{background:linear-gradient(135deg,var(--espresso),var(--mahogany));color:var(--honey);box-shadow:var(--shadow-sm)}
.tab-btn .tab-icon{font-size:.9rem}

/* Panels */
.tab-panel{display:none;animation:panelIn .4s ease both}
.tab-panel.active{display:block}
@keyframes panelIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* ═══════════════════════════════ CARDS ═══════════════════════════════ */
.profile-card{
    background:var(--ivory);border-radius:var(--radius);
    box-shadow:var(--shadow-sm);border:1px solid rgba(201,145,42,.10);
    overflow:hidden;margin-bottom:24px;transition:var(--transition);
}
.profile-card:hover{box-shadow:var(--shadow-md)}
.card-header-bar{
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    padding:22px 28px;display:flex;align-items:center;gap:14px;
}
.card-header-bar i{color:var(--honey);font-size:1.1rem;flex-shrink:0}
.card-header-bar h3{font-family:'Playfair Display',serif;color:var(--white);font-size:1.2rem;font-weight:700;margin:0}
.card-header-bar .card-header-sub{color:rgba(255,255,255,.5);font-size:.8rem;margin-top:3px}
.card-body-pad{padding:28px}

/* Overview stats grid */
.overview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}
.overview-item{
    background:var(--cream);border-radius:var(--radius-sm);
    padding:22px;border-left:4px solid var(--gold);transition:var(--transition);
    border:1px solid rgba(201,145,42,.12);border-left:4px solid var(--gold);
    cursor:default;position:relative;overflow:hidden;
}
.overview-item::before{
    content:'';position:absolute;top:0;right:0;bottom:0;left:0;
    background:linear-gradient(135deg,transparent 60%,rgba(201,145,42,.04));
    pointer-events:none;
}
.overview-item:hover{box-shadow:var(--shadow-sm);transform:translateY(-3px);border-color:rgba(201,145,42,.3)}
.overview-item-icon{
    width:42px;height:42px;border-radius:50%;
    background:rgba(201,145,42,.12);display:flex;align-items:center;justify-content:center;
    color:var(--gold);font-size:1rem;margin-bottom:14px;transition:var(--transition);
}
.overview-item:hover .overview-item-icon{background:rgba(201,145,42,.2);transform:scale(1.08)}
.overview-item-label{font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1.2px;font-weight:700;margin-bottom:5px}
.overview-item-value{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:var(--espresso)}
.overview-item-sub{font-size:.78rem;color:var(--caramel);margin-top:4px}

/* Loyalty card */
.loyalty-card-inner{
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    border-radius:var(--radius-sm);padding:28px;color:var(--white);position:relative;overflow:hidden;
}
.loyalty-card-inner::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse at 80% 20%,rgba(201,145,42,.3) 0%,transparent 60%);
    pointer-events:none;
}
.loyalty-tier-name{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--honey);margin-bottom:4px}
.loyalty-sub{font-size:.85rem;color:rgba(255,255,255,.55);margin-bottom:20px}
.loyalty-bar-wrap{background:rgba(255,255,255,.12);height:8px;border-radius:4px;overflow:hidden;margin-bottom:8px}
.loyalty-bar-fill{height:100%;border-radius:4px;background:linear-gradient(to right,var(--caramel),var(--gold),var(--honey));transition:width 1.5s ease}
.loyalty-bar-meta{display:flex;justify-content:space-between;font-size:.75rem;color:rgba(255,255,255,.45)}
.loyalty-perks{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.loyalty-perk{
    background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);
    color:rgba(255,255,255,.75);padding:5px 14px;border-radius:50px;
    font-size:.78rem;font-weight:500;display:flex;align-items:center;gap:6px;
}

/* Activity timeline */
.activity-timeline{display:flex;flex-direction:column;gap:0}
.activity-item{
    display:flex;gap:16px;align-items:flex-start;
    padding:18px 0;border-bottom:1px solid var(--mist);position:relative;
}
.activity-item:last-child{border-bottom:none}
.activity-item::before{
    content:'';position:absolute;left:17px;top:50px;bottom:-18px;
    width:2px;background:linear-gradient(to bottom,var(--mist),transparent);
}
.activity-item:last-child::before{display:none}
.activity-dot{
    width:36px;height:36px;border-radius:50%;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:.85rem;
    margin-top:2px;position:relative;z-index:1;
}
.activity-dot.gold{background:rgba(201,145,42,.15);color:var(--gold);border:1.5px solid rgba(201,145,42,.25)}
.activity-dot.sage{background:rgba(122,158,126,.15);color:var(--sage);border:1.5px solid rgba(122,158,126,.25)}
.activity-dot.mah{background:rgba(90,45,12,.12);color:var(--mahogany);border:1.5px solid rgba(90,45,12,.2)}
.activity-content{flex:1}
.activity-title{font-weight:600;color:var(--espresso);font-size:.9rem}
.activity-sub{font-size:.82rem;color:var(--caramel);margin-top:3px}
.activity-time{
    font-size:.72rem;color:var(--mist);margin-top:5px;font-style:italic;
    display:flex;align-items:center;gap:4px;
}
.activity-badge{
    display:inline-block;background:rgba(201,145,42,.1);color:var(--caramel);
    padding:2px 8px;border-radius:50px;font-size:.7rem;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;margin-left:6px;
}

/* ═══════════════════════════════ FORMS ═══════════════════════════════ */
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:22px}
.form-group{display:flex;flex-direction:column;gap:7px}
.form-group label{font-size:.78rem;font-weight:700;color:var(--mahogany);letter-spacing:.5px;text-transform:uppercase;display:flex;align-items:center;gap:6px}
.form-control{
    padding:12px 16px;border:2px solid var(--mist);border-radius:var(--radius-sm);
    background:var(--cream);color:var(--espresso);font-family:'DM Sans',sans-serif;
    font-size:.92rem;font-weight:500;transition:var(--transition);outline:none;width:100%;
}
.form-control:focus{border-color:var(--gold);background:var(--ivory);box-shadow:0 0 0 4px rgba(201,145,42,.1)}
.form-control:disabled{opacity:.5;cursor:not-allowed;background:var(--mist)}
.form-control::placeholder{color:var(--caramel);opacity:.6}
textarea.form-control{resize:vertical;min-height:90px}

/* Buttons */
.btn-primary-paw{
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    color:var(--honey);border:none;border-radius:50px;
    padding:12px 28px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.9rem;
    cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:8px;
}
.btn-primary-paw:hover{background:var(--gold);color:var(--white);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn-gold-paw{
    background:linear-gradient(135deg,var(--gold),var(--honey));
    color:var(--espresso);border:none;border-radius:50px;
    padding:12px 28px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.9rem;
    cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:8px;
    box-shadow:0 4px 14px rgba(201,145,42,.3);
}
.btn-gold-paw:hover{background:linear-gradient(135deg,var(--espresso),var(--mahogany));color:var(--honey);transform:translateY(-2px)}
.btn-danger-paw{
    background:transparent;border:2px solid var(--danger);color:var(--danger);border-radius:50px;
    padding:10px 24px;font-family:'DM Sans',sans-serif;font-weight:700;font-size:.88rem;
    cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:8px;
}
.btn-danger-paw:hover{background:var(--danger);color:var(--white);transform:translateY(-1px)}
.btn-outline-paw{
    background:transparent;border:2px solid var(--espresso);color:var(--espresso);border-radius:50px;
    padding:10px 24px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:.88rem;
    cursor:pointer;transition:var(--transition);display:inline-flex;align-items:center;gap:8px;
}
.btn-outline-paw:hover{background:var(--espresso);color:var(--honey);transform:translateY(-1px)}

/* ═══════════════════════════════ PHOTO PANEL ═══════════════════════════════ */
.avatar-upload-zone{
    border:2.5px dashed rgba(201,145,42,.35);border-radius:var(--radius);
    padding:40px;text-align:center;transition:var(--transition);cursor:pointer;
    background:rgba(201,145,42,.03);position:relative;
}
.avatar-upload-zone:hover,.avatar-upload-zone.drag-over{
    border-color:var(--gold);background:rgba(201,145,42,.08);
}
.avatar-upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.avatar-preview-wrap{display:flex;gap:40px;align-items:center;justify-content:center;flex-wrap:wrap;margin-bottom:28px}
.avatar-preview-item{text-align:center}
.avatar-preview{
    width:110px;height:110px;border-radius:50%;object-fit:cover;
    border:3px solid var(--gold);box-shadow:var(--shadow-sm);display:block;margin:0 auto 8px;
}
.preview-tag{display:block;font-size:.7rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1.2px;font-weight:700}
.photo-tips{
    background:rgba(122,158,126,.08);border:1px solid rgba(122,158,126,.2);
    border-radius:var(--radius-sm);padding:16px 20px;margin-top:20px;
}
.photo-tips-title{font-size:.8rem;font-weight:700;color:var(--sage);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.photo-tips ul{list-style:none;display:flex;flex-direction:column;gap:6px}
.photo-tips ul li{font-size:.83rem;color:var(--caramel);display:flex;align-items:center;gap:8px}
.photo-tips ul li::before{content:'✓';color:var(--sage);font-weight:700}

/* ═══════════════════════════════ ADDRESS CARDS ═══════════════════════════════ */
.address-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px;margin-bottom:24px}
.address-card{
    background:var(--cream);border-radius:var(--radius-sm);
    border:2px solid rgba(201,145,42,.15);padding:22px;
    position:relative;transition:var(--transition);
}
.address-card:hover{border-color:rgba(201,145,42,.4);box-shadow:var(--shadow-sm);transform:translateY(-2px)}
.address-card.is-default{
    border-color:var(--gold);
    background:linear-gradient(135deg,rgba(201,145,42,.06),rgba(232,184,109,.05));
}
.addr-card-header{
    display:flex;justify-content:space-between;align-items:center;
    border-bottom:1px solid rgba(201,145,42,.12);padding-bottom:10px;margin-bottom:14px;
}
.addr-card-title{
    font-size:.78rem;font-weight:700;color:var(--mahogany);
    text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;
}
.addr-default-badge{
    background:linear-gradient(135deg,var(--gold),var(--honey));
    color:var(--espresso);padding:3px 12px;border-radius:50px;
    font-size:.7rem;font-weight:800;letter-spacing:.5px;text-transform:uppercase;
    display:inline-flex;align-items:center;gap:4px;
}
.addr-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.addr-field-label{font-size:.7rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:2px;display:flex;align-items:center;gap:4px}
.addr-field-value{font-size:.9rem;color:var(--espresso);font-weight:500}
.addr-actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

/* ═══════════════════════════════ SECURITY ═══════════════════════════════ */
.security-level{
    background:linear-gradient(135deg,rgba(122,158,126,.1),rgba(122,158,126,.05));
    border:1px solid rgba(122,158,126,.25);border-radius:var(--radius-sm);
    padding:18px 22px;display:flex;align-items:center;gap:16px;margin-bottom:28px;
}
.security-icon{
    width:46px;height:46px;border-radius:50%;background:rgba(122,158,126,.18);
    display:flex;align-items:center;justify-content:center;color:var(--sage);font-size:1.1rem;flex-shrink:0;
}
.security-label{font-weight:700;color:var(--espresso);font-size:.9rem}
.security-sub{font-size:.8rem;color:var(--sage);font-weight:500;margin-top:2px}
.security-bar{height:5px;background:var(--mist);border-radius:3px;margin-top:8px;overflow:hidden}
.security-fill{height:100%;border-radius:3px;background:linear-gradient(to right,var(--sage-light),var(--sage));width:70%}

.security-checklist{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:24px}
.security-check-item{
    background:var(--cream);border-radius:var(--radius-sm);padding:14px 16px;
    display:flex;align-items:center;gap:10px;border:1px solid rgba(201,145,42,.1);
}
.security-check-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.8rem}
.security-check-icon.ok{background:rgba(122,158,126,.15);color:var(--sage)}
.security-check-icon.warn{background:rgba(201,145,42,.15);color:var(--gold)}
.security-check-text{font-size:.82rem;color:var(--espresso);font-weight:500}

/* Danger zone */
.danger-zone{
    background:rgba(192,57,43,.04);border:2px solid rgba(192,57,43,.18);
    border-radius:var(--radius);padding:30px;
}
.danger-zone-title{
    font-family:'Playfair Display',serif;color:var(--danger);
    font-size:1.2rem;font-weight:700;display:flex;align-items:center;gap:10px;margin-bottom:14px;
}
.danger-warning{
    background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.35);
    padding:14px 18px;border-radius:var(--radius-sm);
    color:#7d5e04;font-size:.84rem;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start;
    line-height:1.6;
}

/* ═══════════════════════════════ MODAL ═══════════════════════════════ */
.paw-modal{display:none;position:fixed;z-index:2000;inset:0;background:rgba(44,26,14,.65);backdrop-filter:blur(6px);animation:fadeInBg .3s ease}
.paw-modal.active{display:flex;align-items:center;justify-content:center}
@keyframes fadeInBg{from{opacity:0}to{opacity:1}}
@keyframes slideUpModal{from{transform:translateY(40px);opacity:0}to{transform:translateY(0);opacity:1}}
.paw-modal-content{
    background:var(--ivory);border-radius:var(--radius);box-shadow:var(--shadow-lg);
    width:90%;max-width:560px;overflow:hidden;animation:slideUpModal .4s ease;
    border:1px solid rgba(201,145,42,.2);
}
.paw-modal-header{
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    color:var(--honey);padding:24px 28px;
    display:flex;justify-content:space-between;align-items:center;
}
.paw-modal-header h3{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;margin:0}
.paw-modal-close{
    background:rgba(255,255,255,.1);border:none;color:var(--honey);
    width:36px;height:36px;border-radius:50%;cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:.95rem;transition:var(--transition);
}
.paw-modal-close:hover{background:rgba(255,255,255,.22);transform:rotate(90deg)}
.paw-modal-body{padding:28px}
.paw-modal-footer{padding:20px 28px;border-top:1px solid var(--mist);display:flex;gap:12px;background:var(--cream);justify-content:flex-end}

/* ═══════════════════════════════ TOAST ═══════════════════════════════ */
.toast-container{position:fixed;bottom:30px;left:30px;z-index:3000}
.custom-toast{
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    border-radius:var(--radius-sm);font-size:.95rem;padding:14px 20px;
    box-shadow:var(--shadow-lg);min-width:270px;max-width:350px;
    color:var(--cream);border-left:4px solid var(--gold);
}
.custom-toast .toast-body{padding:0;display:flex;justify-content:space-between;align-items:center;gap:12px}
.custom-toast .btn-close{filter:invert(1) brightness(.75);flex-shrink:0}

/* ═══════════════════════════════ SCROLL TOP ═══════════════════════════════ */
.scroll-to-top{
    position:fixed;bottom:30px;right:30px;z-index:999;
    width:48px;height:48px;border-radius:50%;
    background:linear-gradient(135deg,var(--espresso),var(--mahogany));
    border:none;color:var(--honey);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:1.1rem;
    box-shadow:var(--shadow-md);opacity:0;visibility:hidden;transition:var(--transition);
}
.scroll-to-top.show{opacity:1;visibility:visible}
.scroll-to-top:hover{background:var(--gold);color:var(--white);transform:translateY(-3px)}

/* ═══════════════════════════════ FOOTER ═══════════════════════════════ */
footer{background:var(--espresso);color:rgba(255,255,255,.75);padding:64px 5% 28px;margin-top:auto;position:relative}
footer::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(to right,var(--caramel),var(--gold),var(--honey),var(--gold),var(--caramel))}
.footer-content{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:40px;margin-bottom:40px}
.footer-section h3{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--honey);margin-bottom:20px}
.footer-section p{font-size:.88rem;line-height:1.8;margin-bottom:14px}
.social-links{display:flex;gap:12px;margin-top:16px}
.social-links a{width:40px;height:40px;border-radius:50%;background:rgba(201,145,42,.15);border:1px solid rgba(201,145,42,.3);color:var(--honey);display:flex;align-items:center;justify-content:center;text-decoration:none;transition:var(--transition);font-size:.9rem}
.social-links a:hover{background:var(--gold);border-color:var(--gold);color:var(--white);transform:translateY(-3px)}
.footer-links{display:flex;flex-direction:column;gap:10px}
.footer-links a{color:rgba(255,255,255,.65);text-decoration:none;font-size:.88rem;transition:var(--transition)}
.footer-links a:hover{color:var(--honey);padding-left:6px}
.copyright{border-top:1px solid rgba(255,255,255,.08);padding-top:20px;text-align:center;font-size:.82rem;color:rgba(255,255,255,.35)}

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

/* ═══════════════════════════════ RESPONSIVE ═══════════════════════════════ */
@media(max-width:768px){
    .navbar{padding:0 20px}
    .nav-links a:not(.active):not(:last-child){display:none}
    .hero-content{flex-direction:column;gap:28px;text-align:center}
    .hero-stats-row{justify-content:center}
    .tab-btn span{display:none}
    .tab-btn .tab-icon{font-size:1.1rem}
    .overview-grid{grid-template-columns:1fr 1fr}
    .address-grid{grid-template-columns:1fr}
    .tab-nav{top:72px}
    .slide-cart { width: 92vw; right: -92vw; }
}
@media(max-width:480px){
    .overview-grid{grid-template-columns:1fr}
    .form-row{grid-template-columns:1fr}
    .hero-stats-row{flex-direction:column}
    .hero-stat{border-right:none;border-bottom:1px solid rgba(255,255,255,.08)}
}
</style>
</head>
<body>

<!-- ════════════════════ NAVBAR ════════════════════ -->
<div class="navbar">
    <a href="main.php" style="text-decoration:none">
   <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" height="40">
    </a>
    <div class="nav-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <?php if (isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <div class="profile-dropdown">
            <img src="<?= $profile_pic_safe ?>" alt="Profile" class="profile-pic" onerror="this.src='images/profile.jpg'">
            <div class="dropdown-content">
                <div class="dropdown-profile-info">
                    <div class="dropdown-profile-name"><?= $nav_username ?></div>
                    <div class="dropdown-profile-role"><?= $nav_role ?></div>
                    <div class="dropdown-profile-balance">₱<?= $nav_balance ?></div>
                </div>
                <a href="favorites.php"><i class="fas fa-heart"></i> My Favorites</a>
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="purchase_history.php"><i class="fas fa-history"></i> Purchase History</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
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

<!-- ════════════════════ TOAST ════════════════════ -->
<div class="toast-container">
    <div id="toastMessage" class="toast text-white border-0 custom-toast" role="alert" data-bs-delay="3500">
        <div class="toast-body">
            <span id="toastText">Notification</span>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- ════════════════════ HERO ════════════════════ -->
<section class="profile-hero">
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>
    <div class="hero-deco hero-deco-3"></div>
    <div class="hero-content">
        <div class="hero-avatar-wrap">
            <div class="avatar-tier-badge"><i class="fas fa-paw" style="margin-right:4px"></i><?= $loyalty_tier ?></div>
            <img src="<?= $profile_pic_safe ?>" alt="<?= htmlspecialchars($username) ?>"
                 class="hero-avatar" id="heroAvatar" onerror="this.src='images/profile.jpg'"
                 onclick="switchTab('picture')" title="Click to change photo">
            <div class="avatar-edit-badge" onclick="switchTab('picture')" title="Change photo">
                <i class="fas fa-camera"></i>
            </div>
        </div>
        <div class="hero-text">
            <div class="hero-label"><i class="fas fa-paw"></i> MY ACCOUNT</div>
            <h1 class="hero-name"><?= htmlspecialchars($username) ?></h1>
            <div class="hero-meta">
                <div class="hero-role-badge"><i class="fas fa-shield-alt" style="font-size:.8rem"></i><?= htmlspecialchars($role) ?></div>
                <div class="hero-since"><i class="fas fa-calendar-alt"></i> Member since <?= $member_date ?></div>
            </div>
            <div class="hero-stats-row">
                <div class="hero-stat" title="Available Balance">
                    <div class="hero-stat-num">₱<?= number_format($balance, 0) ?></div>
                    <div class="hero-stat-label">Balance</div>
                </div>
                <div class="hero-stat" title="Total Orders">
                    <div class="hero-stat-num"><?= $order_count ?></div>
                    <div class="hero-stat-label">Orders</div>
                </div>
                <div class="hero-stat" title="Saved Addresses">
                    <div class="hero-stat-num"><?= count($addresses) ?></div>
                    <div class="hero-stat-label">Addresses</div>
                </div>
                <div class="hero-stat" title="Favorites">
                    <div class="hero-stat-num"><?= $fav_count ?></div>
                    <div class="hero-stat-label">Favorites</div>
                </div>
                <div class="hero-stat" title="Years as member">
                    <div class="hero-stat-num"><?= $years_member ?>y</div>
                    <div class="hero-stat-label">Loyalty</div>
                </div>
            </div>
            <div class="hero-loyalty">
                <div class="hero-loyalty-top">
                    <span class="hero-loyalty-label"><i class="fas fa-crown" style="margin-right:4px;color:var(--honey)"></i> Loyalty Tier</span>
                    <span class="hero-loyalty-tier"><?= $loyalty_tier ?> <?php if($loyalty_next!='Max tier!'): ?>→ <?= $loyalty_next ?><?php endif; ?></span>
                </div>
                <div class="hero-loyalty-bar">
                    <div class="hero-loyalty-fill" style="width:<?= $loyalty_pct ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════════ MAIN CONTENT ════════════════════ -->
<div class="page-wrapper">

    <!-- Tab Navigation -->
    <div class="tab-nav" role="tablist">
        <button class="tab-btn active" onclick="switchTab('overview')" id="tab-overview">
            <i class="fas fa-chart-pie tab-icon"></i><span>Overview</span>
        </button>
        <button class="tab-btn" onclick="switchTab('picture')" id="tab-picture">
            <i class="fas fa-camera tab-icon"></i><span>Photo</span>
        </button>
        <button class="tab-btn" onclick="switchTab('account')" id="tab-account">
            <i class="fas fa-user-edit tab-icon"></i><span>Account</span>
        </button>
        <button class="tab-btn" onclick="switchTab('security')" id="tab-security">
            <i class="fas fa-lock tab-icon"></i><span>Security</span>
        </button>
        <button class="tab-btn" onclick="switchTab('addresses')" id="tab-addresses">
            <i class="fas fa-map-marker-alt tab-icon"></i><span>Addresses</span>
        </button>
        <button class="tab-btn" onclick="switchTab('danger')" id="tab-danger">
            <i class="fas fa-exclamation-triangle tab-icon"></i><span>Danger Zone</span>
        </button>
    </div>

    <!-- ══ PANEL: OVERVIEW ══ -->
    <div class="tab-panel active" id="panel-overview">

        <!-- Stats overview -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-chart-bar"></i>
                <div>
                    <h3>Account Overview</h3>
                    <div class="card-header-sub">Member since <?= $member_date ?> · <?= $years_member ?> year<?= $years_member>1?'s':'' ?> with Pawganic</div>
                </div>
            </div>
            <div class="card-body-pad">
                <div class="overview-grid">
                    <div class="overview-item" onclick="switchTab('account')" style="cursor:pointer" title="Manage account">
                        <div class="overview-item-icon"><i class="fas fa-wallet"></i></div>
                        <div class="overview-item-label">Available Balance</div>
                        <div class="overview-item-value">₱<?= number_format($balance, 2) ?></div>
                        <div class="overview-item-sub">Ready to spend</div>
                    </div>
                    <div class="overview-item" onclick="window.location.href='purchase_history.php'" style="cursor:pointer" title="View orders">
                        <div class="overview-item-icon"><i class="fas fa-shopping-bag"></i></div>
                        <div class="overview-item-label">Total Orders</div>
                        <div class="overview-item-value"><?= $order_count ?></div>
                        <div class="overview-item-sub"><?= $last_order ? 'Last: '.date('M d', strtotime($last_order)) : 'No orders yet' ?></div>
                    </div>
                    <div class="overview-item" onclick="window.location.href='purchase_history.php'" style="cursor:pointer">
                        <div class="overview-item-icon"><i class="fas fa-receipt"></i></div>
                        <div class="overview-item-label">Total Spent</div>
                        <div class="overview-item-value">₱<?= number_format($total_spent, 0) ?></div>
                        <div class="overview-item-sub">Lifetime purchases</div>
                    </div>
                    <div class="overview-item" onclick="switchTab('addresses')" style="cursor:pointer" title="Manage addresses">
                        <div class="overview-item-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="overview-item-label">Saved Addresses</div>
                        <div class="overview-item-value"><?= count($addresses) ?></div>
                        <div class="overview-item-sub"><?= count($addresses)>0 ? 'Delivery ready' : 'Add one now' ?></div>
                    </div>
                    <div class="overview-item" onclick="window.location.href='favorites.php'" style="cursor:pointer">
                        <div class="overview-item-icon"><i class="fas fa-heart"></i></div>
                        <div class="overview-item-label">Favorites</div>
                        <div class="overview-item-value"><?= $fav_count ?></div>
                        <div class="overview-item-sub">Saved items</div>
                    </div>
                    <div class="overview-item" onclick="switchTab('account')" style="cursor:pointer">
                        <div class="overview-item-icon"><i class="fas fa-envelope"></i></div>
                        <div class="overview-item-label">Email</div>
                        <div class="overview-item-value" style="font-size:.95rem;word-break:break-all"><?= htmlspecialchars($email) ?></div>
                        <div class="overview-item-sub">Account email</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loyalty card -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-crown"></i>
                <div><h3>Loyalty Status</h3><div class="card-header-sub">Your rewards journey</div></div>
            </div>
            <div class="card-body-pad">
                <div class="loyalty-card-inner">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:20px">
                        <div>
                            <div style="font-size:.72rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;font-weight:600;margin-bottom:4px">Current Tier</div>
                            <div class="loyalty-tier-name"><?= $loyalty_tier ?></div>
                            <div class="loyalty-sub">
                                <?php if($loyalty_next!='Max tier!'): ?>
                                    <?= $order_count ?> / <?= $order_count<5?5:($order_count<10?10:20) ?> orders to <strong style="color:var(--honey)"><?= $loyalty_next ?></strong>
                                <?php else: ?>
                                    You've reached the highest tier! 🎉
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:.72rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:1.5px;font-weight:600;margin-bottom:4px">Total Spent</div>
                            <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--honey)">₱<?= number_format($total_spent, 0) ?></div>
                        </div>
                    </div>
                    <div class="loyalty-bar-wrap">
                        <div class="loyalty-bar-fill" style="width:<?= $loyalty_pct ?>%"></div>
                    </div>
                    <div class="loyalty-bar-meta">
                        <span><?= $order_count ?> orders</span>
                        <span><?php echo $loyalty_next!='Max tier!' ? '20 orders for max tier' : 'Max tier reached!' ?></span>
                    </div>
                    <div class="loyalty-perks">
                        <span class="loyalty-perk"><i class="fas fa-tag"></i> Member discounts</span>
                        <span class="loyalty-perk"><i class="fas fa-truck"></i> Priority shipping</span>
                        <span class="loyalty-perk"><i class="fas fa-gift"></i> Birthday rewards</span>
                        <?php if($order_count>=10): ?><span class="loyalty-perk"><i class="fas fa-star"></i> Exclusive deals</span><?php endif; ?>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top" style="border-top-color: rgba(201,145,42,0.15) !important;">
                    <h4 style="font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--mahogany); margin-bottom:16px;">
                        <i class="fas fa-list-ul" style="margin-right:8px; color:var(--gold);"></i> Tier Benefits & Privileges
                    </h4>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:16px;">
                        
                        <!-- Kitten Tier Card -->
                        <div style="background: var(--cream); border: 2px solid <?= ($order_count < 5) ? 'var(--gold)' : 'var(--mist)' ?>; border-radius: var(--radius-sm); padding: 16px; position:relative;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="color:var(--espresso); font-size:0.95rem;">🐾 Kitten</strong>
                                <?php if ($order_count < 5): ?>
                                    <span style="font-size:0.7rem; background:var(--gold); color:var(--espresso); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">Active</span>
                                <?php else: ?>
                                    <span style="font-size:0.7rem; background:var(--sage); color:white; padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">✓ Unlocked</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--caramel); margin-bottom:6px;">0 - 4 Orders Required</div>
                            <ul style="list-style:none; padding:0; margin:0; font-size:0.8rem; color:var(--espresso); display:flex; flex-direction:column; gap:4px;">
                                <li>• Standard member discounts</li>
                                <li>• Access to cat care resources</li>
                            </ul>
                        </div>

                        <!-- Cat Person Tier Card -->
                        <div style="background: var(--cream); border: 2px solid <?= ($order_count >= 5 && $order_count < 10) ? 'var(--gold)' : 'var(--mist)' ?>; border-radius: var(--radius-sm); padding: 16px; opacity: <?= ($order_count >= 5) ? '1' : '0.6' ?>;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="color:var(--espresso); font-size:0.95rem;">🧶 Cat Person</strong>
                                <?php if ($order_count >= 5 && $order_count < 10): ?>
                                    <span style="font-size:0.7rem; background:var(--gold); color:var(--espresso); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">Active</span>
                                <?php elseif ($order_count >= 10): ?>
                                    <span style="font-size:0.7rem; background:var(--sage); color:white; padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">✓ Unlocked</span>
                                <?php else: ?>
                                    <span style="font-size:0.7rem; background:var(--mist); color:var(--caramel); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;"><i class="fas fa-lock"></i> Locked</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--caramel); margin-bottom:6px;">5 - 9 Orders Required</div>
                            <ul style="list-style:none; padding:0; margin:0; font-size:0.8rem; color:var(--espresso); display:flex; flex-direction:column; gap:4px;">
                                <li>• 10% off selected treats/toys</li>
                                <li>• Organic birthday treats gift</li>
                            </ul>
                        </div>

                        <!-- Purrfect Tier Card -->
                        <div style="background: var(--cream); border: 2px solid <?= ($order_count >= 10 && $order_count < 20) ? 'var(--gold)' : 'var(--mist)' ?>; border-radius: var(--radius-sm); padding: 16px; opacity: <?= ($order_count >= 10) ? '1' : '0.6' ?>;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="color:var(--espresso); font-size:0.95rem;">👑 Purrfect</strong>
                                <?php if ($order_count >= 10 && $order_count < 20): ?>
                                    <span style="font-size:0.7rem; background:var(--gold); color:var(--espresso); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">Active</span>
                                <?php elseif ($order_count >= 20): ?>
                                    <span style="font-size:0.7rem; background:var(--sage); color:white; padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">✓ Unlocked</span>
                                <?php else: ?>
                                    <span style="font-size:0.7rem; background:var(--mist); color:var(--caramel); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;"><i class="fas fa-lock"></i> Locked</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--caramel); margin-bottom:6px;">10 - 19 Orders Required</div>
                            <ul style="list-style:none; padding:0; margin:0; font-size:0.8rem; color:var(--espresso); display:flex; flex-direction:column; gap:4px;">
                                <li>• 15% off all checkout bills</li>
                                <li>• Free priority home shipping</li>
                                <li>• Monthly exclusive deal access</li>
                            </ul>
                        </div>

                        <!-- Apex Pawrent Tier Card -->
                        <div style="background: var(--cream); border: 2px solid <?= ($order_count >= 20) ? 'var(--gold)' : 'var(--mist)' ?>; border-radius: var(--radius-sm); padding: 16px; opacity: <?= ($order_count >= 20) ? '1' : '0.6' ?>;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="color:var(--espresso); font-size:0.95rem;">🏆 Apex Pawrent</strong>
                                <?php if ($order_count >= 20): ?>
                                    <span style="font-size:0.7rem; background:var(--gold); color:var(--espresso); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;">Active</span>
                                <?php else: ?>
                                    <span style="font-size:0.7rem; background:var(--mist); color:var(--caramel); padding:2px 8px; border-radius:50px; font-weight:800; text-transform:uppercase;"><i class="fas fa-lock"></i> Locked</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.75rem; color:var(--caramel); margin-bottom:6px;">20+ Orders Required</div>
                            <ul style="list-style:none; padding:0; margin:0; font-size:0.8rem; color:var(--espresso); display:flex; flex-direction:column; gap:4px;">
                                <li>• 20% flat discount checkout</li>
                                <li>• Instant free priority shipping</li>
                                <li>• Annual VIP gift surprise basket</li>
                                <li>• Dedicated 24/7 client helpline</li>
                            </ul>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity timeline -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-history"></i>
                <div><h3>Recent Activity</h3><div class="card-header-sub">Your latest account interactions</div></div>
            </div>
            <div class="card-body-pad">
                <div class="activity-timeline">
                    <div class="activity-item">
                        <div class="activity-dot gold"><i class="fas fa-sign-in-alt"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Session started <span class="activity-badge">Now</span></div>
                            <div class="activity-sub">Logged in to Pawganic Supplies</div>
                            <div class="activity-time"><i class="far fa-clock"></i> Just now</div>
                        </div>
                    </div>
                    <?php if ($order_count > 0): ?>
                    <div class="activity-item">
                        <div class="activity-dot sage"><i class="fas fa-shopping-bag"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Order placed</div>
                            <div class="activity-sub">Most recent order on <?= $last_order ? date('F d, Y', strtotime($last_order)) : 'N/A' ?></div>
                            <div class="activity-time"><i class="far fa-clock"></i><?= $last_order ? date('g:i A', strtotime($last_order)) : '' ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($fav_count > 0): ?>
                    <div class="activity-item">
                        <div class="activity-dot gold"><i class="fas fa-heart"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Favorites saved</div>
                            <div class="activity-sub"><?= $fav_count ?> product<?= $fav_count>1?'s':'' ?> in your wishlist</div>
                            <div class="activity-time"><i class="far fa-clock"></i> On file</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (count($addresses) > 0): ?>
                    <div class="activity-item">
                        <div class="activity-dot mah"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Delivery address on file</div>
                            <div class="activity-sub"><?= htmlspecialchars($addresses[0]['city']) ?>, <?= htmlspecialchars($addresses[0]['postal_code']) ?></div>
                            <div class="activity-time"><i class="far fa-clock"></i> Saved</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="activity-item">
                        <div class="activity-dot gold"><i class="fas fa-user-plus"></i></div>
                        <div class="activity-content">
                            <div class="activity-title">Joined Pawganic</div>
                            <div class="activity-sub">Welcome to the family, <?= htmlspecialchars($username) ?>! 🐾</div>
                            <div class="activity-time"><i class="far fa-clock"></i> <?= $member_date ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-bolt"></i>
                <div><h3>Quick Actions</h3><div class="card-header-sub">Jump to what you need</div></div>
            </div>
            <div class="card-body-pad" style="display:flex;gap:12px;flex-wrap:wrap">
                <button class="btn-gold-paw" onclick="switchTab('account')"><i class="fas fa-user-edit"></i> Edit Profile</button>
                <button class="btn-primary-paw" onclick="switchTab('addresses')"><i class="fas fa-map-marker-alt"></i> Manage Addresses</button>
                <a href="shop.php" style="text-decoration:none"><button class="btn-outline-paw"><i class="fas fa-shopping-cart"></i> Go Shopping</button></a>
                <a href="purchase_history.php" style="text-decoration:none"><button class="btn-outline-paw"><i class="fas fa-history"></i> Order History</button></a>
                <a href="favorites.php" style="text-decoration:none"><button class="btn-outline-paw"><i class="fas fa-heart"></i> My Favorites</button></a>
            </div>
        </div>

    </div><!-- /overview panel -->

    <!-- ══ PANEL: PHOTO ══ -->
    <div class="tab-panel" id="panel-picture">
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-camera"></i>
                <div><h3>Profile Picture</h3><div class="card-header-sub">Upload a clear, friendly photo of yourself</div></div>
            </div>
            <div class="card-body-pad">
                <div class="avatar-preview-wrap">
                    <div class="avatar-preview-item">
                        <img src="<?= $profile_pic_safe ?>" id="currentPreview" class="avatar-preview" onerror="this.src='images/profile.jpg'" alt="Current photo">
                        <span class="preview-tag">Current Photo</span>
                    </div>
                    <div id="newPreviewWrap" style="display:none" class="avatar-preview-item">
                        <img src="" id="newPreview" class="avatar-preview" alt="New photo preview">
                        <span class="preview-tag">New Preview</span>
                    </div>
                </div>

                <div class="avatar-upload-zone" id="dropZone">
                    <input type="file" id="profilePicInput" accept="image/*">
                    <i class="fas fa-cloud-upload-alt" style="font-size:2.4rem;color:var(--gold);display:block;margin-bottom:14px"></i>
                    <div style="font-weight:700;color:var(--mahogany);font-size:1.05rem;margin-bottom:8px">Drop your photo here or click to browse</div>
                    <div style="font-size:.84rem;color:var(--caramel)">JPEG, PNG, WebP · Max 5 MB · Square crop recommended</div>
                    <div id="fileName" style="margin-top:12px;font-size:.85rem;color:var(--gold);font-weight:600"></div>
                </div>

                <div class="photo-tips">
                    <div class="photo-tips-title"><i class="fas fa-lightbulb"></i> Photo Tips</div>
                    <ul>
                        <li>Use a well-lit, clear photo with your face centered</li>
                        <li>Square images work best — they won't get cropped awkwardly</li>
                        <li>JPEG or PNG at least 200×200 pixels recommended</li>
                    </ul>
                </div>

                <div style="margin-top:22px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                    <button class="btn-gold-paw" id="uploadBtn" onclick="uploadPhoto()">
                        <i class="fas fa-upload"></i> Upload Photo
                    </button>
                    <div id="uploadStatus" style="font-size:.85rem;display:none"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ PANEL: ACCOUNT ══ -->
    <div class="tab-panel" id="panel-account">

        <!-- Account info -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-id-card"></i>
                <div><h3>Account Information</h3><div class="card-header-sub">Your public profile details</div></div>
            </div>
            <div class="card-body-pad">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:24px">
                    <div style="background:var(--cream);border-radius:var(--radius-sm);padding:18px;border:1px solid rgba(201,145,42,.12)">
                        <div style="font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:6px">Username</div>
                        <div style="font-size:1.05rem;font-weight:700;color:var(--espresso)"><?= htmlspecialchars($username) ?></div>
                    </div>
                    <div style="background:var(--cream);border-radius:var(--radius-sm);padding:18px;border:1px solid rgba(201,145,42,.12)">
                        <div style="font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:6px">Email</div>
                        <div style="font-size:.95rem;font-weight:600;color:var(--espresso);word-break:break-all"><?= htmlspecialchars($email) ?></div>
                    </div>
                    <div style="background:var(--cream);border-radius:var(--radius-sm);padding:18px;border:1px solid rgba(201,145,42,.12)">
                        <div style="font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:6px">Role</div>
                        <div style="font-size:1.05rem;font-weight:700;color:var(--espresso);text-transform:capitalize"><?= htmlspecialchars($role) ?></div>
                    </div>
                    <div style="background:var(--cream);border-radius:var(--radius-sm);padding:18px;border:1px solid rgba(201,145,42,.12)">
                        <div style="font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:6px">Member Since</div>
                        <div style="font-size:1.05rem;font-weight:700;color:var(--espresso)"><?= $member_date ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-envelope"></i>
                <div><h3>Update Email</h3><div class="card-header-sub">Used for login and notifications</div></div>
            </div>
            <div class="card-body-pad">
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope" style="color:var(--caramel)"></i> Current Email</label>
                            <input class="form-control" type="email" value="<?= htmlspecialchars($email) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-edit" style="color:var(--caramel)"></i> New Email</label>
                            <input class="form-control" type="email" name="new_email" placeholder="your@newemail.com" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-paw"><i class="fas fa-save"></i> Save Email</button>
                </form>
            </div>
        </div>

        <!-- Username -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-user"></i>
                <div><h3>Update Username</h3><div class="card-header-sub">Your display name on Pawganic</div></div>
            </div>
            <div class="card-body-pad">
                <form method="POST">
                    <input type="hidden" name="action" value="update_username">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user" style="color:var(--caramel)"></i> Current Username</label>
                            <input class="form-control" type="text" value="<?= htmlspecialchars($username) ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-edit" style="color:var(--caramel)"></i> New Username</label>
                            <input class="form-control" type="text" name="new_username" placeholder="Enter new username" minlength="3" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-paw"><i class="fas fa-save"></i> Save Username</button>
                </form>
            </div>
        </div>

        <!-- Logout -->
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-sign-out-alt"></i>
                <div><h3>Session</h3><div class="card-header-sub">Sign out of your current session</div></div>
            </div>
            <div class="card-body-pad" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <div style="font-weight:700;color:var(--mahogany);margin-bottom:4px">Signed in as <?= htmlspecialchars($username) ?></div>
                    <div style="font-size:.85rem;color:var(--caramel)"><?= htmlspecialchars($email) ?> · <?= htmlspecialchars($role) ?></div>
                </div>
                <a href="logout.php"><button class="btn-danger-paw"><i class="fas fa-sign-out-alt"></i> Log Out</button></a>
            </div>
        </div>

    </div><!-- /account panel -->

    <!-- ══ PANEL: SECURITY ══ -->
    <div class="tab-panel" id="panel-security">
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-shield-alt"></i>
                <div><h3>Password & Security</h3><div class="card-header-sub">Keep your account protected</div></div>
            </div>
            <div class="card-body-pad">
                <div class="security-level">
                    <div class="security-icon"><i class="fas fa-lock"></i></div>
                    <div style="flex:1">
                        <div class="security-label">Security Status: Standard</div>
                        <div class="security-sub">Password-protected account · Standard tier</div>
                        <div class="security-bar"><div class="security-fill"></div></div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:1.4rem;font-weight:700;color:var(--sage);font-family:'Playfair Display',serif">70%</div>
                        <div style="font-size:.72rem;color:var(--caramel);text-transform:uppercase;letter-spacing:.5px">Secure</div>
                    </div>
                </div>

                <div class="security-checklist">
                    <div class="security-check-item">
                        <div class="security-check-icon ok"><i class="fas fa-check"></i></div>
                        <div class="security-check-text">Email verified</div>
                    </div>
                    <div class="security-check-item">
                        <div class="security-check-icon ok"><i class="fas fa-check"></i></div>
                        <div class="security-check-text">Password set</div>
                    </div>
                    <div class="security-check-item">
                        <div class="security-check-icon warn"><i class="fas fa-exclamation"></i></div>
                        <div class="security-check-text">2FA not enabled</div>
                    </div>
                    <div class="security-check-item">
                        <div class="security-check-icon ok"><i class="fas fa-check"></i></div>
                        <div class="security-check-text">Profile complete</div>
                    </div>
                </div>

                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock" style="color:var(--caramel)"></i> Current Password</label>
                            <div style="position:relative">
                                <input class="form-control" type="password" id="curPw" name="current_password" placeholder="Your current password" required>
                                <button type="button" onclick="togglePw('curPw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--caramel);cursor:pointer;font-size:.9rem;padding:0"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-key" style="color:var(--caramel)"></i> New Password</label>
                            <div style="position:relative">
                                <input class="form-control" type="password" id="newPw" name="new_password" placeholder="Min 6 characters" required>
                                <button type="button" onclick="togglePw('newPw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--caramel);cursor:pointer;font-size:.9rem;padding:0"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-check-double" style="color:var(--caramel)"></i> Confirm New Password</label>
                            <div style="position:relative">
                                <input class="form-control" type="password" id="conPw" name="confirm_password" placeholder="Repeat new password" required>
                                <button type="button" onclick="togglePw('conPw',this)" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--caramel);cursor:pointer;font-size:.9rem;padding:0"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- strength meter -->
                    <div id="strengthWrap" style="margin-bottom:22px;display:none">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <span style="font-size:.78rem;color:var(--caramel);font-weight:600;text-transform:uppercase;letter-spacing:.5px">Password Strength</span>
                            <span id="strengthLabel" style="font-size:.82rem;font-weight:700">—</span>
                        </div>
                        <div style="height:6px;background:var(--mist);border-radius:3px;overflow:hidden">
                            <div id="strengthBar" style="height:100%;border-radius:3px;width:0%;transition:width .4s ease,background .4s ease"></div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary-paw"><i class="fas fa-lock"></i> Change Password</button>
                </form>
            </div>
        </div>
    </div><!-- /security panel -->

    <!-- ══ PANEL: ADDRESSES ══ -->
    <div class="tab-panel" id="panel-addresses">
        <div class="profile-card">
            <div class="card-header-bar">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <h3>Delivery Addresses</h3>
                    <div class="card-header-sub"><?= count($addresses) ?> address<?= count($addresses)!==1?'es':'' ?> saved · Used at checkout</div>
                </div>
            </div>
            <div class="card-body-pad">
                <?php if (!empty($addresses)): ?>
                <div class="address-grid">
                    <?php foreach ($addresses as $addr): ?>
                    <div class="address-card <?= $addr['is_default']?'is-default':'' ?>">
                        <div class="addr-card-header">
                            <span class="addr-card-title"><i class="fas fa-map-marker-alt" style="color:var(--gold)"></i>Saved Address</span>
                            <?php if ($addr['is_default']): ?>
                                <span class="addr-default-badge"><i class="fas fa-star" style="margin-right:4px"></i>Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="addr-grid-2">
                            <div>
                                <div class="addr-field-label"><i class="fas fa-user"></i> Full Name</div>
                                <div class="addr-field-value"><?= htmlspecialchars($addr['full_name']) ?></div>
                            </div>
                            <div>
                                <div class="addr-field-label"><i class="fas fa-phone"></i> Phone</div>
                                <div class="addr-field-value"><?= htmlspecialchars($addr['phone']) ?></div>
                            </div>
                            <div>
                                <div class="addr-field-label"><i class="fas fa-city"></i> City</div>
                                <div class="addr-field-value"><?= htmlspecialchars($addr['city']) ?></div>
                            </div>
                            <div>
                                <div class="addr-field-label"><i class="fas fa-inbox"></i> Postal</div>
                                <div class="addr-field-value"><?= htmlspecialchars($addr['postal_code']) ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="addr-field-label"><i class="fas fa-home"></i> Full Address</div>
                            <div class="addr-field-value" style="line-height:1.5"><?= htmlspecialchars($addr['address']) ?></div>
                        </div>
                        <div class="addr-actions">
                            <button class="btn-outline-paw" style="padding:8px 16px;font-size:.82rem"
                                onclick="openEditModal(<?= $addr['id'] ?>,'<?= addslashes($addr['full_name']) ?>','<?= addslashes($addr['phone']) ?>','<?= addslashes($addr['city']) ?>','<?= addslashes($addr['postal_code']) ?>','<?= addslashes($addr['address']) ?>', <?= $addr['is_default'] ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if (!$addr['is_default']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="set_default_address">
                                <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                <button type="submit" class="btn-gold-paw" style="padding:8px 16px;font-size:.82rem">
                                    <i class="fas fa-star"></i> Set Default
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this address?')">
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?= $addr['id'] ?>">
                                <button type="submit" class="btn-danger-paw" style="padding:8px 16px;font-size:.82rem">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:56px 0;color:var(--caramel)">
                    <i class="fas fa-map-marked-alt" style="font-size:3.5rem;opacity:.25;display:block;margin-bottom:18px"></i>
                    <div style="font-weight:700;font-size:1.1rem;color:var(--mahogany);margin-bottom:8px">No saved addresses yet</div>
                    <div style="font-size:.9rem;margin-bottom:20px">Add a delivery address to speed up your checkout experience</div>
                </div>
                <?php endif; ?>
                <button class="btn-gold-paw" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Address
                </button>
            </div>
        </div>
    </div><!-- /addresses panel -->

    <!-- ══ PANEL: DANGER ZONE ══ -->
    <div class="tab-panel" id="panel-danger">
        <div class="profile-card">
            <div class="card-header-bar" style="background:linear-gradient(135deg,#7b1818,#a52222)">
                <i class="fas fa-exclamation-triangle"></i>
                <div><h3>Danger Zone</h3><div class="card-header-sub">Irreversible actions — proceed with extreme caution</div></div>
            </div>
            <div class="card-body-pad">
                <div class="danger-zone">
                    <div class="danger-zone-title">
                        <i class="fas fa-trash-alt"></i> Delete Account Permanently
                    </div>
                    <div class="danger-warning">
                        <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;font-size:1rem"></i>
                        <span>This action is <strong>permanent and irreversible</strong>. Your profile, orders, addresses, favorites, and all associated data will be deleted immediately with no possibility of recovery.</span>
                    </div>
                    <p style="font-size:.9rem;color:var(--caramel);margin-bottom:22px;line-height:1.6">To confirm account deletion, enter your current password below. Once submitted, you will be logged out and your data purged from our systems.</p>
                    <form method="POST" onsubmit="return confirm('Are you absolutely sure? This permanently deletes your Pawganic account and all data. This cannot be undone!')">
                        <input type="hidden" name="action" value="delete_account">
                        <div class="form-group" style="margin-bottom:22px;max-width:380px">
                            <label><i class="fas fa-lock" style="color:var(--danger)"></i> Confirm Your Password</label>
                            <input class="form-control" type="password" name="delete_password" placeholder="Enter password to confirm deletion" required>
                        </div>
                        <button type="submit" class="btn-danger-paw">
                            <i class="fas fa-trash-alt"></i> Delete My Account Forever
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div><!-- /danger panel -->

</div><!-- /page-wrapper -->

<!-- ════════════════════ ADDRESS MODAL ════════════════════ -->
<div id="addressModal" class="paw-modal">
    <div class="paw-modal-content">
        <div class="paw-modal-header">
            <h3 id="addrModalTitle"><i class="fas fa-map-marker-alt" style="margin-right:10px"></i>Add Address</h3>
            <button class="paw-modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="paw-modal-body">
            <form method="POST" id="addressForm">
                <input type="hidden" name="action" id="addrAction" value="add_address">
                <input type="hidden" name="address_id" id="addrId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input class="form-control" type="text" name="address_full_name" id="af_name" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input class="form-control" type="tel" name="address_phone" id="af_phone" placeholder="+63 9XX XXX XXXX" required>
                    </div>
                    <div class="form-group">
                        <label>City / Municipality</label>
                        <input class="form-control" type="text" name="address_city" id="af_city" placeholder="e.g. Quezon City" required>
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input class="form-control" type="text" name="address_postal_code" id="af_postal" placeholder="e.g. 1100" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Full Address</label>
                        <textarea class="form-control" name="address_address" id="af_addr" placeholder="Street, barangay, building, landmarks…" rows="3" required></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1; flex-direction:row; align-items:center; gap:10px; margin-top:8px;">
                        <input type="checkbox" name="address_is_default" id="af_default" value="1" style="width:20px; height:20px; accent-color:var(--gold); cursor:pointer;">
                        <label for="af_default" style="margin:0; cursor:pointer; text-transform:none; font-size:.85rem; font-weight:600;">Set as default address</label>
                    </div>
                </div>
            </form>
        </div>
        <div class="paw-modal-footer">
            <button class="btn-outline-paw" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
            <button class="btn-gold-paw" type="submit" form="addressForm" id="addrSubmitBtn">
                <i class="fas fa-save"></i> Save Address
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════ FOOTER ════════════════════ -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Since 2020, crafting premium, health-conscious treats by devoted cat lovers to support feline wellness in every bite.</p>
            <div class="social-links">
                <a href="https://www.facebook.com/" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://x.com/home" target="_blank"><i class="fab fa-twitter"></i></a>
                <a href="https://www.instagram.com/" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://www.tiktok.com/en/" target="_blank"><i class="fab fa-tiktok"></i></a>
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
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey);margin-right:8px"></i> 123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey);margin-right:8px"></i> +1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey);margin-right:8px"></i> meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey);margin-right:8px"></i> Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved. Made with 🐾 for cat lovers.</p>
    </div>
</footer>

<button class="scroll-to-top" id="scrollTopBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ════════ TAB SYSTEM ════════ */
function switchTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById('panel-' + name);
    const btn   = document.getElementById('tab-' + name);
    if (panel) panel.classList.add('active');
    if (btn)   btn.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ════════ TOAST ════════ */
function showToast(msg, type = 'success') {
    const el = document.getElementById('toastMessage');
    const txt = document.getElementById('toastText');
    if (!el || !txt) return;
    txt.textContent = msg;
    el.style.borderLeftColor = type === 'success' ? 'var(--sage)' : 'var(--danger)';
    new bootstrap.Toast(el, { delay: 3500 }).show();
}

/* ════════ PHP MESSAGE ════════ */
<?php if ($message): ?>
document.addEventListener('DOMContentLoaded', () => {
    showToast('<?= addslashes($message) ?>', '<?= $messageType ?>');
    switchTab('<?= $openTab ?>');
});
<?php else: ?>
document.addEventListener('DOMContentLoaded', () => switchTab('overview'));
<?php endif; ?>

/* ════════ NAVBAR DROPDOWN ════════ */
document.addEventListener('DOMContentLoaded', () => {
    const pd = document.querySelector('.profile-dropdown');
    if (pd) {
        pd.querySelector('.profile-pic').addEventListener('click', e => {
            e.stopPropagation();
            pd.classList.toggle('open');
        });
        document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
    }

    // Scroll to top
    const sBtn = document.getElementById('scrollTopBtn');
    window.addEventListener('scroll', () => sBtn.classList.toggle('show', window.pageYOffset > 300));
    sBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    // Animate loyalty bars on load
    setTimeout(() => {
        document.querySelectorAll('.hero-loyalty-fill, .loyalty-bar-fill').forEach(el => {
            const w = el.style.width;
            el.style.width = '0%';
            requestAnimationFrame(() => { el.style.width = w; });
        });
    }, 400);
    updateCartDisplay();
});

/* ════════ PHOTO UPLOAD ════════ */
const dropZone = document.getElementById('dropZone');
const picInput = document.getElementById('profilePicInput');

picInput && picInput.addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('fileName').textContent = '📎 ' + file.name;
    const reader = new FileReader();
    reader.onload = ev => {
        document.getElementById('newPreview').src = ev.target.result;
        document.getElementById('newPreviewWrap').style.display = 'block';
    };
    reader.readAsDataURL(file);
});

['dragover','dragleave','drop'].forEach(ev => {
    dropZone && dropZone.addEventListener(ev, e => {
        e.preventDefault();
        if (ev === 'dragover') dropZone.classList.add('drag-over');
        else if (ev === 'dragleave') dropZone.classList.remove('drag-over');
        else {
            dropZone.classList.remove('drag-over');
            const dt = e.dataTransfer;
            if (dt.files.length) {
                const transfer = new DataTransfer();
                transfer.items.add(dt.files[0]);
                picInput.files = transfer.files;
                picInput.dispatchEvent(new Event('change'));
            }
        }
    });
});

async function uploadPhoto() {
    const file = picInput && picInput.files[0];
    if (!file) { showToast('Please select a photo first.', 'error'); return; }
    if (file.size > 5 * 1024 * 1024) { showToast('File exceeds 5 MB limit.', 'error'); return; }
    const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!allowed.includes(file.type)) { showToast('Invalid file type. Use JPEG, PNG, or WebP.', 'error'); return; }

    const btn = document.getElementById('uploadBtn');
    const status = document.getElementById('uploadStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';
    status.style.display = 'block';
    status.style.color = 'var(--caramel)';
    status.textContent = 'Uploading your photo…';

    const fd = new FormData();
    fd.append('profile_pic', file);
    try {
        const res = await fetch('upload_profile_pic.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            const ts = '?t=' + Date.now();
            document.getElementById('currentPreview').src = data.profile_pic + ts;
            document.getElementById('heroAvatar').src = data.profile_pic + ts;
            document.querySelector('.navbar .profile-pic').src = data.profile_pic + ts;
            document.querySelector('.dropdown-content') && (document.querySelector('.profile-pic').src = data.profile_pic + ts);
            document.getElementById('newPreviewWrap').style.display = 'none';
            document.getElementById('fileName').textContent = '';
            picInput.value = '';
            status.style.color = 'var(--sage)';
            status.textContent = '✓ Photo updated successfully!';
            showToast('Profile photo updated!', 'success');
        } else {
            status.style.color = 'var(--danger)';
            status.textContent = data.message || 'Upload failed.';
            showToast(data.message || 'Upload failed.', 'error');
        }
    } catch (err) {
        status.style.color = 'var(--danger)';
        status.textContent = 'Network error. Please try again.';
        showToast('Network error.', 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-upload"></i> Upload Photo';
}

/* ════════ PASSWORD STRENGTH ════════ */
document.getElementById('newPw')?.addEventListener('input', function() {
    const w = document.getElementById('strengthWrap');
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    const v = this.value;
    w.style.display = v.length ? 'block' : 'none';
    let score = 0;
    if (v.length >= 6) score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
        { w:'20%', bg:'#e74c3c', t:'Very Weak' },
        { w:'40%', bg:'#e67e22', t:'Weak' },
        { w:'60%', bg:'#f1c40f', t:'Fair' },
        { w:'80%', bg:'#2ecc71', t:'Strong' },
        { w:'100%', bg:'#27ae60', t:'Very Strong' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width = l.w; bar.style.background = l.bg;
    lbl.textContent = l.t; lbl.style.color = l.bg;
});

/* ════════ TOGGLE PASSWORD ════════ */
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* ════════ PASSWORD FORM CLIENT VALIDATION ════════ */
document.getElementById('passwordForm')?.addEventListener('submit', e => {
    const np = document.getElementById('newPw').value;
    const cp = document.getElementById('conPw').value;
    if (np.length < 6) { e.preventDefault(); showToast('Password must be at least 6 characters.', 'error'); return; }
    if (np !== cp) { e.preventDefault(); showToast("Passwords don't match!", 'error'); }
});

/* ════════ ADDRESS MODAL ════════ */
function openAddModal() {
    document.getElementById('addressForm').reset();
    document.getElementById('addrAction').value = 'add_address';
    document.getElementById('addrId').value = '';
    document.getElementById('addrModalTitle').innerHTML = '<i class="fas fa-map-marker-alt" style="margin-right:10px"></i>Add New Address';
    document.getElementById('addrSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Save Address';
    
    const defCheckbox = document.getElementById('af_default');
    defCheckbox.checked = false;
    defCheckbox.disabled = false;
    
    document.getElementById('addressModal').classList.add('active');
}

function openEditModal(id, name, phone, city, postal, addr, is_default) {
    document.getElementById('af_name').value = name;
    document.getElementById('af_phone').value = phone;
    document.getElementById('af_city').value = city;
    document.getElementById('af_postal').value = postal;
    document.getElementById('af_addr').value = addr;
    document.getElementById('addrAction').value = 'edit_address';
    document.getElementById('addrId').value = id;
    document.getElementById('addrModalTitle').innerHTML = '<i class="fas fa-edit" style="margin-right:10px"></i>Edit Address';
    document.getElementById('addrSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Update Address';
    
    const defCheckbox = document.getElementById('af_default');
    defCheckbox.checked = !!is_default;
    if (is_default) {
        defCheckbox.disabled = true;
    } else {
        defCheckbox.disabled = false;
    }
    
    document.getElementById('addressModal').classList.add('active');
}

function closeModal() {
    document.getElementById('addressModal').classList.remove('active');
}

window.addEventListener('click', e => {
    if (e.target === document.getElementById('addressModal')) closeModal();
});

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
</script>
</body>
</html>