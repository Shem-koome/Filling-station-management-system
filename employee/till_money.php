<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$user_email = $_SESSION['email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_cost']) && isset($_POST['amount'])) {
    $transaction_cost = floatval($_POST['transaction_cost']);
    $amount = floatval($_POST['amount']);
    $total = $transaction_cost + $amount;

    if ($total > 0) {
        $stmt = $conn->prepare("INSERT INTO till_money (user_email, transaction_cost, amount, total, entry_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sddd", $user_email, $transaction_cost, $amount, $total);
        $stmt->execute();
        $_SESSION['success'] = "Till entry recorded successfully.";
        header("Location: till_money.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter valid transaction cost and amount.";
    }
}

// Fetch till entries
$stmt = $conn->prepare("SELECT * FROM till_money WHERE user_email = ? ORDER BY entry_date DESC");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

$till_entries = [];
$total_sum = 0;

while ($row = $result->fetch_assoc()) {
    $till_entries[] = $row;
    $total_sum += floatval($row['total']);
}
?>

<div class="main-content">
    <h2>Till Money Entries</h2>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Entry Form -->
    <form method="POST" style="margin-bottom: 20px;">
               <label>Amount Received (KES):</label>
        <input type="number" step="0.01" name="amount" required>

         <label>Transaction Cost (KES):</label>
        <input type="number" step="0.01" name="transaction_cost" required>

        <button type="submit">Add Entry</button>
    </form>

    <!-- Till Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Amount Received (KES)</th>
                <th>Total (KES)</th>
                <th>Transaction Cost (KES)</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($till_entries as $entry): ?>
                <tr>
                    <td style="text-align: right;"><?php echo number_format($entry['amount'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($entry['transaction_cost'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format($entry['total'], 2); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($entry['entry_date'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="3" style="text-align: right;">Total Sum:</td>
                <td style="text-align: right;">KES<?php echo number_format($total_sum, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
