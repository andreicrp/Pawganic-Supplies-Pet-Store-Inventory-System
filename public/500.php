<?php
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>500 — Server Error · Pawganic Supplies</title>
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

  /* ===================== MAIN CONTENT ===================== */
  main { flex: 1; display: flex; flex-direction: column; }

  /* ===================== HERO ERROR SECTION ===================== */
  .error-hero {
    background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
    padding: 90px 5% 100px;
    position: relative;
    overflow: hidden;
    text-align: center;
  }

  .error-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.20) 0%, transparent 65%),
                radial-gradient(ellipse at 15% 80%, rgba(122,158,126,0.10) 0%, transparent 50%);
  }

  .error-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0; height: 70px;
    background: var(--cream);
    clip-path: ellipse(55% 100% at 50% 100%);
  }

  /* floating deco circles */
  .hero-deco {
    position: absolute; border-radius: 50%; opacity: 0.06; background: var(--honey);
    pointer-events: none;
    animation: float 8s ease-in-out infinite alternate;
  }
  .hd1 { width: 440px; height: 440px; top: -120px; right: -100px; }
  .hd2 { width: 260px; height: 260px; bottom: 0px;  left: 3%; animation-delay: -2s; }
  .hd3 { width: 140px; height: 140px; top:  20px; left: 28%; opacity: 0.04; animation-delay: -4s; }

  @keyframes float {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(-15px) scale(1.03); }
  }

  .error-hero-inner { position: relative; z-index: 2; max-width: 760px; margin: 0 auto; }

  /* Paw trail animation */
  .paw-trail {
    display: flex; justify-content: center; gap: 10px;
    margin-bottom: 30px;
    animation: fadeInDown 0.8s ease both;
  }

  .paw-trail i {
    font-size: 1.4rem; color: rgba(232,184,109,0.5);
    animation: pawBounce 1.8s ease-in-out infinite;
  }
  .paw-trail i:nth-child(1) { animation-delay: 0s;    color: rgba(232,184,109,0.25); }
  .paw-trail i:nth-child(2) { animation-delay: 0.15s; color: rgba(232,184,109,0.40); }
  .paw-trail i:nth-child(3) { animation-delay: 0.30s; color: rgba(232,184,109,0.60); }
  .paw-trail i:nth-child(4) { animation-delay: 0.45s; color: rgba(232,184,109,0.80); }
  .paw-trail i:nth-child(5) { animation-delay: 0.60s; color: rgba(232,184,109,1.00); }

  @keyframes pawBounce {
    0%, 100% { transform: translateY(0) scale(1); }
    50%       { transform: translateY(-10px) scale(1.15); }
  }

  /* 500 display number */
  .error-number {
    font-family: 'Playfair Display', serif;
    font-size: clamp(7rem, 18vw, 13rem);
    font-weight: 900;
    line-height: 1;
    color: transparent;
    -webkit-text-stroke: 2px rgba(232,184,109,0.5);
    background: linear-gradient(135deg, var(--honey) 0%, var(--gold) 50%, rgba(201,145,42,0.4) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: fadeInDown 0.9s ease both 0.1s;
    position: relative;
    display: inline-block;
    filter: drop-shadow(0 8px 32px rgba(201,145,42,0.3));
  }

  .error-number::after {
    content: attr(data-text);
    position: absolute; inset: 0;
    -webkit-text-stroke: 0;
    background: none;
    -webkit-background-clip: unset;
    background-clip: unset;
    -webkit-text-fill-color: rgba(255,255,255,0.04);
    filter: blur(20px);
    transform: translateY(8px);
    z-index: -1;
  }

  .error-hero-label {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.4);
    color: var(--honey); padding: 6px 16px; border-radius: 50px;
    font-size: 0.78rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 20px;
    animation: fadeInDown 0.8s ease both 0.25s;
  }

  .error-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.8rem, 4vw, 3rem);
    font-weight: 700;
    color: var(--white);
    margin-bottom: 16px;
    line-height: 1.2;
    animation: fadeInDown 0.8s ease both 0.35s;
  }

  .error-title em { font-style: italic; color: var(--honey); }

  .error-subtitle {
    color: rgba(255,255,255,0.65);
    font-size: 1.05rem;
    line-height: 1.7;
    max-width: 520px;
    margin: 0 auto 36px;
    animation: fadeInDown 0.8s ease both 0.45s;
  }

  .hero-action-group {
    display: flex; gap: 14px; flex-wrap: wrap; justify-content: center;
    animation: fadeInDown 0.8s ease both 0.55s;
  }

  .btn-hero-primary {
    background: linear-gradient(135deg, var(--gold), var(--honey));
    color: var(--espresso); border: none; border-radius: 50px;
    padding: 14px 32px; font-family: 'DM Sans', sans-serif;
    font-weight: 700; font-size: 0.95rem; cursor: pointer;
    transition: var(--transition); text-decoration: none;
    display: inline-flex; align-items: center; gap: 9px;
    box-shadow: 0 4px 18px rgba(201,145,42,0.4);
  }
  .btn-hero-primary:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(201,145,42,0.5); color: var(--espresso); }

  .btn-hero-secondary {
    background: rgba(255,255,255,0.10); border: 1.5px solid rgba(255,255,255,0.30);
    color: rgba(255,255,255,0.88); border-radius: 50px;
    padding: 14px 32px; font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 0.95rem; cursor: pointer;
    transition: var(--transition); text-decoration: none;
    display: inline-flex; align-items: center; gap: 9px;
    backdrop-filter: blur(8px);
  }
  .btn-hero-secondary:hover { background: rgba(255,255,255,0.18); color: var(--white); transform: translateY(-3px); }

  @keyframes fadeInDown { from { opacity:0; transform:translateY(-24px); } to { opacity:1; transform:translateY(0); } }

  /* ===================== INFO SECTION ===================== */
  .info-section {
    max-width: 1160px; margin: 60px auto; padding: 0 24px;
    display: grid; grid-template-columns: 1fr 1fr; gap: 28px;
  }

  /* What is 500 card */
  .info-card {
    background: var(--ivory); border-radius: var(--radius);
    padding: 36px; border: 1px solid rgba(201,145,42,0.12);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
  }
  .info-card:hover { box-shadow: var(--shadow-md); transform: translateY(-4px); border-color: rgba(201,145,42,0.25); }

  .info-card-icon {
    width: 54px; height: 54px; border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; margin-bottom: 20px;
    background: linear-gradient(135deg, rgba(201,145,42,0.15), rgba(232,184,109,0.1));
    border: 1px solid rgba(201,145,42,0.2);
    color: var(--gold);
  }

  .info-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.25rem; font-weight: 700; color: var(--mahogany);
    margin-bottom: 12px;
  }

  .info-card p {
    font-size: 0.9rem; color: var(--caramel); line-height: 1.75;
  }

  /* Cause list */
  .cause-list { list-style: none; margin-top: 14px; display: flex; flex-direction: column; gap: 10px; }
  .cause-list li {
    display: flex; align-items: flex-start; gap: 12px;
    font-size: 0.88rem; color: var(--caramel); line-height: 1.5;
  }
  .cause-list li i { color: var(--gold); margin-top: 3px; flex-shrink: 0; }

  /* Quick links card */
  .quick-links-grid { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; }

  .quick-link-item {
    display: flex; align-items: center; gap: 14px;
    background: var(--cream); border: 1px solid rgba(201,145,42,0.12);
    border-radius: var(--radius-sm); padding: 12px 16px;
    text-decoration: none; transition: var(--transition);
    color: var(--espresso);
  }
  .quick-link-item:hover {
    background: var(--ivory); border-color: rgba(201,145,42,0.3);
    transform: translateX(6px); box-shadow: var(--shadow-sm); color: var(--mahogany);
  }
  .quick-link-item-icon {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    color: var(--honey); font-size: 0.85rem;
  }
  .quick-link-item-text { flex: 1; }
  .quick-link-item-text strong { display: block; font-size: 0.88rem; font-weight: 700; color: var(--espresso); }
  .quick-link-item-text span   { font-size: 0.78rem; color: var(--caramel); }
  .quick-link-item i.arrow     { color: var(--mist); font-size: 0.8rem; transition: var(--transition); }
  .quick-link-item:hover i.arrow { color: var(--gold); transform: translateX(4px); }

  /* HTTP facts ticker */
  .facts-band {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    padding: 56px 5%;
    position: relative; overflow: hidden;
  }
  .facts-band::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(201,145,42,0.15) 0%, transparent 55%);
  }
  .facts-band-inner { position: relative; z-index: 2; max-width: 1160px; margin: 0 auto; }

  .facts-band-label {
    display: inline-flex; align-items: center; gap: 8px;
    color: rgba(232,184,109,0.7); font-size: 0.75rem; font-weight: 700;
    letter-spacing: 2.5px; text-transform: uppercase; margin-bottom: 32px;
  }

  .facts-band-label::before, .facts-band-label::after {
    content: ''; flex: 1; height: 1px; background: rgba(232,184,109,0.25);
  }

  .http-facts {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
  }

  .http-fact {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(201,145,42,0.2);
    border-radius: var(--radius-sm); padding: 24px 22px;
    transition: var(--transition);
    position: relative; overflow: hidden;
  }
  .http-fact:hover { background: rgba(201,145,42,0.12); border-color: rgba(201,145,42,0.4); transform: translateY(-4px); }
  .http-fact::before {
    content: attr(data-code);
    position: absolute; right: 16px; top: 12px;
    font-family: 'Playfair Display', serif;
    font-size: 3.5rem; font-weight: 900; opacity: 0.06;
    color: var(--honey); line-height: 1;
    pointer-events: none;
  }
  .http-fact-status {
    display: inline-block; background: rgba(201,145,42,0.2); border: 1px solid rgba(201,145,42,0.35);
    color: var(--honey); padding: 3px 10px; border-radius: 50px;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 12px;
  }
  .http-fact h4 { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 700; color: var(--white); margin-bottom: 8px; }
  .http-fact p  { font-size: 0.82rem; color: rgba(255,255,255,0.5); line-height: 1.6; }

  /* ===================== SEARCH / RECOVER SECTION ===================== */
  .recover-section {
    max-width: 680px; margin: 0 auto 60px; padding: 0 24px; text-align: center;
  }

  .recover-section-title {
    font-family: 'Playfair Display', serif;
    font-size: 2rem; font-weight: 700; color: var(--mahogany);
    margin-bottom: 10px;
  }

  .recover-section-subtitle {
    font-size: 0.92rem; color: var(--caramel); margin-bottom: 28px; line-height: 1.6;
  }

  .recover-bar {
    background: var(--ivory); border-radius: var(--radius);
    padding: 20px 24px;
    box-shadow: var(--shadow-sm); border: 1px solid rgba(201,145,42,0.12);
    display: flex; gap: 12px; align-items: center;
  }

  .recover-bar-icon { color: var(--caramel); font-size: 0.9rem; flex-shrink: 0; }

  .recover-bar input {
    flex: 1; border: none; background: transparent;
    font-family: 'DM Sans', sans-serif; font-size: 0.95rem; color: var(--espresso);
    outline: none;
  }
  .recover-bar input::placeholder { color: rgba(155,106,47,0.55); }

  .recover-bar button {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    color: var(--honey); border: none; border-radius: 50px;
    padding: 10px 22px; font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 0.88rem; cursor: pointer;
    transition: var(--transition); white-space: nowrap;
  }
  .recover-bar button:hover { background: var(--gold); color: var(--white); }

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

  /* Floating cat paw cursor sparkle */
  .paw-spark {
    position: fixed; pointer-events: none; z-index: 9999;
    font-size: 1.1rem; color: var(--gold); opacity: 0;
    transform: translate(-50%, -50%) scale(0.5);
    transition: opacity 0.5s, transform 0.5s;
  }
  .paw-spark.pop {
    opacity: 1; transform: translate(-50%, -80%) scale(1.2);
  }
  .paw-spark.fade { opacity: 0; transform: translate(-50%, -120%) scale(0.8); }

  /* Section title helper */
  .section-eyebrow {
    font-size: 0.75rem; font-weight: 700; letter-spacing: 2.5px;
    text-transform: uppercase; color: var(--caramel); margin-bottom: 8px;
    display: flex; align-items: center; gap: 10px;
  }
  .section-eyebrow::after { content: ''; flex: 0 0 32px; height: 2px; background: var(--mist); border-radius: 2px; }

  /* ===================== RESPONSIVE ===================== */
  @media (max-width: 900px) {
    .info-section { grid-template-columns: 1fr; }
    .http-facts    { grid-template-columns: 1fr; }
  }

  @media (max-width: 768px) {
    .navbar { padding: 0 20px; }
    .nav-links a:not(.active) { display: none; }
    .error-hero { padding: 60px 24px 80px; }
    .recover-section { margin-bottom: 40px; }
    .recover-bar { flex-direction: column; }
    .recover-bar button { width: 100%; }
  }
  </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<div class="navbar">
  <a href="main.php" style="text-decoration:none;">
    <img src="assets/pagelogo.png" alt="Pawganic Supplies" class="logo-img">
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
      $nav_username = $_SESSION['username'] ?? 'User';
      $nav_role     = $_SESSION['role'] ?? 'customer';
      $nav_balance  = $_SESSION['balance'] ?? 0;
      $user_id      = $_SESSION['user_id'];
      $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
      $pic_stmt->bind_param("i", $user_id);
      $pic_stmt->execute();
      $pic_stmt->bind_result($profile_pic);
      $pic_stmt->fetch();
      $pic_stmt->close();
      if (!$profile_pic) $profile_pic = 'images/profile.jpg';
      $profile_pic_safe = htmlspecialchars($profile_pic);
      echo '
      <div class="profile-dropdown">
        <img src="'.$profile_pic_safe.'" alt="Profile" class="profile-pic" onerror="this.src=\'images/profile.jpg\'">
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
  </div>
