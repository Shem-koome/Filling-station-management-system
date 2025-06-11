<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Fetch debts with batch and pump info
$debts = [];
$res = $conn->query("
    SELECT d.*, fb.id AS batch_id, p.pump_number
    FROM debts d
    LEFT JOIN fuel_batches fb ON d.batch_id = fb.id
    LEFT JOIN pumps p ON fb.pump_id = p.id
    ORDER BY d.created_at DESC
");
while ($row = $res->fetch_assoc()) {
    $debts[] = $row;
}
?>

<style>
.status-cleared {
    background-color: #d4edda;
    color: #038120FF;
    font-weight: bold;
    text-align: center;
}
.status-pending {
    background-color: #fff3cd;
    color: #A7800EFF;
    font-weight: bold;
    text-align: center;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 40px;
}
th, td {
    border: 1px solid #ccc;
    padding: 8px;
}
thead {
    background-color: #f2f2f2;
}
ul {
    margin: 5px 0 15px 20px;
    padding: 0;
    list-style-type: disc;
}
</style>

<div class="main-content">
    <h2>All Debts (Admin View)</h2>

    <table>
        <thead>
            <tr>
                <th>Debtor</th>
                <th>Description</th>
                <th>Pump</th>
                <th>Batch ID</th>
                <th>Total Debt (KES)</th>
                <th>Remaining (KES)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($debts as $debt): ?>
            <tr>
                <td><?= htmlspecialchars($debt['debtor_name']); ?></td>
                <td><?= htmlspecialchars($debt['description']); ?></td>
                <td><?= $debt['pump_number'] ?? 'N/A'; ?></td>
                <td><?= $debt['batch_id'] ?? 'N/A'; ?></td>
                <td style="text-align: right;"><?= number_format($debt['total_amount'], 2); ?></td>
                <td style="text-align: right;"><?= number_format($debt['remaining_amount'], 2); ?></td>
                <td class="<?= $debt['status'] === 'cleared' ? 'status-cleared' : 'status-pending'; ?>">
                    <?= ucfirst($debt['status']); ?>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="background-color: #fafafa;">
                    <strong>Payment History:</strong>
                    <ul>
                        <?php
                        $stmt = $conn->prepare("SELECT paid_amount, payment_date, remaining_after_payment FROM debts_paid WHERE debt_id = ? ORDER BY payment_date DESC");
                        $stmt->bind_param("i", $debt['id']);
                        $stmt->execute();
                        $payments = $stmt->get_result();

                        if ($payments->num_rows > 0):
                            while ($p = $payments->fetch_assoc()): ?>
                                <li>
                                    Paid KES <?= number_format($p['paid_amount'], 2); ?> on <?= date('Y-m-d H:i', strtotime($p['payment_date'])); ?>
                                    — Remaining KES <?= number_format($p['remaining_after_payment'], 2); ?>
                                </li>
                            <?php endwhile;
                        else: ?>
                            <em>No payments made yet.</em>
                        <?php endif; ?>
                    </ul>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>
