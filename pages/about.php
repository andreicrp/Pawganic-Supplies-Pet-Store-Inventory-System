<?php
require_once __DIR__ . '/../config/db.php';
// Session is started in db.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>About Us — Pawganic Supplies</title>
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

  /* ===================== ABOUT HERO ===================== */
  .about-hero {
    background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
    padding: 90px 5% 80px;
    position: relative;
    overflow: hidden;
  }

  .about-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.25) 0%, transparent 65%),
                radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.15) 0%, transparent 50%);
  }

  .about-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0; height: 60px;
    background: var(--cream);
    clip-path: ellipse(55% 100% at 50% 100%);
  }

  .hero-deco {
    position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey);
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

  .hero-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2.8rem, 5vw, 4.5rem);
    font-weight: 900; color: var(--white); line-height: 1.1; margin-bottom: 18px;
  }
  .hero-title em { font-style: italic; color: var(--honey); }

  .hero-subtitle {
    color: rgba(255,255,255,0.65); font-size: 1.05rem; line-height: 1.7;
    max-width: 480px; margin-bottom: 32px;
  }

  .hero-badge-group { display: flex; gap: 12px; flex-wrap: wrap; }

  .hero-badge {
    background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.8); padding: 8px 18px; border-radius: 50px;
    font-size: 0.82rem; font-weight: 500; backdrop-filter: blur(8px);
    transition: var(--transition);
  }
  .hero-badge:hover { background: var(--gold); border-color: var(--gold); color: var(--white); }
  .hero-badge i { margin-right: 6px; }

  .hero-stats { display: flex; gap: 36px; }
  .hero-stat { text-align: center; }
  .hero-stat-num {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem; font-weight: 700; color: var(--honey); line-height: 1;
  }
  .hero-stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }

  /* ===================== MAIN CONTENT WRAPPER ===================== */
  .page-body { max-width: 1200px; margin: 0 auto; padding: 0 24px; flex: 1; }

  /* ===================== SECTION TITLES ===================== */
  .section-header { text-align: center; margin-bottom: 56px; }
  .section-label {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(201,145,42,0.1); border: 1px solid rgba(201,145,42,0.25);
    color: var(--caramel); padding: 5px 14px; border-radius: 50px;
    font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
    margin-bottom: 14px;
  }
  .section-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2rem, 3.5vw, 3rem); font-weight: 900; color: var(--espresso);
    line-height: 1.15; margin-bottom: 16px;
  }
  .section-title em { font-style: italic; color: var(--gold); }
  .section-desc { color: var(--caramel); font-size: 1rem; line-height: 1.75; max-width: 560px; margin: 0 auto; }
  .section-divider {
    display: flex; align-items: center; gap: 14px; margin-top: 20px; justify-content: center;
  }
  .section-divider span { height: 1px; width: 60px; background: var(--mist); }
  .section-divider i { color: var(--gold); font-size: 0.9rem; }

  /* ===================== STORY SECTION ===================== */
  .story-section { padding: 88px 0 72px; }

  .story-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center;
  }

  .story-text h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2.4rem; font-weight: 800; color: var(--espresso);
    line-height: 1.2; margin-bottom: 24px;
  }
  .story-text h2 em { font-style: italic; color: var(--gold); }

  .story-text p { color: var(--caramel); font-size: 0.98rem; line-height: 1.85; margin-bottom: 18px; }

  .story-highlight {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    color: var(--honey); padding: 22px 28px; border-radius: var(--radius);
    font-family: 'Playfair Display', serif; font-style: italic; font-size: 1.15rem;
    line-height: 1.6; margin-top: 28px; position: relative;
    box-shadow: var(--shadow-md);
  }
  .story-highlight::before {
    content: '"'; font-size: 4rem; opacity: 0.2; position: absolute; top: -10px; left: 20px;
    font-family: 'Playfair Display', serif; line-height: 1;
  }

  .story-visual {
    position: relative;
  }

  .story-visual-card {
    background: var(--ivory); border-radius: var(--radius);
    padding: 40px; box-shadow: var(--shadow-lg);
    border: 1px solid rgba(201,145,42,0.12);
    position: relative; overflow: hidden;
  }

  .story-visual-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 5px;
    background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey));
  }

  .timeline {
    display: flex; flex-direction: column; gap: 0;
  }

  .timeline-item {
    display: flex; gap: 20px; position: relative; padding-bottom: 32px;
  }
  .timeline-item:last-child { padding-bottom: 0; }

  .timeline-marker {
    display: flex; flex-direction: column; align-items: center; flex-shrink: 0;
  }

  .timeline-dot {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    display: flex; align-items: center; justify-content: center;
    color: var(--honey); font-size: 1rem; flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(44,26,14,0.25); z-index: 1;
  }

  .timeline-line {
    width: 2px; flex: 1; background: linear-gradient(to bottom, var(--gold), var(--mist));
    margin-top: 6px;
  }

  .timeline-content { padding-top: 8px; }
  .timeline-year {
    font-family: 'Playfair Display', serif; font-weight: 700;
    color: var(--gold); font-size: 0.85rem; letter-spacing: 1px; margin-bottom: 4px;
  }
  .timeline-title { font-weight: 700; color: var(--espresso); font-size: 1rem; margin-bottom: 4px; }
  .timeline-desc { font-size: 0.85rem; color: var(--caramel); line-height: 1.6; }

  /* ===================== PHILOSOPHY SECTION ===================== */
  .philosophy-section { padding: 72px 0; }

  .philosophy-cards {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;
  }

  .philosophy-card {
    background: var(--ivory); border-radius: var(--radius);
    padding: 38px 32px; box-shadow: var(--shadow-sm);
    border: 1px solid rgba(201,145,42,0.08);
    transition: var(--transition); position: relative; overflow: hidden;
    cursor: default;
  }

  .philosophy-card::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(to right, var(--caramel), var(--gold));
    transform: scaleX(0); transform-origin: left;
    transition: transform 0.4s ease;
  }

  .philosophy-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }
  .philosophy-card:hover::after { transform: scaleX(1); }

  .philosophy-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, rgba(201,145,42,0.15), rgba(201,145,42,0.05));
    border: 2px solid rgba(201,145,42,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: var(--gold); margin-bottom: 24px;
    transition: var(--transition);
  }
  .philosophy-card:hover .philosophy-icon {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    color: var(--honey); border-color: transparent;
    box-shadow: 0 8px 20px rgba(44,26,14,0.25);
  }

  .philosophy-card h3 {
    font-family: 'Playfair Display', serif; font-size: 1.35rem;
    font-weight: 700; color: var(--espresso); margin-bottom: 14px;
  }
  .philosophy-card p { font-size: 0.9rem; color: var(--caramel); line-height: 1.75; }

  /* ===================== STATS BANNER ===================== */
  .stats-banner {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    border-radius: var(--radius); padding: 56px 48px;
    margin: 16px 0 72px; display: grid;
    grid-template-columns: repeat(4, 1fr); gap: 24px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-lg);
  }

  .stats-banner::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 20%, rgba(201,145,42,0.2) 0%, transparent 60%);
  }

  .stat-item { text-align: center; position: relative; z-index: 1; }

  .stat-item + .stat-item {
    border-left: 1px solid rgba(255,255,255,0.08);
  }

  .stat-num {
    font-family: 'Playfair Display', serif;
    font-size: 3rem; font-weight: 900; color: var(--honey); line-height: 1; margin-bottom: 8px;
    counter-reset: none;
  }
  .stat-label { font-size: 0.82rem; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 1.5px; }

  /* ===================== PROCESS SECTION ===================== */
  .process-section { padding: 72px 0; }

  .process-steps {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    position: relative;
  }

  .process-steps::before {
    content: '';
    position: absolute; top: 40px; left: 10%; right: 10%; height: 2px;
    background: linear-gradient(to right, var(--mist), var(--gold), var(--mist));
    z-index: 0;
  }

  .process-step {
    display: flex; flex-direction: column; align-items: center;
    text-align: center; padding: 0 20px; position: relative; z-index: 1;
  }

  .step-number {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--ivory); border: 3px solid var(--mist);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 24px; transition: var(--transition);
    box-shadow: var(--shadow-sm);
  }

  .step-number span {
    font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900;
    color: var(--mist); transition: var(--transition);
  }

  .process-step:hover .step-number {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    border-color: var(--gold);
    box-shadow: 0 0 0 6px rgba(201,145,42,0.12), var(--shadow-md);
  }
  .process-step:hover .step-number span { color: var(--honey); }

  .step-icon {
    font-size: 1.6rem; color: var(--gold); margin-bottom: 14px;
    transition: var(--transition);
  }
  .process-step:hover .step-icon { transform: scale(1.2); }

  .step-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; font-weight: 700; color: var(--espresso); margin-bottom: 10px; }
  .step-desc { font-size: 0.85rem; color: var(--caramel); line-height: 1.65; }

  /* ===================== TEAM SECTION ===================== */
  .team-section { padding: 72px 0; }

  .team-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }

  .team-card {
    background: var(--ivory); border-radius: var(--radius);
    overflow: hidden; box-shadow: var(--shadow-sm);
    border: 1px solid rgba(201,145,42,0.08);
    transition: var(--transition); text-align: center;
  }
  .team-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }

  .team-card-header {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    padding: 36px 20px 20px; position: relative;
  }

  .team-avatar {
    width: 90px; height: 90px; border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), var(--honey));
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; margin: 0 auto 14px; border: 4px solid rgba(255,255,255,0.2);
    box-shadow: 0 8px 24px rgba(44,26,14,0.3);
  }

  .team-name { font-family: 'Playfair Display', serif; font-size: 1.2rem; font-weight: 700; color: var(--white); }
  .team-role { font-size: 0.78rem; color: var(--honey); font-weight: 600; letter-spacing: 1px; text-transform: uppercase; margin-top: 4px; }

  .team-card-body { padding: 24px; }
  .team-desc { font-size: 0.88rem; color: var(--caramel); line-height: 1.7; }

  .team-skills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 16px; justify-content: center; }
  .team-skill {
    background: rgba(201,145,42,0.1); color: var(--caramel); border: 1px solid rgba(201,145,42,0.2);
    padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600;
  }

  /* ===================== VALUES SECTION ===================== */
  .values-section {
    padding: 72px 0; background: var(--ivory);
    border-radius: var(--radius); margin: 16px 0;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(201,145,42,0.08);
  }

  .values-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; max-width: 900px; margin: 0 auto; }

  .value-item {
    padding: 36px 40px; display: flex; gap: 22px; align-items: flex-start;
    border-bottom: 1px solid var(--mist); transition: var(--transition);
  }
  .value-item:nth-child(odd) { border-right: 1px solid var(--mist); }
  .value-item:nth-last-child(-n+2) { border-bottom: none; }
  .value-item:hover { background: rgba(201,145,42,0.03); }

  .value-icon {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    display: flex; align-items: center; justify-content: center;
    color: var(--honey); font-size: 1.2rem; box-shadow: var(--shadow-sm);
  }

  .value-content h4 { font-family: 'Playfair Display', serif; font-size: 1.15rem; font-weight: 700; color: var(--espresso); margin-bottom: 8px; }
  .value-content p { font-size: 0.87rem; color: var(--caramel); line-height: 1.7; }

  /* ===================== FAQ SECTION ===================== */
  .faq-section { padding: 72px 0 88px; }

  .faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: start; }

  .faq-item {
    background: var(--ivory); border-radius: var(--radius);
    overflow: hidden; box-shadow: var(--shadow-sm);
    border: 1px solid rgba(201,145,42,0.08);
    transition: var(--transition);
  }
  .faq-item.open { border-color: rgba(201,145,42,0.25); box-shadow: var(--shadow-md); }

  .faq-header {
    padding: 22px 26px; cursor: pointer; display: flex;
    align-items: center; justify-content: space-between; gap: 16px;
    transition: var(--transition);
  }
  .faq-item.open .faq-header { background: linear-gradient(135deg, rgba(44,26,14,0.03), rgba(201,145,42,0.05)); }
  .faq-header:hover { background: rgba(201,145,42,0.04); }

  .faq-question { font-weight: 700; color: var(--espresso); font-size: 0.96rem; line-height: 1.4; flex: 1; }

  .faq-icon {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    background: var(--mist); display: flex; align-items: center; justify-content: center;
    color: var(--caramel); font-size: 0.85rem; transition: var(--transition);
  }
  .faq-item.open .faq-icon {
    background: linear-gradient(135deg, var(--espresso), var(--mahogany));
    color: var(--honey); transform: rotate(45deg);
  }

  .faq-body {
    max-height: 0; overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), padding 0.4s ease;
    padding: 0 26px;
  }
  .faq-item.open .faq-body { max-height: 300px; padding: 0 26px 22px; }
  .faq-body p { font-size: 0.89rem; color: var(--caramel); line-height: 1.75; }

  /* ===================== CTA SECTION ===================== */
  .cta-section {
    background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
    border-radius: var(--radius); padding: 72px 48px;
    text-align: center; margin: 0 0 88px;
    position: relative; overflow: hidden;
    box-shadow: var(--shadow-lg);
  }
  .cta-section::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(201,145,42,0.2) 0%, transparent 60%);
  }
  .cta-inner { position: relative; z-index: 1; }
  .cta-section h2 {
    font-family: 'Playfair Display', serif; font-size: 2.6rem; font-weight: 900;
    color: var(--white); margin-bottom: 16px; line-height: 1.2;
  }
  .cta-section h2 em { font-style: italic; color: var(--honey); }
  .cta-section p { color: rgba(255,255,255,0.65); font-size: 1rem; max-width: 500px; margin: 0 auto 36px; line-height: 1.7; }

  .cta-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }

  .cta-btn-primary {
    background: linear-gradient(135deg, var(--gold), var(--honey));
    color: var(--espresso); border: none; border-radius: 50px;
    padding: 16px 36px; font-family: 'DM Sans', sans-serif; font-weight: 700;
    font-size: 1rem; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 6px 20px rgba(201,145,42,0.35); transition: var(--transition);
  }
  .cta-btn-primary:hover {
    transform: translateY(-3px); box-shadow: 0 10px 28px rgba(201,145,42,0.45);
    color: var(--espresso);
  }

  .cta-btn-secondary {
    background: rgba(255,255,255,0.1); color: var(--honey); border: 2px solid rgba(255,255,255,0.25);
    border-radius: 50px; padding: 14px 36px; font-family: 'DM Sans', sans-serif;
    font-weight: 600; font-size: 1rem; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px;
    backdrop-filter: blur(8px); transition: var(--transition);
  }
  .cta-btn-secondary:hover {
    background: rgba(255,255,255,0.18); color: var(--white);
    border-color: rgba(255,255,255,0.5); transform: translateY(-3px);
  }

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

  /* ===================== SCROLL REVEAL ===================== */
  .reveal {
    opacity: 0; transform: translateY(28px);
    transition: opacity 0.65s ease, transform 0.65s cubic-bezier(0.4,0,0.2,1);
  }
  .reveal.visible { opacity: 1; transform: translateY(0); }

  /* ===================== RESPONSIVE ===================== */
  @media (max-width: 900px) {
    .story-grid { grid-template-columns: 1fr; gap: 40px; }
    .philosophy-cards { grid-template-columns: 1fr 1fr; }
    .team-grid { grid-template-columns: 1fr 1fr; }
    .process-steps { grid-template-columns: 1fr 1fr; gap: 40px; }
    .process-steps::before { display: none; }
    .stats-banner { grid-template-columns: 1fr 1fr; }
    .stat-item + .stat-item { border-left: none; }
    .stat-item:nth-child(odd) { border-right: 1px solid rgba(255,255,255,0.08); }
    .faq-grid { grid-template-columns: 1fr; }
    .values-grid { grid-template-columns: 1fr; }
    .value-item:nth-child(odd) { border-right: none; }
    .value-item:nth-last-child(-n+2) { border-bottom: 1px solid var(--mist); }
    .value-item:last-child { border-bottom: none; }
  }

  @media (max-width: 640px) {
    .navbar { padding: 0 20px; }
    .nav-links a:not(.active) { display: none; }
    .philosophy-cards { grid-template-columns: 1fr; }
    .team-grid { grid-template-columns: 1fr; }
    .cta-section { padding: 48px 24px; }
    .hero-stats { display: none; }
    .hero-inner { flex-direction: column; gap: 24px; }
    .about-hero { padding: 60px 24px 60px; }
    .stats-banner { grid-template-columns: 1fr 1fr; padding: 36px 24px; }
    .slide-cart { width: 92vw; right: -92vw; }
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
    <a href="about.php" class="active">About</a>
    <?php
    if (isset($_SESSION['user_id'])) {
      if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo '<a href="admin.php">Admin</a>';
      }
      $check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_pic'");
      if (!$check_column || $check_column->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
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
<section class="about-hero">
  <div class="hero-deco hero-deco-1"></div>
  <div class="hero-deco hero-deco-2"></div>
  <div class="hero-deco hero-deco-3"></div>
  <div class="hero-inner">
    <div>
      <div class="hero-label"><i class="fas fa-paw"></i> OUR STORY</div>
      <h1 class="hero-title">Crafted with <em>Love,</em><br>Built for Cats.</h1>
      <p class="hero-subtitle">From a single homemade recipe to a trusted feline wellness brand — discover the passion and purpose behind every Pawganic product.</p>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-num">2020</div>
          <div class="hero-stat-label">Founded</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num">500+</div>
          <div class="hero-stat-label">Happy Cats</div>
        </div>
        <div class="hero-stat">
          <div class="hero-stat-num">★ 4.9</div>
          <div class="hero-stat-label">Rating</div>
        </div>
      </div>
    </div>
    <div class="hero-badge-group">
      <span class="hero-badge"><i class="fas fa-leaf"></i> 100% Natural</span>
      <span class="hero-badge"><i class="fas fa-shield-alt"></i> Vet Approved</span>
      <span class="hero-badge"><i class="fas fa-award"></i> Award Winning</span>
      <span class="hero-badge"><i class="fas fa-heart"></i> Made with Love</span>
    </div>
  </div>
</section>

<!-- ===================== PAGE BODY ===================== -->
<div class="page-body">

  <!-- STORY SECTION -->
  <section class="story-section">
    <div class="story-grid">
      <div class="story-text reveal">
        <div class="hero-label" style="margin-bottom:18px;"><i class="fas fa-book-open"></i> OUR ORIGIN</div>
        <h2>A Little Treat Led to a <em>Big Mission</em></h2>
        <p>It all began in a small home kitchen in 2020, when our founder Sofia Reyes noticed her beloved cat Miso turning her nose up at every commercial treat on the market. Determined to find something truly healthy and delicious, Sofia began experimenting with natural, human-grade ingredients — and Miso finally purred with delight.</p>
        <p>Word spread quickly among fellow cat parents in the neighborhood. What started as batches baked for friends became a movement. Within months, Pawganic Supplies was officially born — built on the simple belief that cats deserve the same quality of care we give ourselves.</p>
        <p>Today, we operate a dedicated kitchen facility with a team of passionate cat lovers, veterinary nutritionists, and quality specialists all working together to deliver joy in every treat.</p>
        <div class="story-highlight">
          "Every cat deserves to thrive, not just survive. We exist to make that possible — one wholesome treat at a time."
          <div style="margin-top:12px;font-size:0.82rem;opacity:0.7;font-family:'DM Sans',sans-serif;font-style:normal;">— Sofia Reyes, Founder</div>
        </div>
      </div>
      <div class="story-visual reveal">
        <div class="story-visual-card">
          <div class="timeline">
            <div class="timeline-item">
              <div class="timeline-marker">
                <div class="timeline-dot"><i class="fas fa-lightbulb"></i></div>
                <div class="timeline-line"></div>
              </div>
              <div class="timeline-content">
                <div class="timeline-year">2020</div>
                <div class="timeline-title">The First Recipe</div>
                <div class="timeline-desc">Sofia bakes her first batch of tuna-oat treats for Miso. Neighbors request batches immediately.</div>
              </div>
            </div>
            <div class="timeline-item">
              <div class="timeline-marker">
                <div class="timeline-dot"><i class="fas fa-store"></i></div>
                <div class="timeline-line"></div>
              </div>
              <div class="timeline-content">
                <div class="timeline-year">2021</div>
                <div class="timeline-title">Pawganic is Born</div>
                <div class="timeline-desc">Official launch with 3 SKUs and a dedicated e-commerce store. 100 orders in the first month.</div>
              </div>
            </div>
            <div class="timeline-item">
              <div class="timeline-marker">
                <div class="timeline-dot"><i class="fas fa-user-md"></i></div>
                <div class="timeline-line"></div>
              </div>
              <div class="timeline-content">
                <div class="timeline-year">2022</div>
                <div class="timeline-title">Vet Partnership</div>
                <div class="timeline-desc">Partnered with a veterinary nutritionist team to certify all formulations for feline wellness.</div>
              </div>
            </div>
            <div class="timeline-item">
              <div class="timeline-marker">
                <div class="timeline-dot"><i class="fas fa-trophy"></i></div>
              </div>
              <div class="timeline-content">
                <div class="timeline-year">2024</div>
                <div class="timeline-title">Award & Scale</div>
                <div class="timeline-desc">Named Best Pet Brand in PH Consumer Awards. Expanded to a full product line of 20+ items.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STATS BANNER -->
  <div class="stats-banner reveal">
    <div class="stat-item">
      <div class="stat-num" data-target="500">0</div>
      <div class="stat-label">Happy Cat Parents</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="20">0</div>
      <div class="stat-label">Premium Products</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="100">0</div>
      <div class="stat-label">% Natural Ingredients</div>
    </div>
    <div class="stat-item">
      <div class="stat-num" data-target="4">0</div>
      <div class="stat-label">Years of Excellence</div>
    </div>
  </div>

  <!-- PHILOSOPHY SECTION -->
  <section class="philosophy-section">
    <div class="section-header reveal">
      <div class="section-label"><i class="fas fa-star"></i> Our Philosophy</div>
      <h2 class="section-title">Three Pillars of <em>Pawganic</em></h2>
      <p class="section-desc">Everything we do flows from three uncompromising commitments that define who we are and how we work.</p>
      <div class="section-divider"><span></span><i class="fas fa-paw"></i><span></span></div>
    </div>
    <div class="philosophy-cards">
      <div class="philosophy-card reveal">
        <div class="philosophy-icon"><i class="fas fa-gem"></i></div>
        <h3>Uncompromising Quality</h3>
        <p>We source only premium, human-grade ingredients from verified suppliers who share our values. Every batch is quality-checked before it leaves our kitchen — no exceptions, no shortcuts.</p>
      </div>
      <div class="philosophy-card reveal">
        <div class="philosophy-icon"><i class="fas fa-heartbeat"></i></div>
        <h3>Feline-First Health</h3>
        <p>Every formula is developed with certified feline nutritionists to support your cat's specific dietary needs. We go beyond taste — our treats actively contribute to coat, joint, and immune health.</p>
      </div>
      <div class="philosophy-card reveal">
        <div class="philosophy-icon"><i class="fas fa-leaf"></i></div>
        <h3>Sustainable Practices</h3>
        <p>We're committed to minimizing our environmental footprint. From compostable packaging to local ingredient sourcing and zero-waste kitchen practices, sustainability is baked into our DNA.</p>
      </div>
    </div>
  </section>

  <!-- OUR VALUES -->
  <section style="padding: 0 0 72px;">
    <div class="section-header reveal">
      <div class="section-label"><i class="fas fa-seedling"></i> Core Values</div>
      <h2 class="section-title">What We <em>Stand For</em></h2>
      <p class="section-desc">These aren't just words on a wall. They shape every decision we make, every ingredient we choose, and every customer we serve.</p>
      <div class="section-divider"><span></span><i class="fas fa-paw"></i><span></span></div>
    </div>
    <div class="values-section reveal">
      <div class="values-grid">
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-shield-alt"></i></div>
          <div class="value-content">
            <h4>Transparency</h4>
            <p>Full ingredient disclosure on every product. No mystery fillers, no vague "natural flavors." You always know exactly what your cat is eating.</p>
          </div>
        </div>
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-users"></i></div>
          <div class="value-content">
            <h4>Community</h4>
            <p>We're more than a brand — we're a community of cat lovers. From our social channels to local adoption events, we invest in the feline welfare ecosystem.</p>
          </div>
        </div>
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-microscope"></i></div>
          <div class="value-content">
            <h4>Science-Backed</h4>
            <p>Every formulation is reviewed by veterinary nutritionists. We continuously research emerging science in feline health to stay at the cutting edge.</p>
          </div>
        </div>
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-handshake"></i></div>
          <div class="value-content">
            <h4>Integrity</h4>
            <p>We stand behind every product with a 100% satisfaction guarantee. If your cat isn't delighted, neither are we — and we'll make it right, no questions asked.</p>
          </div>
        </div>
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-recycle"></i></div>
          <div class="value-content">
            <h4>Sustainability</h4>
            <p>Eco-friendly packaging, responsible sourcing, and a commitment to reducing our carbon footprint at every step of our supply chain.</p>
          </div>
        </div>
        <div class="value-item">
          <div class="value-icon"><i class="fas fa-star"></i></div>
          <div class="value-content">
            <h4>Excellence</h4>
            <p>We never settle for good enough. From recipe formulation to packaging design and customer service, we relentlessly pursue the highest standard in everything.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- PROCESS SECTION -->
  <section class="process-section">
    <div class="section-header reveal">
      <div class="section-label"><i class="fas fa-cogs"></i> How We Work</div>
      <h2 class="section-title">From Kitchen to <em>Cat Bowl</em></h2>
      <p class="section-desc">A meticulous 4-step process ensures that every Pawganic product meets our gold standard of quality and safety before it reaches your home.</p>
      <div class="section-divider"><span></span><i class="fas fa-paw"></i><span></span></div>
    </div>
    <div class="process-steps reveal">
      <div class="process-step">
        <div class="step-number"><span>01</span></div>
        <div class="step-icon"><i class="fas fa-flask"></i></div>
        <div class="step-title">Formulation</div>
        <div class="step-desc">Our vet nutritionist team develops and refines every recipe with your cat's specific health needs in mind.</div>
      </div>
      <div class="process-step">
        <div class="step-number"><span>02</span></div>
        <div class="step-icon"><i class="fas fa-search"></i></div>
        <div class="step-title">Sourcing</div>
        <div class="step-desc">We select only premium, human-grade ingredients from certified local and international suppliers.</div>
      </div>
      <div class="process-step">
        <div class="step-number"><span>03</span></div>
        <div class="step-icon"><i class="fas fa-fire"></i></div>
        <div class="step-title">Small-Batch Baking</div>
        <div class="step-desc">Every batch is hand-crafted in our dedicated kitchen facility to maximize freshness and quality control.</div>
      </div>
      <div class="process-step">
        <div class="step-number"><span>04</span></div>
        <div class="step-icon"><i class="fas fa-check-double"></i></div>
        <div class="step-title">Quality Testing</div>
        <div class="step-desc">Rigorous sensory, nutritional, and safety tests — including a panel of feline taste-testers! — before any product ships.</div>
      </div>
    </div>
  </section>

  <!-- TEAM SECTION -->
  <section class="team-section">
    <div class="section-header reveal">
      <div class="section-label"><i class="fas fa-users"></i> Our Team</div>
      <h2 class="section-title">The <em>Humans</em> Behind the Paws</h2>
      <p class="section-desc">A passionate, dedicated team united by one goal: making every cat's life a little more delicious.</p>
      <div class="section-divider"><span></span><i class="fas fa-paw"></i><span></span></div>
    </div>
    <div class="team-grid">
      <div class="team-card reveal">
        <div class="team-card-header">
          <div class="team-avatar"><i class="fas fa-user"></i></div>
          <div class="team-name">Sofia Reyes</div>
          <div class="team-role">Founder & CEO</div>
        </div>
        <div class="team-card-body">
          <p class="team-desc">Cat mom to three rescues and the creative force behind every Pawganic recipe. Sofia brings 8+ years of food science and a lifetime of feline obsession to her work.</p>
          <div class="team-skills">
            <span class="team-skill">Recipe Development</span>
            <span class="team-skill">Brand Strategy</span>
            <span class="team-skill">Cat Whispering</span>
          </div>
        </div>
      </div>
      <div class="team-card reveal">
        <div class="team-card-header">
          <div class="team-avatar"><i class="fas fa-stethoscope"></i></div>
          <div class="team-name">Dr. Marco Santos</div>
          <div class="team-role">Head Veterinary Nutritionist</div>
        </div>
        <div class="team-card-body">
          <p class="team-desc">A practicing veterinarian with a specialization in small animal nutrition. Dr. Santos reviews and certifies every formulation to ensure it supports optimal feline health.</p>
          <div class="team-skills">
            <span class="team-skill">Feline Nutrition</span>
            <span class="team-skill">Ingredient Safety</span>
            <span class="team-skill">Wellness Science</span>
          </div>
        </div>
      </div>
      <div class="team-card reveal">
        <div class="team-card-header">
          <div class="team-avatar"><i class="fas fa-box-open"></i></div>
          <div class="team-name">Anya Cruz</div>
          <div class="team-role">Operations & Sustainability</div>
        </div>
        <div class="team-card-body">
          <p class="team-desc">The engine that keeps Pawganic running smoothly. Anya oversees our supply chain, eco-packaging initiatives, and ensures every order arrives perfectly — and on time.</p>
          <div class="team-skills">
            <span class="team-skill">Supply Chain</span>
            <span class="team-skill">Eco Packaging</span>
            <span class="team-skill">Logistics</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ SECTION -->
  <section class="faq-section" id="faq">
    <div class="section-header reveal">
      <div class="section-label"><i class="fas fa-question-circle"></i> FAQ</div>
      <h2 class="section-title">Frequently Asked <em>Questions</em></h2>
      <p class="section-desc">Everything you need to know about Pawganic products, our process, and how we care for your cats.</p>
      <div class="section-divider"><span></span><i class="fas fa-paw"></i><span></span></div>
    </div>
    <div class="faq-grid reveal">
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">Are your treats safe for all cats?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>Yes! All Pawganic treats are formulated with veterinary nutritionists and made from natural, cat-safe ingredients. However, we always recommend checking with your vet if your cat has specific dietary needs, allergies, or chronic conditions.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">How should I store the treats?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>Store treats in a cool, dry place in their original packaging or an airtight container. For maximum freshness, use within 30 days of opening. Our treats contain no artificial preservatives, so they're best enjoyed fresh!</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">What ingredients do you use?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>We use only premium, human-grade ingredients including chicken, turkey, fish, and organic vegetables. All ingredients are sourced from certified suppliers who meet our high standards. Full ingredient lists are printed on every package.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">Do you offer international shipping?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>Currently, we ship within the Philippines. We're actively working on expanding our shipping options to Southeast Asia. Contact our team at meow@pawganic.com for inquiries about international orders.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">What if my cat doesn't like the treats?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>We offer a 100% satisfaction guarantee! If your cat doesn't enjoy our treats within 14 days of purchase, contact us for a full refund or product exchange. Your feline friend's happiness is our absolute priority.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">How often should I give treats to my cat?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>Treats should make up no more than 10% of your cat's daily caloric intake. We recommend 2–5 treats per day depending on the product and your cat's weight. Each packaging includes feeding guidelines tailored to the specific treat.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">Are your products grain-free?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>We offer both grain-free and grain-inclusive options. Our product descriptions clearly indicate which formulations are grain-free. If your cat has a sensitivity, our vet team is happy to advise on the best options via email.</p></div>
      </div>
      <div class="faq-item">
        <div class="faq-header" onclick="toggleFaq(this)">
          <div class="faq-question">Can I visit your kitchen or facility?</div>
          <div class="faq-icon"><i class="fas fa-plus"></i></div>
        </div>
        <div class="faq-body"><p>We occasionally host open kitchen days for our community members — follow us on social media to be the first to know. For media or partnership inquiries about facility visits, please reach out to meow@pawganic.com.</p></div>
      </div>
    </div>
  </section>

  <!-- CTA SECTION -->
  <div class="cta-section reveal">
    <div class="cta-inner">
      <div class="hero-label" style="justify-content:center; margin-bottom:20px;"><i class="fas fa-paw"></i> READY TO TREAT YOUR CAT?</div>
      <h2>Give Your Cat the <em>Best</em></h2>
      <p>Browse our full range of vet-approved, hand-crafted treats and supplies. Your cat will thank you for it.</p>
      <div class="cta-buttons">
        <a href="shop.php" class="cta-btn-primary"><i class="fas fa-store"></i> Shop Now</a>
        <a href="mailto:meow@pawganic.com" class="cta-btn-secondary"><i class="fas fa-envelope"></i> Get in Touch</a>
      </div>
    </div>
  </div>

</div><!-- /page-body -->

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
        <a href="#faq">FAQs</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ===================== PROFILE DROPDOWN ===================== */
document.addEventListener('DOMContentLoaded', function () {
  const profileDropdown = document.querySelector('.profile-dropdown');
  if (profileDropdown) {
    const profilePic = profileDropdown.querySelector('.profile-pic');
    profilePic.addEventListener('click', function (e) {
      e.stopPropagation();
      profileDropdown.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('open');
    });
  }

  /* ===================== SCROLL TO TOP ===================== */
  const btn = document.getElementById('scrollToTopBtn');
  window.addEventListener('scroll', () => { btn.classList.toggle('show', window.pageYOffset > 300); });
  btn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });

  /* ===================== SCROLL REVEAL ===================== */
  const reveals = document.querySelectorAll('.reveal');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => entry.target.classList.add('visible'), i * 80);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  reveals.forEach(el => observer.observe(el));

  /* ===================== COUNTER ANIMATION ===================== */
  const counters = document.querySelectorAll('.stat-num[data-target]');
  const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.dataset.target);
        const suffix = el.dataset.suffix || '';
        let start = 0;
        const duration = 1600;
        const step = target / (duration / 16);
        const timer = setInterval(() => {
          start = Math.min(start + step, target);
          el.textContent = Math.floor(start) + suffix;
          if (start >= target) {
            el.textContent = target + suffix;
            clearInterval(timer);
          }
        }, 16);
        counterObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });
  counters.forEach(el => counterObserver.observe(el));
});

/* ===================== FAQ TOGGLE ===================== */
function toggleFaq(header) {
  const item = header.closest('.faq-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
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