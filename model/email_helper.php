<?php

/**
 * Simple email helper that NEVER blocks user creation
 * Email failure = show link, continue normally
 */

require_once __DIR__ . '/config.php';

/**
 * Send verification email using PHP mail() function
 * Returns true/false but NEVER throws exceptions
 * 
 * @param string $to Recipient email
 * @param string $username Recipient username  
 * @param string $token Verification token
 * @return bool True if mail was accepted for delivery, false otherwise
 */
function sendVerificationEmail($to, $username, $token)
{
    $verificationLink = BASE_URL . "/controller/auth/verify-email.php?token=" . urlencode($token);

    $subject = "Verify Your Email - " . APP_NAME;

    // HTML Email Body
    $htmlBody = getEmailHTML($username, $verificationLink);

    // Plain text fallback
    $textBody = "Hello $username!\n\n";
    $textBody .= "Verify your email: $verificationLink\n\n";
    $textBody .= "This link expires in 24 hours.\n";

    // Email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";

    // Simple mail() call - NO try/catch, NO exceptions
    // Just returns true/false based on whether PHP accepted the mail
    return @mail($to, $subject, $htmlBody, $headers);
}

/**
 * Get HTML email template
 */
function getEmailHTML($username, $verificationLink)
{
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Email Verification</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 30px; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; text-align: center; }
            .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
            .warning { color: #ff9800; font-size: 12px; margin-top: 10px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🐣 ' . APP_NAME . '</h1>
                <p>Verify Your Email Address</p>
            </div>
            <div class="content">
                <h2>Hello ' . htmlspecialchars($username) . '!</h2>
                <p>Thank you for creating an account. Please verify your email address.</p>
                <a href="' . $verificationLink . '" class="button">Verify Email Address</a>
                <p class="warning">⚠️ This verification link will expire in 24 hours.</p>
                <p>If you did not create an account, please ignore this email.</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Log email activity (doesn't affect flow)
 */
function logEmailActivity($conn, $userId, $action, $success, $details = null)
{
    if (!$conn) return;

    $status = $success ? "Success" : "Failed";
    $actionMsg = $action . " - " . $status;
    if ($details) {
        $actionMsg .= " - " . substr($details, 0, 200);
    }

    try {
        $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $actionMsg]);
    } catch (Exception $e) {
        // Silent fail - logging shouldn't break anything
        error_log("Failed to log email activity: " . $e->getMessage());
    }
}

/**
 * Get verification link for display in UI
 */
function getVerificationLink($token)
{
    return BASE_URL . "/controller/auth/verify-email.php?token=" . urlencode($token);
}
