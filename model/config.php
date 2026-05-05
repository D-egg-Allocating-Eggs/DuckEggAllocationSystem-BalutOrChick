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

// =====================================================
// APPLICATION CONFIGURATION
// =====================================================

// Base URL for your application - UPDATE THIS to match your setup
define('BASE_URL', 'http://localhost/DuckEggAllocationSystem-BalutOrChick');

// Email configuration
define('MAIL_FROM', 'noreply@eggflow.com');
define('MAIL_FROM_NAME', 'EggFlow System');
define('APP_NAME', 'EggFlow');

// Make BASE_URL available globally
$base_url = BASE_URL;