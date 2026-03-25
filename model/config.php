<?php
session_start();

$host = "localhost";
$dbname = "u442411629_duckegg";
$user = "u442411629_dev_duckegg";
$pass = "tI24o4^>W_M-";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
