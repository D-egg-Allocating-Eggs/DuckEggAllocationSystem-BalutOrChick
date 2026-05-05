<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · modern refresh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/users/css/index.css">
</head>

<body>
    <div class="container">
        <h2>Log in</h2>

        <?php
        require 'model/config.php';

        $error = '';

        if (isset($_POST['login'])) {
            // FIXED: Use login_input instead of username
            $loginInput = trim($_POST['login_input'] ?? '');
            $password = $_POST['password'] ?? '';

            // Validation
            if (empty($loginInput) || empty($password)) {
                $error = "Please enter both username/email and password!";
            } else {
                // FIXED: Query that checks BOTH username AND email fields
                // Using LIMIT 1 for security and performance
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->execute([$loginInput, $loginInput]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Verify password and user exists
                if ($user && password_verify($password, $user['password'])) {
                    // CHECK IF EMAIL IS VERIFIED (keeping existing logic)
                    if ($user['is_verified'] == 0) {
                        $error = "Invalid credentials or email not verified! Please check your email for verification link.";
                    } else {
                        // Set session variables (keeping existing structure)
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['user_role'] = $user['user_role'];
                        $_SESSION['user_name'] = $user['username'];

                        // Redirect based on role (keeping existing logic)
                        if ($user['user_role'] === 'admin') {
                            header("Location: view/admin/dashboard.php");
                            exit;
                        } elseif ($user['user_role'] === 'manager') {
                            header("Location: view/manager/dashboard.php");
                            exit;
                        } else {
                            header("Location: view/user/dashboard.php");
                            exit;
                        }
                    }
                } else {
                    $error = "Invalid username/email or password!";
                }
            }
        }
        ?>

        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <!-- FIXED: Changed name to 'login_input' and placeholder to reflect both options -->
                <input type="text" name="login_input" placeholder="Username or Email"
                    value="<?php echo isset($_POST['login_input']) ? htmlspecialchars($_POST['login_input']) : ''; ?>"
                    required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="hint-pw">
                <i class="fas fa-key"></i> Demo password: <strong>password123</strong>
            </div>

            <button type="submit" name="login">
                <span>Sign in</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <div class="note">
            <i class="fas fa-shield-alt" style="opacity: 0.6;"></i>
            secure login — <a href="#">reset</a> · <a href="#">help</a>
        </div>
    </div>
</body>

</html>