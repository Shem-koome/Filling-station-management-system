<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Fetch all expenses
$result = $conn->query("SELECT user_email, expense_desc, amount, expense_date FROM expenses ORDER BY expense_date DESC");

$expenses = [];
$total_expenses = 0;

while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total_expenses += floatval($row['amount']);
}
?>

<div class="main-content">
    <h2>All Expenses (Admin View)</h2>

    <!-- Expenses Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Employee Email</th>
                <th>Expense Description</th>
                <th>Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($expenses) > 0): ?>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($expense['expense_date'])); ?></td>
                        <td><?php echo htmlspecialchars($expense['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($expense['expense_desc']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($expense['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;">No expenses recorded yet.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="3" style="text-align: right;">Total Expenses:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_expenses, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
