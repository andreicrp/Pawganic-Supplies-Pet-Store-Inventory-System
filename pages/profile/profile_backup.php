<?php
require_once __DIR__ . '/../../config/db.php';
// Session is started in db.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT username, role, balance FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $role, $balance);
$stmt->fetch();
$stmt->close();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_username') {
        $new_username = $_POST['new_username'] ?? '';
        
        if (empty($new_username)) {
            $message = 'Username cannot be empty!';
            $messageType = 'error';
        } else if (strlen($new_username) < 3) {
            $message = 'Username must be at least 3 characters long!';
            $messageType = 'error';
        } else {
            try {
                $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_username, $user_id);
                
                if ($update_stmt->execute()) {
                    $username = $new_username;
                    $message = 'Username updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Username already exists!';
                    $messageType = 'error';
                }
                $update_stmt->close();
            } catch (Exception $e) {
                $message = 'Username already exists!';
                $messageType = 'error';
            }
        }
    } 
    else if ($action == 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Fetch password from database
        $check_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_stmt->bind_result($hashed_password);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if (!password_verify($current_password, $hashed_password)) {
            $message = 'Current password is incorrect!';
            $messageType = 'error';
        } else if (empty($new_password)) {
            $message = 'New password cannot be empty!';
            $messageType = 'error';
        } else if (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters long!';
            $messageType = 'error';
        } else if ($new_password !== $confirm_password) {
            $message = 'Passwords do not match!';
            $messageType = 'error';
        } else {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_new_password, $user_id);
            
            if ($update_stmt->execute()) {
                $message = 'Password updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating password!';
                $messageType = 'error';
            }
            $update_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Pawganic Supplies</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/petv10/favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/petv10/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/petv10/favicon_io/favicon-16x16.png">
    <link rel="manifest" href="/petv10/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/petv10/favicon_io/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #d3c4a0 0%, #f7f2e8 100%);
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        header {
            background-color: rgba(183, 172, 137, 0.15);
            padding: 20px 0;
            border-bottom: 2px solid #b7ac89;
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #b7ac89;
            font-size: 24px;
            font-weight: bold;
        }

        .logo img {
            height: 40px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: #666;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #b7ac89;
        }

        .nav-links a.active {
            color: #b7ac89;
            border-bottom: 2px solid #b7ac89;
            padding-bottom: 5px;
        }

        .container-main {
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(183, 172, 137, 0.2);
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #b7ac89, #d3c4a0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            box-shadow: 0 4px 15px rgba(183, 172, 137, 0.3);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .detail-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #b7ac89;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }

        .badge-role {
            display: inline-block;
            padding: 6px 12px;
            background: #b7ac89;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .edit-section {
            margin-bottom: 40px;
        }

        .edit-section h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-section h2 i {
            color: #b7ac89;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            font-size: 16px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .form-control:focus {
            border-color: #b7ac89;
            box-shadow: 0 0 10px rgba(183, 172, 137, 0.25);
            outline: none;
        }

        .btn-update {
            background: linear-gradient(to right, #b7ac89, #d3c4a0);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-update:hover {
            background: linear-gradient(to right, #a59b7b, #c0b28c);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(183, 172, 137, 0.3);
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .edit-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            background: #f9f9f9;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .divider {
            border-top: 2px solid #f0f0f0;
            margin: 40px 0;
        }

        /* Toast Styles */
        #toastNotification {
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border: none;
            min-width: 300px;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        #toastNotification.hide {
            display: none !important;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(400px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        #toastNotification.success .toast-header {
            background-color: #d4edda;
            border-bottom: 1px solid #c3e6cb;
        }

        #toastNotification.success .toast-body {
            background-color: #f8f9fa;
            color: #155724;
        }

        #toastNotification.error .toast-header {
            background-color: #f8d7da;
            border-bottom: 1px solid #f5c6cb;
        }

        #toastNotification.error .toast-body {
            background-color: #f8f9fa;
            color: #721c24;
        }

        #toastNotification .toast-header strong {
            color: inherit;
        }

        #toastNotification .btn-close {
            filter: brightness(0.7);
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-details {
                grid-template-columns: 1fr;
            }

            .edit-form-row {
                grid-template-columns: 1fr;
            }

            .profile-container {
                padding: 20px;
            }

            .nav-links {
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <!-- Toast Container -->
    <div class="position-fixed p-3" style="top: 0; right: 0; z-index: 11;">
        <div id="toastNotification" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header" id="toastHeader">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody">
                
            </div>
        </div>
    </div>

    <!-- Header Navigation -->
    <header>
        <div class="navbar">
            <a href="main.php" class="logo">
                <i class="fas fa-bone"></i>
                <span>Pawganic Supplies</span>
            </a>
            <ul class="nav-links">
                <li><a href="main.php">Home</a></li>
                <li><a href="shop.php">Shop</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
            </ul>
        </div>
    </header>

    <div class="container-main">
        <div class="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($username); ?></h1>
                    <div class="profile-details">
                        <div class="detail-item">
                            <div class="detail-label">Role</div>
                            <div class="detail-value" style="text-transform: capitalize;">
                                <span class="badge-role"><?php echo htmlspecialchars($role); ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Account Balance</div>
                            <div class="detail-value">₱<?php echo number_format($balance, 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Member Since</div>
                            <div class="detail-value">June 2026</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Edit Username Section -->
            <div class="edit-section">
                <h2><i class="fas fa-user-edit"></i> Change Username</h2>
                <form method="POST" id="usernameForm">
                    <input type="hidden" name="action" value="update_username">
                    <div class="edit-form-row">
                        <div class="form-group">
                            <label for="current_username">Current Username</label>
                            <input type="text" class="form-control" id="current_username" value="<?php echo htmlspecialchars($username); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="new_username">New Username</label>
                            <input type="text" class="form-control" id="new_username" name="new_username" placeholder="Enter new username" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-update">
                        <i class="fas fa-save"></i> Update Username
                    </button>
                </form>
            </div>

            <div class="divider"></div>

            <!-- Change Password Section -->
            <div class="edit-section">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="update_password">
                    <div class="edit-form-row">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Enter new password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-update">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </form>
            </div>

            <div class="divider"></div>

            <!-- Logout Section -->
            <div class="edit-section">
                <h2><i class="fas fa-sign-out-alt"></i> Account</h2>
                <p style="color: #666; margin-bottom: 20px;">Click the button below to log out from your account.</p>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toastElement = document.getElementById('toastNotification');
            const toastHeader = document.getElementById('toastHeader');
            const toastBody = document.getElementById('toastBody');
            
            // Set message and type
            toastBody.textContent = message;
            
            // Remove previous type classes
            toastElement.classList.remove('success', 'error');
            
            // Add the appropriate class
            toastElement.classList.add(type);
            
            // Update header title
            const headerText = type === 'success' ? 'Success!' : 'Error!';
            toastHeader.innerHTML = `<strong class="me-auto">${headerText}</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>`;
            
            // Show toast
            toastElement.classList.remove('hide');
            
            if (typeof bootstrap !== 'undefined') {
                const toast = new bootstrap.Toast(toastElement, {
                    delay: type === 'success' ? 2000 : 3000
                });
                toast.show();
            } else {
                toastElement.style.display = 'block';
                setTimeout(() => {
                    toastElement.style.display = 'none';
                }, type === 'success' ? 2000 : 3000);
            }
        }

        // Check if there's a message to display
        <?php if ($message): ?>
            showToast('<?php echo addslashes($message); ?>', '<?php echo $messageType; ?>');
        <?php endif; ?>

        // Form submission handler
        document.getElementById('usernameForm').addEventListener('submit', function(e) {
            const newUsername = document.getElementById('new_username').value.trim();
            if (newUsername.length < 3) {
                e.preventDefault();
                showToast('Username must be at least 3 characters long!', 'error');
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                showToast('Password must be at least 6 characters long!', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showToast('Passwords do not match!', 'error');
            }
        });
    </script>
</body>
</html>
