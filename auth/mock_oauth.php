<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

$provider = sanitizeInput($_GET['provider'] ?? 'google', 'text');
$action = sanitizeInput($_GET['action'] ?? 'login', 'text');


$targetUrl = ($action === 'signup') ? 'register.php' : 'login.php';
$targetUrl .= '?social=' . $provider;

$brandName = "Pawganic Supplies";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($provider === 'google') ? 'Sign in with Google' : 'Log in with Facebook'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        /* ===================== GOOGLE THEME ===================== */
        .google-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 0 2px rgba(0,0,0,0.05);
            max-width: 450px;
            width: 100%;
            padding: 40px;
            box-sizing: border-box;
            border: 1px solid #dadce0;
            font-family: 'Roboto', sans-serif;
        }

        .google-logo {
            width: 75px;
            height: auto;
            margin-bottom: 16px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .google-title {
            font-size: 24px;
            font-weight: 400;
            color: #202124;
            text-align: center;
            margin-bottom: 8px;
        }

        .google-subtitle {
            font-size: 16px;
            color: #202124;
            text-align: center;
            margin-bottom: 28px;
        }

        .google-subtitle span {
            color: #1a73e8;
            font-weight: 500;
        }

        .google-account-list {
            border-top: 1px solid #dadce0;
            margin-bottom: 24px;
        }

        .google-account-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #dadce0;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
        }

        .google-account-row:hover {
            background-color: #f8f9fa;
        }

        .google-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #c9912a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 14px;
            margin-right: 12px;
        }

        .google-avatar-blue {
            background-color: #1a73e8;
        }

        .google-avatar-gray {
            background-color: #f1f3f4;
            color: #5f6368;
        }

        .google-account-info {
            flex-grow: 1;
        }

        .google-account-name {
            font-size: 14px;
            font-weight: 500;
            color: #3c4043;
            margin-bottom: 2px;
        }

        .google-account-email {
            font-size: 12px;
            color: #5f6368;
        }

        .google-footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #70757a;
            margin-top: 36px;
        }

        .google-footer a {
            color: #70757a;
            text-decoration: none;
            margin-left: 12px;
        }

        .google-footer a:hover {
            color: #202124;
        }

        /* ===================== FACEBOOK THEME ===================== */
        .facebook-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 12px 28px rgba(0,0,0,0.12), 0 2px 4px rgba(0,0,0,0.08);
            max-width: 480px;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Open Sans', sans-serif;
            overflow: hidden;
        }

        .facebook-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e5e5;
            padding: 16px 20px;
            display: flex;
            align-items: center;
        }

        .facebook-logo-f {
            color: #1877f2;
            font-size: 32px;
            margin-right: 12px;
        }

        .facebook-body {
            padding: 24px 30px;
        }

        .facebook-app-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            margin-bottom: 16px;
        }

        .facebook-title {
            font-size: 20px;
            font-weight: 700;
            color: #1c1e21;
            margin-bottom: 12px;
        }

        .facebook-desc {
            font-size: 14px;
            color: #606770;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .facebook-desc strong {
            color: #1c1e21;
        }

        .facebook-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-fb-primary {
            background-color: #1877f2;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 12px;
            border-radius: 6px;
            border: none;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-fb-primary:hover {
            background-color: #166fe5;
            color: #ffffff;
        }

        .btn-fb-secondary {
            background-color: #e4e6eb;
            color: #4b4f56;
            font-weight: 600;
            font-size: 15px;
            padding: 12px;
            border-radius: 6px;
            border: none;
            width: 100%;
            cursor: pointer;
            transition: background-color 0.2s;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-fb-secondary:hover {
            background-color: #d8dadf;
            color: #4b4f56;
        }

        .facebook-policy {
            font-size: 11px;
            color: #90949c;
            margin-top: 24px;
            line-height: 1.4;
        }

        .facebook-policy a {
            color: #1877f2;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <?php if ($provider === 'google'): ?>
        <!-- GOOGLE CONSENT MOCKUP -->
        <div class="google-container">
            <svg class="google-logo" viewBox="0 0 24 24" width="75" height="75" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
            </svg>
            
            <h1 class="google-title">Choose an account</h1>
            <p class="google-subtitle">to continue to <span><?php echo htmlspecialchars($brandName); ?></span></p>

            <div class="google-account-list">
                <!-- User One account row -->
                <a href="<?php echo htmlspecialchars($targetUrl); ?>" class="google-account-row">
                    <div class="google-avatar">U</div>
                    <div class="google-account-info">
                        <div class="google-account-name">User One</div>
                        <div class="google-account-email">user@gmail.com</div>
                    </div>
                </a>

                <!-- Andrei account row -->
                <a href="<?php echo htmlspecialchars($targetUrl); ?>" class="google-account-row">
                    <div class="google-avatar google-avatar-blue">A</div>
                    <div class="google-account-info">
                        <div class="google-account-name">Andrei Carpio</div>
                        <div class="google-account-email">andreicarpio11@gmail.com</div>
                    </div>
                </a>

                <!-- Use another account mockup row -->
                <a href="<?php echo htmlspecialchars($targetUrl); ?>" class="google-account-row">
                    <div class="google-avatar google-avatar-gray">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="google-account-info">
                        <div class="google-account-name" style="color: #1a73e8; font-weight: 500;">Use another account</div>
                    </div>
                </a>
            </div>

            <p style="font-size: 13px; color: #5f6368; line-height: 1.4; margin-bottom: 24px;">
                To continue, Google will share your name, email address, language preference, and profile picture with <?php echo htmlspecialchars($brandName); ?>. Before using this app, you can review its <a href="#" style="color: #1a73e8; text-decoration: none;">privacy policy</a> and <a href="#" style="color: #1a73e8; text-decoration: none;">terms of service</a>.
            </p>

            <div class="google-footer">
                <div>English (United States)</div>
                <div>
                    <a href="#">Help</a>
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- FACEBOOK CONSENT MOCKUP -->
        <div class="facebook-container">
            <div class="facebook-header">
                <i class="fab fa-facebook facebook-logo-f"></i>
                <span style="font-weight: 600; color: #4b4f56; font-size: 15px;">Facebook Login</span>
            </div>
            
            <div class="facebook-body">
                <img src="/petv10/assets/pagelogo.png" alt="App Icon" class="facebook-app-icon">
                <h1 class="facebook-title">Log in with Facebook</h1>
                <p class="facebook-desc">
                    <strong><?php echo htmlspecialchars($brandName); ?></strong> is requesting access to your name, profile picture, and email address.
                </p>

                <div class="facebook-actions">
                    <a href="<?php echo htmlspecialchars($targetUrl); ?>" class="btn-fb-primary">
                        Continue as Clark
                    </a>
                    <a href="<?php echo htmlspecialchars($action === 'signup' ? 'register.php' : 'login.php'); ?>" class="btn-fb-secondary">
                        Cancel
                    </a>
                </div>

                <p class="facebook-policy">
                    This doesn't let the app post to Facebook. Review the <a href="#">privacy policy</a> and <a href="#">terms</a> for <?php echo htmlspecialchars($brandName); ?>.
                </p>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
