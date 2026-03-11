<?php
require '../../model/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'admin') {
    header("Location: ../../index.php");
    exit;
}

// Fetch user's eggs
$stmt = $conn->prepare("SELECT * FROM egg WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$eggs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>

<body>
    <div class="container">
        <h1>Welcome User!</h1>
        <p><a href="../../logout.php">Logout</a></p>
        <p>Hello, user!</p>
    </div>
</body>

</html>