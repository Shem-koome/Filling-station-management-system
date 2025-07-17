<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Get selected date from GET or default to today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch expenses for the selected date, now also fetch batch_id
$stmt = $conn->prepare("SELECT user_email, expense_desc, amount, expense_date, batch_id 
                        FROM expenses 
                        WHERE DATE(expense_date) = ? 
                        ORDER BY expense_date DESC");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
$total_expenses = 0;

while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total_expenses += floatval($row['amount']);
}
?>
<div class="main-content">
    <h2>Expenses for <?php echo htmlspecialchars($selected_date); ?> (Admin View)</h2>

    <!-- Date filter form with auto submit -->
    <form method="GET" style="margin-bottom: 20px;">
        <label for="date">Select Date:</label>
        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="this.form.submit()">
    </form>

    <!-- Expenses Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Employee Email</th>
                <th>Expense Description</th>
                <th>Amount (KES)</th>
                <th>Batch ID</th>
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
                        <td style="text-align: center;"><?php echo htmlspecialchars($expense['batch_id']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center;">No expenses recorded for this date.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="4" style="text-align: right;">Total Expenses:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_expenses, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>

