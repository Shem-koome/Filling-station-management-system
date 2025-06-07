<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Fetch all deposits
$result = $conn->query("SELECT user_email, amount, deposit_date FROM cash_deposits ORDER BY deposit_date DESC");

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
                <th>Amount Deposited (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($deposits) > 0): ?>
                <?php foreach ($deposits as $deposit): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($deposit['deposit_date'])); ?></td>
                        <td><?php echo htmlspecialchars($deposit['user_email']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($deposit['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No deposits recorded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="2">Total Deposited</td>
                <td style="text-align: right;">KES <?php echo number_format($total_amount, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
