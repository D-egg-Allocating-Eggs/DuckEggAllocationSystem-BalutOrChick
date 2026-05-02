<?php
session_start();

$host = "localhost";
$dbname = "duck_egg_system";
$user = "root";
$pass = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Email configuration - update with your SMTP settings
define('SMTP_HOST', 'smtp.gmail.com'); // or your mail server
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'EggFlow System');
define('APP_URL', 'http://localhost/DuckEggAllocationSystem-BalutOrChick'); // Update with your URL
