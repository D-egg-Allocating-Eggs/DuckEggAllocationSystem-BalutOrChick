<?php
require_once '../../model/config.php';
require_once '../../model/email_helper.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Find user by token - FIX: use 'user_id' not 'id'
$stmt = $conn->prepare("
    SELECT user_id, username, email, verification_token 
    FROM users 
    WHERE verification_token = ? AND is_verified = 0
");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found or already verified']);
    exit;
}

// Generate new token
$newToken = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Update token - FIX: use 'user_id'
$updateStmt = $conn->prepare("
    UPDATE users 
    SET verification_token = ?, email_verification_expires = ? 
    WHERE user_id = ?
");
$updateStmt->execute([$newToken, $expires, $user['user_id']]);

// Send email
$emailSent = sendVerificationEmail($user['email'], $user['username'], $newToken);
logEmailActivity($conn, $user['user_id'], 'Verification email resent', $emailSent);

$verificationLink = getVerificationLink($newToken);

if ($emailSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Verification email has been resent. Please check your inbox.',
        'verification_link' => $verificationLink
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Verification email failed to send, but you can use this link:',
        'verification_link' => $verificationLink
    ]);
}
