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

    // Auto generate batch number
    $stmt = $conn->prepare("SELECT MAX(batch_number) AS last_batch FROM egg WHERE user_id=?");
    $stmt->execute([$user_id]);
    $last_batch = $stmt->fetch(PDO::FETCH_ASSOC)['last_batch'];
    $batch_number = $last_batch ? $last_batch + 1 : 1;

    $date_started = date('Y-m-d H:i:s');

    // Insert batch
    $stmt = $conn->prepare("INSERT INTO egg (user_id, batch_number, total_egg, status, date_started_incubation)
                            VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $batch_number, $total_egg, $status, $date_started]);

    // Activity Log
    $action = "Added Batch #$batch_number with $total_egg eggs";
    $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
    $stmt->execute([$user_id, $action]);

    header("Location: dashboard.php");
    exit;
}

// Delete batch
if (isset($_POST['delete_batch'])) {

    $egg_id = $_POST['egg_id'];

    // Get batch info
    $stmt = $conn->prepare("SELECT batch_number FROM egg WHERE egg_id=? AND user_id=?");
    $stmt->execute([$egg_id, $user_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {

        // Delete batch
        $stmt = $conn->prepare("DELETE FROM egg WHERE egg_id=? AND user_id=?");
        $stmt->execute([$egg_id, $user_id]);

        // Log action
        $action = "Deleted Batch #" . $batch['batch_number'];
        $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    }

    header("Location: dashboard.php");
    exit;
}

// Update batch
if (isset($_POST['update_batch'])) {

    $egg_id = $_POST['egg_id'];
    $status = $_POST['status'];
    $balut = $_POST['balut'];
    $chick = $_POST['chick'];
    $failed = $_POST['failed'];

    // Get batch info
    $stmt = $conn->prepare("SELECT total_egg, batch_number FROM egg WHERE egg_id=? AND user_id=?");
    $stmt->execute([$egg_id, $user_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {

        $total_input = $balut + $chick + $failed;

        if ($total_input > $batch['total_egg']) {
            die("Error: Total results exceed total eggs.");
        }

        // Update batch
        $stmt = $conn->prepare("UPDATE egg 
                                SET status=?, balut_count=?, chick_count=?, failed_count=? 
                                WHERE egg_id=? AND user_id=?");

        $stmt->execute([$status, $balut, $chick, $failed, $egg_id, $user_id]);

        // Activity Log
        $action = "Updated Batch #" . $batch['batch_number'];
        $stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$user_id, $action]);
    }

    header("Location: dashboard.php");
    exit;
}

// ---------------------------
// Fetch Dashboard Data
// ---------------------------

// Summary Cards
$stmt = $conn->prepare("SELECT
    SUM(CASE WHEN status='incubating' THEN total_egg ELSE 0 END) AS incubating_eggs,
    SUM(balut_count) AS total_balut,
    SUM(chick_count) AS hatched_chicks,
    SUM(failed_count) AS total_failed,
    COUNT(*) AS active_batches,
    IFNULL(SUM(chick_count)/NULLIF(SUM(total_egg),0)*100,0) AS success_rate
    FROM egg WHERE user_id=?");

$stmt->execute([$user_id]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Batches
$stmt = $conn->prepare("SELECT * FROM egg WHERE user_id=? 
                        ORDER BY date_started_incubation DESC, batch_number DESC");
$stmt->execute([$user_id]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Activity Logs
$stmt = $conn->prepare("SELECT * FROM user_activity_logs 
                        WHERE user_id=? 
                        ORDER BY log_date DESC 
                        LIMIT 10");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../../assets/user_style.css">
</head>

<body>

    <div class="wrapper">

        <!-- Sidebar -->
        <aside class="sidebar">
            <h2>Egg System</h2>

            <ul>
                <li class="active">Dashboard</li>
                <li><a href="../../controller/auth/signout.php">Logout</a></li>
            </ul>
        </aside>

        <!-- Main -->
        <main class="content">

            <div class="header">

                <div>
                    <h1>Dashboard</h1>
                    <p>Monitor your egg incubation batches</p>
                </div>

                <button class="btn-primary" onclick="openModal('addModal')">
                    + Add Batch
                </button>

            </div>

            <!-- Summary Cards -->
            <div class="card-grid">

                <div class="stat-card">
                    <span>Incubating Eggs</span>
                    <h2><?= $summary['incubating_eggs'] ?? 0 ?></h2>
                </div>

                <div class="stat-card">
                    <span>Balut</span>
                    <h2><?= $summary['total_balut'] ?? 0 ?></h2>
                </div>

                <div class="stat-card">
                    <span>Hatched Chicks</span>
                    <h2><?= $summary['hatched_chicks'] ?? 0 ?></h2>
                </div>

                <div class="stat-card">
                    <span>Failed Eggs</span>
                    <h2><?= $summary['total_failed'] ?? 0 ?></h2>
                </div>

                <div class="stat-card">
                    <span>Active Batches</span>
                    <h2><?= $summary['active_batches'] ?? 0 ?></h2>
                </div>

                <div class="stat-card success">
                    <span>Success Rate</span>
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

                        <?php foreach ($batches as $batch): ?>

                            <tr>

                                <td>#<?= $batch['batch_number'] ?></td>
                                <td><?= $batch['total_egg'] ?></td>

                                <td>
                                    <span class="status-badge">
                                        <?= $batch['status'] ?>
                                    </span>
                                </td>

                                <td><?= date("M d, Y", strtotime($batch['date_started_incubation'])) ?></td>

                                <td><?= $batch['balut_count'] ?></td>
                                <td><?= $batch['chick_count'] ?></td>
                                <td><?= $batch['failed_count'] ?></td>

                                <td>

                                    <button onclick="openEditModal(
<?= $batch['egg_id'] ?>,
<?= $batch['batch_number'] ?>,
<?= $batch['total_egg'] ?>,
'<?= $batch['status'] ?>',
<?= $batch['balut_count'] ?>,
<?= $batch['chick_count'] ?>,
<?= $batch['failed_count'] ?>
)">Edit</button>

                                    <form method="post" onsubmit="return confirm('Delete this batch?');">

                                        <input type="hidden" name="egg_id" value="<?= $batch['egg_id'] ?>">

                                        <button class="btn-delete" type="submit" name="delete_batch">
                                            Delete
                                        </button>

                                    </form>

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

                        <?php if ($logs): ?>

                            <?php foreach ($logs as $log): ?>

                                <tr>
                                    <td><?= $log['action'] ?></td>
                                    <td><?= date("M d, Y h:i A", strtotime($log['log_date'])) ?></td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

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

    <!-- Edit Batch Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">

            <h3>Edit Batch</h3>

            <form method="post">

                <input type="hidden" name="egg_id" id="edit_egg_id">

                <label>Batch Number:</label>
                <p id="edit_batch_number"></p>

                <label>Total Eggs:</label>
                <p id="edit_total_eggs"></p>

                <label>Status:</label>
                <select name="status" id="edit_status">
                    <option value="incubating">Incubating</option>
                    <option value="hatched">Hatched</option>
                    <option value="failed">Failed</option>
                </select>

                <label>Balut Count:</label>
                <input type="number" name="balut" id="edit_balut" required>

                <label>Chick Count:</label>
                <input type="number" name="chick" id="edit_chick" required>

                <label>Failed Count:</label>
                <input type="number" name="failed" id="edit_failed" required>

                <button type="submit" name="update_batch" class="save-btn">Update</button>
                <button type="button" class="cancel-btn" onclick="closeModal('editModal')">Cancel</button>

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

        function openEditModal(id, batchNumber, total, status, balut, chick, failed) {

            document.getElementById('editModal').classList.add('active');

            document.getElementById('edit_egg_id').value = id;

            document.getElementById('edit_batch_number').innerText = "#" + batchNumber;

            document.getElementById('edit_total_eggs').innerText = total;

            document.getElementById('edit_status').value = status;

            document.getElementById('edit_balut').value = balut;

            document.getElementById('edit_chick').value = chick;

            document.getElementById('edit_failed').value = failed;

        }

    </script>

</body>

</html>