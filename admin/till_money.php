<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
date_default_timezone_set('Africa/Nairobi');

// Handle date filter
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get till entries for the day with pagination
$stmt = $conn->prepare("
    SELECT tm.user_email, tm.amount, tm.transaction_cost, tm.total, tm.entry_date, p.pump_number, fb.id as batch_id 
    FROM till_money tm 
    LEFT JOIN pumps p ON tm.pump_id = p.id 
    LEFT JOIN fuel_batches fb ON tm.batch_id = fb.id 
    WHERE DATE(tm.entry_date) = ?
    ORDER BY tm.entry_date DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("sii", $selected_date, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$till_entries = [];
while ($row = $result->fetch_assoc()) {
    $till_entries[] = $row;
}

// Get count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total_rows FROM till_money WHERE DATE(entry_date) = ?");
$count_stmt->bind_param("s", $selected_date);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();
$total_rows = $count_result['total_rows'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Get total for current day
$daily_total_stmt = $conn->prepare("SELECT SUM(total) as daily_total FROM till_money WHERE DATE(entry_date) = ?");
$daily_total_stmt->bind_param("s", $selected_date);
$daily_total_stmt->execute();
$daily_total_result = $daily_total_stmt->get_result()->fetch_assoc();
$daily_total = $daily_total_result['daily_total'] ?? 0;

// Get grand total
$grand_total_result = $conn->query("SELECT SUM(total) as grand_total FROM till_money");
$grand_total = ($grand_total_result && $grand_total_result->num_rows > 0) ? $grand_total_result->fetch_assoc()['grand_total'] : 0;

// Totals by employee for the day
$employee_totals_stmt = $conn->prepare("
    SELECT user_email, SUM(total) as total_per_employee
    FROM till_money
    WHERE DATE(entry_date) = ?
    GROUP BY user_email
    ORDER BY total_per_employee DESC
");
$employee_totals_stmt->bind_param("s", $selected_date);
$employee_totals_stmt->execute();
$employee_totals_result = $employee_totals_stmt->get_result();

$employee_totals = [];
while ($row = $employee_totals_result->fetch_assoc()) {
    $employee_totals[] = $row;
}
?>

<div class="main-content">
    <h2>All Till Money Entries (Admin View)</h2>

    <!-- Date Filter -->
    <form method="GET" style="margin-bottom: 20px;">
        <label for="date">Select Date: </label>
        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($selected_date); ?>" onchange="this.form.submit()">
    </form>

    <!-- Totals by Employee -->
    <?php if (count($employee_totals) > 0): ?>
        <h3>Totals by Employee on <?php echo htmlspecialchars($selected_date); ?></h3>
        <table border="1" cellpadding="8" cellspacing="0" style="width:50%; border-collapse: collapse; margin-bottom: 30px;">
            <thead style="background-color: #f9f9f9;">
                <tr>
                    <th>Employee Email</th>
                    <th>Total (KES)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employee_totals as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['user_email']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($emp['total_per_employee'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Till Entries Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Employee Email</th>
                <th>Pump</th>
                <th>Batch ID</th>
                <th>Amount Received (KES)</th>
                <th>Transaction Cost (KES)</th>
                <th>Total (KES)</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($till_entries) > 0): ?>
                <?php foreach ($till_entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['user_email']); ?></td>
                        <td><?php echo htmlspecialchars($entry['pump_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $entry['batch_id'] ?? 'N/A'; ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['amount'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['transaction_cost'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['total'], 2); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($entry['entry_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center;">No till entries recorded for this date.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #dff0d8;">
                <td colspan="5" style="text-align: right;">Total for <?php echo htmlspecialchars($selected_date); ?>:</td>
                <td style="text-align: right;">KES <?php echo number_format($daily_total, 2); ?></td>
                <td></td>
            </tr>
            <tr style="font-weight: bold; background-color: #d9edf7;">
                <td colspan="5" style="text-align: right;">Grand Total (All Dates):</td>
                <td style="text-align: right;">KES <?php echo number_format($grand_total, 2); ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination Links -->
    <div style="margin-top: 20px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?date=<?php echo urlencode($selected_date); ?>&page=<?php echo $i; ?>"
               style="margin-right:5px; padding:5px 10px; <?php echo ($i == $page) ? 'background:#428bca;color:white;' : 'border:1px solid #ddd;'; ?>">
               <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
