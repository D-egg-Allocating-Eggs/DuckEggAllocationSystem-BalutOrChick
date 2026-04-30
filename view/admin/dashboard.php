<?php
require '../../model/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Helper: format date/time properly
function formatDateTime($datetime)
{
    if (!$datetime) return 'Never';
    $timestamp = strtotime($datetime);
    return date('M j, Y g:i A', $timestamp);
}

// Helper: time ago
function timeAgo($datetime)
{
    if (!$datetime) return 'Never';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    elseif ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    elseif ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    elseif ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    elseif ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    else return 'Just now';
}

// Log access
if (!isset($_SESSION['admin_dashboard_logged'])) {
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, "Admin accessed dashboard"]);
    $_SESSION['admin_dashboard_logged'] = true;
}

// Get active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$validTabs = ['overview', 'analytics', 'reports'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'overview';
}

// ─────────────────────────────────────────────────────────────────────────────
// OVERVIEW TAB STATISTICS
// ─────────────────────────────────────────────────────────────────────────────

// User Statistics
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='admin'")->fetchColumn();
$totalManagers = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='manager'")->fetchColumn();
$totalRegularUsers = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='user'")->fetchColumn();

// User Growth (Last 7 days)
$userGrowth = [];
$growthLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $growthLabels[] = date('M d', strtotime($date));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $userGrowth[] = (int)$stmt->fetchColumn();
}

// Production Statistics
$totalBatches = $conn->query("SELECT COUNT(*) FROM egg")->fetchColumn();
$totalEggs = $conn->query("SELECT SUM(total_egg) FROM egg")->fetchColumn() ?: 0;
$totalChicks = $conn->query("SELECT SUM(chick_count) FROM egg")->fetchColumn() ?: 0;
$totalBalut = $conn->query("SELECT SUM(balut_count) FROM egg")->fetchColumn() ?: 0;
$totalFailed = $conn->query("SELECT SUM(failed_count) FROM egg")->fetchColumn() ?: 0;

// Success/Failure Rates
$totalSuccessful = $totalChicks + $totalBalut;
$successRate = $totalEggs > 0 ? round(($totalSuccessful / $totalEggs) * 100, 1) : 0;
$failureRate = $totalEggs > 0 ? round(($totalFailed / $totalEggs) * 100, 1) : 0;

// Batch Status Distribution
$incubatingBatches = $conn->query("SELECT COUNT(*) FROM egg WHERE status='incubating'")->fetchColumn();
$completeBatches = $conn->query("SELECT COUNT(*) FROM egg WHERE status='complete'")->fetchColumn();

// Recent Activity (Last 24 hours)
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM user_activity_logs 
    WHERE log_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute();
$activity24h = $stmt->fetchColumn();

