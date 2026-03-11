<?php
require '../../model/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../../assets/style.css">
</head>

<body>
    <div class="container">
        <h1>Welcome Admin!</h1>
        <p><a href="../../logout.php">Logout</a></p>
        <p>Here you can manage users and eggs.</p>
    </div>
</body>

</html>