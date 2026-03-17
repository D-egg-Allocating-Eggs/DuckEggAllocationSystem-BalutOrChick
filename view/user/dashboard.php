<?php
require '../../model/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'admin') {
    header("Location: ../../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---------------------------
// Handle CRUD Operations
// ---------------------------

// Add new batch
if (isset($_POST['add_batch'])) {
    $total_egg = $_POST['total_egg'];
    $status = 'incubating';

    $stmt = $conn->prepare("SELECT MAX(batch_number) AS last_batch FROM egg WHERE user_id=?");
    $stmt->execute([$user_id]);
    $last_batch = $stmt->fetch(PDO::FETCH_ASSOC)['last_batch'];
    $batch_number = $last_batch ? $last_batch + 1 : 1;

    $date_started = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO egg (user_id, batch_number, total_egg, status, date_started_incubation)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $batch_number, $total_egg, $status, $date_started]);

    $action = "Added Batch #$batch_number with $total_egg eggs";
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$user_id, $action]);

    header("Location: dashboard.php");
    exit;
}

// Delete batch
if (isset($_POST['delete_batch'])) {
    $egg_id = $_POST['egg_id'];

    $stmt = $conn->prepare("SELECT batch_number FROM egg WHERE egg_id=? AND user_id=?");
    $stmt->execute([$egg_id, $user_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        $stmt = $conn->prepare("DELETE FROM egg WHERE egg_id=? AND user_id=?");
        $stmt->execute([$egg_id, $user_id]);

        $action = "Deleted Batch #" . $batch['batch_number'];
        $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    }

    header("Location: dashboard.php");
    exit;
}

// Update daily batch
if (isset($_POST['update_daily'])) {
    $egg_id = $_POST['egg_id'];
    $failed = $_POST['failed_count'] ?? 0;
    $balut = $_POST['balut_count'] ?? 0;
    $chick = $_POST['chick_count'] ?? 0;

    $stmt = $conn->prepare("SELECT * FROM egg WHERE egg_id=? AND user_id=?");
    $stmt->execute([$egg_id, $user_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$batch) exit('Batch not found');

    // Automatic day number
    $day_number = floor((time() - strtotime($batch['date_started_incubation'])) / 86400) + 1;

    // Insert daily log
    $stmt = $conn->prepare("INSERT INTO egg_daily_logs 
        (egg_id, day_number, failed_count, balut_count, chick_count)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$egg_id, $day_number, $failed, $balut, $chick]);

    // Update totals
    $new_failed = $batch['failed_count'] + $failed;
    $new_balut = $batch['balut_count'] + $balut;
    $new_chick = $batch['chick_count'] + $chick;

    $total_recorded = $new_failed + $new_balut + $new_chick;
    $status = 'incubating';
    if ($total_recorded >= $batch['total_egg']) {
        $status = 'complete';
        // Avoid exceeding total eggs
        $excess = $total_recorded - $batch['total_egg'];
        if ($excess > 0) {
            $new_failed = max(0, $new_failed - $excess);
        }
    }

    $stmt = $conn->prepare("UPDATE egg SET 
        failed_count=?, balut_count=?, chick_count=?, status=? 
        WHERE egg_id=? AND user_id=?");
    $stmt->execute([$new_failed, $new_balut, $new_chick, $status, $egg_id, $user_id]);

    $action = "Updated Batch #$batch[batch_number] - Day $day_number (F:$failed B:$balut C:$chick)";
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$user_id, $action]);

    header("Location: dashboard.php");
    exit;
}

// ---------------------------
// Fetch Dashboard Data
// ---------------------------

