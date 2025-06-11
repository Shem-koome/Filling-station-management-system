<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Fetch all till entries with pump and batch info
$result = $conn->query("SELECT tm.user_email, tm.amount, tm.transaction_cost, tm.total, tm.entry_date, p.pump_number, fb.id as batch_id FROM till_money tm LEFT JOIN pumps p ON tm.pump_id = p.id LEFT JOIN fuel_batches fb ON tm.batch_id = fb.id ORDER BY tm.entry_date DESC");

$till_entries = [];
$total_sum = 0;

while ($row = $result->fetch_assoc()) {
    $till_entries[] = $row;
    $total_sum += floatval($row['total']);
}
?>

<div class="main-content">
    <h2>All Till Money Entries (Admin View)</h2>

    <!-- Till Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Employee Email</th>
                <th>Pump</th>
                <th>Batch ID</th>
                <th>Amount Received (KES)</th>
                <th>Transaction Cost (KES)</th>
                <th>Total (KES)</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($till_entries) > 0): ?>
                <?php foreach ($till_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($entry['pump_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $entry['batch_id'] ?? 'N/A'; ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['amount'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['transaction_cost'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['total'], 2); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($entry['entry_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">No till entries recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="5" style="text-align: right;">Grand Total:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_sum, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
