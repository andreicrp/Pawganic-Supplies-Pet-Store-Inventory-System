<?php
require_once __DIR__ . '/../config/db.php';

$message = '';
$messageType = '';

// Check for social login callback simulation
if (isset($_GET['social'])) {
    $social_provider = sanitizeInput($_GET['social'], 'text');
    if ($social_provider === 'google' || $social_provider === 'facebook') {
        try {
            // Find a valid account to simulate social login (user1 -> first user in db)
            $username = '';
            $id = 0;
            $role = '';
            $balance = 0;
            $profile_pic = '';
            
            // Try to fetch user1
            $stmt = $conn->prepare("SELECT id, username, role, balance, profile_pic FROM users WHERE username=?");
            $test_username = 'user1';
            $stmt->bind_param("s", $test_username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $username, $role, $balance, $profile_pic);
                $stmt->fetch();
            }
            $stmt->close();
            
            // Fallback to first user in database if still empty
            if (empty($username)) {
                $res = $conn->query("SELECT id, username, role, balance, profile_pic FROM users LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $id = $row['id'];
                    $username = $row['username'];
                    $role = $row['role'];
                    $balance = $row['balance'];
                    $profile_pic = $row['profile_pic'];
                }
            }
            
            if (!empty($username)) {
                // Establish session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['balance'] = $balance;
                $_SESSION['profile_pic'] = $profile_pic ?? 'images/profile.jpg';
                $_SESSION['login_time'] = time();
                
                $message = 'Successfully logged in using ' . ucfirst($social_provider) . '!';
                $messageType = 'success';
                
                header("Location: " . ($role == "admin" ? "admin.php" : "main.php"));
                exit;
            } else {
                $message = 'No user accounts exist in the database for social login simulation.';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            logError("Social login simulation error: " . $e->getMessage());
            $message = 'Social login error. Please try again.';
            $messageType = 'error';
        }
    }
}

