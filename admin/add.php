<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name        = $_POST['name'];
    $description = $_POST['description'] ?? '';
    $category    = $_POST['category'];
    $stock       = intval($_POST['stock']);
    $price       = floatval($_POST['price']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $image_path  = NULL;

    $sale_price   = (!empty($_POST['sale_price']) && floatval($_POST['sale_price']) > 0) ? floatval($_POST['sale_price']) : NULL;
    $badge        = !empty($_POST['badge']) ? trim($_POST['badge']) : NULL;
    $rating       = isset($_POST['rating']) && $_POST['rating'] !== '' ? floatval($_POST['rating']) : 5.00;
    $reviews_count = isset($_POST['reviews_count']) && $_POST['reviews_count'] !== '' ? intval($_POST['reviews_count']) : 0;

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $image          = basename($_FILES['image']['name']);
        $image_tmp      = $_FILES['image']['tmp_name'];
        $imageFileType  = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($imageFileType, $valid_extensions)) {
            die("Invalid image type. Allowed types: jpg, jpeg, png, gif, webp.");
        }

        $upload_path = "uploads/" . $image;
        if (!move_uploaded_file($image_tmp, $upload_path)) {
            echo "Error uploading image.";
            exit();
        }
        $image_path = $image;
    }

    $stmt = $conn->prepare("INSERT INTO products (name, description, category, stock, price, expiry_date, image, sale_price, badge, rating, reviews_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssidssdsdi", $name, $description, $category, $stock, $price, $expiry_date, $image_path, $sale_price, $badge, $rating, $reviews_count);

    if ($stmt->execute()) {
        header("Location: index.php?status=added");
        exit();
    } else {
        echo "Database error: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        --danger:     #c0392b;
        --white:      #ffffff;
        --shadow-sm:  0 2px 12px rgba(44,26,14,0.10);
        --shadow-md:  0 8px 32px rgba(44,26,14,0.16);
        --shadow-lg:  0 24px 64px rgba(44,26,14,0.22);
        --radius:     20px;
        --radius-sm:  12px;
        --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--espresso);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }

    /* ===== Background pattern ===== */
    body::before {
        content: '';
        position: fixed; inset: 0; z-index: 0;
        background:
            radial-gradient(ellipse at 15% 20%, rgba(201,145,42,0.08) 0%, transparent 50%),
            radial-gradient(ellipse at 85% 75%, rgba(90,45,12,0.06) 0%, transparent 50%),
            radial-gradient(ellipse at 50% 50%, rgba(122,158,126,0.04) 0%, transparent 70%);
        pointer-events: none;
    }

    /* ===================== NAVBAR ===================== */
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(253,248,240,0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 0 5%;
        height: 72px;
        position: sticky;
        top: 0; z-index: 1000;
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
        display: flex; align-items: center; gap: 7px;
    }

    .nav-links a:hover { background: var(--gold); color: var(--white); }
    .nav-links a.active {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey);
    }

    /* ===================== PAGE LAYOUT ===================== */
    .page-wrapper {
        position: relative; z-index: 1;
        flex: 1; display: flex; flex-direction: column;
        align-items: center;
        padding: 52px 24px 80px;
    }

    /* ===================== FORM CARD ===================== */
    .form-card {
        background: var(--ivory);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        border: 1px solid rgba(201,145,42,0.12);
        width: 100%; max-width: 760px;
        overflow: hidden;
        animation: cardReveal 0.6s cubic-bezier(0.4,0,0.2,1) both;
    }

    @keyframes cardReveal {
        from { opacity:0; transform: translateY(32px); }
        to   { opacity:1; transform: translateY(0); }
    }

    /* Card Header */
    .card-header {
        background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #7a3a10 100%);
        padding: 40px 44px 38px;
        position: relative; overflow: hidden;
    }

    .card-header::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 90% 50%, rgba(201,145,42,0.22) 0%, transparent 60%);
    }

    /* decorative circles */
    .header-deco {
        position: absolute; border-radius: 50%; opacity: 0.07; background: var(--honey);
    }
    .header-deco-1 { width: 260px; height: 260px; top: -80px; right: -60px; }
    .header-deco-2 { width: 120px; height: 120px; bottom: -30px; left: 5%; }

    .header-inner { position: relative; z-index: 2; }

    .header-label {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(201,145,42,0.18); border: 1px solid rgba(201,145,42,0.38);
        color: var(--honey); padding: 5px 13px; border-radius: 50px;
        font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
        margin-bottom: 14px;
    }

    .header-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.2rem; font-weight: 900;
        color: var(--white); line-height: 1.1; margin-bottom: 8px;
    }

    .header-title em { font-style: italic; color: var(--honey); }

    .header-subtitle {
        color: rgba(255,255,255,0.58); font-size: 0.92rem; line-height: 1.6;
    }

    /* Progress breadcrumb */
    .header-steps {
        display: flex; align-items: center; gap: 0;
        margin-top: 24px;
    }

    .step {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 14px; border-radius: 50px;
        font-size: 0.78rem; font-weight: 600;
        background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.45);
        border: 1px solid rgba(255,255,255,0.1);
        cursor: default; transition: var(--transition);
    }

    .step.active {
        background: rgba(201,145,42,0.25); color: var(--honey);
        border-color: rgba(201,145,42,0.4);
    }

    .step-divider { width: 28px; height: 1px; background: rgba(255,255,255,0.12); }

    /* Card Body */
    .card-body {
        padding: 40px 44px 44px;
    }

    /* ===================== SECTION ===================== */
    .form-section {
        margin-bottom: 36px;
    }

    .section-header {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 22px;
    }

    .section-icon {
        width: 38px; height: 38px; border-radius: 12px;
        background: rgba(201,145,42,0.1); border: 1px solid rgba(201,145,42,0.2);
        display: flex; align-items: center; justify-content: center;
        color: var(--gold); font-size: 0.95rem; flex-shrink: 0;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.15rem; font-weight: 700; color: var(--espresso);
    }

    .section-divider {
        height: 1px; background: linear-gradient(to right, var(--mist), transparent);
        margin-bottom: 22px; margin-top: -6px;
    }

    /* ===================== FORM FIELDS ===================== */
    .field-group {
        margin-bottom: 20px;
    }

    .field-label {
        display: block; font-size: 0.78rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.9px;
        color: var(--caramel); margin-bottom: 8px;
    }

    .field-hint {
        font-size: 0.78rem; color: var(--caramel); opacity: 0.7;
        margin-top: 5px; display: flex; align-items: center; gap: 5px;
    }

    .field-hint i { font-size: 0.72rem; }

    .form-input {
        width: 100%; padding: 13px 16px;
        border: 2px solid var(--mist); border-radius: var(--radius-sm);
        background: var(--cream); color: var(--espresso);
        font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 500;
        transition: var(--transition); outline: none;
        -webkit-appearance: none; appearance: none;
    }

    .form-input:focus {
        border-color: var(--gold); background: var(--ivory);
        box-shadow: 0 0 0 4px rgba(201,145,42,0.12);
    }

    .form-input::placeholder { color: var(--caramel); opacity: 0.5; }

    textarea.form-input {
        resize: vertical; min-height: 100px; line-height: 1.6;
    }

    select.form-input {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%23c9912a' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        padding-right: 44px;
    }

    /* Input with icon prefix */
    .input-with-icon {
        position: relative;
    }

    .input-with-icon .input-icon {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        color: var(--gold); font-size: 0.95rem; pointer-events: none;
        z-index: 2;
    }

    .input-with-icon .form-input {
        padding-left: 44px;
    }

    /* Category pills */
    .category-pills {
        display: flex; flex-wrap: wrap; gap: 10px;
        margin-bottom: 8px;
    }

    .cat-pill {
        display: flex; align-items: center; gap: 7px;
        padding: 10px 18px; border-radius: 50px;
        border: 2px solid var(--mist); background: var(--cream);
        color: var(--caramel); font-family: 'DM Sans', sans-serif;
        font-weight: 600; font-size: 0.84rem; cursor: pointer;
        transition: var(--transition); user-select: none;
        white-space: nowrap;
    }

    .cat-pill:hover { border-color: var(--gold); color: var(--gold); }

    .cat-pill.selected {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        border-color: var(--espresso); color: var(--honey);
        box-shadow: var(--shadow-sm);
    }

    .cat-pill i { font-size: 0.85rem; }

    /* hidden select that syncs with pills */
    #category { display: none; }

    /* ===================== IMAGE UPLOAD ===================== */
    .upload-zone {
        border: 2px dashed var(--mist); border-radius: var(--radius-sm);
        background: var(--cream); padding: 36px 24px;
        text-align: center; cursor: pointer;
        transition: var(--transition); position: relative;
        overflow: hidden;
    }

    .upload-zone:hover, .upload-zone.dragover {
        border-color: var(--gold); background: rgba(201,145,42,0.04);
    }

    .upload-zone input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 5;
    }

    .upload-icon {
        width: 64px; height: 64px; border-radius: 18px;
        background: rgba(201,145,42,0.1); border: 1px solid rgba(201,145,42,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; color: var(--gold);
        margin: 0 auto 16px; transition: var(--transition);
    }

    .upload-zone:hover .upload-icon, .upload-zone.dragover .upload-icon {
        background: rgba(201,145,42,0.18); transform: scale(1.06);
    }

    .upload-title {
        font-family: 'Playfair Display', serif;
        font-size: 1rem; font-weight: 700; color: var(--espresso); margin-bottom: 5px;
    }

    .upload-subtitle {
        font-size: 0.82rem; color: var(--caramel); opacity: 0.7; margin-bottom: 12px;
    }

    .upload-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(201,145,42,0.1); border: 1px solid rgba(201,145,42,0.2);
        color: var(--caramel); padding: 4px 12px; border-radius: 50px;
        font-size: 0.74rem; font-weight: 600;
    }

    /* Preview */
    #imagePreviewWrap {
        display: none; margin-top: 16px;
        background: var(--ivory); border-radius: var(--radius-sm);
        padding: 14px; border: 1px solid rgba(201,145,42,0.15);
        position: relative; z-index: 6;
    }

    #imagePreviewWrap img {
        max-height: 180px; max-width: 100%; border-radius: var(--radius-sm);
        display: block; margin: 0 auto;
        box-shadow: var(--shadow-sm);
    }

    .preview-filename {
        margin-top: 8px; font-size: 0.78rem; color: var(--caramel);
        text-align: center; font-weight: 500;
    }

    .preview-remove {
        position: absolute; top: 10px; right: 10px; z-index: 10;
        background: var(--danger); color: white; border: none;
        width: 26px; height: 26px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 0.7rem; transition: var(--transition);
    }

    .preview-remove:hover { transform: scale(1.1); background: #a02020; }

    /* ===================== FORM GRID ===================== */
    .field-row {
        display: grid; grid-template-columns: 1fr 1fr; gap: 18px;
    }

    @media (max-width: 560px) {
        .field-row { grid-template-columns: 1fr; }
    }

    /* ===================== SUBMIT AREA ===================== */
    .submit-area {
        display: flex; gap: 12px; margin-top: 8px;
    }

    .btn-submit {
        flex: 1; padding: 16px 28px;
        background: linear-gradient(135deg, var(--gold), var(--honey));
        color: var(--espresso); border: none; border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 1rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 10px;
        box-shadow: 0 6px 20px rgba(201,145,42,0.35);
        letter-spacing: 0.3px;
    }

    .btn-submit:hover {
        background: linear-gradient(135deg, var(--espresso), var(--mahogany));
        color: var(--honey); transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(44,26,14,0.25);
    }

    .btn-submit:active { transform: translateY(-1px); }

    .btn-back {
        padding: 16px 24px;
        background: var(--ivory); color: var(--mahogany);
        border: 2px solid var(--mist); border-radius: 50px;
        font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: 0.92rem;
        cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: 8px;
        text-decoration: none; white-space: nowrap;
    }

    .btn-back:hover { background: var(--mist); border-color: var(--caramel); color: var(--espresso); }

    /* ===================== CHAR COUNTER ===================== */
    .char-counter {
        font-size: 0.74rem; color: var(--caramel); opacity: 0.6;
        text-align: right; margin-top: 4px;
    }

    .char-counter.warn { color: var(--danger); opacity: 1; }

    /* ===================== VALIDATION ===================== */
    .form-input.invalid { border-color: var(--danger); }
    .form-input.invalid:focus { box-shadow: 0 0 0 4px rgba(192,57,43,0.12); }
    .error-msg { font-size: 0.78rem; color: var(--danger); margin-top: 5px; display: none; }
    .form-input.invalid ~ .error-msg { display: block; }

    /* ===================== FOOTER ===================== */
    footer {
        background: var(--espresso); color: rgba(255,255,255,0.75);
        padding: 50px 5% 24px; position: relative; z-index: 1;
    }

    footer::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
        background: linear-gradient(to right, var(--caramel), var(--gold), var(--honey), var(--gold), var(--caramel));
    }

    .footer-content {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 36px; margin-bottom: 36px;
        max-width: 1300px; margin-left: auto; margin-right: auto;
    }

    .footer-section h3 { font-family: 'Playfair Display', serif; font-size: 1.05rem; color: var(--honey); margin-bottom: 18px; }
    .footer-section p  { font-size: 0.85rem; line-height: 1.8; margin-bottom: 8px; }

    .footer-links { display: flex; flex-direction: column; gap: 9px; }
    .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; transition: var(--transition); display: flex; align-items: center; gap: 7px; }
    .footer-links a:hover { color: var(--honey); padding-left: 5px; }
    .footer-links a i { width: 13px; color: var(--caramel); }

    .social-links { display: flex; gap: 9px; margin-top: 14px; }
    .social-links a {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(201,145,42,0.12); border: 1px solid rgba(201,145,42,0.22);
        color: var(--honey); display: flex; align-items: center; justify-content: center;
        text-decoration: none; transition: var(--transition); font-size: 0.85rem;
    }
    .social-links a:hover { background: var(--gold); border-color: var(--gold); color: var(--white); transform: translateY(-3px); }

    .copyright {
        border-top: 1px solid rgba(255,255,255,0.07); padding-top: 18px;
        text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.28);
        max-width: 1300px; margin: 0 auto;
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .card-header, .card-body { padding: 28px 24px; }
        .header-title { font-size: 1.8rem; }
        .header-steps { flex-wrap: wrap; gap: 6px; }
        .step-divider { display: none; }
        .submit-area { flex-direction: column; }
        .btn-back { width: 100%; justify-content: center; }
    }
    </style>
