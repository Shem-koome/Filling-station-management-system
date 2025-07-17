<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
date_default_timezone_set('Africa/Nairobi');

// Fetch all deposits
$result = $conn->query("
    SELECT cd.user_email, cd.fuel, cd.spares, cd.cash_at_hand, cd.bank, cd.deposit_date, 
           fb.id AS batch_id, p.pump_number
    FROM cash_deposits cd
    LEFT JOIN fuel_batches fb ON cd.batch_id = fb.id
    LEFT JOIN pumps p ON fb.pump_id = p.id
    ORDER BY cd.deposit_date DESC
");

$deposits = [];
$total_fuel = $total_spares = $total_cash_at_hand = $total_bank = 0;

while ($row = $result->fetch_assoc()) {
    $deposits[] = $row;
    $total_fuel += floatval($row['fuel']);
    $total_spares += floatval($row['spares']);
    $total_cash_at_hand += floatval($row['cash_at_hand']);
    $total_bank += floatval($row['bank']);
}
?>

<div class="main-content">
    <h2>All Cash Deposits (Admin View)</h2>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Employee</th>
                <th>Pump</th>
                <th>Batch ID</th>
                <th>Fuel</th>
                <th>Spares</th>
                <th>Cash At Hand</th>
                <th>Bank</th>
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
                        <td style="text-align: right;"><?= number_format($deposit['fuel'], 2); ?></td>
                        <td style="text-align: right;"><?= number_format($deposit['spares'], 2); ?></td>
                        <td style="text-align: right;"><?= number_format($deposit['cash_at_hand'], 2); ?></td>
                        <td style="text-align: right;"><?= number_format($deposit['bank'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No deposits recorded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="4" style="text-align: right;">TOTALS:</td>
                <td style="text-align: right;">KES <?= number_format($total_fuel, 2); ?></td>
                <td style="text-align: right;">KES <?= number_format($total_spares, 2); ?></td>
                <td style="text-align: right;">KES <?= number_format($total_cash_at_hand, 2); ?></td>
                <td style="text-align: right;">KES <?= number_format($total_bank, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