// Check for session timeout message
if (isset($_GET['timeout'])) {
    $message = 'Your session has expired. Please log in again.';
    $messageType = 'warning';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        // Sanitize inputs
        $username = sanitizeInput($_POST['username'] ?? '', 'text');
        $password = trim($_POST['password'] ?? '');
        
        // Basic validation
        if (empty($username) || empty($password)) {
            $message = 'Username and password are required.';
            $messageType = 'error';
        } else if (!checkLoginAttempts($username)) {
            // Rate limiting: block if too many failed attempts
            $message = 'Too many login attempts. Please try again in 15 minutes.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $conn->prepare("SELECT id, password, role, balance, profile_pic FROM users WHERE username=?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($id, $hashed_password, $role, $balance, $profile_pic);
                    $stmt->fetch();

                    if (password_verify($password, $hashed_password)) {
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $id;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = $role;
                        $_SESSION['balance'] = $balance;
                        $_SESSION['profile_pic'] = $profile_pic ?? 'images/profile.jpg';
                        $_SESSION['login_time'] = time();
                        
                        // Clear failed login attempts on successful login
                        resetLoginAttempts($username);
                        
                        header("Location: " . ($role == "admin" ? "admin.php" : "main.php"));
                        exit;
                    } else {
                        // Track failed login attempt
                        incrementLoginAttempts($username);
                        $message = 'Invalid password!';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'No account found! Click Register to create one.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                logError("Login error: " . $e->getMessage());
                $message = 'Login error. Please try again.';
                $messageType = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ===================== DESIGN VARIABLES ===================== */
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

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--cream);
            font-family: 'DM Sans', sans-serif;
            color: var(--espresso);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ===================== LAYOUT ===================== */
        .split-container {
            display: flex;
            width: 100vw;
            min-height: 100vh;
        }

        /* Left side - Decorative Hero */
        .hero-panel {
            flex: 1.2;
            background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 60%, #8b4513 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 60px 6%;
            position: relative;
            overflow: hidden;
            color: var(--white);
        }

        #particle-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .hero-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 75% 50%, rgba(201,145,42,0.22) 0%, transparent 65%),
                        radial-gradient(ellipse at 10% 80%, rgba(122,158,126,0.12) 0%, transparent 50%);
            z-index: 1;
        }

        /* Floating decorative circles */
        .hero-deco {
            position: absolute;
            border-radius: 50%;
            background: var(--honey);
            opacity: 0.06;
            z-index: 1;
            pointer-events: none;
            animation: float 8s ease-in-out infinite alternate;
        }
        .hero-deco-1 { width: 380px; height: 380px; top: -100px; right: -80px; }
        .hero-deco-2 { width: 220px; height: 220px; bottom: 20px; left: 5%; animation-delay: -2s; }
        .hero-deco-3 { width: 120px; height: 120px; top: 30%; left: 30%; opacity: 0.04; animation-delay: -4s; }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-15px) scale(1.03); }
        }

        .hero-inner {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .hero-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hero-logo-img {
            height: 38px;
            width: auto;
        }

        .hero-body {
            max-width: 520px;
            margin: auto 0;
        }

        .hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(201,145,42,0.20);
            border: 1px solid rgba(201,145,42,0.40);
            color: var(--honey);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2rem, 3.5vw, 3.5rem);
            font-weight: 900;
            line-height: 1.15;
            color: var(--white);
            margin-bottom: 20px;
        }

        .hero-title em {
            font-style: italic;
            color: var(--honey);
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        /* Glassmorphic badges */
        .badge-list {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .glass-badge {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(44,26,14,0.15);
        }

        .glass-badge i {
            color: var(--honey);
        }

        /* Stats */
        .hero-stats {
            display: flex;
            gap: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--honey);
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 4px;
        }

        /* Right side - Form Panel */
        .form-panel {
            flex: 1;
            background: var(--ivory);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 8%;
            position: relative;
        }

        .form-container {
            max-width: 400px;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .brand-logo-link {
            align-self: center;
            margin-bottom: 24px;
        }

        .brand-logo {
            height: 54px;
            width: auto;
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.04);
        }

        .welcome-text {
            font-family: 'Playfair Display', serif;
            font-size: 2.1rem;
            color: var(--espresso);
            font-weight: 700;
            margin-bottom: 6px;
            text-align: center;
        }

        .welcome-sub {
            font-size: 0.92rem;
            color: var(--caramel);
            margin-bottom: 32px;
            text-align: center;
            font-weight: 400;
        }

        /* ===================== FORM ELEMENTS ===================== */
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--espresso);
            margin-bottom: 8px;
            letter-spacing: 0.3px;
            transition: var(--transition);
        }

        .form-group:focus-within .form-label {
            color: var(--gold);
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            background-color: var(--cream);
            border: 1px solid var(--mist);
            color: var(--espresso);
            border-radius: 50px;
            padding: 13px 20px 13px 48px;
            font-size: 0.95rem;
            font-family: 'DM Sans', sans-serif;
            width: 100%;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--gold);
            background-color: var(--white);
            box-shadow: 0 0 0 4px rgba(201, 145, 42, 0.15);
            outline: none;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--caramel);
            font-size: 1.05rem;
            transition: var(--transition);
            pointer-events: none;
        }

        .form-group:focus-within .input-icon {
            color: var(--gold);
        }

        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--caramel);
            cursor: pointer;
            font-size: 1.05rem;
            padding: 5px;
            transition: var(--transition);
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--gold);
        }

        /* Buttons & Actions */
        .btn-submit {
            background: linear-gradient(135deg, var(--espresso) 0%, var(--mahogany) 100%);
            border: none;
            color: var(--honey);
            font-weight: 600;
            padding: 14px 30px;
            font-size: 0.98rem;
            border-radius: 50px;
            width: 100%;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background: linear-gradient(135deg, var(--mahogany) 0%, var(--espresso) 100%);
            color: var(--white);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            color: var(--caramel);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(201,145,42,0.2);
        }

        .divider::before { margin-right: 15px; }
        .divider::after { margin-left: 15px; }

        .btn-register {
            background-color: transparent;
            border: 2px solid var(--espresso);
            color: var(--espresso);
            font-weight: 600;
            padding: 13px 30px;
            font-size: 0.95rem;
            border-radius: 50px;
            width: 100%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-register:hover {
            background-color: var(--espresso);
            color: var(--honey);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .btn-register:active {
            transform: translateY(1px);
        }

        /* Animations */
        .animate-fade-in {
            opacity: 0;
            animation: fadeIn 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===================== CUSTOM TOASTS ===================== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
        }

        #toastNotification {
            background: linear-gradient(135deg, var(--espresso) 0%, #1e1108 100%);
            border: 1px solid rgba(201, 145, 42, 0.35);
            border-radius: 14px;
            box-shadow: var(--shadow-lg);
            min-width: 320px;
            display: none;
            animation: slideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        #toastNotification.show {
            display: block !important;
        }

        #toastNotification.hide {
            display: none !important;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .toast-content {
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .toast-icon {
            font-size: 1.3rem;
            margin-top: 1px;
        }

        #toastNotification.success .toast-icon { color: var(--sage); }
        #toastNotification.error .toast-icon { color: var(--danger); }
        #toastNotification.warning .toast-icon { color: var(--gold); }

        .toast-text {
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            color: var(--white);
            font-size: 0.95rem;
            margin-bottom: 3px;
        }

        .toast-desc {
            color: var(--cream);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .toast-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.45);
            cursor: pointer;
            font-size: 1rem;
            padding: 2px;
            transition: var(--transition);
            margin-top: -3px;
            margin-right: -6px;
        }

        .toast-close:hover {
            color: var(--white);
        }

        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            .hero-panel {
                display: none !important;
            }
            .form-panel {
                flex: 1;
                padding: 40px 6%;
                background-color: var(--cream);
            }
            .form-container {
                max-width: 100%;
                background-color: var(--ivory);
                padding: 40px 30px;
                border-radius: var(--radius);
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(201,145,42,0.12);
            }
        }

        /* ===================== SOCIAL LOGIN STYLES ===================== */
        .social-login-group {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            width: 100%;
        }
        .btn-social {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid var(--mist);
            background-color: var(--cream);
            color: var(--espresso);
        }
        .btn-social:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        .btn-google:hover {
            background-color: #ffffff;
            color: #df4a32;
            border-color: #df4a32;
        }
        .btn-facebook:hover {
            background-color: #ffffff;
            color: #3b5998;
            border-color: #3b5998;
        }
    </style>
</head>
<body>

    <!-- Custom Toast Container -->
    <div class="toast-container">
        <div id="toastNotification" class="toast hide" role="alert" aria-live="polite" aria-atomic="true">
            <div class="toast-content">
                <div class="toast-icon">
                    <i class="fas fa-exclamation-circle" id="toastIcon"></i>
                </div>
                <div class="toast-text">
                    <div class="toast-title" id="toastTitle">Error!</div>
                    <div class="toast-desc" id="toastBody"></div>
                </div>
                <button type="button" class="toast-close" onclick="closeToast()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Main View Split Screen -->
    <div class="split-container">
        
        <!-- Left Side: Luxury Hero -->
        <div class="hero-panel">
            <canvas id="particle-canvas"></canvas>
            <!-- Decorative Elements -->
            <div class="hero-deco hero-deco-1"></div>
            <div class="hero-deco hero-deco-2"></div>
            <div class="hero-deco hero-deco-3"></div>
            
            <div class="hero-inner">
                <!-- Branding -->
                <div class="hero-header">
                </div>
                
                <!-- Center Hero Message -->
                <div class="hero-body">
                    <div class="hero-label">
                        <i class="fas fa-paw"></i> Premium Pet Care
                    </div>
                    <h1 class="hero-title">Elevate Your Pet's <em>Wellness</em></h1>
                    <p class="hero-subtitle">
                        Experience the finest organic ingredients, vetted by experts and crafted with love for your loyal companion.
                    </p>
                    
                    <!-- Badges -->
                    <div class="badge-list">
                        <div class="glass-badge">
                            <i class="fas fa-leaf"></i> 100% Organic
                        </div>
                        <div class="glass-badge">
                            <i class="fas fa-user-md"></i> Vet Approved
                        </div>
                        <div class="glass-badge">
                            <i class="fas fa-award"></i> Premium Quality
                        </div>
                    </div>
                </div>
                
                <!-- Bottom Stats -->
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-num">3+</span>
                        <span class="stat-label">Luxury Products</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-num">100%</span>
                        <span class="stat-label">Organic Certified</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-num">4.9★</span>
                        <span class="stat-label">Customer Rating</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="form-panel">
            <div class="form-container">
                
                <a href="/petv10/" class="brand-logo-link animate-fade-in">
                    <img src="/petv10/assets/pagelogo.png" alt="Pawganic Supplies" class="brand-logo">
                </a>
                
                <h2 class="welcome-text animate-fade-in delay-1">Welcome Back</h2>
                <p class="welcome-sub animate-fade-in delay-1">Sign in to access your premium pet supplies</p>
                
                <form method="POST">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                    
                    <div class="form-group animate-fade-in delay-2">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="username" name="username" class="form-control" required placeholder="Enter your username" autocomplete="username">
                        </div>
                    </div>
                    
                    <div class="form-group animate-fade-in delay-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label">Password</label>
                            <a href="forgot_password.php" style="font-size:0.78rem; color:var(--caramel); text-decoration:none; font-weight:600; margin-bottom:8px; transition:var(--transition);" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='var(--caramel)'">Forgot Password?</a>
                        </div>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password" autocomplete="current-password">
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit animate-fade-in delay-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="divider animate-fade-in delay-3">OR SIGN IN WITH</div>
                
                <div class="social-login-group animate-fade-in delay-3">
                    <a href="mock_oauth.php?provider=google&action=login" class="btn-social btn-google">
                        <i class="fab fa-google"></i> Google
                    </a>
                    <a href="mock_oauth.php?provider=facebook&action=login" class="btn-social btn-facebook">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                </div>
                
                <div class="divider animate-fade-in delay-3">OR</div>
                
                <a href="register.php" class="btn-register animate-fade-in delay-3">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
                
            </div>
        </div>
        
    </div>

    <!-- Scripts -->
    <script>
        // Custom Toast Notification function
        function showToast(message, type = 'error') {
            const toastElement = document.getElementById('toastNotification');
            const toastBody = document.getElementById('toastBody');
            const toastTitle = document.getElementById('toastTitle');
            const toastIcon = document.getElementById('toastIcon');
            
            // Set message
            toastBody.textContent = message;
            
            // Remove previous classes
            toastElement.classList.remove('success', 'error', 'warning');
            toastElement.classList.add(type);
            
            // Set title & icon based on type
            if (type === 'success') {
                toastTitle.textContent = 'Success!';
                toastIcon.className = 'fas fa-check-circle';
            } else if (type === 'warning') {
                toastTitle.textContent = 'Warning!';
                toastIcon.className = 'fas fa-exclamation-triangle';
            } else {
                toastTitle.textContent = 'Error!';
                toastIcon.className = 'fas fa-exclamation-circle';
            }
            
            // Show toast element
            toastElement.classList.remove('hide');
            toastElement.classList.add('show');
            
            // Use Bootstrap Toast if available, otherwise fallback
            if (typeof bootstrap !== 'undefined') {
                const toast = new bootstrap.Toast(toastElement, {
                    delay: 4000
                });
                toast.show();
            } else {
                setTimeout(() => {
                    closeToast();
                }, 4000);
            }
        }

        function closeToast() {
            const toastElement = document.getElementById('toastNotification');
            toastElement.classList.remove('show');
            toastElement.classList.add('hide');
            if (typeof bootstrap !== 'undefined') {
                const toast = bootstrap.Toast.getInstance(toastElement);
                if (toast) toast.hide();
            }
        }

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });

        // Load Bootstrap first, then show toast messages
        function initializeToasts() {
            <?php if ($message): ?>
                showToast('<?php echo addslashes($message); ?>', '<?php echo $messageType; ?>');
            <?php endif; ?>
        }

        // Initialize after Bootstrap loads
        if (typeof bootstrap !== 'undefined') {
            initializeToasts();
        } else {
            document.addEventListener('DOMContentLoaded', initializeToasts);
        }

        // Prevent double form submission
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = this.querySelector('.btn-submit');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            }
        });

        // ===================== INTERACTIVE HOVER EFFECTS =====================
        document.addEventListener('DOMContentLoaded', () => {
            // --- 1. Canvas Particle Engine (Antigravity & Mouse Repulsion) ---
            const canvas = document.getElementById('particle-canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                const heroPanel = canvas.parentElement;
                let particles = [];
                let mouse = { x: null, y: null, active: false };

                // Set canvas size
                function resizeCanvas() {
                    canvas.width = heroPanel.offsetWidth;
                    canvas.height = heroPanel.offsetHeight;
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);

                // Track mouse position on the hero panel
                heroPanel.addEventListener('mousemove', (e) => {
                    const rect = heroPanel.getBoundingClientRect();
                    mouse.x = e.clientX - rect.left;
                    mouse.y = e.clientY - rect.top;
                    mouse.active = true;
                });

                heroPanel.addEventListener('mouseleave', () => {
                    mouse.active = false;
                });

                // Particle Class
                class Particle {
                    constructor() {
                        this.reset(true);
                    }

                    reset(init = false) {
                        this.x = Math.random() * canvas.width;
                        this.y = init ? Math.random() * canvas.height : canvas.height + Math.random() * 50;
                        this.radius = Math.random() * 4 + 1.2; // 1.2px to 5.2px
                        this.baseSpeedY = -(Math.random() * 0.8 + 0.4); // upward speed
                        this.speedX = (Math.random() - 0.5) * 0.4;
                        this.color = Math.random() > 0.5 ? '201, 145, 42' : '232, 184, 109'; // Gold or Honey
                        this.alpha = Math.random() * 0.22 + 0.06; // 0.06 to 0.28 opacity
                        this.originalAlpha = this.alpha;
                        this.vx = 0;
                        this.vy = 0;
                    }

                    update() {
                        let targetVx = this.speedX;
                        let targetVy = this.baseSpeedY;

                        // Mouse repulsion
                        if (mouse.active && mouse.x !== null && mouse.y !== null) {
                            const dx = this.x - mouse.x;
                            const dy = this.y - mouse.y;
                            const distance = Math.hypot(dx, dy);
                            const forceRadius = 140;

                            if (distance < forceRadius) {
                                const force = (forceRadius - distance) / forceRadius; // 0 to 1
                                const angle = Math.atan2(dy, dx);
                                const pushX = Math.cos(angle) * force * 3.5;
                                const pushY = Math.sin(angle) * force * 3.5;
                                
                                targetVx += pushX;
                                targetVy += pushY;
                                
                                this.alpha = Math.min(this.originalAlpha * 2.2, 0.65);
                            } else {
                                this.alpha += (this.originalAlpha - this.alpha) * 0.05;
                            }
                        } else {
                            this.alpha += (this.originalAlpha - this.alpha) * 0.05;
                        }

                        // Ease values
                        this.vx += (targetVx - this.vx) * 0.08;
                        this.vy += (targetVy - this.vy) * 0.08;

                        this.x += this.vx;
                        this.y += this.vy;

                        if (this.y < -10 || this.x < -10 || this.x > canvas.width + 10) {
                            this.reset(false);
                        }
                    }

                    draw() {
                        ctx.beginPath();
                        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                        ctx.fillStyle = `rgba(${this.color}, ${this.alpha})`;
                        ctx.fill();
                    }
                }

                // Initialize particles
                const particleCount = 70;
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle());
                }

                // Animation loop
                function animate() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    particles.forEach(p => {
                        p.update();
                        p.draw();
                    });
                    requestAnimationFrame(animate);
                }
                animate();
            }

        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