$stmt = $conn->prepare("SELECT
    SUM(CASE WHEN status='incubating' THEN total_egg ELSE 0 END) AS incubating_eggs,
    SUM(balut_count) AS total_balut,
    SUM(chick_count) AS hatched_chicks,
    SUM(failed_count) AS total_failed,
    COUNT(*) AS active_batches,
    SUM(chick_count)/SUM(total_egg)*100 AS success_rate
    FROM egg WHERE user_id=?");
$stmt->execute([$user_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM egg WHERE user_id=? ORDER BY date_started_incubation DESC, batch_number DESC");
$stmt->execute([$user_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM user_activity_logs WHERE user_id=? ORDER BY log_date DESC LIMIT 10");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../../assets/user/js/css/user_style.css">
</head>

<body>
    <div class="wrapper">

        <aside class="sidebar">
            <h2>Egg System</h2>
            <ul>
                <li class="active">Dashboard</li>
                <li><a href="../../controller/auth/signout.php">Logout</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Monitor your egg incubation batches</p>
                </div>
                <button class="btn-primary" onclick="openModal('addModal')">+ Add Batch</button>
            </div>

            <!-- Summary Cards -->
            <div class="card-grid">
                <div class="stat-card"><span>Incubating Eggs</span>
                    <h2><?= $summary['incubating_eggs'] ?? 0 ?></h2>
                </div>
                <div class="stat-card"><span>Balut</span>
                    <h2><?= $summary['total_balut'] ?? 0 ?></h2>
                </div>
                <div class="stat-card"><span>Hatched Chicks</span>
                    <h2><?= $summary['hatched_chicks'] ?? 0 ?></h2>
                </div>
                <div class="stat-card"><span>Failed Eggs</span>
                    <h2><?= $summary['total_failed'] ?? 0 ?></h2>
                </div>
                <div class="stat-card"><span>Active Batches</span>
                    <h2><?= $summary['active_batches'] ?? 0 ?></h2>
                </div>
                <div class="stat-card success"><span>Success Rate</span>
                    <h2><?= $summary['success_rate'] ? round($summary['success_rate'], 2) : 0 ?>%</h2>
                </div>
            </div>

            <!-- Egg Batches -->
            <div class="table-box">
                <div class="table-header">
                    <h2>Egg Batches</h2>
                </div>
                <table>
                    <thead>
                        <tr>
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
                        <?php foreach ($batches as $batch):
                            // Calculate day number automatically
                            $day_since = floor((time() - strtotime($batch['date_started_incubation'])) / 86400) + 1;

                            $guide = '';
                            if ($day_since >= 18 && $batch['status'] == 'incubating') $guide = 'Balut stage approaching';
                            if ($day_since >= 21 && $batch['status'] == 'incubating') $guide = 'Chick hatching stage approaching';
                            if ($batch['status'] == 'complete') $guide = 'Batch complete';
                        ?>
                            <tr>
                                <td>#<?= $batch['batch_number'] ?></td>
                                <td><?= $batch['total_egg'] ?></td>
                                <td>
                                    <span class="status-badge"><?= $batch['status'] ?></span>
                                    <?php if ($guide): ?><br><small class="guide-text"><?= $guide ?></small><?php endif; ?>
                                </td>
                                <td><?= date("M d, Y", strtotime($batch['date_started_incubation'])) ?></td>
                                <td><?= $batch['balut_count'] ?></td>
                                <td><?= $batch['chick_count'] ?></td>
                                <td><?= $batch['failed_count'] ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete this batch?');">
                                        <input type="hidden" name="egg_id" value="<?= $batch['egg_id'] ?>">
                                        <button class="btn-delete" type="submit" name="delete_batch">Delete</button>
                                    </form>

                                    <!-- Update daily -->
                                    <button class="btn-primary" onclick="openUpdateModal(<?= $batch['egg_id'] ?>, <?= $day_since ?>)">
                                        Update <!-- <?= $day_since ?> -->
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Activity Logs -->
            <div class="table-box">
                <div class="table-header">
                    <h2>Recent Activity</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= $log['action'] ?></td>
                                    <td><?= date("M d, Y h:i A", strtotime($log['log_date'])) ?></td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="2">No activity yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Add Batch Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <h3>Add New Batch</h3>
            <form method="post">
                <label>Total Eggs</label>
                <input type="number" name="total_egg" required>
                <div class="modal-actions">
                    <button class="btn-primary" type="submit" name="add_batch">Save</button>
                    <button class="btn-secondary" type="button" onclick="closeModal('addModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Daily Modal -->
    <div class="modal" id="updateModal">
        <div class="modal-content">
            <h3>Update Daily Batch - Day <span id="modalDayNumber">1</span></h3>
            <form method="post">
                <input type="hidden" name="egg_id" id="updateEggId">
                <label>Failed Eggs</label>
                <input type="number" name="failed_count" value="0" min="0">
                <label>Balut Eggs</label>
                <input type="number" name="balut_count" value="0" min="0">
                <label>Hatched Chicks</label>
                <input type="number" name="chick_count" value="0" min="0">
                <div class="modal-actions">
                    <button class="btn-primary" type="submit" name="update_daily">Update</button>
                    <button class="btn-secondary" type="button" onclick="closeModal('updateModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add("active");
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove("active");
        }

        function openUpdateModal(eggId) {
            document.getElementById('updateEggId').value = eggId;
            openModal('updateModal');
        }
    </script>
</body>

</html>