<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$user_email = $_SESSION['email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expense_desc']) && isset($_POST['amount'])) {
    $expense_desc = trim($_POST['expense_desc']);
    $amount = floatval($_POST['amount']);

    if ($expense_desc !== '' && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO expenses (user_email, expense_desc, amount, expense_date) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssd", $user_email, $expense_desc, $amount);
        $stmt->execute();
        $_SESSION['success'] = "Expense recorded successfully.";
        header("Location: expenses.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter valid expense description and amount.";
    }
}

// Fetch expenses
$stmt = $conn->prepare("SELECT expense_desc, amount, expense_date FROM expenses WHERE user_email = ? ORDER BY expense_date DESC");
$stmt->bind_param("s", $user_email);
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

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Expense Entry Form -->
    <form method="POST" style="margin-bottom: 20px;">
        <label>Expense Description:</label><br>
        <input type="text" name="expense_desc" required style="width: 300px;"><br><br>

        <label>Amount (KES):</label><br>
        <input type="number" name="amount" step="0.01" required><br><br>

        <button type="submit">Add Expense</button>
    </form>

    <!-- Expenses Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Expense Description</th>
                <th>Amount (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($expense['expense_date'])); ?></td>
                    <td><?php echo htmlspecialchars($expense['expense_desc']); ?></td>
                    <td style="text-align: right;"><?php echo number_format($expense['amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="2" style="text-align: right;">Total Expenses:</td>
                <td style="text-align: right;">KES<?php echo number_format($total_expenses, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
