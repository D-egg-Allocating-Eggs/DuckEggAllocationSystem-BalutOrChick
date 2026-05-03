<?php
require_once '../../model/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

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
$stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, log_date) VALUES (?, ?, NOW())");
$stmt->execute([$user_id, ucfirst($user_role) . " accessed User Management"]);

// Fetch users based on role with proper role-based data display
if ($user_role === 'admin') {
    // Admin sees all users with full data including verification status and last activity
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.email, u.user_role, u.is_verified, u.created_at,
               (SELECT MAX(log_date) FROM user_activity_logs WHERE user_id = u.user_id) as last_activity,
               COALESCE(SUM(e.balut_count), 0) AS total_balut,
               COALESCE(SUM(e.chick_count), 0) AS total_chicks,
               COALESCE(SUM(e.failed_count), 0) AS total_failed,
               COALESCE(COUNT(e.egg_id), 0) AS batch_count
        FROM users u
        LEFT JOIN egg e ON u.user_id = e.user_id
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($user_role === 'manager') {
    // Manager sees limited user data (no email, limited activity)
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.user_role, u.created_at,
               (SELECT MAX(log_date) FROM user_activity_logs WHERE user_id = u.user_id) as last_activity,
               COALESCE(COUNT(e.egg_id), 0) AS batch_count
        FROM users u
        LEFT JOIN egg e ON u.user_id = e.user_id
        WHERE u.user_role = 'user' OR u.user_id = ?
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Regular user sees only basic info
    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.user_role, u.created_at
        FROM users u
        WHERE u.user_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch user egg records for selected user
$selected_user_id = isset($_GET['view_user']) ? (int)$_GET['view_user'] : 0;
$userEggRecords = [];
$selectedUsername = '';
$userActivities = [];
$selectedUserData = [];
$canView = false;

if ($selected_user_id > 0) {
    if ($user_role === 'admin') {
        $canView = true;
    } else {
        $stmt = $conn->prepare("SELECT user_role, is_verified, email, created_at FROM users WHERE user_id = ?");
        $stmt->execute([$selected_user_id]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($targetUser && ($targetUser['user_role'] === 'user' || $selected_user_id == $user_id)) {
            $canView = true;
        }
    }

    if ($canView) {
        // Get user data with field restrictions based on viewer role
        if ($user_role === 'admin') {
            $stmt = $conn->prepare("SELECT username, email, user_role, is_verified, created_at FROM users WHERE user_id = ?");
        } else {
            $stmt = $conn->prepare("SELECT username, user_role, created_at FROM users WHERE user_id = ?");
        }
        $stmt->execute([$selected_user_id]);
        $selectedUserData = $stmt->fetch(PDO::FETCH_ASSOC);
        $selectedUsername = $selectedUserData['username'] ?? '';

        // Get egg records (basic info for all roles)
        $stmt = $conn->prepare("
            SELECT e.*, 
                   DATEDIFF(NOW(), e.date_started_incubation) as days_in_incubation
            FROM egg e
            WHERE e.user_id = ?
            ORDER BY e.date_started_incubation DESC
        ");
        $stmt->execute([$selected_user_id]);
        $userEggRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get activity logs (limited for non-admin)
        if ($user_role === 'admin') {
            $stmt = $conn->prepare("
                SELECT l.*, u.username
                FROM user_activity_logs l
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.user_id = ?
                ORDER BY l.log_date DESC
                LIMIT 50
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT action, log_date
                FROM user_activity_logs 
                WHERE user_id = ?
                ORDER BY log_date DESC
                LIMIT 20
            ");
        }
        $stmt->execute([$selected_user_id]);
        $userActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Statistics
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBatches = $conn->query("SELECT COUNT(*) FROM egg")->fetchColumn();
$totalEggs = $conn->query("SELECT SUM(total_egg) FROM egg")->fetchColumn() ?: 0;
$totalChicks = $conn->query("SELECT SUM(chick_count) FROM egg")->fetchColumn() ?: 0;
$totalBalut = $conn->query("SELECT SUM(balut_count) FROM egg")->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= ucfirst($user_role) ?> - User Management | EggFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/users/css/user-management_style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>

<body data-user-role="<?= $user_role ?>" data-current-user-id="<?= (int)$user_id ?>">
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileMenu()"></div>

    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-chart-line"></i> EggFlow</h2>
                <p><?= ucfirst($user_role) ?> Panel</p>
            </div>
            <ul class="sidebar-menu">
                <?php if ($user_role === 'admin'): ?>
                    <li>
                        <a href="../admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Overview</a>
                    </li>
                    <li class="active">
                        <a href="user-management.php"><i class="fas fa-users"></i> User Management</a>
                    </li>
                    <li>
                        <a href="../admin/dashboard.php?tab=analytics"><i class="fas fa-chart-line"></i> Analytics</a>
                    </li>
                    <li>
                        <a href="../admin/dashboard.php?tab=reports"><i class="fas fa-file-alt"></i> Reports</a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="../manager/dashboard.php?tab=overview"><i class="fas fa-tachometer-alt"></i> Overview</a>
                    </li>
                    <li class="active">
                        <a href="user-management.php"><i class="fas fa-users"></i> User Management</a>
                    </li>
                    <li>
                        <a href="../manager/dashboard.php?tab=analytics"><i class="fas fa-chart-line"></i> Analytics</a>
                    </li>
                    <li>
                        <a href="../manager/dashboard.php?tab=reports"><i class="fas fa-file-alt"></i> Reports</a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="../../controller/auth/signout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="welcome-text">
                    <h1>User Management</h1>
                    <p><i class="fas fa-users-cog"></i> <?= $user_role === 'admin' ? 'Full access to manage all users and system records' : ($user_role === 'manager' ? 'Manage regular users and view operational records' : 'View your account information') ?></p>
                </div>
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?= date('M d, Y') ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <p><?= number_format($totalUsers) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Batches</h3>
                        <p><?= number_format($totalBatches) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-egg"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Chicks</h3>
                        <p><?= number_format($totalChicks) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-hat-wizard"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total Balut</h3>
                        <p><?= number_format($totalBalut) ?></p>
                    </div>
                    <div class="stat-icon"><i class="fas fa-drumstick-bite"></i></div>
                </div>
            </div>

            <!-- Users Tab -->
            <?php if (!$selected_user_id): ?>
                <div class="action-bar">
                    <div class="action-bar-left">
                        <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                <i class="fas fa-user-plus"></i> Add New User
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search by username..." onkeyup="filterUsers()">
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <h3><i class="fas fa-users"></i> All Users</h3>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <?php if ($user_role === 'admin'): ?>
                                        <th>Email</th>
                                    <?php endif; ?>
                                    <th>Role</th>
                                    <?php if ($user_role === 'admin'): ?>
                                        <th>Verified</th>
                                        <th>Last Activity</th>
                                    <?php elseif ($user_role === 'manager'): ?>
                                        <th>Last Activity</th>
                                    <?php endif; ?>
                                    <th>Joined</th>
                                    <?php if ($user_role === 'admin'): ?>
                                        <th>Batches</th>
                                        <th>Total Balut</th>
                                        <th>Total Chicks</th>
                                    <?php elseif ($user_role === 'manager'): ?>
                                        <th>Batches</th>
                                    <?php endif; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php foreach ($users as $u): ?>
                                    <tr data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>">
                                        <td>
                                            <div class="user-info">
                                                <div class="avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                                                <div><?= htmlspecialchars($u['username']) ?></div>
                                            </div>
                                        </td>
                                        <?php if ($user_role === 'admin'): ?>
                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                        <?php endif; ?>
                                        <td><span class="role-badge <?= $u['user_role'] ?>"><?= ucfirst($u['user_role']) ?></span></td>
                                        <?php if ($user_role === 'admin'): ?>
                                            <td>
                                                <?php if ($u['is_verified']): ?>
                                                    <span class="badge badge-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                                <?php else: ?>
                                                    <span class="badge badge-unverified"><i class="fas fa-clock"></i> Not Verified</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="activity-time"><?= !empty($u['last_activity']) ? timeAgo($u['last_activity']) : 'No activity' ?></td>
                                        <?php elseif ($user_role === 'manager' && isset($u['last_activity'])): ?>
                                            <td class="activity-time"><?= !empty($u['last_activity']) ? timeAgo($u['last_activity']) : 'No activity' ?></td>
                                        <?php endif; ?>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <?php if ($user_role === 'admin'): ?>
                                            <td><?= number_format($u['batch_count']) ?></td>
                                            <td><strong><?= number_format($u['total_balut']) ?></strong></td>
                                            <td><?= number_format($u['total_chicks']) ?></td>
                                        <?php elseif ($user_role === 'manager' && isset($u['batch_count'])): ?>
                                            <td><?= number_format($u['batch_count']) ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn btn-outline btn-sm" onclick="openViewModal(<?= $u['user_id'] ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($user_role === 'admin' || ($user_role === 'manager' && $u['user_role'] === 'user')): ?>
                                                    <button class="btn btn-warning btn-sm" onclick="openEditModal(<?= $u['user_id'] ?>, '<?= addslashes($u['username']) ?>', '<?= $u['user_role'] ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($user_role === 'admin' && $u['user_id'] != $user_id): ?>
                                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(<?= $u['user_id'] ?>, '<?= addslashes($u['username']) ?>')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- User Details View -->
            <?php if ($selected_user_id > 0 && $canView): ?>
                <div class="back-button">
                    <a href="user-management.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>

                <!-- User Records Header -->
                <div class="table-container" style="margin-bottom: 0.75rem;">
                    <div class="table-header">
                        <h3><i class="fas fa-user-circle"></i> User Records: <?= htmlspecialchars($selectedUsername) ?></h3>
                        <div class="export-dropdown">
                            <button class="btn btn-primary">
                                <i class="fas fa-download"></i> Export <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="export-dropdown-content">
                                <a href="#" onclick="exportEggRecordsCSV(<?= $selected_user_id ?>)">
                                    <i class="fas fa-file-csv"></i> Egg Records (CSV)
                                </a>
                                <a href="#" onclick="exportActivityLogsCSV(<?= $selected_user_id ?>)">
                                    <i class="fas fa-file-csv"></i> Activity Logs (CSV)
                                </a>
                                <a href="#" onclick="exportEggRecordsPDF(<?= $selected_user_id ?>)">
                                    <i class="fas fa-file-pdf"></i> Egg Records (PDF)
                                </a>
                                <a href="#" onclick="exportActivityLogsPDF(<?= $selected_user_id ?>)">
                                    <i class="fas fa-file-pdf"></i> Activity Logs (PDF)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Egg Records Section -->
                <div class="table-container" id="eggRecordsSection">
                    <div class="table-header">
                        <h3><i class="fas fa-egg"></i> Egg Batches</h3>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table" id="eggRecordsTable">
                            <thead>
                                <tr>
                                    <th>Batch #</th>
                                    <th>Total Eggs</th>
                                    <th>Balut</th>
                                    <th>Chicks</th>
                                    <th>Failed</th>
                                    <th>Status</th>
                                    <th>Started</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($userEggRecords)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: #64748b; padding: 1.5rem;">
                                            <i class="fas fa-info-circle"></i> No egg batches found for this user
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userEggRecords as $batch): ?>
                                        <tr>
                                            <td><strong>#<?= htmlspecialchars($batch['batch_number'] ?? $batch['egg_id']) ?></strong></td>
                                            <td><?= number_format($batch['total_egg']) ?></td>
                                            <td><?= number_format($batch['balut_count']) ?></td>
                                            <td><?= number_format($batch['chick_count']) ?></td>
                                            <td><?= number_format($batch['failed_count']) ?></td>
                                            <td>
                                                <span class="badge <?= $batch['status'] == 'incubating' ? 'badge-warning' : 'badge-success' ?>">
                                                    <?= ucfirst($batch['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($batch['date_started_incubation'])) ?></td>
                                            <td><?= $batch['days_in_incubation'] ?? 0 ?> days</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Activity Logs Section -->
                <div class="table-container" id="activityLogsSection">
                    <div class="table-header">
                        <h3><i class="fas fa-history"></i> Activity History</h3>
                    </div>
                    <div class="table-scroll-wrapper">
                        <table class="data-table" id="activityLogsTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($userActivities)): ?>
                                    <tr>
                                        <td colspan="2" style="text-align: center; color: #64748b; padding: 1.5rem;">
                                            <i class="fas fa-info-circle"></i> No activity logs found for this user
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userActivities as $activity): ?>
                                        <tr>
                                            <td class="activity-time"><?= timeAgo($activity['log_date']) ?><br><small><?= formatDateTime($activity['log_date']) ?></small></td>
                                            <td><?= htmlspecialchars($activity['action']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- View User Modal (Enhanced) -->
    <div id="viewUserModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> User Details</h3>
                <button class="close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="loading-spinner-small" style="text-align: center; padding: 2rem;">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm" onsubmit="saveUser(event)">
                <input type="hidden" id="editUserId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" id="modalUsername" name="username" required minlength="3" maxlength="50">
                    </div>
                    <div class="form-group" id="emailFieldGroup">
                        <label>Email Address *</label>
                        <input type="email" id="modalEmail" name="email" placeholder="user@example.com">
                        <small style="color: #666;">A verification email will be sent to this address</small>
                    </div>
                    <div class="form-group">
                        <label id="passwordLabel">Password *</label>
                        <input type="password" id="modalPassword" name="password" minlength="6">
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select id="modalRole" name="role">
                            <?php if ($user_role === 'admin'): ?>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="user">Regular User</option>
                            <?php else: ?>
                                <option value="user">Regular User</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" id="verificationCheckboxGroup">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="modalSendVerification" name="send_verification" value="1" checked style="width: auto;">
                            <span>Send email verification</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            If unchecked, user will be auto-verified (no email sent)
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Generating PDF...</div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toastMsg"></span>
    </div>

    <script src="../../assets/users/js/user-management_function.js" defer></script>
</body>

</html>