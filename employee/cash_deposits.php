<?php include 'header.php'; ?>
<?php
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

// Handle deposit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount']) && $batch_id) {
    $amount = floatval($_POST['amount']);

    if ($amount > 0) {
        $stmt = $conn->prepare("INSERT INTO cash_deposits (pump_id, batch_id, user_email, amount, deposit_date) VALUES (NULL, ?, ?, ?, NOW())");
        $stmt->bind_param("isd", $batch_id, $user_email, $amount);
        $stmt->execute();
        $_SESSION['success'] = "Cash deposit recorded successfully.";
        header("Location: cash_deposits.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter a valid positive amount.";
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM cash_deposits WHERE user_email = ? AND batch_id = ?");
$count_stmt->bind_param("si", $user_email, $batch_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Get cash deposits
$stmt = $conn->prepare("SELECT amount, deposit_date FROM cash_deposits WHERE user_email = ? AND batch_id = ? ORDER BY deposit_date DESC LIMIT ?, ?");
$stmt->bind_param("siii", $user_email, $batch_id, $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();

$deposits = [];
$total_sum = 0;
while ($row = $result->fetch_assoc()) {
    $deposits[] = $row;
    $total_sum += floatval($row['amount']);
}
?>

<div class="main-content">
    <h2>Cash Deposits</h2>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Deposit Form -->
    <form method="POST" style="margin-bottom: 20px;">
        <label>Amount Deposited (KES):</label>
        <input type="number" step="0.01" name="amount" required>
        <button type="submit">Add Deposit</button>
    </form>

    <!-- Deposits Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Amount (KES)</th>
                <th>Deposit Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deposits as $deposit): ?>
                <tr>
                    <td style="text-align: right;"><?php echo number_format($deposit['amount'], 2); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($deposit['deposit_date'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td style="text-align: right;">Total:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_sum, 2); ?></td>
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
