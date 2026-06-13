<?php
require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pawganic Supplies — Premium Cat Treats</title>
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

    .nav-links a:hover, .nav-links a.active {
      background: var(--gold);
      color: var(--white);
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

    /* ===================== HERO ===================== */
    .hero {
      position: relative;
      height: 100vh;
      min-height: 680px;
      max-height: 860px;
      display: flex;
      align-items: center;
      overflow: hidden;
    }

    .slideshow-container {
      position: absolute;
      inset: 0;
      z-index: 0;
    }

    .slide {
      position: absolute;
      inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      object-position: center;
      opacity: 0;
      transition: opacity 1.8s ease-in-out;
    }

    .slide.active { opacity: 1; }

    .hero-overlay {
      position: absolute;
      inset: 0;
      z-index: 1;
      background: linear-gradient(
        110deg,
        rgba(28,14,6,0.85) 0%,
        rgba(44,26,14,0.72) 45%,
        rgba(90,45,12,0.45) 100%
      );
    }

    .hero-wave {
      position: absolute;
      bottom: 0; left: 0; right: 0;
      z-index: 2;
      height: 80px;
      background: var(--cream);
      clip-path: ellipse(56% 100% at 50% 100%);
    }

    /* Progress bar */
    .slide-progress {
      position: absolute;
      bottom: 80px; left: 5%; right: 5%;
      height: 2px;
      background: rgba(255,255,255,0.18);
      border-radius: 2px;
      z-index: 3;
    }
    .slide-progress-fill {
      height: 100%;
      background: var(--honey);
      border-radius: 2px;
      width: 0%;
      transition: width 0.1s linear;
    }

    /* Slide dots */
    .slide-dots {
      position: absolute;
      bottom: 95px; right: 5%;
      z-index: 4;
      display: flex; gap: 8px;
    }
    .slide-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: rgba(255,255,255,0.35);
      cursor: pointer;
      border: 1.5px solid rgba(255,255,255,0.5);
      transition: var(--transition);
    }
    .slide-dot.active { background: var(--honey); transform: scale(1.4); border-color: var(--honey); }

    .hero-content {
      position: relative;
      z-index: 3;
      padding: 0 5%;
      max-width: 760px;
    }

    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,145,42,0.2);
      border: 1px solid rgba(201,145,42,0.45);
      color: var(--honey);
      padding: 7px 18px; border-radius: 50px;
      font-size: 0.75rem; font-weight: 600; letter-spacing: 2.5px; text-transform: uppercase;
      margin-bottom: 24px;
      backdrop-filter: blur(8px);
      animation: heroIn 0.9s 0.1s both;
    }

    @keyframes heroIn {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(3rem, 6vw, 5.5rem);
      font-weight: 900;
      color: var(--white);
      line-height: 1.06;
      margin-bottom: 22px;
      letter-spacing: -1px;
      animation: heroIn 0.9s 0.2s both;
    }

    .hero h1 em { font-style: italic; color: var(--honey); }

    .hero-sub {
      font-size: 1.1rem;
      color: rgba(255,255,255,0.78);
      line-height: 1.75;
      max-width: 500px;
      margin-bottom: 38px;
      font-weight: 300;
      animation: heroIn 0.9s 0.3s both;
    }

    .hero-ctas {
      display: flex; gap: 14px; flex-wrap: wrap;
      animation: heroIn 0.9s 0.4s both;
    }

    .btn-primary-hero {
      display: inline-flex; align-items: center; gap: 10px;
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--espresso);
      padding: 16px 36px; border-radius: 50px;
      font-weight: 700; font-size: 0.95rem; letter-spacing: 0.3px;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 8px 28px rgba(201,145,42,0.4);
    }
    .btn-primary-hero:hover {
      background: var(--white);
      color: var(--mahogany);
      transform: translateY(-4px);
      box-shadow: 0 16px 40px rgba(201,145,42,0.5);
    }

    .btn-ghost-hero {
      display: inline-flex; align-items: center; gap: 10px;
      background: transparent;
      color: var(--white);
      padding: 15px 32px; border-radius: 50px;
      border: 1.5px solid rgba(255,255,255,0.45);
      font-weight: 500; font-size: 0.95rem;
      text-decoration: none;
      transition: var(--transition);
      backdrop-filter: blur(8px);
    }
    .btn-ghost-hero:hover {
      background: rgba(255,255,255,0.12);
      border-color: rgba(255,255,255,0.85);
      transform: translateY(-4px);
    }

    /* Hero trust pills */
    .hero-trust {
      position: absolute;
      right: 5%; top: 50%; transform: translateY(-50%);
      z-index: 3;
      display: flex; flex-direction: column; gap: 14px;
      animation: heroIn 0.9s 0.5s both;
    }

    .trust-pill {
      background: rgba(253,248,240,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      backdrop-filter: blur(12px);
      padding: 14px 20px;
      border-radius: var(--radius-sm);
      display: flex; align-items: center; gap: 12px;
      color: var(--white);
      min-width: 190px;
      transition: var(--transition);
    }

    .trust-pill:hover { background: rgba(253,248,240,0.2); transform: translateX(-4px); }

    .trust-icon {
      width: 36px; height: 36px; border-radius: 8px;
      background: rgba(201,145,42,0.3);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; color: var(--honey);
      flex-shrink: 0;
    }

    .trust-text { font-size: 0.8rem; font-weight: 600; }
    .trust-sub { font-size: 0.72rem; color: rgba(255,255,255,0.55); margin-top: 2px; }

    /* ===================== MARQUEE ===================== */
    .marquee-strip {
      background: var(--espresso);
      color: var(--honey);
      padding: 14px 0;
      overflow: hidden;
      white-space: nowrap;
    }

    .marquee-track {
      display: inline-block;
      animation: marqueeScroll 28s linear infinite;
    }

    @keyframes marqueeScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    .marquee-item {
      display: inline-flex; align-items: center; gap: 10px;
      font-size: 0.72rem; font-weight: 600;
      letter-spacing: 2px; text-transform: uppercase;
      margin-right: 48px;
      color: rgba(232,184,109,0.8);
    }

    .marquee-item i { color: rgba(201,145,42,0.45); font-size: 7px; }

    /* ===================== SECTIONS LAYOUT ===================== */
    .section-wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 24px;
    }

    .section-header {
      text-align: center;
      margin-bottom: 56px;
    }

    .section-label {
      display: inline-block;
      background: rgba(201,145,42,0.12);
      color: var(--caramel);
      border: 1px solid rgba(201,145,42,0.25);
      padding: 5px 16px; border-radius: 50px;
      font-size: 0.72rem; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase;
      margin-bottom: 16px;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 900;
      color: var(--espresso);
      line-height: 1.15;
      margin-bottom: 14px;
    }

    .section-title em { font-style: italic; color: var(--gold); }

    .section-sub {
      font-size: 1rem;
      color: var(--caramel);
      max-width: 480px;
      margin: 0 auto;
      line-height: 1.7;
      font-weight: 300;
    }

    /* ===================== FEATURES ===================== */
    .features-section {
      padding: 100px 0;
      background: var(--ivory);
      position: relative;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
    }

    .feature-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 42px 34px;
      border: 1px solid rgba(201,145,42,0.1);
      box-shadow: var(--shadow-sm);
      position: relative; overflow: hidden;
      transition: var(--transition);
      opacity: 0; transform: translateY(28px);
    }

    .feature-card.visible {
      opacity: 1; transform: translateY(0);
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 3px;
      background: linear-gradient(90deg, var(--gold), var(--honey));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s ease;
    }

    .feature-card:hover::before { transform: scaleX(1); }
    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(201,145,42,0.25);
    }

    .feature-icon-wrap {
      width: 66px; height: 66px;
      background: linear-gradient(135deg, rgba(232,217,181,0.5), rgba(201,145,42,0.15));
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 26px;
      transition: var(--transition);
    }

    .feature-card:hover .feature-icon-wrap {
      background: linear-gradient(135deg, var(--gold), var(--honey));
      transform: rotate(5deg) scale(1.08);
    }

    .feature-icon { font-size: 1.8rem; color: var(--gold); transition: color 0.3s; }
    .feature-card:hover .feature-icon { color: var(--white); }

    .feature-card h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem; font-weight: 700;
      color: var(--espresso); margin-bottom: 12px;
    }

    .feature-card p {
      color: var(--caramel); font-size: 0.9rem;
      line-height: 1.8; font-weight: 300;
    }

    /* ===================== STATS ===================== */
    .stats-section {
      background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
      padding: 80px 0;
      position: relative; overflow: hidden;
    }

    .stats-section::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.2) 0%, transparent 60%);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0;
      position: relative; z-index: 1;
    }

    .stat-item {
      text-align: center;
      padding: 40px 24px;
      border-right: 1px solid rgba(255,255,255,0.08);
      opacity: 0; transform: translateY(18px);
      transition: var(--transition);
    }

    .stat-item:last-child { border-right: none; }

    .stat-item.visible { opacity: 1; transform: translateY(0); }

    .stat-num {
      font-family: 'Playfair Display', serif;
      font-size: 3rem; font-weight: 900;
      color: var(--honey);
      display: block; line-height: 1; margin-bottom: 8px;
    }

    .stat-label {
      font-size: 0.78rem; color: rgba(255,255,255,0.5);
      text-transform: uppercase; letter-spacing: 2px;
    }

    /* ===================== PRODUCTS PREVIEW ===================== */
    .products-preview-section {
      padding: 100px 0;
      background: var(--cream);
    }

    .products-preview-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }

    /* Product card — same style as shop.php */
    .product-card {
      background: var(--ivory);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(201,145,42,0.08);
      cursor: pointer;
      transition: transform 0.35s cubic-bezier(0.4,0,0.2,1),
                  box-shadow 0.35s cubic-bezier(0.4,0,0.2,1),
                  border-color 0.35s ease;
      display: flex; flex-direction: column;
      position: relative;
      opacity: 0; transform: translateY(20px);
    }

    .product-card.visible { opacity: 1; transform: translateY(0); }

    .product-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(201,145,42,0.3);
    }

    .stock-badge {
      position: absolute; top: 14px; left: 14px; z-index: 5;
      padding: 4px 12px; border-radius: 50px; font-size: 0.72rem;
      font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .stock-badge.in-stock { background: rgba(122,158,126,0.15); color: var(--sage); border: 1px solid rgba(122,158,126,0.3); }
    .stock-badge.low-stock { background: rgba(201,145,42,0.15); color: var(--gold); border: 1px solid rgba(201,145,42,0.3); }
    .stock-badge.out-stock { background: rgba(192,57,43,0.12); color: #c0392b; border: 1px solid rgba(192,57,43,0.25); }

    .card-image {
      height: 220px;
      background: linear-gradient(145deg, var(--cream), var(--mist));
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; position: relative;
    }

    .card-image img {
      max-width: 76%; max-height: 76%; object-fit: contain;
      transition: transform 0.5s cubic-bezier(0.4,0,0.2,1);
      filter: drop-shadow(0 8px 20px rgba(44,26,14,0.15));
    }

    .product-card:hover .card-image img { transform: scale(1.08) translateY(-4px); }

    .card-body {
      padding: 22px 20px 10px;
      flex: 1; display: flex; flex-direction: column; gap: 6px;
      position: relative;
    }

    .price-ribbon {
      position: absolute; top: -18px; right: 18px;
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      color: var(--honey);
      padding: 7px 16px; border-radius: 50px;
      font-family: 'Playfair Display', serif;
      font-size: 1rem; font-weight: 700;
      box-shadow: 0 4px 14px rgba(44,26,14,0.22);
    }

    .card-category {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(201,145,42,0.1); color: var(--caramel);
      padding: 3px 10px; border-radius: 50px;
      font-size: 0.72rem; font-weight: 600;
      border: 1px solid rgba(201,145,42,0.2);
      width: fit-content;
    }

    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--espresso); line-height: 1.3;
    }

    .card-desc {
      font-size: 0.82rem; color: var(--caramel); line-height: 1.6;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .card-footer-actions {
      padding: 14px 20px 20px;
      border-top: 1px solid var(--mist);
    }

    .btn-view {
      width: 100%; padding: 11px;
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--espresso);
      border: none; border-radius: 50px;
      font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.88rem;
      cursor: pointer; transition: var(--transition);
      display: flex; align-items: center; justify-content: center; gap: 8px;
      text-decoration: none;
    }
    .btn-view:hover {
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      color: var(--honey); transform: translateY(-2px); box-shadow: var(--shadow-sm);
    }

    .view-all-wrap { text-align: center; margin-top: 14px; }

    .btn-view-all {
      display: inline-flex; align-items: center; gap: 10px;
      background: transparent;
      border: 2px solid var(--espresso);
      color: var(--espresso);
      padding: 14px 36px; border-radius: 50px;
      font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.9rem;
      text-decoration: none;
      transition: var(--transition);
    }
    .btn-view-all:hover {
      background: var(--espresso); color: var(--honey);
      transform: translateY(-2px); box-shadow: var(--shadow-md);
    }

    /* ===================== HOW IT WORKS ===================== */
    .how-section {
      padding: 100px 0;
      background: var(--ivory);
    }

    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 0;
      position: relative;
    }

    .steps-grid::before {
      content: '';
      position: absolute;
      top: 40px; left: 12.5%; right: 12.5%;
      height: 2px;
      background: linear-gradient(90deg, var(--gold), var(--honey), var(--gold));
      z-index: 0;
    }

    .step-item {
      text-align: center;
      padding: 0 20px 40px;
      position: relative; z-index: 1;
      opacity: 0; transform: translateY(24px);
      transition: var(--transition);
    }

    .step-item.visible { opacity: 1; transform: translateY(0); }

    .step-num {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      color: var(--honey);
      border-radius: 50%;
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; font-weight: 900;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
      box-shadow: 0 8px 28px rgba(44,26,14,0.25);
      border: 4px solid var(--ivory);
      transition: var(--transition);
    }

    .step-item:hover .step-num {
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--espresso);
      transform: scale(1.1);
    }

    .step-item h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.1rem; font-weight: 700;
      color: var(--espresso); margin-bottom: 10px;
    }

    .step-item p {
      font-size: 0.87rem; color: var(--caramel);
      line-height: 1.75; font-weight: 300;
    }

    /* ===================== TESTIMONIALS ===================== */
    .testimonials-section {
      padding: 100px 0;
      background: var(--cream);
    }

    .testimonials-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 24px;
    }

    .testimonial-card {
      background: var(--ivory);
      padding: 36px 32px;
      border-radius: var(--radius);
      border: 1px solid rgba(201,145,42,0.12);
      box-shadow: var(--shadow-sm);
      position: relative; overflow: hidden;
      transition: var(--transition);
      opacity: 0; transform: translateY(24px);
    }

    .testimonial-card.visible { opacity: 1; transform: translateY(0); }

    .testimonial-card:hover {
      transform: translateY(-10px) scale(1.02);
      box-shadow: var(--shadow-lg);
      border-color: rgba(201,145,42,0.3);
    }

    .quote-mark {
      font-family: 'Playfair Display', serif;
      font-size: 5rem; line-height: 0.7;
      color: rgba(201,145,42,0.18);
      display: block; margin-bottom: 14px;
      font-weight: 900;
    }

    .stars { display: flex; gap: 3px; margin-bottom: 14px; }
    .stars i { color: var(--gold); font-size: 0.9rem; }

    .testimonial-text {
      color: #6a5040; font-size: 0.9rem;
      line-height: 1.85; margin-bottom: 22px;
      font-style: italic; font-weight: 300;
    }

    .testimonial-author {
      display: flex; align-items: center; gap: 12px;
    }

    .avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg, var(--gold), var(--honey));
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.85rem; color: var(--espresso);
      flex-shrink: 0;
    }

    .author-name { font-weight: 700; color: var(--mahogany); font-size: 0.9rem; }

    .verified {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 0.72rem; font-weight: 600;
      color: var(--sage);
      background: rgba(122,158,126,0.12);
      padding: 3px 9px; border-radius: 10px;
      margin-top: 4px;
    }

    /* ===================== FAQ ===================== */
    .faq-section {
      padding: 100px 0;
      background: var(--ivory);
    }

    .faq-grid {
      max-width: 780px; margin: 0 auto;
    }

    .accordion-wrap {
      background: var(--white);
      border-radius: var(--radius);
      padding: 12px;
      box-shadow: var(--shadow-sm);
      border: 1px solid rgba(201,145,42,0.1);
    }

    .accordion-item {
      border-radius: 12px;
      margin-bottom: 6px;
      overflow: hidden;
    }
    .accordion-item:last-child { margin-bottom: 0; }

    .accordion-item.open { background: rgba(232,184,109,0.07); }

    .accordion-header {
      padding: 18px 22px;
      cursor: pointer;
      display: flex; align-items: center;
      justify-content: space-between;
      border-radius: 12px;
      transition: var(--transition);
    }

    .accordion-header:hover { background: rgba(201,145,42,0.06); }

    .accordion-q {
      display: flex; align-items: center; gap: 12px;
      font-size: 0.95rem; font-weight: 600;
      color: var(--mahogany); margin: 0;
    }

    .accordion-icon-wrap {
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(201,145,42,0.12);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: var(--transition);
    }

    .accordion-icon-wrap i { color: var(--gold); font-size: 0.8rem; transition: transform 0.3s; }
    .accordion-item.open .accordion-icon-wrap { background: var(--gold); }
    .accordion-item.open .accordion-icon-wrap i { color: var(--white); transform: rotate(45deg); }

    .accordion-body {
      max-height: 0; overflow: hidden;
      transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      padding: 0 22px;
    }

    .accordion-item.open .accordion-body {
      max-height: 300px;
      padding-bottom: 18px;
    }

    .accordion-body p {
      font-size: 0.9rem; color: #6a5040;
      line-height: 1.85; font-weight: 300;
      padding-left: 42px;
    }

    /* ===================== CTA SECTION ===================== */
    .cta-section {
      padding: 100px 5%;
      background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 55%, #8b4513 100%);
      text-align: center;
      position: relative; overflow: hidden;
    }

    .cta-section::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 70% 40%, rgba(201,145,42,0.22) 0%, transparent 60%),
                  radial-gradient(ellipse at 15% 70%, rgba(122,158,126,0.12) 0%, transparent 50%);
    }

    /* Deco bubbles */
    .cta-deco {
      position: absolute; border-radius: 50%; opacity: 0.06;
      background: var(--honey);
    }
    .cta-deco-1 { width: 340px; height: 340px; top: -120px; right: -60px; }
    .cta-deco-2 { width: 200px; height: 200px; bottom: -60px; left: 8%; }

    .cta-section .section-label { background: rgba(201,145,42,0.2); color: var(--honey); border-color: rgba(201,145,42,0.4); }

    .cta-section h2 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 3.2rem);
      font-weight: 900; color: var(--white);
      line-height: 1.15; margin-bottom: 18px;
      position: relative; z-index: 1;
    }

    .cta-section h2 em { font-style: italic; color: var(--honey); }

    .cta-section p {
      font-size: 1rem; color: rgba(255,255,255,0.72);
      max-width: 500px; margin: 0 auto 38px;
      line-height: 1.7; font-weight: 300;
      position: relative; z-index: 1;
    }

    .btn-cta {
      display: inline-flex; align-items: center; gap: 10px;
      background: var(--white);
      color: var(--mahogany);
      padding: 18px 46px; border-radius: 50px;
      font-family: 'DM Sans', sans-serif;
      font-weight: 700; font-size: 1rem;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 8px 28px rgba(0,0,0,0.22);
      position: relative; z-index: 1;
    }
    .btn-cta:hover {
      background: linear-gradient(135deg, var(--gold), var(--honey));
      color: var(--espresso);
      transform: translateY(-5px) scale(1.04);
      box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }

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
      display: flex; align-items: center; gap: 8px;
    }
    .footer-links a::before { content: '→'; opacity: 0; transform: translateX(-8px); transition: var(--transition); }
    .footer-links a:hover { color: var(--honey); padding-left: 8px; }
    .footer-links a:hover::before { opacity: 1; transform: translateX(0); }

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
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      box-shadow: var(--shadow-md);
      opacity: 0; visibility: hidden; transition: var(--transition);
    }
    .scroll-to-top.show { opacity: 1; visibility: visible; }
    .scroll-to-top:hover { background: var(--gold); color: var(--white); transform: translateY(-3px); }

    /* ===================== NEWSLETTER STRIP ===================== */
    .newsletter-strip {
      background: linear-gradient(135deg, var(--mist), var(--cream));
      border-top: 1px solid rgba(201,145,42,0.15);
      border-bottom: 1px solid rgba(201,145,42,0.15);
      padding: 60px 5%;
    }

    .newsletter-inner {
      max-width: 700px; margin: 0 auto; text-align: center;
    }

    .newsletter-inner h3 {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; font-weight: 700;
      color: var(--espresso); margin-bottom: 10px;
    }

    .newsletter-inner p {
      color: var(--caramel); font-size: 0.9rem; margin-bottom: 24px; font-weight: 300;
    }

    .newsletter-form {
      display: flex; gap: 10px; max-width: 460px; margin: 0 auto;
    }

    .newsletter-form input {
      flex: 1; padding: 12px 20px;
      border: 2px solid var(--mist); border-radius: 50px;
      background: var(--ivory);
      color: var(--espresso); font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem; outline: none; transition: var(--transition);
    }
    .newsletter-form input:focus { border-color: var(--gold); }
    .newsletter-form input::placeholder { color: var(--caramel); opacity: 0.6; }

    .newsletter-form button {
      padding: 12px 26px; border-radius: 50px;
      background: linear-gradient(135deg, var(--espresso), var(--mahogany));
      color: var(--honey); border: none;
      font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.88rem;
      cursor: pointer; white-space: nowrap;
      transition: var(--transition);
    }
    .newsletter-form button:hover { background: var(--gold); color: var(--white); transform: translateY(-1px); }

    /* ===================== BRAND STRIP ===================== */
    .brand-strip {
      padding: 48px 5%;
      background: var(--ivory);
      border-bottom: 1px solid rgba(201,145,42,0.1);
    }

    .brand-strip-inner {
      max-width: 900px; margin: 0 auto;
      display: flex; align-items: center; flex-wrap: wrap;
      gap: 14px; justify-content: center;
    }

    .brand-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(201,145,42,0.08);
      border: 1px solid rgba(201,145,42,0.2);
      color: var(--caramel);
      padding: 10px 20px; border-radius: 50px;
      font-size: 0.8rem; font-weight: 600;
      transition: var(--transition);
    }
    .brand-badge:hover { background: rgba(201,145,42,0.15); color: var(--mahogany); transform: translateY(-2px); }
    .brand-badge i { color: var(--gold); }

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
      .hero-trust { display: none; }
      .steps-grid::before { display: none; }
    }

    @media (max-width: 768px) {
      .navbar { padding: 0 20px; }
      .nav-links a:not(.active):not(:last-child) { display: none; }
      .hero h1 { font-size: 2.6rem; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .stat-item:nth-child(2) { border-right: none; }
      .newsletter-form { flex-direction: column; }
      .newsletter-form button { width: 100%; text-align: center; justify-content: center; display: flex; }
      .slide-cart { width: 92vw; right: -92vw; }
    }

    @media (max-width: 480px) {
      .hero h1 { font-size: 2rem; }
      .hero-ctas { flex-direction: column; }
      .btn-primary-hero, .btn-ghost-hero { width: 100%; justify-content: center; }
      .products-preview-grid { grid-template-columns: 1fr; }
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
    <a href="main.php" class="active">Home</a>
    <a href="shop.php">Shop</a>
    <a href="about.php">About</a>
    <?php
    if (isset($_SESSION['user_id'])) {
      if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo '<a href="admin.php">Admin</a>';
      }
      $check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
      if (!$check_column || $check_column->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
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

<!-- ===================== HERO ===================== -->
<section class="hero">
  <div class="slideshow-container">
    <img class="slide" src="assets/banner/banner1.png" alt="Cat Treats" onerror="this.onerror=null;const alts=['assets/banner/banner1.jfif','assets/banner/banner11.png'];let i=0;const t=()=>{if(i<alts.length){this.src=alts[i++];this.onerror=t;}else{this.style.display='none';}};t();">
    <img class="slide" src="assets/banner/banner2.png" alt="Cat Enjoying Treats" onerror="this.onerror=null;const alts=['assets/banner/banner11.png','assets/banner/banner4.jpg'];let i=0;const t=()=>{if(i<alts.length){this.src=alts[i++];this.onerror=t;}else{this.style.display='none';}};t();">
    <img class="slide" src="assets/banner/banner3.png" alt="Premium Cat Treats" onerror="this.onerror=null;const alts=['assets/banner/banner4.jpg','assets/banner/banner2.png'];let i=0;const t=()=>{if(i<alts.length){this.src=alts[i++];this.onerror=t;}else{this.style.display='none';}};t();">
    <img class="slide" src="assets/banner/banner4.png" alt="Premium Cat Treats" onerror="this.onerror=null;const alts=['assets/banner/banner1.png','assets/banner/banner11.png'];let i=0;const t=()=>{if(i<alts.length){this.src=alts[i++];this.onerror=t;}else{this.style.display='none';}};t();">
  </div>

  <div class="hero-overlay"></div>

  <div class="hero-content">
    <div class="hero-eyebrow"><i class="fas fa-paw"></i> Premium Feline Treats</div>
    <h1>Made for <em>Cats</em>,<br>Loved by All</h1>
    <p class="hero-sub">Natural ingredients, vet-approved recipes, and flavors your cat can't resist — delivered fresh to your door.</p>
    <div class="hero-ctas">
      <a href="shop.php" class="btn-primary-hero"><i class="fas fa-shopping-bag"></i> Shop Now</a>
      <a href="about.php" class="btn-ghost-hero"><i class="fas fa-play-circle"></i> Our Story</a>
    </div>
  </div>

  <!-- Trust pills — right side -->
  <div class="hero-trust">
    <div class="trust-pill">
      <div class="trust-icon"><i class="fas fa-leaf"></i></div>
      <div>
        <div class="trust-text">100% Natural</div>
        <div class="trust-sub">No artificial additives</div>
      </div>
    </div>
    <div class="trust-pill">
      <div class="trust-icon"><i class="fas fa-shield-alt"></i></div>
      <div>
        <div class="trust-text">Vet Approved</div>
        <div class="trust-sub">Nutritionist-formulated</div>
      </div>
    </div>
    <div class="trust-pill">
      <div class="trust-icon"><i class="fas fa-truck"></i></div>
      <div>
        <div class="trust-text">Fast Delivery</div>
        <div class="trust-sub">Ships Philippines-wide</div>
      </div>
    </div>
    <div class="trust-pill">
      <div class="trust-icon"><i class="fas fa-heart"></i></div>
      <div>
        <div class="trust-text">Made with Love</div>
        <div class="trust-sub">Small-batch quality</div>
      </div>
    </div>
  </div>

  <!-- Slide progress + dots -->
  <div class="slide-progress"><div class="slide-progress-fill" id="progressFill"></div></div>
  <div class="slide-dots" id="slideDots"></div>

  <div class="hero-wave"></div>
</section>

<!-- ===================== MARQUEE ===================== -->
<div class="marquee-strip">
  <div class="marquee-track" id="marqueeTrack"></div>
</div>

<!-- ===================== BRAND BADGES ===================== -->
<div class="brand-strip">
  <div class="brand-strip-inner">
    <span class="brand-badge"><i class="fas fa-seedling"></i> All-Natural Ingredients</span>
    <span class="brand-badge"><i class="fas fa-heartbeat"></i> Vet Approved Formula</span>
    <span class="brand-badge"><i class="fas fa-box-open"></i> Small Batch Baked</span>
    <span class="brand-badge"><i class="fas fa-truck"></i> Philippines-Wide Delivery</span>
    <span class="brand-badge"><i class="fas fa-star"></i> 4.9★ Rated</span>
    <span class="brand-badge"><i class="fas fa-undo"></i> 100% Satisfaction Guarantee</span>
    <span class="brand-badge"><i class="fas fa-paw"></i> Feline-Tested Daily</span>
  </div>
</div>

<!-- ===================== FEATURES ===================== -->
<section class="features-section">
  <div class="section-wrap">
    <div class="section-header">
      <span class="section-label">Why Choose Us</span>
      <h2 class="section-title">Why Cats <em>Love</em> Us</h2>
      <p class="section-sub">Every treat starts with a question: would our own cats love this?</p>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon-wrap"><i class="fas fa-seedling feature-icon"></i></div>
        <h3>All-Natural Ingredients</h3>
        <p>Our treats are crafted from 100% natural, human-grade ingredients — carefully chosen to deliver both irresistible flavor and genuine nutrition for your feline companion.</p>
      </div>
      <div class="feature-card" style="transition-delay:0.1s">
        <div class="feature-icon-wrap"><i class="fas fa-heartbeat feature-icon"></i></div>
        <h3>Vet Approved</h3>
        <p>Every recipe is developed alongside veterinary nutritionists to ensure our treats actively support your cat's wellbeing while keeping taste front and center.</p>
      </div>
      <div class="feature-card" style="transition-delay:0.2s">
        <div class="feature-icon-wrap"><i class="fas fa-box-open feature-icon"></i></div>
        <h3>Fresh Delivery</h3>
        <p>Baked in small batches and shipped directly to your door — maximum freshness guaranteed, no preservatives needed, just pure taste that keeps your cat purring.</p>
      </div>
      <div class="feature-card" style="transition-delay:0.3s">
        <div class="feature-icon-wrap"><i class="fas fa-recycle feature-icon"></i></div>
        <h3>Eco-Conscious Packaging</h3>
        <p>Our packaging is 100% recyclable and made from sustainably sourced materials — because we care about the planet your cat's grandkittens will inherit.</p>
      </div>
      <div class="feature-card" style="transition-delay:0.4s">
        <div class="feature-icon-wrap"><i class="fas fa-award feature-icon"></i></div>
        <h3>Award-Winning Recipes</h3>
        <p>Recognized by the Philippine Pet Health Association for excellence in feline nutrition — our formulas set the industry standard for premium cat treats.</p>
      </div>
      <div class="feature-card" style="transition-delay:0.5s">
        <div class="feature-icon-wrap"><i class="fas fa-headset feature-icon"></i></div>
        <h3>24/7 Support</h3>
        <p>Our dedicated cat-loving support team is always just a message away — whether you have questions about ingredients, orders, or kitty nutrition advice.</p>
      </div>
    </div>
  </div>
</section>

<!-- ===================== STATS ===================== -->
<section class="stats-section">
  <div class="section-wrap">
    <div class="stats-grid">
      <div class="stat-item">
        <span class="stat-num" data-target="10" data-suffix="K+">0</span>
        <div class="stat-label">Happy Cats</div>
      </div>
      <div class="stat-item" style="transition-delay:0.1s">
        <span class="stat-num" data-target="50" data-suffix="K+">0</span>
        <div class="stat-label">Treats Sold</div>
      </div>
      <div class="stat-item" style="transition-delay:0.2s">
        <span class="stat-num" data-target="4.9" data-suffix="★" data-decimal="1">0</span>
        <div class="stat-label">Customer Rating</div>
      </div>
      <div class="stat-item" style="transition-delay:0.3s">
        <span class="stat-num" data-static="5">5+</span>
        <div class="stat-label">Years of Excellence</div>
      </div>
      <div class="stat-item" style="transition-delay:0.4s">
        <span class="stat-num" data-target="24" data-suffix="/7">0</span>
        <div class="stat-label">Customer Support</div>
      </div>
    </div>
  </div>
</section>

<!-- ===================== PRODUCTS PREVIEW ===================== -->
<section class="products-preview-section">
  <div class="section-wrap">
    <div class="section-header">
      <span class="section-label">Our Collection</span>
      <h2 class="section-title">Featured <em>Products</em></h2>
      <p class="section-sub">Hand-picked, vet-approved treats and supplies crafted with love.</p>
    </div>
    <div class="products-preview-grid">
      <?php
      // Use featured products if configured, otherwise fall back to newest 3
      $fp_check = $conn->query("SELECT COUNT(*) as cnt FROM featured_products");
      $fp_count = $fp_check ? $fp_check->fetch_assoc()['cnt'] : 0;

      if ($fp_count >= 3) {
          $preview = $conn->prepare("
              SELECT p.* FROM products p
              INNER JOIN featured_products fp ON fp.product_id = p.id
              ORDER BY fp.sort_order ASC
              LIMIT 3
          ");
      } else {
          $preview = $conn->prepare("SELECT * FROM products WHERE stock > 0 ORDER BY id DESC LIMIT 3");
      }
      $preview->execute();
      $prev_result = $preview->get_result();
      while ($row = $prev_result->fetch_assoc()):
        $low_stock = $row['stock'] > 0 && $row['stock'] <= 5;
      ?>
      <div class="product-card" onclick="window.location.href='product.php?id=<?= $row['id'] ?>'">
        <?php if ($low_stock): ?>
          <span class="stock-badge low-stock"><i class="fas fa-fire"></i> Only <?= $row['stock'] ?> left!</span>
        <?php else: ?>
          <span class="stock-badge in-stock"><i class="fas fa-check-circle"></i> In Stock</span>
        <?php endif; ?>

        <div class="card-image">
          <?php if (!empty($row['image']) && file_exists("uploads/".$row['image'])): ?>
            <img loading="lazy" src="uploads/<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
          <?php else: ?>
            <div style="color: var(--mist); text-align:center;">
              <i class="fas fa-image" style="font-size:2.5rem; display:block; margin-bottom:8px;"></i>
              <span style="font-size:0.82rem;">No Image</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="card-body">
          <div class="price-ribbon">₱<?= number_format($row['price'], 2) ?></div>
          <span class="card-category"><i class="fas fa-tag"></i><?= htmlspecialchars($row['category']) ?></span>
          <h5 class="card-title"><?= htmlspecialchars($row['name']) ?></h5>
          <p class="card-desc"><?= htmlspecialchars($row['description'] ?? '') ?></p>
        </div>

        <div class="card-footer-actions">
          <a href="product.php?id=<?= $row['id'] ?>" class="btn-view" onclick="event.stopPropagation();">
            <i class="fas fa-eye"></i> View Product
          </a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>

    <div class="view-all-wrap">
      <a href="shop.php" class="btn-view-all"><i class="fas fa-th-large"></i> View All Products</a>
    </div>
  </div>
</section>

<!-- ===================== HOW IT WORKS ===================== -->
<section class="how-section">
  <div class="section-wrap">
    <div class="section-header">
      <span class="section-label">How It Works</span>
      <h2 class="section-title">From Our Kitchen <em>to Yours</em></h2>
      <p class="section-sub">Simple steps to deliver premium feline joy, straight to your door.</p>
    </div>
    <div class="steps-grid">
      <div class="step-item">
        <div class="step-num">01</div>
        <h3>Browse Our Shop</h3>
        <p>Explore our curated collection of vet-approved treats and supplies tailored for your cat's unique needs and tastes.</p>
      </div>
      <div class="step-item" style="transition-delay:0.1s">
        <div class="step-num">02</div>
        <h3>Add to Cart</h3>
        <p>Select your favourite products and quantities. Mix and match across categories for the perfect feline care bundle.</p>
      </div>
      <div class="step-item" style="transition-delay:0.2s">
        <div class="step-num">03</div>
        <h3>Secure Checkout</h3>
        <p>Complete your order through our safe, encrypted checkout. Multiple payment options available for your convenience.</p>
      </div>
      <div class="step-item" style="transition-delay:0.3s">
        <div class="step-num">04</div>
        <h3>Fresh Delivery</h3>
        <p>Your order is packed fresh and delivered right to your doorstep. Watch your cat's eyes light up at first taste!</p>
      </div>
    </div>
  </div>
</section>

<!-- ===================== NEWSLETTER ===================== -->
<div class="newsletter-strip">
  <div class="newsletter-inner">
    <h3>🐾 Stay in the Loop</h3>
    <p>Get early access to new products, exclusive discounts, and expert cat care tips — straight to your inbox.</p>
    <form class="newsletter-form" onsubmit="handleNewsletter(event)">
      <input type="email" placeholder="your@email.com" required id="nlEmail">
      <button type="submit"><i class="fas fa-paper-plane"></i> Subscribe</button>
    </form>
  </div>
</div>

<!-- ===================== TESTIMONIALS ===================== -->
<section class="testimonials-section">
  <div class="section-wrap">
    <div class="section-header">
      <span class="section-label">Happy Customers</span>
      <h2 class="section-title">What Cat Parents <em>Say</em></h2>
      <p class="section-sub">Over 10,000 satisfied cats and their humans agree — Pawganic is purrfect.</p>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <p class="testimonial-text">My fluffy won't stop meowing for these treats! They're absolutely delicious and made with such obvious care. I'll never switch brands again.</p>
        <div class="testimonial-author">
          <div class="avatar">MS</div>
          <div>
            <div class="author-name">Maria Santos</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Verified Buyer</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card" style="transition-delay:0.1s">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <p class="testimonial-text">My vet approved them on the spot! I love that they use only natural ingredients. Best investment I've ever made for my cat's happiness and health.</p>
        <div class="testimonial-author">
          <div class="avatar">JD</div>
          <div>
            <div class="author-name">Juan Dela Cruz</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Pet Owner</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card" style="transition-delay:0.2s">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <p class="testimonial-text">Finally found treats my picky eater actually loves! The salmon bites are her absolute favorite. And the customer service is genuinely top-notch.</p>
        <div class="testimonial-author">
          <div class="avatar">AR</div>
          <div>
            <div class="author-name">Ana Reyes</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Cat Lover</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card" style="transition-delay:0.3s">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div>
        <p class="testimonial-text">Ordering is a breeze and delivery is lightning fast. My three cats go absolutely crazy every single time the Pawganic box arrives. 10/10 recommend!</p>
        <div class="testimonial-author">
          <div class="avatar">RG</div>
          <div>
            <div class="author-name">Rico Garcia</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Repeat Buyer</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card" style="transition-delay:0.4s">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <p class="testimonial-text">As a vet, I'm very selective about what I recommend. Pawganic consistently exceeds my standards. Pure ingredients, proper nutrition profiles — excellent.</p>
        <div class="testimonial-author">
          <div class="avatar">DV</div>
          <div>
            <div class="author-name">Dr. Diana Villanueva</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Veterinarian</div>
          </div>
        </div>
      </div>
      <div class="testimonial-card" style="transition-delay:0.5s">
        <span class="quote-mark">"</span>
        <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
        <p class="testimonial-text">The eco packaging sealed the deal for me. I feel good about supporting a brand that genuinely cares — about cats AND about the planet. Truly impressed.</p>
        <div class="testimonial-author">
          <div class="avatar">LC</div>
          <div>
            <div class="author-name">Lara Cruz</div>
            <div class="verified"><i class="fas fa-check-circle" style="font-size:9px;"></i> Verified Buyer</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===================== FAQ ===================== -->
<section class="faq-section" id="faq">
  <div class="section-wrap">
    <div class="section-header">
      <span class="section-label">Got Questions?</span>
      <h2 class="section-title">Frequently <em>Asked</em></h2>
      <p class="section-sub">Everything you need to know about Pawganic treats and how we work.</p>
    </div>
    <div class="faq-grid">
      <div class="accordion-wrap">
        <?php
        $faqs = [
          ["fa-shield-alt", "Are your treats safe for all cats?", "Yes! All Pawganic treats are formulated with veterinary nutritionists and made from natural, cat-safe ingredients. However, we always recommend checking with your vet if your cat has specific dietary needs or known allergies."],
          ["fa-box", "How should I store the treats?", "Store treats in a cool, dry place in their original resealable packaging. For maximum freshness, use within 30 days of opening. Our treats contain no artificial preservatives — they're best enjoyed fresh!"],
          ["fa-leaf", "What ingredients do you use?", "We use only premium, human-grade ingredients including free-range chicken, wild-caught tuna, organic vegetables, and natural flavour enhancers. Every ingredient is traceable to its source."],
          ["fa-truck", "Do you offer nationwide shipping?", "Yes — we ship Philippines-wide! Metro Manila orders typically arrive within 1–2 business days. Provincial deliveries take 3–5 business days. Free shipping on orders over ₱999."],
          ["fa-undo", "What if my cat doesn't like the treats?", "We offer a 100% satisfaction guarantee. If your cat isn't thrilled, contact us within 14 days for a full refund or product exchange. Your feline friend's happiness is our top priority."],
          ["fa-calendar", "Can I set up a subscription?", "Absolutely! Our subscription service lets you save up to 15% and never run out of your cat's favourite treats. You can pause, skip, or cancel anytime with no hidden fees."],
        ];
        foreach ($faqs as $faq):
        ?>
        <div class="accordion-item">
          <div class="accordion-header" onclick="toggleAccordion(this)">
            <h3 class="accordion-q">
              <div class="accordion-icon-wrap"><i class="fas fa-plus"></i></div>
              <?= htmlspecialchars($faq[1]) ?>
            </h3>
          </div>
          <div class="accordion-body">
            <p><?= htmlspecialchars($faq[2]) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="cta-section">
  <div class="cta-deco cta-deco-1"></div>
  <div class="cta-deco cta-deco-2"></div>
  <span class="section-label">Ready to Start?</span>
  <h2>Delight Your <em>Feline</em><br>Friend Today</h2>
  <p>Join thousands of happy cat parents who trust Pawganic Supplies for premium, natural treats their cats adore.</p>
  <a href="shop.php" class="btn-cta"><i class="fas fa-paw"></i> Start Shopping Now</a>
</section>

<!-- ===================== FOOTER ===================== -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h3>Pawganic Supplies</h3>
      <p>Since 2020, crafting premium, health-conscious treats by devoted cat lovers to support feline wellness in every single bite.</p>
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
        <a href="#faq">FAQs</a>
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

<button class="scroll-to-top" id="scrollBtn"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Navbar profile dropdown ── */
const pd = document.querySelector('.profile-dropdown');
if (pd) {
  pd.querySelector('.profile-pic').addEventListener('click', e => { e.stopPropagation(); pd.classList.toggle('open'); });
  document.addEventListener('click', e => { if (!pd.contains(e.target)) pd.classList.remove('open'); });
}

/* ── Slideshow ── */
let curSlide = 0;
const slides = document.querySelectorAll('.slide');
const dotsEl  = document.getElementById('slideDots');
const fill    = document.getElementById('progressFill');
let progressInterval;

slides.forEach((_, i) => {
  const d = document.createElement('div');
  d.className = 'slide-dot' + (i === 0 ? ' active' : '');
  d.addEventListener('click', () => goTo(i));
  dotsEl.appendChild(d);
});

function getDots() { return document.querySelectorAll('.slide-dot'); }

function goTo(idx) {
  slides[curSlide].classList.remove('active');
  getDots()[curSlide].classList.remove('active');
  curSlide = idx;
  slides[curSlide].classList.add('active');
  getDots()[curSlide].classList.add('active');
  animateProgress();
}

function animateProgress() {
  clearInterval(progressInterval);
  fill.style.width = '0%';
  let w = 0;
  progressInterval = setInterval(() => {
    w += 100 / (5000 / 100);
    if (w >= 100) { w = 100; clearInterval(progressInterval); }
    fill.style.width = w + '%';
  }, 100);
}

slides[0].classList.add('active');
animateProgress();
setInterval(() => goTo((curSlide + 1) % slides.length), 5000);

/* ── Marquee ── */
const items = ['100% Natural','Vet Approved','Fresh Baked Daily','No Artificial Preservatives','Feline Loved','Ships Philippines-Wide','Small Batch Quality','Satisfaction Guaranteed'];
const track = document.getElementById('marqueeTrack');
[...items, ...items].forEach(t => {
  const s = document.createElement('span');
  s.className = 'marquee-item';
  s.innerHTML = `<i class="fas fa-circle"></i>${t}`;
  track.appendChild(s);
});

/* ── Scroll reveal ── */
const revealObs = new IntersectionObserver(entries => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 70);
      revealObs.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

document.querySelectorAll('.feature-card, .testimonial-card, .stat-item, .step-item, .product-card').forEach(el => revealObs.observe(el));

/* ── Counter animation ── */
const cntObs = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (!entry.isIntersecting) return;
    const el = entry.target;
    if (el.dataset.static) return;
    const target  = parseFloat(el.dataset.target);
    const decimal = parseInt(el.dataset.decimal || '0');
    const suffix  = el.dataset.suffix || '';
    const dur = 1800;
    const step = target / (dur / 16);
    let cur = 0;
    const timer = setInterval(() => {
      cur = Math.min(cur + step, target);
      el.textContent = (decimal > 0 ? cur.toFixed(decimal) : Math.floor(cur)) + suffix;
      if (cur >= target) clearInterval(timer);
    }, 16);
    cntObs.unobserve(el);
  });
}, { threshold: 0.5 });

document.querySelectorAll('.stat-num[data-target]').forEach(el => cntObs.observe(el));

/* ── Accordion ── */
function toggleAccordion(header) {
  const item = header.parentElement;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.accordion-item').forEach(i => i.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

/* ── Scroll to top ── */
const scrollBtn = document.getElementById('scrollBtn');
window.addEventListener('scroll', () => {
  scrollBtn.classList.toggle('show', window.pageYOffset > 400);
});
scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

/* ── Newsletter ── */
function handleNewsletter(e) {
  e.preventDefault();
  const email = document.getElementById('nlEmail').value;
  if (email) {
    const btn = e.target.querySelector('button');
    btn.innerHTML = '<i class="fas fa-check"></i> Subscribed!';
    btn.style.background = 'var(--sage)';
    btn.style.color = 'var(--white)';
    btn.disabled = true;
    document.getElementById('nlEmail').value = '';
    setTimeout(() => {
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Subscribe';
      btn.style.background = '';
      btn.style.color = '';
      btn.disabled = false;
    }, 3000);
  }
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