// Top Performing Users (by chicks + balut)
$stmt = $conn->prepare("
    SELECT u.username, 
           COUNT(DISTINCT e.egg_id) as batch_count,
           SUM(e.total_egg) as total_eggs,
           SUM(e.chick_count) + SUM(e.balut_count) as total_success,
           SUM(e.failed_count) as total_failed,
           ROUND((SUM(e.chick_count) + SUM(e.balut_count)) / NULLIF(SUM(e.total_egg), 0) * 100, 1) as success_rate
    FROM users u
    JOIN egg e ON u.user_id = e.user_id
    WHERE u.user_role = 'user'
    GROUP BY u.user_id
    ORDER BY total_success DESC
    LIMIT 5
");
$stmt->execute();
$topPerformers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Batches
$stmt = $conn->prepare("
    SELECT e.*, u.username,
           DATEDIFF(NOW(), e.date_started_incubation) as days_in_incubation
    FROM egg e
    JOIN users u ON e.user_id = u.user_id
    ORDER BY e.date_started_incubation DESC
    LIMIT 10
");
$stmt->execute();
$recentBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent Activity Logs
$stmt = $conn->prepare("
    SELECT l.*, u.username
    FROM user_activity_logs l
    LEFT JOIN users u ON l.user_id = u.user_id
    WHERE l.log_date IS NOT NULL
    ORDER BY l.log_date DESC
    LIMIT 15
");
$stmt->execute();
$activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Active Users Today
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT user_id) as active_users
    FROM user_activity_logs
    WHERE DATE(log_date) = CURDATE()
");
$stmt->execute();
$activeToday = $stmt->fetchColumn();

// ─────────────────────────────────────────────────────────────────────────────
// ANALYTICS TAB - Advanced Statistics
// ─────────────────────────────────────────────────────────────────────────────

// Daily Production Trend (Last 30 days)
$dailyProduction = [];
$dailyLabels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[] = date('M d', strtotime($date));

    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(balut_count), 0) as balut,
            COALESCE(SUM(chick_count), 0) as chicks,
            COALESCE(SUM(failed_count), 0) as failed,
            COALESCE(COUNT(*), 0) as batches
        FROM egg
        WHERE DATE(date_started_incubation) = ?
    ");
    $stmt->execute([$date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $dailyProduction[] = [
        'balut' => (int)$result['balut'],
        'chicks' => (int)$result['chicks'],
        'failed' => (int)$result['failed'],
        'batches' => (int)$result['batches']
    ];
}

// Weekly Production Summary (Last 4 weeks) - Fixed version
$weeklySummary = [];
// Define the last 4 weeks
for ($i = 3; $i >= 0; $i--) {
    $weekStart = date('Y-m-d', strtotime("-$i weeks"));
    $weekEnd = date('Y-m-d', strtotime("-$i weeks +6 days"));
    $weekNumber = 4 - $i;

    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT egg_id) as batches,
            COALESCE(SUM(total_egg), 0) as total_eggs,
            COALESCE(SUM(balut_count), 0) as balut,
            COALESCE(SUM(chick_count), 0) as chicks,
            COALESCE(SUM(failed_count), 0) as failed
        FROM egg
        WHERE DATE(date_started_incubation) BETWEEN ? AND ?
    ");
    $stmt->execute([$weekStart, $weekEnd]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $weeklySummary[] = [
        'week_label' => "Week $weekNumber",
        'batches' => (int)$result['batches'],
        'total_eggs' => (int)$result['total_eggs'],
        'balut' => (int)$result['balut'],
        'chicks' => (int)$result['chicks'],
        'failed' => (int)$result['failed']
    ];
}

// Hourly Activity Pattern
$hourlyActivity = array_fill(0, 24, 0);
$stmt = $conn->prepare("
    SELECT HOUR(log_date) as hour, COUNT(*) as count
    FROM user_activity_logs
    WHERE log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(log_date)
");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hourlyActivity[(int)$row['hour']] = (int)$row['count'];
}

// Most Active Users (Last 30 days)
$stmt = $conn->prepare("
    SELECT u.username, u.user_role, COUNT(l.log_id) as action_count
    FROM users u
    LEFT JOIN user_activity_logs l ON u.user_id = l.user_id 
        AND l.log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.user_id
    ORDER BY action_count DESC
    LIMIT 10
");
$stmt->execute();
$mostActiveUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Production by User Role
$stmt = $conn->prepare("
    SELECT 
        u.user_role,
        COUNT(DISTINCT e.egg_id) as total_batches,
        COALESCE(SUM(e.total_egg), 0) as total_eggs,
        COALESCE(SUM(e.balut_count), 0) as total_balut,
        COALESCE(SUM(e.chick_count), 0) as total_chicks,
        COALESCE(SUM(e.failed_count), 0) as total_failed
    FROM users u
    LEFT JOIN egg e ON u.user_id = e.user_id
    GROUP BY u.user_role
");
$stmt->execute();
$productionByRole = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Batch Efficiency Distribution
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN (balut_count + chick_count) / total_egg >= 0.9 THEN 'Excellent (90%+)'
            WHEN (balut_count + chick_count) / total_egg >= 0.7 THEN 'Good (70-89%)'
            WHEN (balut_count + chick_count) / total_egg >= 0.5 THEN 'Average (50-69%)'
            ELSE 'Poor (<50%)'
        END as efficiency_level,
        COUNT(*) as batch_count,
        ROUND(AVG((balut_count + chick_count) / total_egg * 100), 1) as avg_efficiency
    FROM egg
    WHERE total_egg > 0
    GROUP BY efficiency_level
    ORDER BY avg_efficiency DESC
");
$stmt->execute();
$efficiencyDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no data in efficiency distribution, provide default values
if (empty($efficiencyDistribution)) {
    $efficiencyDistribution = [
        ['efficiency_level' => 'Excellent (90%+)', 'batch_count' => 0, 'avg_efficiency' => 0],
        ['efficiency_level' => 'Good (70-89%)', 'batch_count' => 0, 'avg_efficiency' => 0],
        ['efficiency_level' => 'Average (50-69%)', 'batch_count' => 0, 'avg_efficiency' => 0],
        ['efficiency_level' => 'Poor (<50%)', 'batch_count' => 0, 'avg_efficiency' => 0]
    ];
}

// Failure Analysis by Day of Incubation
$stmt = $conn->prepare("
    SELECT day_number, ROUND(AVG(failed_count), 2) as avg_failures, COUNT(*) as log_count
    FROM egg_daily_logs
    GROUP BY day_number
    ORDER BY day_number
");
$stmt->execute();
$failureByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

// User Retention (Users who created batches in last 30 days vs total)
$totalUsersWithBatches = $conn->query("SELECT COUNT(DISTINCT user_id) FROM egg")->fetchColumn();
$activeUsersLast30 = $conn->prepare("
    SELECT COUNT(DISTINCT user_id) 
    FROM egg 
    WHERE date_started_incubation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$activeUsersLast30->execute();
$activeUsersLast30Count = $activeUsersLast30->fetchColumn();
$retentionRate = $totalUsersWithBatches > 0 ? round(($activeUsersLast30Count / $totalUsersWithBatches) * 100, 1) : 0;

// Top Actions Summary
$stmt = $conn->prepare("
    SELECT action, COUNT(*) as count
    FROM user_activity_logs
    WHERE log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY action
    ORDER BY count DESC
    LIMIT 8
");
$stmt->execute();
$topActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX activity refresh
if (isset($_GET['get_activity_ajax'])) {
    $stmt = $conn->prepare("
        SELECT l.log_date, u.username, l.action 
        FROM user_activity_logs l 
        LEFT JOIN users u ON l.user_id = u.user_id 
        WHERE l.log_date IS NOT NULL 
        ORDER BY l.log_date DESC 
        LIMIT 15
    ");
    $stmt->execute();
    $freshLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $logsData = [];
    foreach ($freshLogs as $log) {
        $logsData[] = [
            'formatted_date' => formatDateTime($log['log_date']),
            'time_ago' => timeAgo($log['log_date']),
            'username' => $log['username'] ?? 'System',
            'action' => $log['action']
        ];
    }
    echo json_encode(['logs' => $logsData]);
    exit;
}

// Handle activity log export
if (isset($_GET['export_activity']) && $_GET['export_activity'] === 'csv') {
    $stmt = $conn->prepare("
        SELECT l.log_date, u.username, l.action
        FROM user_activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE l.log_date IS NOT NULL
        ORDER BY l.log_date DESC
    ");
    $stmt->execute();
    $allLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_activity_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Date & Time', 'User', 'Action']);
    foreach ($allLogs as $log) {
        fputcsv($output, [formatDateTime($log['log_date']), $log['username'] ?? 'System', $log['action']]);
    }
    fclose($output);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORTS TAB - Report Generation
// ─────────────────────────────────────────────────────────────────────────────

// Get report parameters
$reportType = isset($_GET['report']) ? $_GET['report'] : 'userSummary';
$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$reportData = [];
$reportColumns = [];
$reportTitle = '';

// Handle CSV Export
if (isset($_GET['export_csv']) && isset($_GET['report_type'])) {
    $exportType = $_GET['report_type'];
    $exportStart = $_GET['start'] ?? date('Y-m-01');
    $exportEnd = $_GET['end'] ?? date('Y-m-d');
    $exportData = [];
    $exportHeaders = [];

    switch ($exportType) {
        case 'userSummary':
            $stmt = $conn->prepare("
                SELECT u.username, u.user_role, DATE(u.created_at) as created_date,
                       COALESCE(COUNT(DISTINCT e.egg_id), 0) as total_batches,
                       COALESCE(SUM(e.balut_count), 0) as total_balut,
                       COALESCE(SUM(e.chick_count), 0) as total_chicks,
                       COALESCE(SUM(e.failed_count), 0) as total_failed
                FROM users u
                LEFT JOIN egg e ON u.user_id = e.user_id AND DATE(e.date_started_incubation) BETWEEN ? AND ?
                GROUP BY u.user_id
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Username', 'Role', 'Created Date', 'Total Batches', 'Total Balut', 'Total Chicks', 'Total Failed'];
            $filename = "user_summary_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'batchProduction':
            $stmt = $conn->prepare("
                SELECT e.batch_number, u.username, e.total_egg, e.balut_count, e.chick_count, e.failed_count,
                       e.status, DATE(e.date_started_incubation) as start_date
                FROM egg e
                JOIN users u ON e.user_id = u.user_id
                WHERE DATE(e.date_started_incubation) BETWEEN ? AND ?
                ORDER BY e.date_started_incubation DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Batch Number', 'User', 'Total Eggs', 'Balut', 'Chicks', 'Failed', 'Status', 'Start Date'];
            $filename = "batch_production_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'dailyEggLogs':
            $stmt = $conn->prepare("
                SELECT e.batch_number, u.username, d.day_number, d.balut_count, d.chick_count, d.failed_count, DATE(d.created_at) as log_date
                FROM egg_daily_logs d
                JOIN egg e ON d.egg_id = e.egg_id
                JOIN users u ON e.user_id = u.user_id
                WHERE DATE(d.created_at) BETWEEN ? AND ?
                ORDER BY d.created_at DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Batch Number', 'User', 'Day', 'Daily Balut', 'Daily Chicks', 'Daily Failed', 'Log Date'];
            $filename = "daily_egg_logs_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'managerPerformance':
            $stmt = $conn->prepare("
                SELECT u.username, DATE(u.created_at) as created_date,
                       COUNT(DISTINCT al.log_id) as action_count,
                       COUNT(DISTINCT DATE(al.log_date)) as active_days
                FROM users u
                LEFT JOIN user_activity_logs al ON u.user_id = al.user_id 
                    AND DATE(al.log_date) BETWEEN ? AND ?
                WHERE u.user_role = 'manager'
                GROUP BY u.user_id
                ORDER BY action_count DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Manager Name', 'Created Date', 'Actions Performed', 'Active Days'];
            $filename = "manager_performance_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'userActivityLogs':
            $stmt = $conn->prepare("
                SELECT u.username, u.user_role, l.action, DATE(l.log_date) as log_date, TIME(l.log_date) as log_time
                FROM user_activity_logs l
                JOIN users u ON l.user_id = u.user_id
                WHERE DATE(l.log_date) BETWEEN ? AND ?
                ORDER BY l.log_date DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Username', 'Role', 'Action', 'Date', 'Time'];
            $filename = "user_activity_logs_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'failedEggAnalysis':
            $stmt = $conn->prepare("
                SELECT u.username, e.batch_number, e.failed_count, e.total_egg,
                       ROUND((e.failed_count / e.total_egg) * 100, 2) as fail_rate,
                       DATE(e.date_started_incubation) as start_date
                FROM egg e
                JOIN users u ON e.user_id = u.user_id
                WHERE e.failed_count > 0 AND DATE(e.date_started_incubation) BETWEEN ? AND ?
                ORDER BY e.failed_count DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['User', 'Batch Number', 'Failed Eggs', 'Total Eggs', 'Failure Rate %', 'Start Date'];
            $filename = "failed_egg_analysis_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'monthlySummary':
            $stmt = $conn->prepare("
                SELECT DATE_FORMAT(e.date_started_incubation, '%Y-%m') as month,
                       COUNT(DISTINCT e.egg_id) as total_batches,
                       COALESCE(SUM(e.total_egg), 0) as total_eggs,
                       COALESCE(SUM(e.balut_count), 0) as total_balut,
                       COALESCE(SUM(e.chick_count), 0) as total_chicks,
                       COALESCE(SUM(e.failed_count), 0) as total_failed,
                       ROUND((COALESCE(SUM(e.balut_count), 0) + COALESCE(SUM(e.chick_count), 0)) / NULLIF(COALESCE(SUM(e.total_egg), 0), 0) * 100, 2) as success_rate
                FROM egg e
                WHERE DATE(e.date_started_incubation) BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(e.date_started_incubation, '%Y-%m')
                ORDER BY month DESC
            ");
            $stmt->execute([$exportStart, $exportEnd]);
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Month', 'Total Batches', 'Total Eggs', 'Total Balut', 'Total Chicks', 'Total Failed', 'Success Rate %'];
            $filename = "monthly_summary_{$exportStart}_to_{$exportEnd}.csv";
            break;

        case 'roleDistribution':
            $stmt = $conn->prepare("
                SELECT user_role as role, COUNT(*) as count,
                       ROUND(COUNT(*) / (SELECT COUNT(*) FROM users) * 100, 1) as percentage
                FROM users
                GROUP BY user_role
            ");
            $stmt->execute();
            $exportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $exportHeaders = ['Role', 'Count', 'Percentage (%)'];
            $filename = "role_distribution.csv";
            break;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $exportHeaders);
    foreach ($exportData as $row) {
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}

// Generate Report Data based on type
switch ($reportType) {
    case 'userSummary':
        $stmt = $conn->prepare("
            SELECT u.username, u.user_role, DATE(u.created_at) as created_date,
                   COALESCE(COUNT(DISTINCT e.egg_id), 0) as total_batches,
                   COALESCE(SUM(e.balut_count), 0) as total_balut,
                   COALESCE(SUM(e.chick_count), 0) as total_chicks,
                   COALESCE(SUM(e.failed_count), 0) as total_failed
            FROM users u
            LEFT JOIN egg e ON u.user_id = e.user_id AND DATE(e.date_started_incubation) BETWEEN ? AND ?
            GROUP BY u.user_id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Username', 'Role', 'Created Date', 'Total Batches', 'Total Balut', 'Total Chicks', 'Total Failed'];
        $reportTitle = 'User Summary Report';
        break;

    case 'batchProduction':
        $stmt = $conn->prepare("
            SELECT e.batch_number, u.username, e.total_egg, e.balut_count, e.chick_count, e.failed_count,
                   e.status, DATE(e.date_started_incubation) as start_date
            FROM egg e
            JOIN users u ON e.user_id = u.user_id
            WHERE DATE(e.date_started_incubation) BETWEEN ? AND ?
            ORDER BY e.date_started_incubation DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Batch Number', 'Assigned User', 'Total Eggs', 'Balut', 'Chicks', 'Failed', 'Status', 'Start Date'];
        $reportTitle = 'Batch Production Report';
        break;

    case 'dailyEggLogs':
        $stmt = $conn->prepare("
            SELECT e.batch_number, u.username, d.day_number, d.balut_count, d.chick_count, d.failed_count, DATE(d.created_at) as log_date
            FROM egg_daily_logs d
            JOIN egg e ON d.egg_id = e.egg_id
            JOIN users u ON e.user_id = u.user_id
            WHERE DATE(d.created_at) BETWEEN ? AND ?
            ORDER BY d.created_at DESC
            LIMIT 1000
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Batch Number', 'User', 'Day Number', 'Daily Balut', 'Daily Chicks', 'Daily Failed', 'Log Date'];
        $reportTitle = 'Daily Egg Logs Report';
        break;

    case 'managerPerformance':
        $stmt = $conn->prepare("
            SELECT u.username, DATE(u.created_at) as created_date,
                   COUNT(DISTINCT al.log_id) as action_count,
                   COUNT(DISTINCT DATE(al.log_date)) as active_days,
                   MAX(DATE(al.log_date)) as last_active
            FROM users u
            LEFT JOIN user_activity_logs al ON u.user_id = al.user_id 
                AND DATE(al.log_date) BETWEEN ? AND ?
            WHERE u.user_role = 'manager'
            GROUP BY u.user_id
            ORDER BY action_count DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Manager Name', 'Created Date', 'Actions Performed', 'Active Days', 'Last Active'];
        $reportTitle = 'Manager Performance Report';
        break;

    case 'userActivityLogs':
        $stmt = $conn->prepare("
            SELECT u.username, u.user_role, l.action, DATE(l.log_date) as log_date, TIME(l.log_date) as log_time
            FROM user_activity_logs l
            JOIN users u ON l.user_id = u.user_id
            WHERE DATE(l.log_date) BETWEEN ? AND ?
            ORDER BY l.log_date DESC
            LIMIT 2000
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Username', 'Role', 'Action', 'Date', 'Time'];
        $reportTitle = 'User Activity Logs Report';
        break;

    case 'failedEggAnalysis':
        $stmt = $conn->prepare("
            SELECT u.username, e.batch_number, e.failed_count, e.total_egg,
                   ROUND((e.failed_count / e.total_egg) * 100, 2) as fail_rate,
                   DATE(e.date_started_incubation) as start_date
            FROM egg e
            JOIN users u ON e.user_id = u.user_id
            WHERE e.failed_count > 0 AND DATE(e.date_started_incubation) BETWEEN ? AND ?
            ORDER BY e.failed_count DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['User', 'Batch Number', 'Failed Eggs', 'Total Eggs', 'Failure Rate %', 'Start Date'];
        $reportTitle = 'Failed Egg Analysis Report';
        break;

    case 'monthlySummary':
        $stmt = $conn->prepare("
            SELECT DATE_FORMAT(e.date_started_incubation, '%Y-%m') as month,
                   COUNT(DISTINCT e.egg_id) as total_batches,
                   COALESCE(SUM(e.total_egg), 0) as total_eggs,
                   COALESCE(SUM(e.balut_count), 0) as total_balut,
                   COALESCE(SUM(e.chick_count), 0) as total_chicks,
                   COALESCE(SUM(e.failed_count), 0) as total_failed,
                   ROUND((COALESCE(SUM(e.balut_count), 0) + COALESCE(SUM(e.chick_count), 0)) / NULLIF(COALESCE(SUM(e.total_egg), 0), 0) * 100, 2) as success_rate
            FROM egg e
            WHERE DATE(e.date_started_incubation) BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(e.date_started_incubation, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Month', 'Total Batches', 'Total Eggs', 'Total Balut', 'Total Chicks', 'Total Failed', 'Success Rate %'];
        $reportTitle = 'Monthly Production Summary';
        break;

    case 'roleDistribution':
        $stmt = $conn->prepare("
            SELECT user_role as role, COUNT(*) as count,
                   ROUND(COUNT(*) / (SELECT COUNT(*) FROM users) * 100, 1) as percentage
            FROM users
            GROUP BY user_role
        ");
        $stmt->execute();
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reportColumns = ['Role', 'Count', 'Percentage (%)'];
        $reportTitle = 'Role Distribution Report';
        break;
}

// Calculate summary statistics for reports
$totalRecords = count($reportData);
$summaryStats = [];
if ($reportType == 'userSummary' && $totalRecords > 0) {
    $summaryStats['total_balut'] = array_sum(array_column($reportData, 'total_balut'));
    $summaryStats['total_chicks'] = array_sum(array_column($reportData, 'total_chicks'));
    $summaryStats['total_failed'] = array_sum(array_column($reportData, 'total_failed'));
    $summaryStats['total_batches'] = array_sum(array_column($reportData, 'total_batches'));
} elseif ($reportType == 'monthlySummary' && $totalRecords > 0) {
    $avgSuccess = array_sum(array_column($reportData, 'success_rate')) / $totalRecords;
    $summaryStats['avg_success_rate'] = round($avgSuccess, 1);
    $summaryStats['total_months'] = $totalRecords;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard | EggFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            font-size: 13px;
            overflow-x: hidden;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .sidebar-header p {
            font-size: 0.65rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0.75rem 0;
        }

        .sidebar-menu li {
            margin: 0.15rem 0;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.6rem 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .sidebar-menu li.active a,
        .sidebar-menu a:hover {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 0.75rem;
            left: 0.75rem;
            z-index: 1001;
            background: #10b981;
            color: white;
            border: none;
            padding: 0.5rem 0.7rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 1rem;
            transition: margin-left 0.3s ease;
            width: calc(100% - 240px);
            overflow-x: auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .welcome-text h1 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
        }

        .welcome-text p {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.2rem;
        }

        .date-badge {
            background: white;
            padding: 0.4rem 0.9rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Tabs */
        .tab-section {
            display: none;
        }

        .tab-section.active {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 0.85rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-info h3 {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 0.35rem;
        }

        .stat-info p {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-info .trend {
            font-size: 0.65rem;
            margin-top: 0.2rem;
            color: #10b981;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(59, 130, 246, 0.12);
            color: #3b82f6;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(139, 92, 246, 0.12);
            color: #8b5cf6;
        }

        .stat-card:nth-child(5) .stat-icon {
            background: rgba(236, 72, 153, 0.12);
            color: #ec4899;
        }

        .stat-card:nth-child(6) .stat-icon {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
        }

        /* Chart Rows */
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .chart-card h3 {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-card canvas {
            max-height: 250px;
            width: 100% !important;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.6rem;
        }

        .table-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .data-table {
            width: 100%;
            font-size: 0.75rem;
            border-collapse: collapse;
            min-width: 500px;
        }

        .data-table th {
            text-align: left;
            padding: 0.7rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            background: white;
        }

        .data-table td {
            padding: 0.6rem 0.5rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-scroll-wrapper {
            max-height: 400px;
            overflow-y: auto;
        }

        /* Badges */
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .role-badge.admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-badge.manager {
            background: #fed7aa;
            color: #92400e;
        }

        .role-badge.user {
            background: #dcfce7;
            color: #166534;
        }

        .badge {
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-excellent {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-good {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-average {
            background: #fed7aa;
            color: #92400e;
        }

        .badge-poor {
            background: #fee2e2;
            color: #991b1b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .avatar {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .activity-time {
            font-size: 0.7rem;
            color: #64748b;
            white-space: nowrap;
        }

        .btn {
            padding: 0.45rem 0.9rem;
            font-size: 0.75rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-primary {
            background: #10b981;
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
        }

        .btn-outline {
            background: white;
            border: 1px solid #e2e8f0;
            color: #334155;
        }

        .btn-outline:hover {
            background: #f1f5f9;
        }

        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: none;
            align-items: center;
            gap: 0.5rem;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            background: #10b981;
        }

        .toast.error {
            background: #ef4444;
        }

        /* Report Controls */
        .report-controls {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .report-controls .form-group {
            flex: 1;
            min-width: 140px;
        }

        .report-controls label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            display: block;
            margin-bottom: 0.3rem;
        }

        .report-controls select,
        .report-controls input[type="date"] {
            width: 100%;
            padding: 0.5rem 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #334155;
            background: white;
        }

        /* Print Styles */
        @media print {

            .sidebar,
            .mobile-menu-btn,
            .sidebar-overlay,
            .top-bar,
            .stats-grid,
            .chart-row,
            .report-controls .btn,
            .table-header .btn,
            .date-badge,
            .btn-outline,
            .btn-primary,
            #reports-section .report-controls {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            #print-area {
                display: block !important;
                margin: 0;
                padding: 0.5in;
            }

            .data-table th,
            .data-table td {
                border: 1px solid #ddd;
            }

            .data-table th {
                background-color: #f2f2f2;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 0.85rem;
                padding-top: 3.5rem;
                width: 100%;
            }

            .chart-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }
    </style>
</head>

<body>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileMenu()"></div>

    <div class="dashboard">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-chart-line"></i> EggFlow</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-menu">
                <li class="<?= $activeTab == 'overview' ? 'active' : '' ?>" data-tab="overview">
                    <a onclick="switchTab('overview')"><i class="fas fa-tachometer-alt"></i> Overview</a>
                </li>
                <li>
                    <a href="../users/user-management.php"><i class="fas fa-users"></i> User Management</a>
                </li>
                <li class="<?= $activeTab == 'analytics' ? 'active' : '' ?>" data-tab="analytics">
                    <a onclick="switchTab('analytics')"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="<?= $activeTab == 'reports' ? 'active' : '' ?>" data-tab="reports">
                    <a onclick="switchTab('reports')"><i class="fas fa-file-alt"></i> Reports</a>
                </li>
                <li><a href="../../controller/auth/signout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>Admin Dashboard</h1>
                    <p id="page-subtitle"><?= $activeTab == 'overview' ? 'System overview & real-time metrics' : ($activeTab == 'analytics' ? 'Deep dive analytics & insights' : 'Generate & export reports') ?></p>
                </div>
                <div class="date-badge"><i class="far fa-calendar-alt"></i> <?= date('M d, Y') ?></div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- OVERVIEW TAB - Complete System Monitoring -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div id="overview-section" class="tab-section <?= $activeTab == 'overview' ? 'active' : '' ?>">
                <!-- Key Metrics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-users"></i> Total Users</h3>
                            <p><?= number_format($totalUsers) ?></p>
                            <div class="trend"><i class="fas fa-chart-line"></i> <?= $totalAdmins ?> Admins | <?= $totalManagers ?> Managers | <?= $totalRegularUsers ?> Users</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-egg"></i> Production Summary</h3>
                            <p><?= number_format($totalBatches) ?> Batches</p>
                            <div class="trend"><i class="fas fa-chart-simple"></i> <?= number_format($totalEggs) ?> Total Eggs</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-egg"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-chart-pie"></i> Success Rate</h3>
                            <p><?= $successRate ?>%</p>
                            <div class="trend"><i class="fas fa-chart-line"></i> <?= number_format($totalSuccessful) ?> / <?= number_format($totalEggs) ?> eggs</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-chart-line"></i> Active Users</h3>
                            <p><?= $activeToday ?> Today</p>
                            <div class="trend"><i class="fas fa-clock"></i> <?= $activity24h ?> actions (24h)</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-dove"></i> Total Chicks</h3>
                            <p><?= number_format($totalChicks) ?></p>
                            <div class="trend"><i class="fas fa-drumstick-bite"></i> Balut: <?= number_format($totalBalut) ?></div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-dove"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3><i class="fas fa-times-circle"></i> Failed Eggs</h3>
                            <p><?= number_format($totalFailed) ?></p>
                            <div class="trend"><i class="fas fa-percentage"></i> <?= $failureRate ?>% failure rate</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>

                <!-- User Growth Chart & Batch Status -->
                <div class="chart-row">
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-line" style="color:#10b981;"></i> User Growth (Last 7 Days)</h3>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-pie" style="color:#f59e0b;"></i> Batch Status Distribution</h3>
                        <canvas id="batchStatusChart"></canvas>
                        <div style="text-align:center; margin-top:0.5rem;">
                            <span class="badge badge-warning">Incubating: <?= $incubatingBatches ?></span>
                            <span class="badge badge-success">Complete: <?= $completeBatches ?></span>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Users -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-trophy" style="color:#f59e0b;"></i> Top Performing Users</h3>
                        <span class="badge badge-info"><i class="fas fa-chart-line"></i> By Success Rate</span>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Batches</th>
                                    <th>Total Eggs</th>
                                    <th>Successful</th>
                                    <th>Failed</th>
                                    <th>Success Rate</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topPerformers)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center">No production data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topPerformers as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div><?= htmlspecialchars($user['username']) ?>
                                                </div>
                                            </td>
                                            <td><?= $user['batch_count'] ?></td>
                                            <td><?= number_format($user['total_eggs']) ?></td>
                                            <td><strong><?= number_format($user['total_success']) ?></strong></td>
                                            <td><?= number_format($user['total_failed']) ?></td>
                                            <td><span class="badge <?= $user['success_rate'] >= 80 ? 'badge-success' : ($user['success_rate'] >= 60 ? 'badge-warning' : 'badge-danger') ?>"><?= $user['success_rate'] ?>%</span></td>
                                            <td>
                                                <div style="width:80px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                                                    <div style="width:<?= $user['success_rate'] ?>%; height:6px; background:#10b981;"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Batches -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-clock"></i> Recent Batches</h3>
                        <button class="btn btn-outline" onclick="window.location.href='?tab=reports&report=batchProduction'"><i class="fas fa-chart-bar"></i> View All</button>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Batch #</th>
                                    <th>User</th>
                                    <th>Total Eggs</th>
                                    <th>Chicks</th>
                                    <th>Balut</th>
                                    <th>Failed</th>
                                    <th>Status</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentBatches)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center">No batches found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentBatches as $batch): ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($batch['batch_number'] ?? $batch['egg_id']) ?></strong></td>
                                            <td><?= htmlspecialchars($batch['username']) ?></td>
                                            <td><?= number_format($batch['total_egg']) ?></td>
                                            <td><span class="badge badge-success"><?= number_format($batch['chick_count']) ?></span></td>
                                            <td><span class="badge badge-info"><?= number_format($batch['balut_count']) ?></span></td>
                                            <td><span class="badge badge-danger"><?= number_format($batch['failed_count']) ?></span></td>
                                            <td><span class="badge <?= $batch['status'] == 'incubating' ? 'badge-warning' : 'badge-success' ?>"><?= ucfirst($batch['status']) ?></span></td>
                                            <td><?= $batch['days_in_incubation'] ?? 0 ?> days</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <button class="btn btn-outline" onclick="exportActivityCSV()"><i class="fas fa-download"></i> Export All Logs</button>
                    </div>
                    <div class="activity-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="activityLogsBody">
                                <?php if ($activityLogs): ?>
                                    <?php foreach ($activityLogs as $log): ?>
                                        <tr>
                                            <td class="activity-time"><i class="far fa-clock"></i> <?= formatDateTime($log['log_date']) ?> <small>(<?= timeAgo($log['log_date']) ?>)</small></td>
                                            <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                            <td><?= htmlspecialchars($log['action']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center">No activity logs found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- ANALYTICS TAB - Deep Dive Analytics -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div id="analytics-section" class="tab-section <?= $activeTab == 'analytics' ? 'active' : '' ?>">
                <!-- Production KPIs -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Avg Success/Batch</h3>
                            <p><?= $totalBatches > 0 ? round($totalSuccessful / $totalBatches) : 0 ?></p>
                            <div class="trend">successful eggs per batch</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Avg Batches/User</h3>
                            <p><?= $totalUsersWithBatches > 0 ? round($totalBatches / $totalUsersWithBatches, 1) : 0 ?></p>
                            <div class="trend">batches per active user</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>User Retention</h3>
                            <p><?= $retentionRate ?>%</p>
                            <div class="trend">active in last 30 days</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <h3>Peak Activity Hour</h3>
                            <p><?= array_search(max($hourlyActivity), $hourlyActivity) ?>:00</p>
                            <div class="trend">highest user engagement</div>
                        </div>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>

                <!-- Daily Production Trend -->
                <div class="chart-card" style="margin-bottom:1rem;">
                    <h3><i class="fas fa-chart-line" style="color:#10b981;"></i> Daily Production Trend (Last 30 Days)</h3>
                    <canvas id="dailyProductionChart"></canvas>
                </div>

                <!-- Weekly Summary & Efficiency Distribution -->
                <div class="chart-row">
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-bar" style="color:#3b82f6;"></i> Weekly Production Summary</h3>
                        <canvas id="weeklySummaryChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3><i class="fas fa-chart-pie" style="color:#f59e0b;"></i> Batch Efficiency Distribution</h3>
                        <canvas id="efficiencyChart"></canvas>
                    </div>
                </div>

                <!-- Hourly Activity Pattern -->
                <div class="chart-card" style="margin-bottom:1rem;">
                    <h3><i class="fas fa-chart-bar" style="color:#8b5cf6;"></i> User Activity Pattern (Last 30 Days)</h3>
                    <canvas id="hourlyActivityChart"></canvas>
                </div>

                <!-- Failure Analysis by Day -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-chart-line" style="color:#ef4444;"></i> Failure Analysis by Incubation Day</h3>
                        <span class="badge badge-info"><i class="fas fa-chart-line"></i> Identifies critical days</span>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Incubation Day</th>
                                    <th>Avg Failures per Batch</th>
                                    <th>Logs Analyzed</th>
                                    <th>Risk Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($failureByDay as $day): ?>
                                    <tr>
                                        <td><strong>Day <?= $day['day_number'] ?></strong></td>
                                        <td><?= round($day['avg_failures'], 2) ?></td>
                                        <td><?= $day['log_count'] ?></td>
                                        <td>
                                            <span class="badge <?= $day['avg_failures'] > 2 ? 'badge-danger' : ($day['avg_failures'] > 1 ? 'badge-warning' : 'badge-success') ?>">
                                                <?= $day['avg_failures'] > 2 ? 'High Risk' : ($day['avg_failures'] > 1 ? 'Medium Risk' : 'Low Risk') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Production by Role -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-chart-simple"></i> Production by User Role</h3>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Batches</th>
                                    <th>Total Eggs</th>
                                    <th>Balut</th>
                                    <th>Chicks</th>
                                    <th>Failed</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productionByRole as $role): ?>
                                    <?php $roleSuccess = ($role['total_eggs'] > 0) ? round((($role['total_balut'] + $role['total_chicks']) / $role['total_eggs']) * 100, 1) : 0; ?>
                                    <tr>
                                        <td><span class="role-badge <?= $role['user_role'] ?>"><?= ucfirst($role['user_role']) ?></span></td>
                                        <td><?= number_format($role['total_batches']) ?></td>
                                        <td><?= number_format($role['total_eggs']) ?></td>
                                        <td><?= number_format($role['total_balut']) ?></td>
                                        <td><?= number_format($role['total_chicks']) ?></td>
                                        <td><?= number_format($role['total_failed']) ?></td>
                                        <td><span class="badge <?= $roleSuccess >= 70 ? 'badge-success' : 'badge-warning' ?>"><?= $roleSuccess ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Most Active Users -->
                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-fire" style="color:#f59e0b;"></i> Most Active Users (Last 30 Days)</h3>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Actions Performed</th>
                                    <th>Activity Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mostActiveUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div><?= htmlspecialchars($user['username']) ?>
                                            </div>
                                        </td>
                                        <td><span class="role-badge <?= $user['user_role'] ?>"><?= ucfirst($user['user_role']) ?></span></td>
                                        <td><strong><?= number_format($user['action_count']) ?></strong></td>
                                        <td>
                                            <div style="width:100px; background:#e2e8f0; border-radius:10px; overflow:hidden;">
                                                <div style="width:<?= min(100, ($user['action_count'] / max($mostActiveUsers[0]['action_count'] ?? 1, 1) * 100)) ?>%; height:6px; background:#f59e0b;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ═══════════════ REPORTS TAB - FULL PROFESSIONAL REPORTING ═══════════════ -->
            <div id="reports-section" class="tab-section <?= $activeTab == 'reports' ? 'active' : '' ?>">
                <!-- Report Controls -->
                <div class="report-controls">
                    <div class="form-group">
                        <label><i class="fas fa-chart-simple"></i> Report Type</label>
                        <select id="reportType">
                            <option value="userSummary" <?= $reportType == 'userSummary' ? 'selected' : '' ?>>1. User Summary Report</option>
                            <option value="batchProduction" <?= $reportType == 'batchProduction' ? 'selected' : '' ?>>2. Batch Production Report</option>
                            <option value="dailyEggLogs" <?= $reportType == 'dailyEggLogs' ? 'selected' : '' ?>>3. Daily Egg Logs Report</option>
                            <option value="managerPerformance" <?= $reportType == 'managerPerformance' ? 'selected' : '' ?>>4. Manager Performance Report</option>
                            <option value="userActivityLogs" <?= $reportType == 'userActivityLogs' ? 'selected' : '' ?>>5. User Activity Logs Report</option>
                            <option value="failedEggAnalysis" <?= $reportType == 'failedEggAnalysis' ? 'selected' : '' ?>>6. Failed Egg Analysis Report</option>
                            <option value="monthlySummary" <?= $reportType == 'monthlySummary' ? 'selected' : '' ?>>7. Monthly Production Summary</option>
                            <option value="roleDistribution" <?= $reportType == 'roleDistribution' ? 'selected' : '' ?>>8. Role Distribution Report</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <input type="date" id="startDate" value="<?= $startDate ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> End Date</label>
                        <input type="date" id="endDate" value="<?= $endDate ?>">
                    </div>
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                    <button class="btn btn-outline" onclick="exportReportCSV()">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                    <button class="btn btn-outline" onclick="printReport()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>

                <!-- Report Preview Area (Printable) -->
                <div id="print-area">
                    <div class="table-container">
                        <div class="table-header">
                            <h3 id="reportTitle"><i class="fas fa-chart-line"></i> <?= $reportTitle ?? 'Report Preview' ?></h3>
                            <span id="reportDateRange" style="font-size:0.7rem; color:#64748b;">
                                <?php if ($reportType != 'roleDistribution'): ?>
                                    <i class="far fa-calendar"></i> <?= date('M d, Y', strtotime($startDate)) ?> - <?= date('M d, Y', strtotime($endDate)) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="table-scroll-wrapper" id="reportContent">
                            <?php if ($reportData && count($reportData) > 0): ?>
                                <table class="data-table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <?php foreach ($reportColumns as $col): ?>
                                                <th><?= htmlspecialchars($col) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData as $row): ?>
                                            <tr>
                                                <?php foreach (array_values($row) as $value): ?>
                                                    <td><?= htmlspecialchars($value ?? '0') ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($reportType == 'monthlySummary' && count($reportData) > 0): ?>
                                    <div style="margin-top:1rem; padding:0.75rem; background:#f0fdf4; border-radius:8px; text-align:center;">
                                        <strong>Overall Success Rate: </strong>
                                        <?php
                                        $totalSuccess = 0;
                                        $totalMonths = 0;
                                        foreach ($reportData as $row) {
                                            $totalSuccess += floatval($row['success_rate']);
                                            $totalMonths++;
                                        }
                                        $avgSuccess = $totalMonths > 0 ? round($totalSuccess / $totalMonths, 1) : 0;
                                        ?>
                                        <?= $avgSuccess ?>% average across <?= $totalMonths ?> month(s)
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="text-align:center; padding: 3rem; color:#94a3b8;">
                                    <i class="fas fa-chart-simple" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                    Select a report type and click Generate Report
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast" class="toast"><i class="fas fa-check-circle"></i> <span id="toastMsg"></span></div>

    <script>
        // Pass PHP data to JavaScript
        const chartData = {
            userGrowth: <?= json_encode($userGrowth) ?>,
            growthLabels: <?= json_encode($growthLabels) ?>,
            incubating: <?= $incubatingBatches ?>,
            complete: <?= $completeBatches ?>,
            dailyProduction: <?= json_encode($dailyProduction) ?>,
            dailyLabels: <?= json_encode($dailyLabels) ?>,
            weeklySummary: <?= json_encode($weeklySummary) ?>,
            hourlyActivity: <?= json_encode(array_values($hourlyActivity)) ?>,
            efficiencyDistribution: <?= json_encode($efficiencyDistribution) ?>,
            failureByDay: <?= json_encode($failureByDay) ?>
        };

        let charts = {};

        function initOverviewCharts() {
            // User Growth Chart
            const ctx1 = document.getElementById('userGrowthChart')?.getContext('2d');
            if (ctx1) {
                charts.userGrowth = new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: chartData.growthLabels,
                        datasets: [{
                            label: 'New Users',
                            data: chartData.userGrowth,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                stepSize: 1
                            }
                        }
                    }
                });
            }

            // Batch Status Chart
            const ctx2 = document.getElementById('batchStatusChart')?.getContext('2d');
            if (ctx2) {
                charts.batchStatus = new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: ['Incubating', 'Complete'],
                        datasets: [{
                            data: [chartData.incubating, chartData.complete],
                            backgroundColor: ['#f59e0b', '#10b981']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }

        function initAnalyticsCharts() {
            // Daily Production Chart
            const ctx1 = document.getElementById('dailyProductionChart')?.getContext('2d');
            if (ctx1) {
                charts.dailyProduction = new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: chartData.dailyLabels,
                        datasets: [{
                                label: 'Balut',
                                data: chartData.dailyProduction.map(d => d.balut),
                                borderColor: '#f59e0b',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                tension: 0.4
                            },
                            {
                                label: 'Chicks',
                                data: chartData.dailyProduction.map(d => d.chicks),
                                borderColor: '#10b981',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                tension: 0.4
                            },
                            {
                                label: 'Failed',
                                data: chartData.dailyProduction.map(d => d.failed),
                                borderColor: '#ef4444',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Weekly Summary Chart
            const ctx2 = document.getElementById('weeklySummaryChart')?.getContext('2d');
            if (ctx2) {
                charts.weeklySummary = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: chartData.weeklySummary.map(w => w.week_label),
                        datasets: [{
                                label: 'Balut',
                                data: chartData.weeklySummary.map(w => w.balut),
                                backgroundColor: '#f59e0b',
                                borderRadius: 4
                            },
                            {
                                label: 'Chicks',
                                data: chartData.weeklySummary.map(w => w.chicks),
                                backgroundColor: '#10b981',
                                borderRadius: 4
                            },
                            {
                                label: 'Failed',
                                data: chartData.weeklySummary.map(w => w.failed),
                                backgroundColor: '#ef4444',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Efficiency Distribution Chart
            const ctx3 = document.getElementById('efficiencyChart')?.getContext('2d');
            if (ctx3 && chartData.efficiencyDistribution.length > 0) {
                charts.efficiency = new Chart(ctx3, {
                    type: 'pie',
                    data: {
                        labels: chartData.efficiencyDistribution.map(e => e.efficiency_level),
                        datasets: [{
                            data: chartData.efficiencyDistribution.map(e => e.batch_count),
                            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Hourly Activity Chart
            const ctx4 = document.getElementById('hourlyActivityChart')?.getContext('2d');
            if (ctx4) {
                const hourLabels = Array.from({
                    length: 24
                }, (_, i) => `${String(i).padStart(2, '0')}:00`);
                charts.hourlyActivity = new Chart(ctx4, {
                    type: 'bar',
                    data: {
                        labels: hourLabels,
                        datasets: [{
                            label: 'User Actions',
                            data: chartData.hourlyActivity,
                            backgroundColor: '#8b5cf6',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }

        function switchTab(tabName) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);

            document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
            document.querySelector(`.sidebar-menu li[data-tab="${tabName}"]`)?.classList.add('active');

            document.querySelectorAll('.tab-section').forEach(section => section.classList.remove('active'));
            document.getElementById(`${tabName}-section`).classList.add('active');

            const subtitles = {
                overview: 'System overview & real-time metrics',
                analytics: 'Deep dive analytics & insights',
                reports: 'Generate & export reports'
            };
            document.getElementById('page-subtitle').textContent = subtitles[tabName];

            // Re-initialize charts when switching tabs
            setTimeout(() => {
                if (tabName === 'overview') {
                    if (charts.userGrowth) charts.userGrowth.resize();
                    if (charts.batchStatus) charts.batchStatus.resize();
                } else if (tabName === 'analytics') {
                    if (charts.dailyProduction) charts.dailyProduction.resize();
                    if (charts.weeklySummary) charts.weeklySummary.resize();
                    if (charts.efficiency) charts.efficiency.resize();
                    if (charts.hourlyActivity) charts.hourlyActivity.resize();
                }
            }, 100);

            if (window.innerWidth <= 768) closeMobileMenu();
        }

        function exportActivityCSV() {
            window.location.href = '?export_activity=csv';
            showToast('Exporting activity logs...', 'success');
        }

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            toast.className = `toast show ${type}`;
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function toggleMobileMenu() {
            document.getElementById('sidebar').classList.toggle('open');
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.style.display = document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
        }

        function closeMobileMenu() {
            document.getElementById('sidebar').classList.remove('open');
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.style.display = 'none';
        }

        document.getElementById('mobileMenuBtn')?.addEventListener('click', toggleMobileMenu);

        // Auto-refresh activity logs every 30 seconds
        setInterval(function() {
            if ('<?= $activeTab ?>' === 'overview') {
                fetch(window.location.href + '&get_activity_ajax=1&nocache=' + Date.now(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.logs && data.logs.length > 0) {
                            const tbody = document.getElementById('activityLogsBody');
                            if (tbody) {
                                let html = '';
                                data.logs.forEach(log => {
                                    html += `<tr><td class="activity-time"><i class="far fa-clock"></i> ${escapeHtml(log.formatted_date)} <small>(${escapeHtml(log.time_ago)})</small></td><td>${escapeHtml(log.username)}</td><td>${escapeHtml(log.action)}</td></tr>`;
                                });
                                tbody.innerHTML = html;
                            }
                        }
                    })
                    .catch(err => console.log('Auto-refresh failed:', err));
            }
        }, 30000);

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize charts based on active tab
        document.addEventListener('DOMContentLoaded', function() {
            initOverviewCharts();
            initAnalyticsCharts();

            const activeTab = '<?= $activeTab ?>';
            if (activeTab === 'analytics') {
                setTimeout(() => {
                    if (charts.dailyProduction) charts.dailyProduction.resize();
                    if (charts.weeklySummary) charts.weeklySummary.resize();
                    if (charts.efficiency) charts.efficiency.resize();
                    if (charts.hourlyActivity) charts.hourlyActivity.resize();
                }, 200);
            }
        });

        // Report Functions
        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            window.location.href = `?tab=reports&report=${reportType}&start=${startDate}&end=${endDate}`;
        }

        function exportReportCSV() {
            const reportType = document.getElementById('reportType').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            window.location.href = `?export_csv=1&report_type=${reportType}&start=${startDate}&end=${endDate}`;
            showToast('Exporting report...', 'success');
        }

        function printReport() {
            const reportTitle = document.getElementById('reportTitle')?.innerText || 'System Report';
            const reportDateRange = document.getElementById('reportDateRange')?.innerText || '';

            const printContent = document.getElementById('print-area').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${reportTitle}</title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Inter', sans-serif; padding: 0.5in; background: white; }
                .report-header { text-align: center; margin-bottom: 20px; }
                .report-header h2 { font-size: 18pt; margin-bottom: 5px; color: #0f172a; }
                .report-header p { font-size: 10pt; color: #64748b; }
                .data-table { width: 100%; font-size: 9pt; border-collapse: collapse; margin-top: 15px; }
                .data-table th { background: #f1f5f9; padding: 8px; text-align: left; border: 1px solid #e2e8f0; }
                .data-table td { padding: 6px 8px; border: 1px solid #e2e8f0; }
                .summary-box { margin-top: 20px; padding: 10px; background: #f0fdf4; border-radius: 8px; text-align: center; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="report-header">
                <h2>${escapeHtml(reportTitle.replace(/<[^>]*>/g, ''))}</h2>
                <p>Generated on: ${new Date().toLocaleString()} | ${escapeHtml(reportDateRange)}</p>
            </div>
            ${printContent}
        </body>
        </html>
    `);
            printWindow.document.close();
            printWindow.print();
            printWindow.onafterprint = () => printWindow.close();
            showToast('Preparing print...', 'success');
        }
    </script>
</body>

</html>