</head>
<body>

<!-- ===================== NAVBAR ===================== -->
<nav class="navbar">
    <a href="index.php" style="text-decoration:none;">
        <img src="assets/pagelogo.png" alt="Pawganic Supplies Logo" class="logo-img">
    </a>
    <div class="nav-links">
        <a href="index.php"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="add.php" class="active"><i class="fas fa-plus-circle"></i> Add Product</a>
        <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
    </div>
</nav>

<!-- ===================== PAGE WRAPPER ===================== -->
<div class="page-wrapper">

    <div class="form-card">

        <!-- Card Header -->
        <div class="card-header">
            <div class="header-deco header-deco-1"></div>
            <div class="header-deco header-deco-2"></div>
            <div class="header-inner">
                <div class="header-label"><i class="fas fa-plus-circle"></i> ADMIN · NEW LISTING</div>
                <h1 class="header-title">Add <em>New</em> Product</h1>
                <p class="header-subtitle">Fill in the details below to list a new item in the Pawganic inventory.</p>
                <div class="header-steps">
                    <div class="step active"><i class="fas fa-info-circle"></i> Product Info</div>
                    <div class="step-divider"></div>
                    <div class="step active"><i class="fas fa-warehouse"></i> Inventory</div>
                    <div class="step-divider"></div>
                    <div class="step active"><i class="fas fa-image"></i> Image</div>
                </div>
            </div>
        </div>

        <!-- Card Body / Form -->
        <div class="card-body">
            <form id="addProductForm" method="POST" enctype="multipart/form-data" novalidate>

                <!-- ===== SECTION 1: Product Info ===== -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-tag"></i></div>
                        <div class="section-title">Product Information</div>
                    </div>
                    <div class="section-divider"></div>

                    <div class="field-group">
                        <label class="field-label" for="name">Product Name <span style="color:var(--danger);">*</span></label>
                        <div class="input-with-icon">
                            <i class="input-icon fas fa-pen"></i>
                            <input type="text" id="name" name="name" class="form-input" placeholder="e.g. Organic Salmon Treats 100g" required maxlength="120">
                        </div>
                        <div class="char-counter" id="nameCounter">0 / 120</div>
                        <span class="error-msg">Product name is required.</span>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Category <span style="color:var(--danger);">*</span></label>
                        <div class="category-pills" id="categoryPills">
                            <div class="cat-pill" data-value="Food"><i class="fas fa-bowl-food"></i> Food</div>
                            <div class="cat-pill" data-value="Toys"><i class="fas fa-gamepad"></i> Toys</div>
                            <div class="cat-pill" data-value="Accessories"><i class="fas fa-gem"></i> Accessories</div>
                            <div class="cat-pill" data-value="Medicine"><i class="fas fa-pills"></i> Medicine</div>
                            <div class="cat-pill" data-value="Grooming"><i class="fas fa-scissors"></i> Grooming</div>
                            <div class="cat-pill" data-value="Other"><i class="fas fa-ellipsis"></i> Other</div>
                        </div>
                        <select id="category" name="category" required>
                            <option value="">Select category</option>
                            <option value="Food">Food</option>
                            <option value="Toys">Toys</option>
                            <option value="Accessories">Accessories</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Grooming">Grooming</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="error-msg" id="categoryError">Please select a category.</span>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-input" placeholder="Briefly describe the product — ingredients, benefits, size, etc." maxlength="500"></textarea>
                        <div class="char-counter" id="descCounter">0 / 500</div>
                    </div>
                </div>

                <!-- ===== SECTION 2: Inventory Details ===== -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="section-title">Inventory Details</div>
                    </div>
                    <div class="section-divider"></div>

                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label" for="price">Price (₱) <span style="color:var(--danger);">*</span></label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-peso-sign"></i>
                                <input type="number" id="price" name="price" class="form-input" placeholder="0.00" min="0" step="0.01" required>
                            </div>
                            <span class="error-msg">Enter a valid price.</span>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="stock">Stock Quantity <span style="color:var(--danger);">*</span></label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-cubes"></i>
                                <input type="number" id="stock" name="stock" class="form-input" placeholder="e.g. 50" min="0" required>
                            </div>
                            <span class="error-msg">Enter a valid stock amount.</span>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="expiry_date">Expiry Date</label>
                        <div class="input-with-icon">
                            <i class="input-icon fas fa-calendar-alt"></i>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-input">
                        </div>
                        <p class="field-hint"><i class="fas fa-circle-info"></i> Leave empty if the product has no expiry date.</p>
                    </div>

                    <!-- Stock indicator preview -->
                    <div id="stockPreview" style="display:none; margin-top:4px; padding:12px 16px; background:var(--cream); border-radius:var(--radius-sm); border:1px solid var(--mist);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:7px;">
                            <span style="font-size:0.75rem; font-weight:700; color:var(--caramel); text-transform:uppercase; letter-spacing:0.8px;">Stock Preview</span>
                            <span id="stockPreviewText" style="font-size:0.8rem; font-weight:700;"></span>
                        </div>
                        <div style="height:6px; background:var(--mist); border-radius:10px; overflow:hidden;">
                            <div id="stockPreviewBar" style="height:100%; border-radius:10px; transition:width 0.5s ease, background 0.5s ease;"></div>
                        </div>
                    </div>
                </div>

                <!-- ===== SECTION 3: Product Image ===== -->
                <div class="form-section" style="margin-bottom:28px;">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-image"></i></div>
                        <div class="section-title">Product Image</div>
                    </div>
                    <div class="section-divider"></div>

                    <div class="upload-zone" id="uploadZone">
                        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                        <div class="upload-title">Drop image here or click to browse</div>
                        <div class="upload-subtitle">PNG, JPG, GIF, WEBP up to 5MB</div>
                        <span class="upload-badge"><i class="fas fa-shield-check"></i> Secure upload</span>
                    </div>

                    <div id="imagePreviewWrap">
                        <button type="button" class="preview-remove" id="removeImage" title="Remove image">
                            <i class="fas fa-times"></i>
                        </button>
                        <img id="imagePreview" src="" alt="Preview">
                        <p class="preview-filename" id="previewFilename"></p>
                    </div>
                </div>

                <!-- ===== SECTION 4: Promotional & Social Proof ===== -->
                <div class="form-section" style="margin-bottom:28px;">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-rectangle-ad"></i></div>
                        <div class="section-title">Promotional & Social Proof Details</div>
                    </div>
                    <div class="section-divider"></div>

                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label" for="sale_price">Sale Price (₱)</label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-tag"></i>
                                <input type="number" id="sale_price" name="sale_price" class="form-input" placeholder="0.00" min="0" step="0.01">
                            </div>
                            <p class="field-hint"><i class="fas fa-info-circle"></i> Leave blank if not on sale.</p>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="badge">Product Badge</label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-award"></i>
                                <input type="text" id="badge" name="badge" class="form-input" placeholder="e.g. SALE, NEW, HOT, LIMITED">
                            </div>
                            <p class="field-hint"><i class="fas fa-info-circle"></i> Custom badge to display on product card.</p>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-group">
                            <label class="field-label" for="rating">Initial Rating</label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-star"></i>
                                <input type="number" id="rating" name="rating" class="form-input" placeholder="5.00" min="1" max="5" step="0.01" value="5.00">
                            </div>
                            <p class="field-hint"><i class="fas fa-info-circle"></i> Display rating (1.00 to 5.00).</p>
                        </div>
                        <div class="field-group">
                            <label class="field-label" for="reviews_count">Reviews Count</label>
                            <div class="input-with-icon">
                                <i class="input-icon fas fa-comment-dots"></i>
                                <input type="number" id="reviews_count" name="reviews_count" class="form-input" placeholder="0" min="0" value="0">
                            </div>
                            <p class="field-hint"><i class="fas fa-info-circle"></i> Number of customer reviews to display.</p>
                        </div>
                    </div>
                </div>

                <!-- ===== SUBMIT ===== -->
                <div class="submit-area">
                    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus-circle"></i>
                        Add Product to Inventory
                    </button>
                </div>

            </form>
        </div>
    </div>

