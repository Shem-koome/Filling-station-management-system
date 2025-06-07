<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$user_email = $_SESSION['email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = floatval($_POST['amount']);
    if ($amount > 0) {
        $stmt = $conn->prepare("INSERT INTO cash_deposits (user_email, amount, deposit_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("sd", $user_email, $amount);
        $stmt->execute();
        $_SESSION['success'] = "Deposit recorded successfully.";
        header("Location: cash_deposits.php");
        exit();
    } else {
        $_SESSION['error'] = "Please enter a valid amount.";
    }
}

// Fetch deposits
$stmt = $conn->prepare("SELECT amount, deposit_date FROM cash_deposits WHERE user_email = ? ORDER BY deposit_date DESC");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();

$deposits = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    $deposits[] = $row;
    $total_amount += floatval($row['amount']);
}
?>

<div class="main-content">
    <h2>Cash Deposits</h2>

    <!-- Success/Error Message -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Deposit Form -->
    <form method="POST" style="margin-bottom: 20px;">
        <label>Amount to Deposit (KES):</label>
        <input type="number" name="amount" step="0.01" required>
        <button type="submit">Submit</button>
    </form>

    <!-- Deposit Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Date/Time</th>
                <th>Money Deposited (KES)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deposits as $deposit): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($deposit['deposit_date'])); ?></td>
                    <td style="text-align: right;"><?php echo number_format($deposit['amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td>Total</td>
                <td style="text-align: right;">KES<?php echo number_format($total_amount, 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>
