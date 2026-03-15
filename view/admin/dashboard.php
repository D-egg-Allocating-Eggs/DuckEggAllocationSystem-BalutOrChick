<?php
require '../../model/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

/* -------------------------
LOG ADMIN ACCESS
--------------------------*/
$action = "Admin opened dashboard";
$stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
$stmt->execute([$_SESSION['user_id'], $action]);

/* -------------------------
DELETE BATCH
--------------------------*/
if (isset($_POST['delete_batch'])) {

    $egg_id = $_POST['egg_id'];

    $stmt = $conn->prepare("SELECT batch_number,user_id FROM egg WHERE egg_id=?");
    $stmt->execute([$egg_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {

        $stmt = $conn->prepare("DELETE FROM egg WHERE egg_id=?");
        $stmt->execute([$egg_id]);

        $action = "Deleted Batch #{$batch['batch_number']}";
        $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id,action) VALUES (?,?)");
        $stmt->execute([$_SESSION['user_id'], $action]);
    }

    header("Location: dashboard.php");
    exit;
}

/* -------------------------
USER STATISTICS
--------------------------*/

$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();

$totalAdmins = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='admin'")
    ->fetchColumn();

$totalManagers = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='manager'")
    ->fetchColumn();

$totalRegularUsers = $conn->query("SELECT COUNT(*) FROM users WHERE user_role='user'")
    ->fetchColumn();

/* -------------------------
EGG SYSTEM STATS
--------------------------*/

$total_batches = $conn->query("SELECT COUNT(*) FROM egg")->fetchColumn();

$total_eggs = $conn->query("SELECT SUM(total_egg) FROM egg")->fetchColumn();

$total_chicks = $conn->query("SELECT SUM(chick_count) FROM egg")->fetchColumn();

/* -------------------------
FETCH BATCHES
--------------------------*/

$stmt = $conn->query("
SELECT e.*,u.username
FROM egg e
JOIN users u ON e.user_id=u.user_id
ORDER BY e.date_started_incubation DESC
");

$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
RECENT ACTIVITY
--------------------------*/

$stmt = $conn->query("
SELECT l.*,u.username
FROM user_activity_logs l
LEFT JOIN users u ON l.user_id=u.user_id
ORDER BY log_date DESC
LIMIT 10
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
CHART DATA
--------------------------*/

$stmt = $conn->query("
SELECT DATE(log_date) as date,COUNT(*) as count
FROM user_activity_logs
WHERE log_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)
GROUP BY DATE(log_date)
ORDER BY date ASC
");

$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->query("
SELECT action,COUNT(*) as count
FROM user_activity_logs
GROUP BY action
ORDER BY count DESC
LIMIT 10
");

$actionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>

    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="../../assets/css/admin_style.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
    </style>

</head>

<body>

    <div class="dashboard-container">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <h2>Egg System</h2>

            <ul>

                <li class="active">Dashboard</li>

                <li>
                    <button onclick="openAddUserModal()">Create User</button>
                </li>

                <li>
                    <a href="../../controller/auth/signout.php">Logout</a>
                </li>

            </ul>

        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <header class="topbar">

                <h1>Welcome Admin</h1>
                <p>System Overview</p>

            </header>


            <!-- SUMMARY CARDS -->
            <div class="cards">

                <div class="card">
                    <h3>Total Users</h3>
                    <p><?= $totalUsers ?></p>
                </div>

                <div class="card">
                    <h3>Admins</h3>
                    <p><?= $totalAdmins ?></p>
                </div>

                <div class="card">
                    <h3>Managers</h3>
                    <p><?= $totalManagers ?></p>
                </div>

                <div class="card">
                    <h3>Regular Users</h3>
                    <p><?= $totalRegularUsers ?></p>
                </div>

                <div class="card">
                    <h3>Total Batches</h3>
                    <p><?= $total_batches ?></p>
                </div>

                <div class="card">
                    <h3>Total Eggs</h3>
                    <p><?= $total_eggs ?? 0 ?></p>
                </div>

                <div class="card">
                    <h3>Total Chicks</h3>
                    <p><?= $total_chicks ?? 0 ?></p>
                </div>

            </div>


            <!-- ACTIVITY ANALYTICS -->

            <h2>Activity Analytics</h2>

            <div class="chart-grid">

                <div class="chart-box">
                    <h3>Daily Activity (7 days)</h3>
                    <canvas id="dailyChart"></canvas>
                </div>

                <div class="chart-box">
                    <h3>Top Actions</h3>
                    <canvas id="actionChart"></canvas>
                </div>

            </div>


            <!-- USERS TABLE -->

            <h2>Users</h2>

            <?php include '../users/user-view.php'; ?>


            <!-- BATCH TABLE -->

            <h2>Egg Batches</h2>

            <table class="styled-table">

                <thead>

                    <tr>

                        <th>User</th>
                        <th>Batch</th>
                        <th>Total Eggs</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Balut</th>
                        <th>Chick</th>
                        <th>Failed</th>
                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($batches as $batch): ?>

                        <tr>

                            <td><?= htmlspecialchars($batch['username']) ?></td>

                            <td>#<?= $batch['batch_number'] ?></td>

                            <td><?= $batch['total_egg'] ?></td>

                            <td><?= $batch['status'] ?></td>

                            <td><?= date("M d Y", strtotime($batch['date_started_incubation'])) ?></td>

                            <td><?= $batch['balut_count'] ?></td>

                            <td><?= $batch['chick_count'] ?></td>

                            <td><?= $batch['failed_count'] ?></td>

                            <td>

                                <form method="POST">

                                    <input type="hidden" name="egg_id" value="<?= $batch['egg_id'] ?>">

                                    <button class="btn-delete" name="delete_batch"
                                        onclick="return confirm('Delete this batch?')">
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>


            <!-- RECENT ACTIVITY -->

            <h2>Recent Activity</h2>

            <table class="styled-table">

                <thead>

                    <tr>

                        <th>User</th>
                        <th>Action</th>
                        <th>Date</th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($logs as $log): ?>

                        <tr>

                            <td><?= $log['username'] ?? 'System/Admin' ?></td>

                            <td><?= $log['action'] ?></td>

                            <td><?= date("M d Y H:i", strtotime($log['log_date'])) ?></td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </main>

    </div>


    <!-- USER MODALS -->
    <?php include '../users/user-create.php'; ?>
    <?php include '../users/user-update.php'; ?>


    <script>
        /* USER MODALS */

        function openEditModal(id, username) {

            document.getElementById("edit_user_id").value = id;
            document.getElementById("edit_username").value = username;

            document.getElementById("editModal").classList.add("active");

        }

        function closeEditModal() {
            document.getElementById("editModal").classList.remove("active");
        }

        function openAddUserModal() {
            document.getElementById("addUserModal").classList.add("active");
        }

        function closeAddUserModal() {
            document.getElementById("addUserModal").classList.remove("active");
        }


        /* CHART DATA */

        const dailyData = <?= json_encode($dailyActivity) ?>;

        new Chart(document.getElementById("dailyChart"), {

            type: "line",

            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: "Activities",
                    data: dailyData.map(d => d.count),
                    borderColor: "#4CAF50",
                    backgroundColor: "rgba(76,175,80,0.2)",
                    fill: true
                }]
            }

        });


        const actionData = <?= json_encode($actionStats) ?>;

        new Chart(document.getElementById("actionChart"), {

            type: "bar",

            data: {
                labels: actionData.map(d => d.action),
                datasets: [{
                    label: "Count",
                    data: actionData.map(d => d.count),
                    backgroundColor: "#3498db"
                }]
            }

        });
    </script>

</body>

</html>