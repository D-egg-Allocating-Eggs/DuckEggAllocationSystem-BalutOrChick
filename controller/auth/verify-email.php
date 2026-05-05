<?php
require_once '../../model/config.php';

$message = '';
$messageType = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // IMPORTANT FIX: Use correct column name 'user_id' not 'id'
    $stmt = $conn->prepare("
        SELECT user_id, username, verification_token, email_verification_expires, is_verified 
        FROM users 
        WHERE verification_token = ? AND is_verified = 0
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $now = new DateTime();
        $expires = new DateTime($user['email_verification_expires']);

        if ($now <= $expires) {
            // FIX: Use 'user_id' not 'id'
            $updateStmt = $conn->prepare("
                UPDATE users 
                SET is_verified = 1, 
                    verification_token = NULL, 
                    email_verification_expires = NULL 
                WHERE user_id = ?
            ");
            $updateStmt->execute([$user['user_id']]);

            // Log verification
            $logStmt = $conn->prepare("
                INSERT INTO user_activity_logs (user_id, action, log_date) 
                VALUES (?, ?, NOW())
            ");
            $logStmt->execute([$user['user_id'], "Email verified successfully"]);

            $message = "Your email has been verified successfully! You can now log in to your account.";
            $messageType = "success";
        } else {
            $message = "This verification link has expired. Please request a new verification email.";
            $messageType = "error";

            $logStmt = $conn->prepare("
                INSERT INTO user_activity_logs (user_id, action, log_date) 
                VALUES (?, ?, NOW())
            ");
            $logStmt->execute([$user['user_id'], "Email verification failed - token expired"]);
        }
    } else {
        $message = "Invalid verification link. The link may have been already used or is incorrect.";
        $messageType = "error";
    }
} else {
    $message = "No verification token provided.";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - EggFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            text-align: center;
            animation: fadeIn 0.5s ease;
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

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            color: white;
        }

        .header i {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .content {
            padding: 40px;
        }

        .message {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message i {
            margin-right: 10px;
            font-size: 20px;
            vertical-align: middle;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .footer {
            padding: 20px;
            background: #f8f9fa;
            font-size: 12px;
            color: #666;
        }

        .verification-link-box {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            word-break: break-all;
            font-size: 12px;
            text-align: left;
        }

        .verification-link-box a {
            color: #667eea;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-envelope-open-text"></i>
            <h1>Email Verification</h1>
            <p>EggFlow Account Verification</p>
        </div>
        <div class="content">
            <div class="message <?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>

            <?php if ($messageType === 'success'): ?>
                <a href="../../index.php" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Proceed to Login
                </a>
            <?php else: ?>
                <button onclick="resendVerification()" class="btn" id="resendBtn">
                    <i class="fas fa-paper-plane"></i> Resend Verification Email
                </button>
            <?php endif; ?>
        </div>
        <div class="footer">
            <p><i class="fas fa-shield-alt"></i> Secure verification process</p>
            <p>Need help? Contact support@eggflow.com</p>
        </div>
    </div>

    <script>
        function resendVerification() {
            const btn = document.getElementById('resendBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner"></span> Sending...';
            btn.disabled = true;

            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');

            fetch('resend-verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: token
                    })
                })
                .then(response => response.json())
                .then(data => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    const messageDiv = document.querySelector('.message');
                    if (data.success) {
                        messageDiv.className = 'message success';
                        messageDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        if (data.verification_link) {
                            const linkHtml = '<div class="verification-link-box"><strong>📧 Verification Link:</strong><br><a href="' + data.verification_link + '" target="_blank">' + data.verification_link + '</a></div>';
                            document.querySelector('.content').insertAdjacentHTML('beforeend', linkHtml);
                        }
                    } else {
                        messageDiv.className = 'message error';
                        messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    }
                })
                .catch(error => {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    const messageDiv = document.querySelector('.message');
                    messageDiv.className = 'message error';
                    messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Failed to send verification email. Please try again later.';
                });
        }
    </script>
</body>

</html>