</div>

<main>
  <!-- ===================== HERO ===================== -->
  <section class="error-hero">
    <div class="hero-deco hd1"></div>
    <div class="hero-deco hd2"></div>
    <div class="hero-deco hd3"></div>

    <div class="error-hero-inner">
      <!-- Paw trail -->
      <div class="paw-trail">
        <i class="fas fa-paw"></i>
        <i class="fas fa-paw"></i>
        <i class="fas fa-paw"></i>
        <i class="fas fa-paw"></i>
        <i class="fas fa-paw"></i>
      </div>

      <div class="error-number" data-text="500">500</div>

      <div style="height:16px;"></div>
      <div class="error-hero-label"><i class="fas fa-server"></i> Server Error</div>

      <h1 class="error-title">
        Our server is having a <em>hairball moment</em>.
      </h1>

      <p class="error-subtitle">
        Something went wrong on the server itself. Unlike a 404, this one is our problem to fix — the request was perfectly valid. We're already on the case!
      </p>

      <div class="hero-action-group">
        <a href="main.php" class="btn-hero-primary">
          <i class="fas fa-home"></i> Back to Home
        </a>
        <a href="shop.php" class="btn-hero-secondary">
          <i class="fas fa-shopping-bag"></i> Browse the Shop
        </a>
      </div>
    </div>
  </section>

  <!-- ===================== INFO CARDS ===================== -->
  <div class="info-section">

    <!-- What is a 500? -->
    <div class="info-card">
      <div class="info-card-icon"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="section-eyebrow">What happened</div>
      <h3>What is a 500 Error?</h3>
      <p>An <strong style="color:var(--mahogany)">HTTP 500 Internal Server Error</strong> is a generic error message returned by the web server when it encounters an unexpected condition that prevented it from fulfilling the request. It means the server had a glitch, but the exact cause isn't specified to the browser for security reasons.</p>
      <p style="margin-top:12px;">This code acts as a catch-all for server-side errors, meaning the issue is on our end rather than your browser or internet connection.</p>
    </div>

    <!-- Why did this happen -->
    <div class="info-card">
      <div class="info-card-icon"><i class="fas fa-search"></i></div>
      <div class="section-eyebrow">Common causes</div>
      <h3>Why Did This Happen?</h3>
      <ul class="cause-list">
        <li><i class="fas fa-tools"></i><span><strong>Configuration typo</strong> — A syntax error in the site's `.htaccess` file can trigger an immediate 500 code.</span></li>
        <li><i class="fas fa-database"></i><span><strong>Database connection failed</strong> — The server might be unable to reach the MySQL database server due to heavy traffic.</span></li>
        <li><i class="fas fa-code"></i><span><strong>Script logic crash</strong> — A programming error in a backend PHP file caused the script to terminate prematurely.</span></li>
        <li><i class="fas fa-memory"></i><span><strong>Resource limits exceeded</strong> — The script request took too long or required more memory than allocated by the hosting environment.</span></li>
      </ul>
    </div>

    <!-- Quick links -->
    <div class="info-card">
      <div class="info-card-icon"><i class="fas fa-map-signs"></i></div>
      <div class="section-eyebrow">Navigate</div>
      <h3>Where Would You Like to Go?</h3>
      <div class="quick-links-grid">
        <a href="main.php" class="quick-link-item">
          <div class="quick-link-item-icon"><i class="fas fa-home"></i></div>
          <div class="quick-link-item-text">
            <strong>Home</strong>
            <span>Return to the main landing page</span>
          </div>
          <i class="fas fa-chevron-right arrow"></i>
        </a>
        <a href="shop.php" class="quick-link-item">
          <div class="quick-link-item-icon"><i class="fas fa-store"></i></div>
          <div class="quick-link-item-text">
            <strong>Shop</strong>
            <span>Browse all our premium pet products</span>
          </div>
          <i class="fas fa-chevron-right arrow"></i>
        </a>
        <a href="about.php" class="quick-link-item">
          <div class="quick-link-item-icon"><i class="fas fa-leaf"></i></div>
          <div class="quick-link-item-text">
            <strong>About Us</strong>
            <span>Learn about Pawganic's story</span>
          </div>
          <i class="fas fa-chevron-right arrow"></i>
        </a>
        <?php if (isset($_SESSION['user_id'])): ?>
        <a href="profile.php" class="quick-link-item">
          <div class="quick-link-item-icon"><i class="fas fa-user"></i></div>
          <div class="quick-link-item-text">
            <strong>My Profile</strong>
            <span>View your account settings</span>
          </div>
          <i class="fas fa-chevron-right arrow"></i>
        </a>
        <?php else: ?>
        <a href="login.php" class="quick-link-item">
          <div class="quick-link-item-icon"><i class="fas fa-sign-in-alt"></i></div>
          <div class="quick-link-item-text">
            <strong>Login</strong>
            <span>Sign in to your account</span>
          </div>
          <i class="fas fa-chevron-right arrow"></i>
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- What to try -->
    <div class="info-card">
      <div class="info-card-icon"><i class="fas fa-lightbulb"></i></div>
      <div class="section-eyebrow">How to fix it</div>
      <h3>What You Can Try</h3>
      <ul class="cause-list">
        <li><i class="fas fa-redo"></i><span><strong>Reload the page</strong> — Many server errors are transient. Try refreshing (F5 or Ctrl+F5) in a few seconds.</span></li>
        <li><i class="fas fa-clock"></i><span><strong>Wait and return</strong> — If the server is experiencing high traffic, it usually recovers within a few minutes.</span></li>
        <li><i class="fas fa-arrow-left"></i><span><strong>Go back</strong> — Navigate back to the previous page you were on.</span></li>
        <li><i class="fas fa-envelope"></i><span><strong>Report it</strong> — Let us know what you were trying to do at <a href="mailto:meow@pawganic.com" style="color:var(--gold);font-weight:600;">meow@pawganic.com</a>.</span></li>
      </ul>
    </div>

  </div>

  <!-- ===================== HTTP STATUS FACTS ===================== -->
  <section class="facts-band">
    <div class="facts-band-inner">
      <div class="facts-band-label"><span>Other HTTP Status Codes You Might Know</span></div>
      <div class="http-facts">
        <div class="http-fact" data-code="200">
          <span class="http-fact-status">200 OK</span>
          <h4>Everything Worked!</h4>
          <p>The request was successful and the server returned the requested content. This is what you want to see — you won't notice it because things just work.</p>
        </div>
        <div class="http-fact" data-code="301">
          <span class="http-fact-status">301 Moved</span>
          <h4>Permanently Redirected</h4>
          <p>The page exists but has permanently moved to a new address. Your browser follows automatically, so you usually never notice this one either.</p>
        </div>
        <div class="http-fact" data-code="403">
          <span class="http-fact-status">403 Forbidden</span>
          <h4>Access Denied</h4>
          <p>The page exists, but you don't have permission to see it. Unlike a 404, the server knows it's there — it's just not letting you in.</p>
        </div>
        <div class="http-fact" data-code="404">
          <span class="http-fact-status">404 Not Found</span>
          <h4>You Are Here</h4>
          <p>The page doesn't exist at the given address. The server understood the request perfectly — it just has nothing to show you for that URL.</p>
        </div>
        <div class="http-fact" data-code="500">
          <span class="http-fact-status">500 Server Error</span>
          <h4>Our Side, Not Yours</h4>
          <p>Something went wrong on the server itself. Unlike a 404, this one is our problem to fix — the request was perfectly valid.</p>
        </div>
        <div class="http-fact" data-code="503">
          <span class="http-fact-status">503 Unavailable</span>
          <h4>Temporarily Down</h4>
          <p>The server is alive but not ready to handle requests — usually due to maintenance or being overwhelmed. Try again in a few minutes.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== RECOVER SECTION ===================== -->
  <section class="recover-section" style="margin-top:60px;">
    <div class="section-eyebrow" style="justify-content:center; margin-bottom:10px;"><i class="fas fa-search"></i> Find It</div>
    <h2 class="recover-section-title">Still Can't Find What You Need?</h2>
    <p class="recover-section-subtitle">Type what you're looking for below and we'll take you to the shop with those search results.</p>
    <div class="recover-bar">
      <i class="fas fa-search recover-bar-icon"></i>
      <input type="text" id="shopSearch" placeholder="Search treats, toys, food, accessories…">
      <button onclick="goSearch()"><i class="fas fa-arrow-right"></i> Search Shop</button>
    </div>
  </section>

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
      <p><i class="fas fa-map-marker-alt" style="color:var(--honey); margin-right:8px;"></i> 123 Feline Street, Purrville, PH</p>
      <p><i class="fas fa-phone" style="color:var(--honey); margin-right:8px;"></i> +1 234 567 8900</p>
      <p><i class="fas fa-envelope" style="color:var(--honey); margin-right:8px;"></i> meow@pawganic.com</p>
      <p><i class="fas fa-clock" style="color:var(--honey); margin-right:8px;"></i> Mon–Fri: 9AM–6PM</p>
    </div>
  </div>
  <div class="copyright">
    <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved.</p>
  </div>
