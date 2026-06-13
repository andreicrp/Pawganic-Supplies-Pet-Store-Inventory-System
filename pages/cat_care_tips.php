<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id      = $_SESSION['user_id'];
$nav_username = $_SESSION['username'] ?? 'User';
$nav_role     = $_SESSION['role'] ?? 'customer';
$nav_balance  = $_SESSION['balance'] ?? 0;

$pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $user_id);
$pic_stmt->execute();
$pic_stmt->bind_result($profile_pic);
$pic_stmt->fetch();
$pic_stmt->close();
if (!$profile_pic) $profile_pic = 'images/profile.jpg';

// Fetch featured food for nutrition section
$food_stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE category = 'Food' LIMIT 2");
$food_stmt->execute();
$food_products = $food_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$food_stmt->close();

// Fetch featured toys for play section
$toy_stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE category = 'Toy' LIMIT 2");
$toy_stmt->execute();
$toy_products = $toy_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$toy_stmt->close();

// Fetch featured accessories for grooming section
$acc_stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE category = 'Accessory' LIMIT 2");
$acc_stmt->execute();
$acc_products = $acc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$acc_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Cat Care Tips — Pawganic Supplies</title>
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
    .nav-links a:hover, .nav-links a.active { background: var(--gold); color: var(--white); }

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

    /* ===================== PAGE HERO ===================== */
    .page-hero {
      background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
      padding: 72px 5% 90px;
      position: relative;
      overflow: hidden;
    }

    .page-hero::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                  radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.12) 0%, transparent 50%);
    }

    .hero-deco {
      position: absolute; border-radius: 50%;
      opacity: 0.07; background: var(--honey);
    }
    .hero-deco-1 { width: 360px; height: 360px; top: -100px; right: -60px; }
    .hero-deco-2 { width: 200px; height: 200px; bottom: 20px; left: 4%; }

    .page-hero::after {
      content: '';
      position: absolute; bottom: 0; left: 0; right: 0; height: 60px;
      background: var(--cream);
      clip-path: ellipse(55% 100% at 50% 100%);
    }

    .page-hero-inner {
      position: relative; z-index: 2;
      max-width: 1200px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      gap: 40px;
    }

    .page-hero-label {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
      color: var(--honey); padding: 6px 14px; border-radius: 50px;
      font-size: 0.75rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
      margin-bottom: 18px;
    }

    .page-hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.4rem, 4.5vw, 4rem);
      font-weight: 900; color: var(--white); line-height: 1.1;
      margin-bottom: 16px;
    }

    .page-hero h1 em { font-style: italic; color: var(--honey); }

    .page-hero-sub {
      color: rgba(255,255,255,0.65);
      font-size: 1rem; line-height: 1.7;
      max-width: 460px;
    }

    /* Breadcrumb */
    .breadcrumb {
      display: flex; align-items: center; gap: 8px;
      margin-top: 22px;
      font-size: 0.82rem;
    }

    .breadcrumb a {
      color: rgba(255,255,255,0.55); text-decoration: none;
      transition: color 0.25s;
      display: flex; align-items: center; gap: 5px;
    }
    .breadcrumb a:hover { color: var(--honey); }
    .breadcrumb-sep { color: rgba(255,255,255,0.25); font-size: 0.7rem; }
    .breadcrumb-cur { color: var(--honey); font-weight: 600; }

    /* Jump nav pills */
    .jump-pills {
      display: flex; flex-direction: column; gap: 8px;
      flex-shrink: 0;
    }

    .jump-pill {
      display: flex; align-items: center; gap: 10px;
      background: rgba(253,248,240,0.1);
      border: 1px solid rgba(255,255,255,0.14);
      backdrop-filter: blur(10px);
      padding: 10px 16px; border-radius: var(--radius-sm);
      color: rgba(255,255,255,0.8);
      text-decoration: none; font-size: 0.82rem; font-weight: 500;
      transition: var(--transition); white-space: nowrap;
    }

    .jump-pill:hover {
      background: rgba(201,145,42,0.25);
      border-color: rgba(201,145,42,0.45);
      color: var(--honey);
      transform: translateX(4px);
    }

    .jump-pill i { color: var(--honey); width: 16px; font-size: 0.85rem; }

    /* ===================== LAYOUT ===================== */
    .main-content {
      flex: 1;
      max-width: 1200px;
      margin: 0 auto;
      width: 100%;
      padding: 60px 24px 80px;
    }

    /* ===================== QUICK TIPS GRID ===================== */
    .quick-grid-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem; font-weight: 700;
      color: var(--espresso); margin-bottom: 28px;
    }

    .quick-grid-title em { font-style: italic; color: var(--gold); }

    .tips-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 72px;
    }

    .tip-card {
      background: var(--ivory);
      border-radius: var(--radius);
      padding: 32px 28px;
      border: 1px solid rgba(201,145,42,0.1);
      box-shadow: var(--shadow-sm);
      position: relative; overflow: hidden;
      transition: var(--transition);
      cursor: pointer;
      text-decoration: none;
      display: block;
      color: inherit;
      opacity: 0; transform: translateY(22px);
      z-index: 1;
    }

    .tip-card.visible { opacity: 1; transform: translateY(0); }

    .tip-card::before {
      content: '';
      position: absolute; top: 0; left: 0;
      width: 100%; height: 4px;
      background: linear-gradient(90deg, var(--gold), var(--honey));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .tip-card::after {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(circle at 50% 120%, rgba(201,145,42,0.06) 0%, transparent 70%);
      opacity: 0;
      transition: opacity 0.4s ease;
      z-index: -1;
    }

    .tip-card:hover::before { transform: scaleX(1); }
    .tip-card:hover::after { opacity: 1; }
    .tip-card:hover {
      transform: translateY(-8px);
      border-color: var(--gold);
      box-shadow: 0 16px 36px rgba(44,26,14,0.12), 0 4px 18px rgba(201,145,42,0.08);
    }

    .tip-icon {
      width: 58px; height: 58px;
      background: linear-gradient(135deg, rgba(232,184,109,0.2), rgba(201,145,42,0.12));
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; color: var(--gold);
      margin-bottom: 20px;
      transition: var(--transition);
    }

    .tip-card:hover .tip-icon {
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--white);
      transform: rotate(5deg) scale(1.08);
    }

    .tip-card h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.15rem; font-weight: 700;
      color: var(--espresso); margin-bottom: 10px;
    }

    .tip-card p {
      font-size: 0.87rem; color: var(--caramel);
      line-height: 1.75; font-weight: 300;
    }

    .tip-arrow {
      position: absolute; bottom: 18px; right: 20px;
      color: var(--mist); font-size: 0.8rem;
      transition: var(--transition);
    }

    .tip-card:hover .tip-arrow { color: var(--gold); transform: translateX(4px); }

    /* ===================== DETAIL SECTIONS ===================== */
    .detail-section {
      background: var(--ivory);
      border-radius: var(--radius);
      padding: 48px 44px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(201,145,42,0.1);
      margin-bottom: 28px;
      opacity: 0; transform: translateY(22px);
      transition: var(--transition);
      scroll-margin-top: 90px;
    }

    .detail-section.visible { opacity: 1; transform: translateY(0); }

    .section-heading {
      display: flex; align-items: center; gap: 14px;
      margin-bottom: 32px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--mist);
    }

    .section-heading-icon {
      width: 52px; height: 52px; border-radius: 14px;
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: var(--honey);
      flex-shrink: 0;
    }

    .section-heading-text h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem; font-weight: 700; color: var(--espresso);
    }

    .section-heading-text span {
      font-size: 0.8rem; color: var(--caramel);
      font-weight: 500; letter-spacing: 0.5px;
    }

    .detail-section h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--mahogany); margin: 28px 0 14px;
      display: flex; align-items: center; gap: 8px;
    }

    .detail-section h3::before {
      content: '';
      width: 4px; height: 18px; border-radius: 2px;
      background: linear-gradient(to bottom, var(--gold), var(--honey));
      flex-shrink: 0;
    }

    .detail-section p {
      color: #6a5040; font-size: 0.92rem;
      line-height: 1.85; margin-bottom: 14px; font-weight: 300;
    }

    .detail-section ul {
      list-style: none;
      margin-bottom: 18px;
    }

    .detail-section ul li {
      color: #6a5040; font-size: 0.92rem;
      line-height: 1.8; padding: 8px 0 8px 26px;
      position: relative; font-weight: 300;
      border-bottom: 1px solid rgba(201,145,42,0.06);
    }

    .detail-section ul li:last-child { border-bottom: none; }

    .detail-section ul li::before {
      content: '';
      position: absolute; left: 0; top: 50%;
      transform: translateY(-50%);
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--gold);
      flex-shrink: 0;
    }

    .detail-section ul li strong {
      color: var(--mahogany); font-weight: 600;
    }

    /* Quick tip highlight box */
    .tip-highlight {
      background: linear-gradient(135deg, rgba(201,145,42,0.08), rgba(232,184,109,0.06));
      border: 1px solid rgba(201,145,42,0.2);
      border-left: 4px solid var(--gold);
      padding: 22px 24px;
      border-radius: var(--radius-sm);
      margin: 24px 0;
    }

    .tip-highlight-head {
      display: flex; align-items: center; gap: 8px;
      color: var(--mahogany); font-weight: 700; font-size: 0.92rem;
      margin-bottom: 14px;
    }

    .tip-highlight-head i { color: var(--gold); }

    .tip-highlight ul { margin-bottom: 0; }

    /* Info chips for age categories */
    .age-chips {
      display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;
    }

    .age-chip {
      display: flex; align-items: center; gap: 7px;
      padding: 8px 16px; border-radius: 50px;
      font-size: 0.8rem; font-weight: 600;
      border: 1px solid;
    }

    .age-chip.kitten {
      background: rgba(122,158,126,0.12); color: var(--sage);
      border-color: rgba(122,158,126,0.3);
    }

    .age-chip.adult {
      background: rgba(201,145,42,0.12); color: var(--caramel);
      border-color: rgba(201,145,42,0.3);
    }

    .age-chip.senior {
      background: rgba(90,45,12,0.1); color: var(--mahogany);
      border-color: rgba(90,45,12,0.2);
    }

    /* Body language table */
    .body-lang-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 12px;
      margin: 18px 0;
    }

    .body-lang-item {
      background: var(--cream);
      border-radius: var(--radius-sm);
      padding: 14px 16px;
      border: 1px solid rgba(201,145,42,0.12);
      transition: var(--transition);
    }

    .body-lang-item:hover {
      border-color: rgba(201,145,42,0.3);
      box-shadow: var(--shadow-sm);
      transform: translateY(-2px);
    }

    .body-lang-signal {
      font-weight: 700; font-size: 0.85rem; color: var(--mahogany);
      margin-bottom: 4px;
    }

    .body-lang-meaning {
      font-size: 0.8rem; color: var(--caramel); font-weight: 300;
    }

    /* ===================== CTA STRIP ===================== */
    .cta-strip {
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      border-radius: var(--radius);
      padding: 48px 44px;
      text-align: center;
      position: relative; overflow: hidden;
      margin-top: 60px;
    }

    .cta-strip::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 70% 40%, rgba(201,145,42,0.2) 0%, transparent 60%);
    }

    .cta-strip h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem; font-weight: 700;
      color: var(--white); margin-bottom: 12px;
      position: relative; z-index: 1;
    }

    .cta-strip h3 em { font-style: italic; color: var(--honey); }

    .cta-strip p {
      color: rgba(255,255,255,0.65); font-size: 0.92rem;
      margin-bottom: 28px; position: relative; z-index: 1;
      max-width: 460px; margin-left: auto; margin-right: auto;
    }

    .cta-strip-btns {
      display: flex; gap: 12px; justify-content: center;
      flex-wrap: wrap; position: relative; z-index: 1;
    }

    .btn-cta-primary {
      display: inline-flex; align-items: center; gap: 9px;
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--espresso); padding: 14px 32px; border-radius: 50px;
      font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.9rem;
      text-decoration: none; transition: var(--transition);
      box-shadow: 0 6px 22px rgba(201,145,42,0.35);
    }
    .btn-cta-primary:hover { background: var(--white); transform: translateY(-3px); box-shadow: 0 12px 36px rgba(201,145,42,0.4); }

    .btn-cta-ghost {
      display: inline-flex; align-items: center; gap: 9px;
      background: transparent; color: var(--white);
      padding: 13px 28px; border-radius: 50px;
      border: 1.5px solid rgba(255,255,255,0.35);
      font-family: 'DM Sans', sans-serif; font-weight: 500; font-size: 0.9rem;
      text-decoration: none; transition: var(--transition);
      backdrop-filter: blur(8px);
    }
    .btn-cta-ghost:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.8); transform: translateY(-3px); }

    /* ===================== FOOTER ===================== */
    footer {
      background: var(--espresso);
      color: rgba(255,255,255,0.72);
      padding: 72px 5% 28px;
      margin-top: auto;
      position: relative;
    }

    footer::before {
      content: ''; position: absolute;
      top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey), var(--gold), var(--caramel));
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 44px; margin-bottom: 44px;
    }

    .footer-section h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; color: var(--honey);
      margin-bottom: 20px; font-weight: 700;
    }

    .footer-section p {
      font-size: 0.87rem; line-height: 1.82; margin-bottom: 12px;
    }

    .footer-section p i { color: var(--honey); margin-right: 8px; width: 14px; }

    .footer-links { display: flex; flex-direction: column; gap: 10px; }

    .footer-links a {
      color: rgba(255,255,255,0.62); text-decoration: none;
      font-size: 0.87rem; transition: var(--transition);
      display: flex; align-items: center; gap: 6px;
    }
    .footer-links a:hover { color: var(--honey); padding-left: 8px; }

    .social-links { display: flex; gap: 12px; margin-top: 20px; }

    .social-links a {
      width: 40px; height: 40px; border-radius: 50%;
      background: rgba(201,145,42,0.12);
      border: 1px solid rgba(201,145,42,0.28);
      color: var(--honey);
      display: flex; align-items: center; justify-content: center;
      text-decoration: none; font-size: 0.88rem;
      transition: var(--transition);
    }
    .social-links a:hover {
      background: var(--gold); border-color: var(--gold); color: var(--white);
      transform: translateY(-3px) rotate(5deg);
    }

    .copyright {
      border-top: 1px solid rgba(255,255,255,0.08);
      padding-top: 22px; text-align: center;
      font-size: 0.8rem; color: rgba(255,255,255,0.3);
    }

    /* ===================== SCROLL TO TOP ===================== */
    .scroll-to-top {
      position: fixed; bottom: 30px; right: 30px; z-index: 999;
      width: 48px; height: 48px; border-radius: 50%;
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      border: none; color: var(--honey);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; box-shadow: var(--shadow-md);
      opacity: 0; visibility: hidden; transition: var(--transition);
    }
    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    /* ===================== EDITORIAL BANNERS ===================== */
    .editorial-banner {
      height: 240px;
      border-radius: var(--radius-sm);
      margin-bottom: 35px;
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: flex-end;
      padding: 30px;
      border: 1px solid rgba(255, 255, 255, 0.1);
      box-shadow: var(--shadow-sm);
    }
    
    .editorial-banner::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to top, rgba(44,26,14,0.95) 0%, rgba(44,26,14,0.3) 60%, transparent 100%);
      z-index: 1;
    }

    .editorial-banner-content {
      position: relative;
      z-index: 2;
      color: var(--white);
    }

    .editorial-banner-tag {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--honey);
      font-weight: 600;
      margin-bottom: 6px;
      display: inline-block;
    }

    .editorial-banner-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.4rem, 2.5vw, 2rem);
      font-weight: 900;
      line-height: 1.25;
    }

    .editorial-banner-nutrition {
      background: linear-gradient(135deg, rgba(90,45,12,0.5) 0%, rgba(44,26,14,0.9) 100%), url('/petv10/images/nutrition_banner.png') center/cover;
    }

    .editorial-banner-play {
      background: linear-gradient(135deg, rgba(155,106,47,0.5) 0%, rgba(44,26,14,0.9) 100%), url('/petv10/images/play_banner.png') center/cover;
    }

    .editorial-banner-grooming {
      background: linear-gradient(135deg, rgba(122,158,126,0.5) 0%, rgba(44,26,14,0.9) 100%), url('/petv10/images/grooming_banner.png') center/cover;
    }

    /* ===================== SUGGESTED PRODUCTS WIDGET ===================== */
    .suggested-products {
      margin-top: 35px;
      padding-top: 30px;
      border-top: 1px solid rgba(201,145,42,0.12);
    }

    .suggested-products-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--mahogany);
      text-transform: uppercase;
      letter-spacing: 1.5px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .suggested-products-title i {
      color: var(--gold);
    }

    .suggestions-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 20px;
    }

    .suggestion-card {
      background: var(--white);
      border: 1px solid var(--mist);
      border-radius: var(--radius-sm);
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 15px;
      transition: var(--transition);
      text-decoration: none;
      color: inherit;
      box-shadow: var(--shadow-sm);
    }

    .suggestion-card:hover {
      border-color: var(--gold);
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }

    .suggestion-image {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid rgba(201,145,42,0.08);
      transition: var(--transition);
      flex-shrink: 0;
    }

    .suggestion-card:hover .suggestion-image {
      transform: scale(1.05);
    }

    .suggestion-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .suggestion-name {
      font-weight: 600;
      font-size: 0.88rem;
      color: var(--espresso);
      line-height: 1.35;
    }

    .suggestion-price {
      font-size: 0.85rem;
      color: var(--gold);
      font-weight: 700;
    }

    .suggestion-btn {
      margin-left: auto;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--cream);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--mahogany);
      font-size: 0.8rem;
      transition: var(--transition);
    }

    .suggestion-card:hover .suggestion-btn {
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
    @media (max-width: 900px) {
      .jump-pills { display: none; }
    }

    @media (max-width: 768px) {
      .navbar { padding: 0 20px; }
      .nav-links a:not(.active):not(:last-child) { display: none; }
      .detail-section { padding: 28px 22px; }
      .tips-grid { grid-template-columns: 1fr 1fr; }
      .slide-cart { width: 92vw; right: -92vw; }
    }

    @media (max-width: 480px) {
      .tips-grid { grid-template-columns: 1fr; }
      .body-lang-grid { grid-template-columns: 1fr 1fr; }
      .cta-strip { padding: 36px 24px; }
      .cta-strip-btns { flex-direction: column; align-items: center; }
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
    <a href="shop.php">Shop</a>
    <a href="about.php">About</a>
    <?php
    if (isset($_SESSION['user_id'])) {
      if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo '<a href="admin.php">Admin</a>';
      }
      echo '
      <div class="profile-dropdown">
        <img src="'.htmlspecialchars($profile_pic).'" alt="Profile" class="profile-pic" onerror="this.src=\'images/profile.jpg\'">
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

<!-- ===================== PAGE HERO ===================== -->
<section class="page-hero">
  <div class="hero-deco hero-deco-1"></div>
  <div class="hero-deco hero-deco-2"></div>
  <div class="page-hero-inner">
    <div>
      <div class="page-hero-label"><i class="fas fa-paw"></i> Pawganic Knowledge Hub</div>
      <h1>Cat Care <em>Tips</em></h1>
      <p class="page-hero-sub">A comprehensive guide to keeping your feline friend healthy, happy, and living their best nine lives.</p>
      <div class="breadcrumb">
        <a href="main.php"><i class="fas fa-home"></i> Home</a>
        <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
        <span class="breadcrumb-cur">Cat Care Tips</span>
      </div>
    </div>
    <div class="jump-pills">
      <a href="#nutrition" class="jump-pill"><i class="fas fa-utensils"></i> Nutrition & Diet</a>
      <a href="#health" class="jump-pill"><i class="fas fa-heartbeat"></i> Health & Wellness</a>
      <a href="#environment" class="jump-pill"><i class="fas fa-home"></i> Environment</a>
      <a href="#exercise" class="jump-pill"><i class="fas fa-running"></i> Exercise & Play</a>
      <a href="#grooming" class="jump-pill"><i class="fas fa-spa"></i> Grooming</a>
      <a href="#behavior" class="jump-pill"><i class="fas fa-brain"></i> Behavior</a>
    </div>
  </div>
</section>

<!-- ===================== MAIN CONTENT ===================== -->
<div class="main-content">

  <!-- Quick Tips Grid -->
  <h2 class="quick-grid-title">Quick <em>Overview</em></h2>
  <div class="tips-grid">
    <a href="#nutrition" class="tip-card">
      <div class="tip-icon"><i class="fas fa-utensils"></i></div>
      <h3>Nutrition & Diet</h3>
      <p>High-quality, balanced food tailored to age and health needs. Fresh water always available.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
    <a href="#health" class="tip-card" style="transition-delay:0.07s">
      <div class="tip-icon"><i class="fas fa-heartbeat"></i></div>
      <h3>Health & Wellness</h3>
      <p>Regular vet checkups, current vaccinations, and watching for behavioral changes.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
    <a href="#environment" class="tip-card" style="transition-delay:0.14s">
      <div class="tip-icon"><i class="fas fa-home"></i></div>
      <h3>Environment & Space</h3>
      <p>Safe, comfortable spaces with hiding spots, perches, and toys. Always clean.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
    <a href="#exercise" class="tip-card" style="transition-delay:0.21s">
      <div class="tip-icon"><i class="fas fa-running"></i></div>
      <h3>Exercise & Play</h3>
      <p>Daily interactive play for fitness and mental stimulation. Rotate toys regularly.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
    <a href="#grooming" class="tip-card" style="transition-delay:0.28s">
      <div class="tip-icon"><i class="fas fa-spa"></i></div>
      <h3>Grooming & Hygiene</h3>
      <p>Regular brushing, dental care, and nail trimming for a healthy, happy coat.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
    <a href="#behavior" class="tip-card" style="transition-delay:0.35s">
      <div class="tip-icon"><i class="fas fa-brain"></i></div>
      <h3>Behavior & Training</h3>
      <p>Positive reinforcement, understanding body language, and building trust and bond.</p>
      <i class="fas fa-arrow-right tip-arrow"></i>
    </a>
  </div>

  <!-- ── NUTRITION ── -->
  <div class="detail-section" id="nutrition">
    <div class="editorial-banner editorial-banner-nutrition">
      <div class="editorial-banner-content">
        <span class="editorial-banner-tag">Feline Wellness Guide</span>
        <h2 class="editorial-banner-title">Science-Backed Nutrition & Diet</h2>
      </div>
    </div>
    
    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-utensils"></i></div>
      <div class="section-heading-text">
        <h2>Nutrition & Diet</h2>
        <span>Fueling your cat's health from the inside out</span>
      </div>
    </div>

    <h3>Choosing the Right Food</h3>
    <p>Cats are obligate carnivores — their bodies are designed to thrive on animal protein. Look for cat foods with a named meat source (chicken, salmon, turkey) listed as the first ingredient, and avoid fillers like corn syrup or artificial preservatives.</p>

    <div class="age-chips">
      <span class="age-chip kitten"><i class="fas fa-circle"></i> Kittens (0–1 yr): high-calorie, protein-rich</span>
      <span class="age-chip adult"><i class="fas fa-circle"></i> Adults (1–7 yrs): balanced, portioned</span>
      <span class="age-chip senior"><i class="fas fa-circle"></i> Seniors (7+): joint & digestive support</span>
    </div>

    <h3>Feeding Guidelines</h3>
    <ul>
      <li>Feed kittens <strong>3–4 times daily</strong> until 6 months old</li>
      <li>Feed adult cats <strong>1–2 times daily</strong> at consistent times</li>
      <li>Provide <strong>fresh water at all times</strong> — consider a cat water fountain</li>
      <li>Monitor portion sizes carefully to <strong>prevent obesity</strong></li>
      <li>Wet food provides extra hydration — especially important for kidney health</li>
    </ul>

    <div class="tip-highlight">
      <div class="tip-highlight-head"><i class="fas fa-lightbulb"></i> Pro Tips</div>
      <ul>
        <li>Transition to new food gradually over <strong>7–10 days</strong> to avoid digestive upset</li>
        <li>Foods to avoid: chocolate, onions, garlic, grapes, raisins, xylitol, alcohol</li>
        <li>Treats should make up no more than <strong>10% of daily calories</strong></li>
        <li>Talk to your vet about breed-specific or health-condition dietary needs</li>
      </ul>
    </div>

    <?php if (!empty($food_products)): ?>
    <div class="suggested-products">
      <div class="suggested-products-title">
        <i class="fas fa-shopping-bag"></i> Recommended Nutrition Products
      </div>
      <div class="suggestions-row">
        <?php foreach ($food_products as $prod): ?>
        <a href="product.php?id=<?= $prod['id'] ?>" class="suggestion-card">
          <?php if ($prod['image']): ?>
          <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="suggestion-image">
          <?php else: ?>
          <div class="bg-light d-flex align-items-center justify-content-center suggestion-image">
            <i class="fas fa-box text-muted"></i>
          </div>
          <?php endif; ?>
          <div class="suggestion-info">
            <div class="suggestion-name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="suggestion-price">₱<?= number_format($prod['price'], 2) ?></div>
          </div>
          <div class="suggestion-btn">
            <i class="fas fa-chevron-right"></i>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── HEALTH ── -->
  <div class="detail-section" id="health">
    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-heartbeat"></i></div>
      <div class="section-heading-text">
        <h2>Health & Wellness</h2>
        <span>Proactive care for a longer, happier life</span>
      </div>
    </div>

    <h3>Preventive Care Essentials</h3>
    <ul>
      <li><strong>Annual Checkups:</strong> Early detection of health issues saves lives and money</li>
      <li><strong>Core Vaccinations:</strong> Rabies, panleukopenia, feline herpesvirus, calicivirus</li>
      <li><strong>Parasite Prevention:</strong> Year-round flea, tick, and heartworm prevention</li>
      <li><strong>Spay/Neuter:</strong> Reduces cancer risk and unwanted behavioral issues</li>
      <li><strong>Microchipping:</strong> Permanent ID in case your cat ever gets lost</li>
    </ul>

    <h3>Warning Signs to Watch For</h3>
    <ul>
      <li>Sudden changes in eating, drinking, or litter box habits</li>
      <li>Unexplained weight loss or gain</li>
      <li>Excessive grooming, hair loss, or skin irritation</li>
      <li>Lethargy, hiding, or uncharacteristic aggression</li>
      <li>Labored breathing, sneezing, or nasal discharge</li>
      <li>Vomiting more than twice per week or blood in stool/urine</li>
    </ul>

    <h3>Mental Health & Stress Reduction</h3>
    <p>Cats are sensitive creatures — environmental changes, new pets, or a new home can cause significant stress. Help keep anxiety at bay:</p>
    <ul>
      <li>Maintain consistent <strong>daily routines</strong> for feeding and play</li>
      <li>Provide multiple <strong>safe hiding spaces</strong> — think cat trees and covered beds</li>
      <li>Use <strong>Feliway pheromone diffusers</strong> during major transitions</li>
      <li>Avoid loud music and sudden loud noises near rest areas</li>
      <li>Introduce new pets or family members <strong>gradually and on their terms</strong></li>
    </ul>
  </div>

  <!-- ── ENVIRONMENT ── -->
  <div class="detail-section" id="environment">
    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-home"></i></div>
      <div class="section-heading-text">
        <h2>Environment & Home Setup</h2>
        <span>Designing a feline-friendly sanctuary</span>
      </div>
    </div>

    <h3>The Five Essential Spaces</h3>
    <ul>
      <li><strong>Litter Box:</strong> Quiet, accessible location away from food and water; one per cat plus one extra</li>
      <li><strong>Sleeping Area:</strong> Elevated, warm, soft — cats sleep 12–16 hours daily</li>
      <li><strong>Scratching Posts:</strong> Tall, sturdy, and sisal-wrapped for natural claw maintenance</li>
      <li><strong>Vertical Territory:</strong> Cat trees, wall shelves — height equals safety to cats</li>
      <li><strong>Window Perch:</strong> Bird-watching is free TV for your cat</li>
    </ul>

    <h3>Safety Checklist</h3>
    <ul>
      <li>Secure windows and balconies — even indoor cats are curious</li>
      <li>Remove toxic plants: lilies, pothos, sago palm, poinsettia, aloe vera</li>
      <li>Keep cleaning products, medications, and chemicals in locked cabinets</li>
      <li>Secure loose electrical cords (cats love chewing them)</li>
      <li>Keep toilet lids closed — kittens can drown in toilet bowls</li>
    </ul>

    <div class="tip-highlight">
      <div class="tip-highlight-head"><i class="fas fa-lightbulb"></i> Litter Box Mastery</div>
      <ul>
        <li>Rule of thumb: <strong>N + 1 litter boxes</strong> for N cats in the household</li>
        <li>Cats strongly prefer <strong>unscented, clumping, fine-grained litter</strong></li>
        <li>Scoop daily, full clean weekly — cats will protest a dirty box by going elsewhere</li>
        <li>Size matters: box should be 1.5× the length of your cat</li>
      </ul>
    </div>
  </div>

  <!-- ── EXERCISE ── -->
  <div class="detail-section" id="exercise">
    <div class="editorial-banner editorial-banner-play">
      <div class="editorial-banner-content">
        <span class="editorial-banner-tag">Physical Enrichment</span>
        <h2 class="editorial-banner-title">Exercise, Play & Mind Stimulation</h2>
      </div>
    </div>

    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-running"></i></div>
      <div class="section-heading-text">
        <h2>Exercise & Play</h2>
        <span>Keeping minds sharp and bodies agile</span>
      </div>
    </div>

    <h3>Daily Exercise Targets</h3>
    <div class="age-chips">
      <span class="age-chip kitten"><i class="fas fa-circle"></i> Kittens: 30–40 min active play</span>
      <span class="age-chip adult"><i class="fas fa-circle"></i> Adults: 20–30 min active play</span>
      <span class="age-chip senior"><i class="fas fa-circle"></i> Seniors: 10–20 min gentle play</span>
    </div>

    <h3>Best Toy Types</h3>
    <ul>
      <li><strong>Wand toys</strong> (feather wands, ribbon teasers) — mimic bird/prey movement</li>
      <li><strong>Puzzle feeders</strong> — slow feeding and mental enrichment combined</li>
      <li><strong>Laser pointers</strong> — always end with a physical toy they can "catch"</li>
      <li><strong>Crinkle balls & mice</strong> — independent play between sessions</li>
      <li><strong>Cat tunnels and boxes</strong> — exploration, ambush, and hiding instincts</li>
    </ul>

    <h3>Smart Play Habits</h3>
    <ul>
      <li>Rotate toys every few days to prevent boredom</li>
      <li>Schedule play during natural active hours: <strong>dawn and dusk</strong></li>
      <li>Always end with a "kill" — a toy they can catch — to satisfy prey instinct</li>
      <li>Follow with a small meal or treat to complete the hunt–eat–groom cycle</li>
      <li>Never use hands or feet as toys — this encourages biting</li>
    </ul>

    <?php if (!empty($toy_products)): ?>
    <div class="suggested-products">
      <div class="suggested-products-title">
        <i class="fas fa-shopping-bag"></i> Recommended Playtime Toys
      </div>
      <div class="suggestions-row">
        <?php foreach ($toy_products as $prod): ?>
        <a href="product.php?id=<?= $prod['id'] ?>" class="suggestion-card">
          <?php if ($prod['image']): ?>
          <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="suggestion-image">
          <?php else: ?>
          <div class="bg-light d-flex align-items-center justify-content-center suggestion-image">
            <i class="fas fa-box text-muted"></i>
          </div>
          <?php endif; ?>
          <div class="suggestion-info">
            <div class="suggestion-name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="suggestion-price">₱<?= number_format($prod['price'], 2) ?></div>
          </div>
          <div class="suggestion-btn">
            <i class="fas fa-chevron-right"></i>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── GROOMING ── -->
  <div class="detail-section" id="grooming">
    <div class="editorial-banner editorial-banner-grooming">
      <div class="editorial-banner-content">
        <span class="editorial-banner-tag">Hygiene & Care</span>
        <h2 class="editorial-banner-title">Grooming & Coat Care Tips</h2>
      </div>
    </div>

    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-spa"></i></div>
      <div class="section-heading-text">
        <h2>Grooming & Hygiene</h2>
        <span>Looking and feeling their purrfect best</span>
      </div>
    </div>

    <h3>Coat Brushing</h3>
    <ul>
      <li><strong>Short-haired cats:</strong> Brush 1–2× per week with a rubber curry brush or fine comb</li>
      <li><strong>Long-haired cats:</strong> Brush 3–5× per week to prevent painful matting</li>
      <li>Regular brushing reduces shedding, hairballs, and strengthens your bond</li>
      <li>Start grooming young to build positive associations early</li>
    </ul>

    <h3>Nail Care</h3>
    <ul>
      <li>Trim every <strong>2–4 weeks</strong> with purpose-made cat nail clippers</li>
      <li>Cut only the <strong>clear tip</strong> — avoid the pink "quick" (blood vessel)</li>
      <li>If your cat resists, do one paw per session — no need to rush</li>
      <li>Provide scratching posts to help naturally wear nails between trims</li>
    </ul>

    <h3>Dental Health</h3>
    <p>Up to 70% of cats show signs of gum disease by age 3. Prevention is far easier than treatment:</p>
    <ul>
      <li>Brush teeth <strong>3–4 times per week</strong> using cat-specific toothpaste (never human toothpaste)</li>
      <li>Dental treats and water additives help between brushings</li>
      <li>Annual professional dental cleanings keep the mouth healthy</li>
      <li>Address bad breath, drooling, or pawing at the mouth immediately</li>
    </ul>

    <h3>Bathing</h3>
    <ul>
      <li>Most healthy cats <strong>don't need regular baths</strong> — they self-groom expertly</li>
      <li>Bathe only if visibly soiled, treated for parasites, or for medical reasons</li>
      <li>Use warm (not hot) water and a gentle, cat-safe shampoo</li>
      <li>Dry thoroughly with a warm towel and keep away from drafts</li>
    </ul>

    <div class="tip-highlight">
      <div class="tip-highlight-head"><i class="fas fa-lightbulb"></i> Ear & Eye Care</div>
      <ul>
        <li>Check ears weekly for wax buildup, redness, or odor — signs of infection</li>
        <li>Clean ears with a vet-approved solution and a cotton ball (never a Q-tip)</li>
        <li>Gently wipe eye discharge with a damp cloth — discharge should be minimal and clear</li>
        <li>Flat-faced breeds (Persians, Himalayans) need more frequent facial cleaning</li>
      </ul>
    </div>

    <?php if (!empty($acc_products)): ?>
    <div class="suggested-products">
      <div class="suggested-products-title">
        <i class="fas fa-shopping-bag"></i> Recommended Grooming Accessories
      </div>
      <div class="suggestions-row">
        <?php foreach ($acc_products as $prod): ?>
        <a href="product.php?id=<?= $prod['id'] ?>" class="suggestion-card">
          <?php if ($prod['image']): ?>
          <img src="uploads/<?= htmlspecialchars($prod['image']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" class="suggestion-image">
          <?php else: ?>
          <div class="bg-light d-flex align-items-center justify-content-center suggestion-image">
            <i class="fas fa-box text-muted"></i>
          </div>
          <?php endif; ?>
          <div class="suggestion-info">
            <div class="suggestion-name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="suggestion-price">₱<?= number_format($prod['price'], 2) ?></div>
          </div>
          <div class="suggestion-btn">
            <i class="fas fa-chevron-right"></i>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── BEHAVIOR ── -->
  <div class="detail-section" id="behavior">
    <div class="section-heading">
      <div class="section-heading-icon"><i class="fas fa-brain"></i></div>
      <div class="section-heading-text">
        <h2>Behavior & Training</h2>
        <span>Speaking your cat's language fluently</span>
      </div>
    </div>

    <h3>Reading Body Language</h3>
    <div class="body-lang-grid">
      <div class="body-lang-item">
        <div class="body-lang-signal">🐾 Tail straight up</div>
        <div class="body-lang-meaning">Friendly, confident, happy to see you</div>
      </div>
      <div class="body-lang-item">
        <div class="body-lang-signal">😼 Slow blink</div>
        <div class="body-lang-meaning">Deep trust and affection — blink back!</div>
      </div>
      <div class="body-lang-item">
        <div class="body-lang-signal">😤 Puffed tail</div>
        <div class="body-lang-meaning">Frightened or feeling threatened</div>
      </div>
      <div class="body-lang-item">
        <div class="body-lang-signal">👂 Ears flattened</div>
        <div class="body-lang-meaning">Anxious, irritated, or fearful</div>
      </div>
      <div class="body-lang-item">
        <div class="body-lang-signal">💤 Showing belly</div>
        <div class="body-lang-meaning">Comfortable — but not always an invite to pet</div>
      </div>
      <div class="body-lang-item">
        <div class="body-lang-signal">😸 Kneading (making biscuits)</div>
        <div class="body-lang-meaning">Contentment — a holdover from kittenhood</div>
      </div>
    </div>

    <h3>Positive Training Principles</h3>
    <ul>
      <li>Use <strong>treats, praise, and play</strong> as rewards — never punishment</li>
      <li>Keep training sessions <strong>short: 5–10 minutes</strong> maximum</li>
      <li>End every session on a success — even a small one</li>
      <li>Be <strong>consistent</strong> — use the same cue words every time</li>
      <li>Cats can learn: sit, come, high-five, stay, spin, and more</li>
    </ul>

    <h3>Solving Common Behavioral Issues</h3>
    <ul>
      <li><strong>Scratching furniture:</strong> Provide more appealing scratching posts nearby; use double-sided tape as deterrent</li>
      <li><strong>Nighttime zoomies:</strong> Play session right before bedtime to burn energy and trigger sleep</li>
      <li><strong>Litter box avoidance:</strong> Rule out medical causes first, then assess box cleanliness and location</li>
      <li><strong>Aggression:</strong> Never escalate — give space and consult a feline behaviorist if persistent</li>
      <li><strong>Excessive vocalization:</strong> Could signal pain, hunger, stress, or a medical issue — check with vet</li>
    </ul>

    <div class="tip-highlight">
      <div class="tip-highlight-head"><i class="fas fa-lightbulb"></i> Behavioral Tips</div>
      <ul>
        <li>Redirect, never punish — punishment creates fear and damages trust</li>
        <li>Spaying/neutering dramatically reduces spraying, roaming, and aggression</li>
        <li>Multi-cat households need adequate resources: food bowls, perches, boxes, litter</li>
        <li>If behavior suddenly changes, always rule out medical causes with your vet first</li>
      </ul>
    </div>
  </div>

  <!-- CTA Strip -->
  <div class="cta-strip">
    <h3>Put the Tips into <em>Action</em></h3>
    <p>Give your cat the best with our vet-approved, all-natural treats and supplies — crafted to support every tip in this guide.</p>
    <div class="cta-strip-btns">
      <a href="shop.php" class="btn-cta-primary"><i class="fas fa-shopping-bag"></i> Shop Now</a>
      <a href="about.php" class="btn-cta-ghost"><i class="fas fa-info-circle"></i> Our Story</a>
    </div>
  </div>

</div><!-- /main-content -->

<!-- ===================== FOOTER ===================== -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h3>Pawganic Supplies</h3>
      <p>Since 2020, crafting premium, health-conscious treats by devoted cat lovers to support feline wellness in every bite.</p>
      <div class="social-links">
        <a href="https://www.facebook.com/" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="https://x.com/home" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        <a href="https://www.instagram.com/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="https://www.tiktok.com/en/" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
      </div>
    </div>
    <div class="footer-section">
      <h3>Quick Links</h3>
      <div class="footer-links">
        <a href="main.php">Home</a>
        <a href="shop.php">Shop</a>
        <a href="about.php">About</a>
        <a href="cat_care_tips.php">Cat Care Tips</a>
        <a href="main.php#faq">FAQs</a>
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

<button class="scroll-to-top" id="scrollBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Navbar profile dropdown ── */
const pd = document.querySelector('.profile-dropdown');
if (pd) {
  pd.querySelector('.profile-pic').addEventListener('click', e => { e.stopPropagation(); pd.classList.toggle('open'); });
  document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
}

/* ── Scroll reveal ── */
const revealObs = new IntersectionObserver(entries => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 60);
      revealObs.unobserve(entry.target);
    }
  });
}, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });

document.querySelectorAll('.tip-card, .detail-section').forEach(el => revealObs.observe(el));

/* ── Scroll to top ── */
const scrollBtn = document.getElementById('scrollBtn');
window.addEventListener('scroll', () => scrollBtn.classList.toggle('show', window.pageYOffset > 400));
scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* ── Active jump pill highlight ── */
const sections = document.querySelectorAll('.detail-section[id]');
const pills    = document.querySelectorAll('.jump-pill');

window.addEventListener('scroll', () => {
  let current = '';
  sections.forEach(s => {
    if (window.scrollY >= s.offsetTop - 120) current = s.id;
  });
  pills.forEach(p => {
    p.style.background = p.getAttribute('href') === '#' + current
      ? 'rgba(201,145,42,0.3)' : '';
    p.style.color = p.getAttribute('href') === '#' + current
      ? 'var(--honey)' : '';
  });
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