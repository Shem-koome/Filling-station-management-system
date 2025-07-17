<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
date_default_timezone_set('Africa/Nairobi');

$user_email = $_SESSION['email'];

// Handle date filter
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get active batch
$batch_stmt = $conn->prepare("SELECT id FROM fuel_batches WHERE remaining_liters > 0 AND is_closed = 0 ORDER BY start_date DESC LIMIT 1");
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
$active_batch = $batch_result->fetch_assoc();
$batch_id = $active_batch ? $active_batch['id'] : null;

if (!$batch_id) {
    $_SESSION['error'] = "No active fuel batch found.";
}

// Fetch fuel from fuel_readings
$fuel_stmt = $conn->prepare("
    SELECT (IFNULL(evening_sales, 0) - IFNULL(morning_sales, 0)) AS sales_made 
    FROM fuel_readings 
    WHERE DATE(reading_date) = ? 
    LIMIT 1
");
$fuel_stmt->bind_param("s", $selected_date);
$fuel_stmt->execute();
$fuel_result = $fuel_stmt->get_result()->fetch_assoc();
$fuel = $fuel_result ? floatval($fuel_result['sales_made']) : 0;

// Fetch total till for the day
$till_stmt = $conn->prepare("
    SELECT SUM(total) as total_till 
    FROM till_money 
    WHERE DATE(entry_date) = ?
");
$till_stmt->bind_param("s", $selected_date);
$till_stmt->execute();
$till_result = $till_stmt->get_result()->fetch_assoc();
$till = $till_result ? floatval($till_result['total_till']) : 0;

// Fetch total expenses for the day
$expense_stmt = $conn->prepare("
    SELECT SUM(amount) as total_expenses 
    FROM expenses 
    WHERE DATE(expense_date) = ?
");
$expense_stmt->bind_param("s", $selected_date);
$expense_stmt->execute();
$expense_result = $expense_stmt->get_result()->fetch_assoc();
$expenses = $expense_result ? floatval($expense_result['total_expenses']) : 0;

// Fetch total spares sold for the day
$spares_stmt = $conn->prepare("
    SELECT SUM(total_price) as total_spares 
    FROM spares_sold 
    WHERE DATE(sale_date) = ?
");
$spares_stmt->bind_param("s", $selected_date);
$spares_stmt->execute();
$spares_result = $spares_stmt->get_result()->fetch_assoc();
$spares = $spares_result ? floatval($spares_result['total_spares']) : 0;

// Calculate cash at hand and bank
$cash_at_hand = $fuel - ($till + $expenses);
if ($cash_at_hand < 0) $cash_at_hand = 0;

$bank = $cash_at_hand + $spares;

// Handle save deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $batch_id) {
    $current_date = date('Y-m-d');

    // Check for existing deposit for the day and batch
    $check = $conn->prepare("SELECT id FROM cash_deposits WHERE DATE(deposit_date) = ? AND batch_id = ?");
    $check->bind_param("si", $current_date, $batch_id);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows == 0) {
        // No existing deposit, insert new one
        $stmt = $conn->prepare("
            INSERT INTO cash_deposits (pump_id, batch_id, user_email, fuel, spares, cash_at_hand, bank, deposit_date)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iissddd", $pump_id,$batch_id, $user_email, $fuel, $spares, $cash_at_hand, $bank);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Deposit saved successfully.";
        } else {
            $_SESSION['error'] = "Failed to save deposit.";
        }
    } else {
        $_SESSION['error'] = "A deposit has already been recorded for this batch today.";
    }

    header("Location: cash_deposits.php?date=$selected_date");
    exit();
}

?>

<div class="main-content">
    <h3>💰 Cash Deposits Summary</h3>

    <!-- Date filter -->
    <div style="margin-bottom: 20px;">
        <label><strong>Select Date:</strong></label>
        <input type="date" value="<?= $selected_date ?>" onchange="window.location.href='cash_deposits.php?date=' + this.value;">
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div style="display: flex; gap: 50px;">
        <!-- Left Container -->
        <div style="flex: 1;">
            <div style="margin-bottom: 15px;">
                <strong>Fuel Sales:</strong>
                <div style="padding: 8px; background: #f2f2f2;">
                    KES <?= number_format($fuel, 2) ?>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Till Money:</strong>
                <div style="padding: 8px; background: #f2f2f2;">
                    KES <?= number_format($till, 2) ?>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Expenses:</strong>
                <div style="padding: 8px; background: #f2f2f2;">
                    KES <?= number_format($expenses, 2) ?>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Cash at Hand:</strong>
                <div style="padding: 8px; background: #dff0d8;">
                    KES <?= number_format($cash_at_hand, 2) ?>
                </div>
            </div>

            <form method="POST">
                <button type="submit" style="padding: 10px 20px;">Save Deposit</button>
            </form>
        </div>

        <!-- Right Container -->
        <div style="flex: 1;">
            <h4>🏦 Bank Summary</h4>
            <div style="margin-bottom: 15px;">
                <strong>Cash at Hand:</strong>
                <div style="padding: 8px; background: #f2f2f2;">
                    KES <?= number_format($cash_at_hand, 2) ?>
                </div>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Spares Sold:</strong>
                <div style="padding: 8px; background: #f2f2f2;">
                    KES <?= number_format($spares, 2) ?>
                </div>
            </div>
            <div>
                <strong>Total Bank:</strong>
                <div style="padding: 8px; background: #dff0d8;">
                    KES <?= number_format($bank, 2) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
