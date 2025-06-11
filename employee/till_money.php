<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$user_email = $_SESSION['email'];

// Get the active batch (global open batch)
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
    $total = $amount + $transaction_cost;

    if ($total > 0) {
        $stmt = $conn->prepare("INSERT INTO till_money (user_email, batch_id, amount, transaction_cost, total, entry_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("siddd", $user_email, $batch_id, $amount, $transaction_cost, $total);
        $stmt->execute();
        $_SESSION['success'] = "Till entry recorded successfully.";
        header("Location: till_money.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter valid amount and transaction cost.";
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Total entries count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM till_money WHERE user_email = ? AND batch_id = ?");
$count_stmt->bind_param("si", $user_email, $batch_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch till entries
$stmt = $conn->prepare("SELECT amount, transaction_cost, total, entry_date FROM till_money WHERE user_email = ? AND batch_id = ? ORDER BY entry_date DESC LIMIT ?, ?");
$stmt->bind_param("siii", $user_email, $batch_id, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$till_entries = [];
$total_sum = 0;
while ($row = $result->fetch_assoc()) {
    $till_entries[] = $row;
    $total_sum += floatval($row['total']);
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

    <!-- Till Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Amount Received (KES)</th>
                <th>Transaction Cost (KES)</th>
                <th>Total (KES)</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($till_entries as $entry): ?>
                <tr>
                    <td style="text-align: right;"><?php echo number_format($entry['amount'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($entry['transaction_cost'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($entry['total'], 2); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($entry['entry_date'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="2" style="text-align: right;">Total:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_sum, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination -->
    <div class="pagination" style="margin-top: 20px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>" style="margin: 0 5px; <?= $i == $page ? 'font-weight: bold;' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
