<?php
require_once '../model/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_role = $_SESSION['user_role'];
$current_user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($selected_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Check permissions
$canView = false;
if ($user_role === 'admin') {
    $canView = true;
} else {
    $stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
    $stmt->execute([$selected_user_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($targetUser && ($targetUser['user_role'] === 'user' || $selected_user_id == $current_user_id)) {
        $canView = true;
    }
}

if (!$canView) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this user']);
    exit;
}

// Fetch user data based on viewer role
$response = ['success' => true, 'data' => []];

// Get basic user info
if ($user_role === 'admin') {
    $stmt = $conn->prepare("SELECT user_id, username, email, user_role, is_verified, created_at FROM users WHERE user_id = ?");
} else {
    $stmt = $conn->prepare("SELECT user_id, username, user_role, created_at FROM users WHERE user_id = ?");
}
$stmt->execute([$selected_user_id]);
$response['data']['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Get egg records
$stmt = $conn->prepare("
    SELECT egg_id, batch_number, total_egg, status, date_started_incubation,
           balut_count, chick_count, failed_count
    FROM egg 
    WHERE user_id = ? 
    ORDER BY date_started_incubation DESC
    LIMIT 10
");
$stmt->execute([$selected_user_id]);
$response['data']['eggRecords'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get activity logs (limited based on role)
if ($user_role === 'admin') {
    $stmt = $conn->prepare("
        SELECT action, log_date 
        FROM user_activity_logs 
        WHERE user_id = ? 
        ORDER BY log_date DESC 
        LIMIT 15
    ");
} else {
    $stmt = $conn->prepare("
        SELECT action, log_date 
        FROM user_activity_logs 
        WHERE user_id = ? 
        ORDER BY log_date DESC 
        LIMIT 10
    ");
}
$stmt->execute([$selected_user_id]);
$response['data']['activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics (only for admin)
if ($user_role === 'admin') {
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(COUNT(egg_id), 0) as total_batches,
            COALESCE(SUM(balut_count), 0) as total_balut,
            COALESCE(SUM(chick_count), 0) as total_chicks,
            COALESCE(SUM(failed_count), 0) as total_failed
        FROM egg 
        WHERE user_id = ?
    ");
    $stmt->execute([$selected_user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['data']['totalBatches'] = $stats['total_batches'];
    $response['data']['totalBalut'] = $stats['total_balut'];
    $response['data']['totalChicks'] = $stats['total_chicks'];
    $response['data']['totalFailed'] = $stats['total_failed'];
} elseif ($user_role === 'manager') {
    $stmt = $conn->prepare("SELECT COUNT(egg_id) as total_batches FROM egg WHERE user_id = ?");
    $stmt->execute([$selected_user_id]);
    $response['data']['totalBatches'] = $stmt->fetchColumn();
}

header('Content-Type: application/json');
echo json_encode($response);
