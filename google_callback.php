<?php
/**
 * Real Google OAuth Callback Handler
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$session_state = $_SESSION['oauth_state'] ?? '';
$action = $_SESSION['oauth_action'] ?? 'login';

// Cleanup session state
unset($_SESSION['oauth_state']);
unset($_SESSION['oauth_action']);

// 1. Verify State Token to prevent CSRF
if (empty($state) || $state !== $session_state) {
    die("Security validation failed (CSRF check failed). Please try logging in again.");
}

if (empty($code)) {
    die("No authorization code received from Google.");
}

// 2. Exchange authorization code for access token via cURL
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postFields = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    die("cURL error during token exchange: " . $error_msg);
}
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    die("Failed to exchange code for token. Response: " . htmlspecialchars($response));
}

$access_token = $tokenData['access_token'];

// 3. Fetch user information using access token via cURL
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP compatibility
$userInfoResponse = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    die("cURL error during userinfo fetch: " . $error_msg);
}
curl_close($ch);

$userInfo = json_decode($userInfoResponse, true);
if (!isset($userInfo['email'])) {
    die("Failed to fetch Google user information. Response: " . htmlspecialchars($userInfoResponse));
}

$email = sanitizeInput($userInfo['email'], 'email');
$name = sanitizeInput($userInfo['name'] ?? 'Google User', 'text');
$google_id = sanitizeInput($userInfo['sub'], 'text');
$picture = sanitizeInput($userInfo['picture'] ?? 'images/profile.jpg', 'text');

try {
    // 4. Check if user already exists (by email or google_id)
    $stmt = $conn->prepare("SELECT id, username, role, balance, profile_pic FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        // User exists -> Log them in
        $stmt->bind_result($id, $username, $role, $balance, $profile_pic);
        $stmt->fetch();
        $stmt->close();
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['balance'] = $balance;
        $_SESSION['profile_pic'] = $profile_pic ?? $picture;
        $_SESSION['login_time'] = time();
        
        header("Location: main.php");
        exit;
    } else {
        $stmt->close();
        
        // User does not exist -> Register a new user
        // Generate a clean unique username
        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', strstr($email, '@', true)));
        if (empty($base_username)) {
            $base_username = 'googleuser';
        }
        
        $username = $base_username;
        $counter = 1;
        
        // Resolve username collision
        while (true) {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows == 0) {
                $check_stmt->close();
                break;
            }
            $check_stmt->close();
            $username = $base_username . $counter;
            $counter++;
        }
        
        $role = 'user';
        $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $initial_balance = 20000.00;
        
        // Insert new user record
        $ins_stmt = $conn->prepare("INSERT INTO users (username, email, password, role, balance, profile_pic) VALUES (?, ?, ?, ?, ?, ?)");
        $ins_stmt->bind_param("ssssds", $username, $email, $dummy_password, $role, $initial_balance, $picture);
        
        if ($ins_stmt->execute()) {
            $new_id = $ins_stmt->insert_id;
            $ins_stmt->close();
            
            // Send welcome email
            sendWelcomeEmail($email, $name, $username);
            
            // Log in the new user immediately
            session_regenerate_id(true);
            $_SESSION['user_id'] = $new_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['balance'] = $initial_balance;
            $_SESSION['profile_pic'] = $picture;
            $_SESSION['login_time'] = time();
            
            header("Location: main.php");
            exit;
        } else {
            $ins_stmt->close();
            die("Failed to register new Google account user.");
        }
    }
} catch (Exception $e) {
    logError("Google OAuth callback error: " . $e->getMessage());
    die("An error occurred during Google Sign-In: " . htmlspecialchars($e->getMessage()));
}
