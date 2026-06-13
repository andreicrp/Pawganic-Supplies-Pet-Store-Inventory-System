<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mail_helper.php';

$message = '';
$messageType = '';
$devResetLink = '';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'user';
    header("Location: " . ($role == "admin" ? "admin.php" : "main.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '', 'email');

        if (empty($email)) {
            $message = 'Please enter your email address.';
            $messageType = 'error';
        } else {
            try {
                // Check if user exists with this email
                $stmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($username);
                    $stmt->fetch();
                    $stmt->close();

                    // Generate reset token and expiry (1 hour)
                    $token = bin2hex(random_bytes(16));
                    $token_hash = hash("sha256", $token);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Remove previous tokens for this email
                    $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $del_stmt->bind_param("s", $email);
                    $del_stmt->execute();
                    $del_stmt->close();

                    // Insert new reset token
                    $ins_stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $ins_stmt->bind_param("sss", $email, $token_hash, $expires_at);
                    if ($ins_stmt->execute()) {
                        $ins_stmt->close();

                        // Build reset link
                        $resetLink = BASE_URL . "/reset_password.php?token=" . $token;

                        // Beautiful HTML email body
                        $emailBody = "
                        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 480px; margin: 0 auto; background-color: #ffffff; padding: 40px 24px; color: #2c1a0e; line-height: 1.6;'>
                            <!-- Header Row -->
                            <table style='width: 100%; border-collapse: collapse; margin-bottom: 40px;'>
                                <tr>
                                    <td style='vertical-align: middle; text-align: left;'>
                                        <h1 style='font-size: 28px; font-weight: bold; color: #2c1a0e; margin: 0; line-height: 1.25; letter-spacing: -0.5px;'>Reset your pawganic<br>password</h1>
                                    </td>
                                    <td style='vertical-align: middle; text-align: right; width: 100px;'>
                                        <img src='cid:pagelogo' alt='Pawganic Supplies' style='height: 48px; width: auto;'>
                                    </td>
                                </tr>
                            </table>

                            <!-- Greeting & Body -->
                            <p style='font-size: 16px; color: #2c1a0e; margin: 0 0 24px 0; text-align: center;'>Hi " . htmlspecialchars($username) . ",</p>
                            <p style='font-size: 15px; color: #5a2d0c; margin: 0 auto 30px auto; max-width: 400px; text-align: center; line-height: 1.6;'>
                                We're sending you this email because you requested a password reset. Click on this link to create a new password:
                            </p>

                            <!-- Button -->
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='" . $resetLink . "' style='background-color: #c9912a; color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 50px; font-weight: bold; display: inline-block; font-size: 16px;'>Set a new password</a>
                            </div>

                            <!-- Disclaimer -->
                            <p style='font-size: 14px; color: #9b6a2f; margin: 30px auto 40px auto; max-width: 420px; text-align: center; line-height: 1.6;'>
                                If you didn't request a password reset, you can ignore this email. Your password will not be changed.
                            </p>

                            <!-- Signature -->
                            <p style='font-size: 15px; font-weight: bold; color: #2c1a0e; margin: 0; text-align: center;'>the pawganic supplies team</p>
                        </div>
                        ";

                        $errorMsg = '';
                        $mailSent = GmailMailer::send($email, "Reset Your Password - Pawganic Supplies", $emailBody, $errorMsg);

                        if ($mailSent) {
                            $message = 'We\'ve sent a password reset link to your email address.';
                            $messageType = 'success';
                        } else {
                            // Fallback developer mode
                            $message = 'We\'ve generated a recovery link for your account.';
                            $messageType = 'success';
                            $devResetLink = $resetLink;
                        }
                    } else {
                        $ins_stmt->close();
                        throw new Exception("Error saving reset token.");
                    }
                } else {
                    $stmt->close();
                    $message = 'No account found with that email address.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                logError("Forgot password error: " . $e->getMessage());
                $message = 'An error occurred. Please try again later.';
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
    <title>Forgot Password — Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400;1,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
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

        .split-container {
            display: flex;
            width: 100vw;
            min-height: 100vh;
        }

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

        .dev-alert-box {
            background: rgba(201, 145, 42, 0.1);
            border: 1px dashed var(--gold);
            border-radius: var(--radius-sm);
            padding: 18px;
            margin-top: 24px;
            text-align: left;
        }
        .dev-alert-title {
            font-weight: 700;
            color: var(--gold);
            font-size: 0.88rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dev-alert-text {
            font-size: 0.84rem;
            color: var(--espresso);
            line-height: 1.5;
        }
        .dev-alert-link {
            color: var(--gold);
            font-weight: 700;
            text-decoration: underline;
        }
        .dev-alert-link:hover {
            color: var(--mahogany);
        }

        @media (max-width: 992px) {
            .hero-panel { display: none !important; }
            .form-panel { flex: 1; padding: 40px 6%; background-color: var(--cream); }
            .form-container {
                max-width: 100%;
                background-color: var(--ivory);
                padding: 40px 30px;
                border-radius: var(--radius);
                box-shadow: var(--shadow-md);
                border: 1px solid rgba(201,145,42,0.12);
            }
        }
    </style>
</head>
<body>

    <!-- Custom Toast Container -->
    <div class="toast-container">
        <div id="toastNotification" class="toast" role="alert" aria-live="polite" aria-atomic="true">
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

    <div class="split-container">
        <!-- Left Side: Hero -->
        <div class="hero-panel">
            <canvas id="particle-canvas"></canvas>
            <div class="hero-deco hero-deco-1"></div>
            <div class="hero-deco hero-deco-2"></div>
            <div class="hero-deco hero-deco-3"></div>
            
            <div class="hero-inner">
                <div></div>
                <div class="hero-body">
                    <div class="hero-label">
                        <i class="fas fa-key"></i> Account Recovery
                    </div>
                    <h1 class="hero-title">Recover Your <em>Account</em></h1>
                    <p class="hero-subtitle">
                        Enter your registered email address and we'll send you a secure link to reset your password and restore access to your account.
                    </p>
                </div>
                <div></div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="form-panel">
            <div class="form-container">
                <a href="/petv10/" class="brand-logo-link animate-fade-in">
                    <img src="/petv10/assets/pagelogo.png" alt="Pawganic Supplies" class="brand-logo">
                </a>
                
                <h2 class="welcome-text animate-fade-in delay-1">Forgot Password</h2>
                <p class="welcome-sub animate-fade-in delay-1">Reset your account credentials securely</p>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                    
                    <div class="form-group animate-fade-in delay-2">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-control" required placeholder="Enter your email address" autocomplete="email">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit animate-fade-in delay-3">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>

                <?php if ($devResetLink): ?>
                    <div class="dev-alert-box animate-fade-in delay-3">
                        <div class="dev-alert-title"><i class="fas fa-tools"></i> Developer Fallback Notice</div>
                        <div class="dev-alert-text">
                            We generated your password reset token successfully! Since real Gmail SMTP is not configured or failed to connect, please use this recovery link to complete testing:
                            <br><br>
                            <a href="<?= $devResetLink ?>" class="dev-alert-link">Reset Password Now &rarr;</a>
                            <br><br>
                            <span style="font-size:0.75rem; color:var(--caramel);">To enable automatic emails, configure <code>SMTP_USER</code> and <code>SMTP_PASS</code> inside <code>config/config.php</code>.</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="divider animate-fade-in delay-3">OR</div>
                
                <a href="login.php" class="btn-register animate-fade-in delay-3">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'error') {
            const toastElement = document.getElementById('toastNotification');
            const toastBody = document.getElementById('toastBody');
            const toastTitle = document.getElementById('toastTitle');
            const toastIcon = document.getElementById('toastIcon');
            
            toastBody.textContent = message;
            toastElement.classList.remove('success', 'error', 'warning');
            toastElement.classList.add(type);
            
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
            
            toastElement.style.display = 'block';
            toastElement.classList.add('show');
            
            setTimeout(() => {
                closeToast();
            }, 6000);
        }

        function closeToast() {
            const toastElement = document.getElementById('toastNotification');
            toastElement.classList.remove('show');
            toastElement.style.display = 'none';
        }

        function initializeToasts() {
            <?php if ($message): ?>
                showToast('<?php echo addslashes($message); ?>', '<?php echo $messageType; ?>');
            <?php endif; ?>
        }
        document.addEventListener('DOMContentLoaded', initializeToasts);

        // Particle Canvas Animation
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('particle-canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                const heroPanel = canvas.parentElement;
                let particles = [];
                let mouse = { x: null, y: null, active: false };

                function resizeCanvas() {
                    canvas.width = heroPanel.offsetWidth;
                    canvas.height = heroPanel.offsetHeight;
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);

                heroPanel.addEventListener('mousemove', (e) => {
                    const rect = heroPanel.getBoundingClientRect();
                    mouse.x = e.clientX - rect.left;
                    mouse.y = e.clientY - rect.top;
                    mouse.active = true;
                });

                heroPanel.addEventListener('mouseleave', () => { mouse.active = false; });

                class Particle {
                    constructor() { this.reset(true); }
                    reset(init = false) {
                        this.x = Math.random() * canvas.width;
                        this.y = init ? Math.random() * canvas.height : canvas.height + Math.random() * 50;
                        this.radius = Math.random() * 4 + 1.2;
                        this.baseSpeedY = -(Math.random() * 0.8 + 0.4);
                        this.speedX = (Math.random() - 0.5) * 0.4;
                        this.color = Math.random() > 0.5 ? '201, 145, 42' : '232, 184, 109';
                        this.alpha = Math.random() * 0.22 + 0.06;
                        this.originalAlpha = this.alpha;
                        this.vx = 0;
                        this.vy = 0;
                    }
                    update() {
                        let targetVx = this.speedX;
                        let targetVy = this.baseSpeedY;
                        if (mouse.active && mouse.x !== null && mouse.y !== null) {
                            const dx = this.x - mouse.x;
                            const dy = this.y - mouse.y;
                            const distance = Math.hypot(dx, dy);
                            const forceRadius = 140;
                            if (distance < forceRadius) {
                                const force = (forceRadius - distance) / forceRadius;
                                const angle = Math.atan2(dy, dx);
                                targetVx += Math.cos(angle) * force * 3.5;
                                targetVy += Math.sin(angle) * force * 3.5;
                                this.alpha = Math.min(this.originalAlpha * 2.2, 0.65);
                            } else {
                                this.alpha += (this.originalAlpha - this.alpha) * 0.05;
                            }
                        } else {
                            this.alpha += (this.originalAlpha - this.alpha) * 0.05;
                        }
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

                for (let i = 0; i < 70; i++) particles.push(new Particle());
                function animate() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    particles.forEach(p => { p.update(); p.draw(); });
                    requestAnimationFrame(animate);
                }
                animate();
            }
        });
    </script>
</body>
</html>
