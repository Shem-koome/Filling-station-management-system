<?php
include 'header.php';
require '../authpage/db.php';

// Get current open batch
$batch_stmt = $conn->prepare("SELECT id FROM fuel_batches WHERE remaining_liters > 0 AND is_closed = 0 ORDER BY start_date DESC LIMIT 1");
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
$active_batch = $batch_result->fetch_assoc();
$batch_id = $active_batch ? $active_batch['id'] : null;

// Handle new debt submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_debt'])) {
    $debtor_name = trim($_POST['debtor_name']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);

    if ($debtor_name && $description && $amount > 0 && $batch_id !== null) {
        $stmt = $conn->prepare("INSERT INTO debts (debtor_name, description, total_amount, remaining_amount, status, batch_id) VALUES (?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("ssddi", $debtor_name, $description, $amount, $amount, $batch_id);
        $stmt->execute();
        $_SESSION['success'] = "New debt added successfully.";
        header("Location: debts.php");
        exit();
    } else {
        $_SESSION['error'] = "Please fill in all fields with valid values, and ensure there is an open batch.";
    }
}

// Handle partial payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_debt'])) {
    $debt_id = intval($_POST['debt_id']);
    $payment = floatval($_POST['payment_amount']);

    $stmt = $conn->prepare("SELECT remaining_amount FROM debts WHERE id = ?");
    $stmt->bind_param("i", $debt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $debt = $result->fetch_assoc();

    if ($debt && $payment > 0 && $payment <= $debt['remaining_amount']) {
        $new_remaining = $debt['remaining_amount'] - $payment;

        // Insert into debts_paid
        $stmt = $conn->prepare("INSERT INTO debts_paid (debt_id, paid_amount, remaining_after_payment) VALUES (?, ?, ?)");
        $stmt->bind_param("idd", $debt_id, $payment, $new_remaining);
        $stmt->execute();

        $status = ($new_remaining == 0) ? 'cleared' : 'pending';
        $stmt = $conn->prepare("UPDATE debts SET remaining_amount = ?, status = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_remaining, $status, $debt_id);
        $stmt->execute();

        $_SESSION['success'] = "Payment recorded successfully.";
        header("Location: debts.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid payment amount.";
    }
}

// Fetch debts
$debts = [];
$res = $conn->query("SELECT debts.*, fb.start_date FROM debts LEFT JOIN fuel_batches fb ON debts.batch_id = fb.id ORDER BY created_at DESC");
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
.message-success {
    color: green;
    margin-bottom: 15px;
}
.message-error {
    color: red;
    margin-bottom: 15px;
}
</style>

<div class="main-content">
    <h2>Debts Management</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="message-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php elseif (isset($_SESSION['error'])): ?>
        <div class="message-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" style="margin-bottom: 30px;">
        <h3>Add New Debt</h3>
        <label>Debtor Name:</label><br>
        <input type="text" name="debtor_name" required><br><br>

        <label>Description:</label><br>
        <input type="text" name="description" required><br><br>

        <label>Amount:</label><br>
        <input type="number" step="0.01" name="amount" min="0.01" required><br><br>

        <button type="submit" name="new_debt">Add Debt</button><br>
        <small>Assigned to Batch ID: <?php echo $batch_id ?? 'No Open Batch'; ?></small>
    </form>

    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Debtor</th>
                <th>Description</th>
                <th>Total (KES)</th>
                <th>Remaining (KES)</th>
                <th>Batch ID</th>
                <th>Status</th>
                <th>Make Payment</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($debts as $debt): ?>
            <tr>
                <td><?php echo htmlspecialchars($debt['debtor_name']); ?></td>
                <td><?php echo htmlspecialchars($debt['description']); ?></td>
                <td style="text-align: right;"><?php echo number_format($debt['total_amount'], 2); ?></td>
                <td style="text-align: right;"><?php echo number_format($debt['remaining_amount'], 2); ?></td>
                <td style="text-align: center;"><?php echo $debt['batch_id'] ?? '—'; ?></td>
                <td class="<?php echo $debt['status'] === 'cleared' ? 'status-cleared' : 'status-pending'; ?>">
                    <?php echo ucfirst($debt['status']); ?>
                </td>
                <td>
                    <?php if ($debt['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="debt_id" value="<?php echo $debt['id']; ?>">
                            <input type="number" step="0.01" name="payment_amount" min="0.01" max="<?php echo $debt['remaining_amount']; ?>" placeholder="Amount" required style="width:100px;">
                            <button type="submit" name="pay_debt">Pay</button>
                        </form>
                    <?php else: ?>
                        Cleared
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td colspan="7" style="background-color: #fafafa;">
                    <strong>Payment History:</strong>
                    <ul style="margin: 5px 0 15px 20px; padding: 0; list-style-type: disc;">
                    <?php
                    $stmt = $conn->prepare("SELECT paid_amount, payment_date, remaining_after_payment FROM debts_paid WHERE debt_id = ? ORDER BY payment_date DESC");
                    $stmt->bind_param("i", $debt['id']);
                    $stmt->execute();
                    $payments = $stmt->get_result();

                    if ($payments->num_rows > 0):
                        while ($p = $payments->fetch_assoc()): ?>
                            <li>
                                Paid KES <?php echo number_format($p['paid_amount'], 2); ?> on <?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?> — Remaining KES <?php echo number_format($p['remaining_after_payment'], 2); ?>
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