</div><!-- /page-wrapper -->

<!-- ===================== FOOTER ===================== -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pawganic Supplies</h3>
            <p>Admin inventory management portal. Keep your store stocked, organized, and ready to delight every customer.</p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Admin Links</h3>
            <div class="footer-links">
                <a href="index.php"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="add.php"><i class="fas fa-plus-circle"></i> Add Product</a>
                <a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="shop.php"><i class="fas fa-store"></i> View Shop</a>
            </div>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <p><i class="fas fa-map-marker-alt" style="color:var(--honey); margin-right:8px;"></i>123 Feline Street, Purrville, PH</p>
            <p><i class="fas fa-phone" style="color:var(--honey); margin-right:8px;"></i>+1 234 567 8900</p>
            <p><i class="fas fa-envelope" style="color:var(--honey); margin-right:8px;"></i>meow@pawganic.com</p>
            <p><i class="fas fa-clock" style="color:var(--honey); margin-right:8px;"></i>Mon–Fri: 9AM–6PM</p>
        </div>
    </div>
    <div class="copyright">
        <p>&copy; <?= date('Y') ?> Pawganic Supplies. All rights reserved. Admin Portal v2.0</p>
    </div>
</footer>

<script>
/* ===== CATEGORY PILLS ===== */
const pills    = document.querySelectorAll('.cat-pill');
const catSelect = document.getElementById('category');
const catError  = document.getElementById('categoryError');

