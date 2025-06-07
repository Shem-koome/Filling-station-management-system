<?php
include 'header.php';
require '../authpage/db.php';

// Fetch debts
$debts = [];
$res = $conn->query("SELECT * FROM debts ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) {
    $debts[] = $row;
}
?>

<style>
.status-cleared {
    background-color: #d4edda; /* light green */
    color: #038120FF;          /* dark green */
    font-weight: bold;
    text-align: center;
}
.status-pending {
    background-color: #fff3cd; /* light yellow */
    color: #A7800EFF;          /* dark orange */
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
    <h2>All Debts (View Only)</h2>

    <table>
        <thead>
            <tr>
                <th>Debtor</th>
                <th>Description</th>
                <th>Total Debt (KES)</th>
                <th>Remaining (KES)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($debts as $debt): ?>
            <tr>
                <td><?php echo htmlspecialchars($debt['debtor_name']); ?></td>
                <td><?php echo htmlspecialchars($debt['description']); ?></td>
                <td style="text-align: right;"><?php echo number_format($debt['total_amount'], 2); ?></td>
                <td style="text-align: right;"><?php echo number_format($debt['remaining_amount'], 2); ?></td>
                <td class="<?php echo $debt['status'] === 'cleared' ? 'status-cleared' : 'status-pending'; ?>">
                    <?php echo ucfirst($debt['status']); ?>
                </td>
            </tr>
            <tr>
                <td colspan="5" style="background-color: #fafafa;">
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
                                    Paid KES <?php echo number_format($p['paid_amount'], 2); ?> on <?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?>
                                    — Remaining KES <?php echo number_format($p['remaining_after_payment'], 2); ?>
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
