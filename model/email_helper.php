<?php

/**
 * Simple email sending function using PHPMailer
 * Install: composer require phpmailer/phpmailer
 * Or download manually from https://github.com/PHPMailer/PHPMailer
 */

require_once __DIR__ . '/config.php';

function sendVerificationEmail($to, $username, $token)
{
    $verificationLink = APP_URL . "/app/auth/verify-email.php?token=" . urlencode($token);

    $subject = "Verify Your Email - EggFlow";

    // HTML Email Template
    $htmlContent = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Verification</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                padding: 20px 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 10px 10px 0 0;
                color: white;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 30px;
                text-align: center;
            }
            .button {
                display: inline-block;
                padding: 12px 30px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
                font-weight: bold;
            }
            .button:hover {
                background-color: #45a049;
            }
            .footer {
                text-align: center;
                padding: 20px;
                font-size: 12px;
                color: #666;
                border-top: 1px solid #eee;
            }
            .warning {
                color: #ff9800;
                font-size: 12px;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🐣 EggFlow</h1>
                <p>Verify Your Email Address</p>
            </div>
            <div class="content">
                <h2>Hello ' . htmlspecialchars($username) . '!</h2>
                <p>Thank you for creating an account with EggFlow. Please verify your email address to start using our service.</p>
                <a href="' . $verificationLink . '" class="button">Verify Email Address</a>
                <p class="warning">⚠️ This verification link will expire in 24 hours.</p>
                <p>If you did not create an account, please ignore this email.</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' EggFlow. All rights reserved.</p>
                <p>This is an automated message, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>';

    // Plain text version
    $textContent = "Hello $username!\n\n";
    $textContent .= "Thank you for creating an account with EggFlow. Please verify your email address to start using our service.\n\n";
    $textContent .= "Verification Link: $verificationLink\n\n";
    $textContent .= "⚠️ This verification link will expire in 24 hours.\n\n";
    $textContent .= "If you did not create an account, please ignore this email.\n\n";
    $textContent .= "Best regards,\nEggFlow Team";

    // Using PHP's mail() function (simplest, but may be filtered as spam)
    // For production, use PHPMailer or SMTP

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">" . "\r\n";

    $mailSent = mail($to, $subject, $htmlContent, $headers);

    // Alternative using PHPMailer (recommended)
    // Uncomment below if you have PHPMailer installed
    /*
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlContent;
        $mail->AltBody = $textContent;
        
        $mailSent = $mail->send();
    } catch (Exception $e) {
        $mailSent = false;
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
    */

    return $mailSent;
}

function logEmailActivity($conn, $userId, $action, $status, $details = null)
{
    $stmt = $conn->prepare("
        INSERT INTO user_activity_logs (user_id, action, log_date) 
        VALUES (?, ?, NOW())
    ");
    $actionMsg = $action . " - " . ($status ? "Success" : "Failed");
    if ($details) $actionMsg .= " - " . $details;
    $stmt->execute([$userId, $actionMsg]);
}
