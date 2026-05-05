<?php
require 'model/config.php';

echo "<h2>Database User Check</h2>";

// Fetch all users
$stmt = $conn->prepare("SELECT user_id, username, email, password, user_role, is_verified FROM users");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Password Hash</th><th>Role</th><th>Verified</th><th>Hash Valid?</th></tr>";

foreach ($users as $user) {
    // Test if password 'password123' works with this hash
    $testPassword = 'password123';
    $valid = password_verify($testPassword, $user['password']);

    echo "<tr>";
    echo "<td>{$user['user_id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['email']}</td>";
    echo "<td style='font-family: monospace; font-size: 11px;'>" . substr($user['password'], 0, 50) . "...</td>";
    echo "<td>{$user['user_role']}</td>";
    echo "<td>" . ($user['is_verified'] ? 'Yes' : 'No') . "</td>";
    echo "<td style='color: " . ($valid ? 'green' : 'red') . "; font-weight: bold;'>" . ($valid ? '✓ Works' : '✗ Invalid') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Test specific login:</h3>";
echo "<form method='post'>";
echo "Username: <input type='text' name='test_user'><br>";
echo "Password: <input type='text' name='test_pass' value='password123'><br>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

if (isset($_POST['test_user'])) {
    $testUser = $_POST['test_user'];
    $testPass = $_POST['test_pass'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$testUser]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($testPass, $user['password'])) {
            echo "<p style='color: green;'>✓ Login successful for '{$testUser}'!</p>";
        } else {
            echo "<p style='color: red;'>✗ Password verification failed for '{$testUser}'</p>";
            echo "<p>Stored hash: " . $user['password'] . "</p>";

            // Try to generate correct hash for comparison
            $correctHash = password_hash($testPass, PASSWORD_DEFAULT);
            echo "<p>Correct hash for '{$testPass}': " . $correctHash . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ User '{$testUser}' not found</p>";
    }
}
