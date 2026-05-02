<?php
require_once '../model/config.php';
require_once '../model/email_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$response = ['success' => false, 'message' => ''];

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'user');
$sendVerification = isset($_POST['send_verification']) ? (bool)$_POST['send_verification'] : true;

// Role restrictions based on user role
if ($user_role !== 'admin') {
    if ($role !== 'user') {
        $response['message'] = 'You can only create regular user accounts.';
        echo json_encode($response);
        exit;
    }
}

// Validation
$errors = [];
if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

// Check if username exists
$chk = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$chk->execute([$username]);
if ($chk->fetch()) $errors[] = 'Username already exists.';

// Check if email exists
$chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$chk->execute([$email]);
if ($chk->fetch()) $errors[] = 'Email already exists.';

if (empty($errors)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Generate verification token if needed
    $verificationToken = null;
    $expires = null;
    $isVerified = 0;

    if ($sendVerification) {
        $verificationToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $isVerified = 0;
    } else {
        $isVerified = 1; // Auto-verify if not sending email
    }

    // Insert user
    $ins = $conn->prepare("
        INSERT INTO users (username, email, password, user_role, is_verified, verification_token, email_verification_expires) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$username, $email, $hash, $role, $isVerified, $verificationToken, $expires]);

    $newUserId = $conn->lastInsertId();

    // Log user creation
    $actionLog = "Created user: $username ($role) with email: $email";
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $actionLog]);

    // Send verification email if needed
    $emailSent = false;
    if ($sendVerification) {
        $emailSent = sendVerificationEmail($email, $username, $verificationToken);
        logEmailActivity($conn, $newUserId, 'Verification email sent', $emailSent);

        if ($emailSent) {
            $response = [
                'success' => true,
                'message' => 'User created successfully. Verification email has been sent to ' . htmlspecialchars($email)
            ];
        } else {
            $response = [
                'success' => true,
                'message' => 'User created but verification email failed to send. Please manually verify the user or resend email.'
            ];
        }
    } else {
        $response = ['success' => true, 'message' => 'User created successfully (auto-verified).'];
    }

    // Log account creation
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
    $stmt->execute([$newUserId, "Account created"]);
} else {
    $response = ['success' => false, 'message' => implode(' ', $errors)];
}

header('Content-Type: application/json');
echo json_encode($response);
