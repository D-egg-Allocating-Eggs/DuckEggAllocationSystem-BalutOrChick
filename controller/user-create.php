<?php
require_once '../model/config.php';
require_once '../model/email_helper.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'user');
$sendVerification = isset($_POST['send_verification']) && $_POST['send_verification'] == '1';

// Role restrictions
if ($user_role !== 'admin' && $role !== 'user') {
    echo json_encode(['success' => false, 'message' => 'You can only create regular user accounts.']);
    exit;
}

// Validation
$errors = [];
if (strlen($username) < 3)
    $errors[] = 'Username must be at least 3 characters.';
if (strlen($password) < 6)
    $errors[] = 'Password must be at least 6 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'Please enter a valid email address.';

// Check if username exists
$chk = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$chk->execute([$username]);
if ($chk->fetch())
    $errors[] = 'Username already exists.';

// Check if email exists  
$chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$chk->execute([$email]);
if ($chk->fetch())
    $errors[] = 'Email already exists.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Generate password hash
$hash = password_hash($password, PASSWORD_DEFAULT);

// =====================================================
// CONDITIONAL VERIFICATION LOGIC - THE FIX
// =====================================================
if ($sendVerification) {
    // User needs to verify email
    $verificationToken = bin2hex(random_bytes(32));
    $isVerified = 0;
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
} else {
    // User is auto-verified - NO token, NO verification needed
    $verificationToken = null;
    $isVerified = 1;
    $expires = null;
}

// =====================================================
// INSERT USER
// =====================================================
try {
    $ins = $conn->prepare("
        INSERT INTO users (username, email, password, user_role, is_verified, verification_token, email_verification_expires) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$username, $email, $hash, $role, $isVerified, $verificationToken, $expires]);

    $newUserId = $conn->lastInsertId();

    // Log user creation
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, "Created user: $username ($role) with email: $email"]);

    $verificationStatus = $sendVerification ? "needs verification" : "auto-verified";
    $stmt->execute([$newUserId, "Account created ($verificationStatus)"]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// =====================================================
// HANDLE VERIFICATION EMAIL (ONLY IF CHECKED)
// =====================================================
$verificationLink = null;
$emailSent = false;

if ($sendVerification) {
    $verificationLink = getVerificationLink($verificationToken);
    $emailSent = sendVerificationEmail($email, $username, $verificationToken);
    logEmailActivity($conn, $newUserId, 'Verification email sent', $emailSent);
}

// =====================================================
// BUILD RESPONSE BASED ON CHECKBOX STATE
// =====================================================
if ($sendVerification) {
    if ($emailSent) {
        $message = "User created successfully! Verification email sent.";
    } else {
        $message = "User created successfully! Verification email failed to send.";
    }
    // ALWAYS include verification link for testing when checkbox is checked
    $response = [
        'success' => true,
        'message' => $message,
        'verification_link' => $verificationLink,
        'user_id' => $newUserId,
        'email_sent' => $emailSent
    ];
} else {
    // No verification needed - user is auto-verified
    $response = [
        'success' => true,
        'message' => 'User created and automatically verified. No email verification required.',
        'user_id' => $newUserId,
        'auto_verified' => true
    ];
}

header('Content-Type: application/json');
echo json_encode($response);