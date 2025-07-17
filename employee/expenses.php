<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
date_default_timezone_set('Africa/Nairobi');

$user_email = $_SESSION['email'];

// Selected date or default to today
$filter_date = $_GET['date'] ?? date('Y-m-d');

// Check if there is an open batch
$batch_stmt = $conn->query("SELECT id FROM fuel_batches WHERE remaining_liters > 0 AND is_closed = 0 LIMIT 1");
$batch_row = $batch_stmt->fetch_assoc();
$current_batch_id = $batch_row ? $batch_row['id'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expense_desc']) && isset($_POST['amount'])) {
    $expense_desc = trim($_POST['expense_desc']);
    $amount = floatval($_POST['amount']);

    if ($expense_desc !== '' && $amount > 0) {
        if ($current_batch_id) {
            $stmt = $conn->prepare("INSERT INTO expenses (user_email, expense_desc, amount, expense_date, batch_id) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("ssdi", $user_email, $expense_desc, $amount, $current_batch_id);
            $stmt->execute();
            $_SESSION['success'] = "Expense recorded successfully.";
        } else {
            $_SESSION['error'] = "Cannot add expense. No open fuel batch available.";
        }
        header("Location: expenses.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter valid expense description and amount.";
    }
}

// Fetch expenses for that date
$stmt = $conn->prepare("SELECT expense_desc, amount, expense_date, batch_id 
                        FROM expenses 
                        WHERE user_email = ? AND DATE(expense_date) = ?
                        ORDER BY expense_date DESC");
$stmt->bind_param("ss", $user_email, $filter_date);
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
    <h2>Expenses</h2>

    <!-- Date filter -->
    <div style="margin-bottom: 20px;">
        <label>Filter by Date: 
            <input type="date" id="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
        </label>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($current_batch_id): ?>
        <!-- Expense Entry Form -->
        <form method="POST" style="margin-bottom: 20px;">
            <label>Expense Description:</label><br>
            <input type="text" name="expense_desc" required style="width: 300px;"><br><br>

            <label>Amount (KES):</label><br>
            <input type="number" name="amount" step="0.01" required><br><br>

            <button type="submit">Add Expense</button>
        </form>
    <?php else: ?>
        <div style="color: red; margin-bottom: 20px;">
            Cannot add expenses. No open fuel batch available.
        </div>
    <?php endif; ?>

    <!-- Expenses Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Expense Description</th>
                <th>Amount (KES)</th>
                <th>Batch ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No expenses recorded for <?= htmlspecialchars($filter_date) ?>.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($expense['expense_date'])); ?></td>
                    <td><?php echo htmlspecialchars($expense['expense_desc']); ?></td>
                    <td style="text-align: right;"><?php echo number_format($expense['amount'], 2); ?></td>
                    <td style="text-align: center;"><?php echo $expense['batch_id']; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="2" style="text-align: right;">Total Expenses:</td>
                <td style="text-align: right;">KES <?php echo number_format($total_expenses, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
document.getElementById('filter_date').addEventListener('change', function() {
    window.location.href = 'expenses.php?date=' + this.value;
});
</script>

<?php include 'footer.php'; ?>