</footer>

<button class="scroll-to-top" id="scrollToTopBtn"><i class="fas fa-arrow-up"></i></button>

<!-- Paw spark element -->
<div class="paw-spark" id="pawSpark"><i class="fas fa-paw"></i></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===================== SCROLL TO TOP ===================== */
document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('scrollToTopBtn');
  window.addEventListener('scroll', () => {
    btn.classList.toggle('show', window.pageYOffset > 300);
  });
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  /* Profile dropdown click toggle */
  const pd = document.querySelector('.profile-dropdown');
  if (pd) {
    pd.querySelector('.profile-pic')?.addEventListener('click', function (e) {
      e.stopPropagation();
      pd.classList.toggle('open');
    });
    document.addEventListener('click', () => pd.classList.remove('open'));
  }

  /* Scroll-reveal on info cards */
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.info-card, .http-fact').forEach(el => {
    el.style.opacity    = '0';
    el.style.transform  = 'translateY(28px)';
    el.style.transition = 'opacity 0.55s ease, transform 0.55s ease';
    observer.observe(el);
  });
});

/* ===================== PAW CURSOR SPARKLE ===================== */
let sparkTimeout;
const spark = document.getElementById('pawSpark');
document.addEventListener('mousemove', function (e) {
  clearTimeout(sparkTimeout);
  spark.style.left = e.clientX + 'px';
  spark.style.top  = e.clientY + 'px';
  spark.classList.remove('pop', 'fade');
  void spark.offsetWidth; // reflow
  spark.classList.add('pop');
  sparkTimeout = setTimeout(() => {
    spark.classList.remove('pop');
    spark.classList.add('fade');
  }, 300);
});

/* ===================== SHOP SEARCH REDIRECT ===================== */
function goSearch() {
  const q = document.getElementById('shopSearch').value.trim();
  if (q) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'shop.php';
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'search';
    input.value = q;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
  } else {
    window.location.href = 'shop.php';
  }
}

document.getElementById('shopSearch').addEventListener('keydown', function (e) {
  if (e.key === 'Enter') goSearch();
});
</script>
</body>
</html>
