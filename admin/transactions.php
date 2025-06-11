<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_GET['batch_id']) || !is_numeric($_GET['batch_id'])) {
    echo "<p>Invalid batch ID.</p>";
    include 'footer.php';
    exit;
}

$batch_id = (int)$_GET['batch_id'];

// Fetch batch info
$batch_stmt = $conn->prepare("SELECT fb.*, p.pump_number, p.fuel_type FROM fuel_batches fb JOIN pumps p ON fb.pump_id = p.id WHERE fb.id = ?");
$batch_stmt->bind_param("i", $batch_id);
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
$batch = $batch_result->fetch_assoc();

if (!$batch) {
    echo "<p>Batch not found.</p>";
    include 'footer.php';
    exit;
}

// Fetch all transactions for this batch
$txn_stmt = $conn->prepare("SELECT * FROM transactions WHERE batch_id = ? ORDER BY updated_at DESC");
$txn_stmt->bind_param("i", $batch_id);
$txn_stmt->execute();
$transactions = $txn_stmt->get_result();

$total_cash = $total_till = $total_debt = 0;
?>

<div class="main-content">
    <h2>Transactions for Batch ID: <?= $batch_id ?></h2>
    <p><strong>Date:</strong> <?= $batch['start_date'] ?> | <strong>Pump:</strong> <?= $batch['pump_number'] ?> | <strong>Fuel:</strong> <?= $batch['fuel_type'] ?></p>

    <?php if ($transactions->num_rows === 0): ?>
        <p>No transactions found for this batch.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse: collapse;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th>Batch ID</th>
                    <th>User Email</th>
                    <th>Type</th>
                    <th>Amount (KES)</th>
                    <th>Description</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $transactions->fetch_assoc()): ?>
                    <?php
                        if ($row['type'] === 'cash') $total_cash += $row['amount'];
                        if ($row['type'] === 'till') $total_till += $row['amount'];
                        if ($row['type'] === 'debt') $total_debt += $row['amount'];
                    ?>
                    <tr>
                        <td><?= $row['batch_id'] ?></td>
                        <td><?= htmlspecialchars($row['user_email']) ?></td>
                        <td><?= ucfirst($row['type']) ?></td>
                        <td style="text-align:right;">KES <?= number_format($row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= $row['updated_at'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <br>
        <h4>Total Summary:</h4>
        <ul>
            <li>Total Cash: KES <?= number_format($total_cash, 2) ?></li>
            <li>Total Till: KES <?= number_format($total_till, 2) ?></li>
            <li>Total Debt: KES <?= number_format($total_debt, 2) ?></li>
        </ul>
    <?php endif; ?>

    <a href="batch_history.php">&larr; Back to Batch History</a>
</div>

<?php include 'footer.php'; ?>
