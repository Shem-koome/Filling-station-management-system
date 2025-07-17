<?php
date_default_timezone_set('Africa/Nairobi'); // ✅ ensure all times are Kenyan time
include 'header.php';
require '../authpage/db.php';

$user_email = $_SESSION['email'];

// Get active batch
$batch_stmt = $conn->prepare("SELECT id FROM fuel_batches WHERE remaining_liters > 0 AND is_closed = 0 ORDER BY start_date DESC LIMIT 1");
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
$active_batch = $batch_result->fetch_assoc();
$batch_id = $active_batch ? $active_batch['id'] : null;

if (!$batch_id) {
    $_SESSION['error'] = "No active fuel batch found.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'], $_POST['transaction_cost']) && $batch_id) {
    $amount = floatval($_POST['amount']);
    $transaction_cost = floatval($_POST['transaction_cost']);
    $total = $amount - $transaction_cost;

    if ($total > 0) {
        $entry_date = date('Y-m-d H:i:s'); // ✅ Kenyan time
        $stmt = $conn->prepare("INSERT INTO till_money (user_email, batch_id, amount, transaction_cost, total, entry_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siddds", $user_email, $batch_id, $amount, $transaction_cost, $total, $entry_date);
        $stmt->execute();

        $_SESSION['success'] = "Till entry recorded successfully.";
        header("Location: till_money.php?filter_date=" . date('Y-m-d'));
        exit();
    } else {
        $_SESSION['error'] = "Please enter valid amount and transaction cost.";
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $del_stmt = $conn->prepare("DELETE FROM till_money WHERE id = ? AND user_email = ? AND batch_id = ?");
    $del_stmt->bind_param("isi", $delete_id, $user_email, $batch_id);
    $del_stmt->execute();

    $_SESSION['success'] = "Till entry deleted successfully.";
    header("Location: till_money.php?filter_date=" . date('Y-m-d'));
    exit();
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Date filter (default to today)
$filter_date = isset($_GET['filter_date']) && $_GET['filter_date'] ? $_GET['filter_date'] : date('Y-m-d');

// Prepare conditions
$conditions = "user_email = ? AND batch_id = ? AND DATE(entry_date) = ?";
$params = [$user_email, $batch_id, $filter_date];
$param_types = "sis";

// Count filtered rows for pagination
$count_sql = "SELECT COUNT(*) as total FROM till_money WHERE $conditions";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($param_types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Compute total for this day (across all pages)
$day_total_sql = "SELECT SUM(total) as day_total FROM till_money WHERE $conditions";
$day_total_stmt = $conn->prepare($day_total_sql);
$day_total_stmt->bind_param($param_types, ...$params);
$day_total_stmt->execute();
$day_total_result = $day_total_stmt->get_result();
$day_total_sum = $day_total_result->fetch_assoc()['day_total'] ?? 0;

// Compute grand total for the batch
$batch_total_stmt = $conn->prepare("SELECT SUM(total) as grand_total FROM till_money WHERE user_email = ? AND batch_id = ?");
$batch_total_stmt->bind_param("si", $user_email, $batch_id);
$batch_total_stmt->execute();
$batch_total_result = $batch_total_stmt->get_result();
$grand_total = $batch_total_result->fetch_assoc()['grand_total'] ?? 0;

// Fetch rows for current page
$data_sql = "SELECT id, amount, transaction_cost, total, entry_date FROM till_money 
             WHERE $conditions ORDER BY entry_date DESC LIMIT ?, ?";
$params_page = array_merge($params, [$offset, $limit]);
$full_param_types = $param_types . "ii";
$stmt = $conn->prepare($data_sql);
$stmt->bind_param($full_param_types, ...$params_page);
$stmt->execute();
$result = $stmt->get_result();

$till_entries = [];
$page_total_sum = 0;
while ($row = $result->fetch_assoc()) {
    $till_entries[] = $row;
    $page_total_sum += floatval($row['total']);
}
?>
<div class="main-content">
    <h2>Till Money Entries</h2>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Entry Form -->
    <form method="POST" style="margin-bottom: 20px;">
        <label>Amount Received (KES):</label>
        <input type="number" step="0.01" name="amount" required>
        <label>Transaction Cost (KES):</label>
        <input type="number" step="0.01" name="transaction_cost" required>
        <button type="submit">Add Entry</button>
    </form>

    <!-- Date Filter Form with auto-submit -->
    <form method="GET" style="margin-bottom: 20px;">
        <label>Filter by Date:</label>
        <input type="date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>"
               onchange="this.form.submit()">
    </form>

    <!-- Till Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Amount Received (KES)</th>
                <th>Transaction Cost (KES)</th>
                <th>Total (KES)</th>
                <th>Date/Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($till_entries)): ?>
                <tr><td colspan="5" style="text-align: center;">No entries found for <?= htmlspecialchars($filter_date) ?>.</td></tr>
            <?php else: ?>
                <?php foreach ($till_entries as $entry): ?>
                    <tr>
                        <td style="text-align: right;"><?php echo number_format($entry['amount'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['transaction_cost'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['total'], 2); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($entry['entry_date'])); ?></td>
                        <td>
                            <a href="?delete_id=<?= $entry['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this entry?');"
                               style="
                                   background-color: #dc3545;
                                   color: white;
                                   padding: 5px 10px;
                                   text-decoration: none;
                                   border-radius: 4px;
                               ">
                               Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #fcf8e3;">
                <td colspan="2" style="text-align: right;">Total for this Page:</td>
                <td style="text-align: right;">KES <?= number_format($page_total_sum, 2) ?></td>
                <td colspan="2"></td>
            </tr>
            <tr style="font-weight: bold; background-color: #d9edf7;">
                <td colspan="2" style="text-align: right;">Total for <?= htmlspecialchars($filter_date) ?>:</td>
                <td style="text-align: right;">KES <?= number_format($day_total_sum, 2) ?></td>
                <td colspan="2"></td>
            </tr>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="2" style="text-align: right;">Total for Batch:</td>
                <td style="text-align: right;">KES <?= number_format($grand_total, 2) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination -->
    <div class="pagination" style="margin-top: 20px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
               style="margin: 0 5px; <?= $i == $page ? 'font-weight: bold;' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