pills.forEach(pill => {
    pill.addEventListener('click', () => {
        pills.forEach(p => p.classList.remove('selected'));
        pill.classList.add('selected');
        catSelect.value = pill.dataset.value;
        catError.style.display = 'none';
        pill.style.animation = 'none';
        requestAnimationFrame(() => {
            pill.style.animation = 'pillPop 0.3s ease';
        });
    });
});

/* ===== CHAR COUNTERS ===== */
function initCounter(inputId, counterId, max) {
    const el  = document.getElementById(inputId);
    const cnt = document.getElementById(counterId);
    if (!el || !cnt) return;
    el.addEventListener('input', () => {
        const len = el.value.length;
        cnt.textContent = `${len} / ${max}`;
        cnt.classList.toggle('warn', len > max * 0.9);
    });
}
initCounter('name', 'nameCounter', 120);
initCounter('description', 'descCounter', 500);

/* ===== STOCK PREVIEW ===== */
const stockInput   = document.getElementById('stock');
const stockPreview = document.getElementById('stockPreview');
const stockBar     = document.getElementById('stockPreviewBar');
const stockText    = document.getElementById('stockPreviewText');

stockInput.addEventListener('input', () => {
    const val = parseInt(stockInput.value) || 0;
    if (val >= 0) {
        stockPreview.style.display = 'block';
        const pct = Math.min(100, (val / 50) * 100);
        let color, label, textColor;
        if (val === 0)      { color = 'linear-gradient(to right,#c0392b,#e07070)'; label = '⚠ Out of Stock'; textColor = '#c0392b'; }
        else if (val <= 5)  { color = 'linear-gradient(to right,#c0392b,#e07070)'; label = '🔥 Low Stock'; textColor = '#c0392b'; }
        else if (val <= 15) { color = 'linear-gradient(to right,#e9a320,#f5d078)'; label = '⚡ Medium Stock'; textColor = '#c9912a'; }
        else                { color = 'linear-gradient(to right,#7a9e7e,#b5ceb8)'; label = '✓ Good Stock'; textColor = '#7a9e7e'; }
        stockBar.style.width = pct + '%';
        stockBar.style.background = color;
        stockText.textContent = `${val} units — ${label.split(' ').slice(1).join(' ')}`;
        stockText.style.color = textColor;
    } else {
        stockPreview.style.display = 'none';
    }
});

