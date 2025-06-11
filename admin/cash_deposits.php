<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Fetch deposits with batch and pump info
$result = $conn->query("
    SELECT cd.user_email, cd.amount, cd.deposit_date, 
           fb.id AS batch_id, p.pump_number
    FROM cash_deposits cd
    LEFT JOIN fuel_batches fb ON cd.batch_id = fb.id
    LEFT JOIN pumps p ON fb.pump_id = p.id
    ORDER BY cd.deposit_date DESC
");

$deposits = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $deposits[] = $row;
    $total_amount += floatval($row['amount']);
}
?>

<div class="main-content">
    <h2>All Cash Deposits (Admin View)</h2>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Employee Email</th>
                <th>Pump</th>
                <th>Batch ID</th>
                <th>Amount Deposited (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($deposits) > 0): ?>
                <?php foreach ($deposits as $deposit): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i:s', strtotime($deposit['deposit_date'])); ?></td>
                        <td><?= htmlspecialchars($deposit['user_email']); ?></td>
                        <td><?= $deposit['pump_number'] ?? 'N/A'; ?></td>
                        <td><?= $deposit['batch_id'] ?? 'N/A'; ?></td>
                        <td style="text-align: right;"><?= number_format($deposit['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No deposits recorded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="4">Total Deposited</td>
                <td style="text-align: right;">KES <?= number_format($total_amount, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