/* ===== IMAGE UPLOAD ===== */
const uploadZone   = document.getElementById('uploadZone');
const imageInput   = document.getElementById('image');
const previewWrap  = document.getElementById('imagePreviewWrap');
const previewImg   = document.getElementById('imagePreview');
const previewName  = document.getElementById('previewFilename');
const removeBtn    = document.getElementById('removeImage');

function handleFile(file) {
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('File size exceeds 5MB. Please choose a smaller image.');
        imageInput.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        previewImg.src = e.target.result;
        previewName.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        previewWrap.style.display = 'block';
        uploadZone.style.borderColor = 'var(--sage)';
        uploadZone.querySelector('.upload-icon').innerHTML = '<i class="fas fa-check-circle" style="color:var(--sage);"></i>';
    };
    reader.readAsDataURL(file);
}

imageInput.addEventListener('change', () => handleFile(imageInput.files[0]));

// Drag-and-drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(dt.files[0]);
        imageInput.files = dataTransfer.files;
        handleFile(dt.files[0]);
    }
});

removeBtn.addEventListener('click', e => {
    e.stopPropagation();
    imageInput.value = '';
    previewWrap.style.display = 'none';
    previewImg.src = '';
    uploadZone.style.borderColor = '';
    uploadZone.querySelector('.upload-icon').innerHTML = '<i class="fas fa-cloud-arrow-up"></i>';
});

/* ===== FORM VALIDATION ===== */
document.getElementById('addProductForm').addEventListener('submit', function(e) {
    let valid = true;

    // Name
    const nameEl = document.getElementById('name');
    if (!nameEl.value.trim()) { nameEl.classList.add('invalid'); valid = false; }
    else nameEl.classList.remove('invalid');

    // Category
    if (!catSelect.value) {
        catError.style.display = 'block';
        valid = false;
    } else {
        catError.style.display = 'none';
    }

    // Price
    const priceEl = document.getElementById('price');
    if (!priceEl.value || parseFloat(priceEl.value) < 0) { priceEl.classList.add('invalid'); valid = false; }
    else priceEl.classList.remove('invalid');

    // Stock
    const stockEl = document.getElementById('stock');
    if (!stockEl.value || parseInt(stockEl.value) < 0) { stockEl.classList.add('invalid'); valid = false; }
    else stockEl.classList.remove('invalid');

    if (!valid) e.preventDefault();
});

// Clear invalid on input
document.querySelectorAll('.form-input').forEach(el => {
    el.addEventListener('input', () => el.classList.remove('invalid'));
});
</script>
</body>
</html>