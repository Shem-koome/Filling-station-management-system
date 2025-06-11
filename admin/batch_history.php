<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$filter_date = $_GET['date'] ?? '';
$filter_pump = $_GET['pump'] ?? '';
$filter_cost = $_GET['transaction_cost'] ?? '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build filters
$conditions = '';
$params = [];
$types = '';

if (!empty($filter_date)) {
    $conditions .= " AND b.start_date = ?";
    $params[] = $filter_date;
    $types .= 's';
}
if (!empty($filter_pump)) {
    $conditions .= " AND p.pump_number = ?";
    $params[] = $filter_pump;
    $types .= 's';
}
if (!empty($filter_cost)) {
    $conditions .= " AND t.amount = ?";
    $params[] = $filter_cost;
    $types .= 'd';
}

// Total count for pagination
$count_sql = "SELECT COUNT(DISTINCT b.id) as total 
              FROM fuel_batches b 
              JOIN pumps p ON b.pump_id = p.id 
              LEFT JOIN transactions t ON t.batch_id = b.id 
              WHERE 1 $conditions";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_batches = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_batches / $limit);

// Fetch batch data with pump info
$sql = "SELECT b.*, p.pump_number, p.fuel_type 
        FROM fuel_batches b 
        JOIN pumps p ON b.pump_id = p.id 
        WHERE 1 $conditions 
        ORDER BY b.start_date DESC 
        LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$batches = [];
while ($row = $result->fetch_assoc()) {
    $batches[] = $row;
}
?>

<div class="main-content">
    <h2>Fuel Batch History</h2>

    <form method="get" class="filters" style="margin-bottom: 20px;">
        <label>Date: <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"></label>
        <label>Pump Number: <input type="text" name="pump" placeholder="e.g. Pump 1" value="<?= htmlspecialchars($filter_pump) ?>"></label>
        <label>Transaction Amount: <input type="number" step="0.01" name="transaction_cost" value="<?= htmlspecialchars($filter_cost) ?>"></label>
        <button type="submit">Apply Filters</button>
    </form>

    <?php if (empty($batches)): ?>
        <p>No batch records found.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th>Batch ID</th>
                    <th>Start Date</th>
                    <th>Pump Number</th>
                    <th>Fuel Type</th>
                    <th>Start Litres</th>
                    <th>Remaining Litres</th>
                    <th>Price Per Litre</th>
                    <th>Closed</th>
                    <th>Transactions (KES)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grand_total_cash = $grand_total_till = $grand_total_debt = 0;
                foreach ($batches as $batch):
                    $batch_id = $batch['id'];

                    // Sum transactions for the batch
                    $txn_stmt = $conn->prepare("SELECT type, SUM(amount) as total FROM transactions WHERE batch_id = ? GROUP BY type");
                    $txn_stmt->bind_param("i", $batch_id);
                    $txn_stmt->execute();
                    $txn_result = $txn_stmt->get_result();

                    $totals = ['cash' => 0, 'till' => 0, 'debt' => 0];
                    while ($txn = $txn_result->fetch_assoc()) {
                        $totals[$txn['type']] = $txn['total'];
                    }

                    $grand_total_cash += $totals['cash'];
                    $grand_total_till += $totals['till'];
                    $grand_total_debt += $totals['debt'];
                ?>
                <tr>
                    <td><?= $batch_id ?></td>
                    <td><?= $batch['start_date'] ?></td>
                    <td><?= htmlspecialchars($batch['pump_number']) ?></td>
                    <td><?= htmlspecialchars($batch['fuel_type']) ?></td>
                    <td style="text-align:right;"><?= number_format($batch['start_liters'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($batch['remaining_liters'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($batch['price_per_liter'], 2) ?></td>
                    <td><?= $batch['is_closed'] ? 'Yes' : 'No' ?></td>
                    <td>
                        Cash: KES <?= number_format($totals['cash'], 2) ?><br>
                        Till: KES <?= number_format($totals['till'], 2) ?><br>
                        Debt: KES <?= number_format($totals['debt'], 2) ?>
                    </td>
                    <td><a href="transactions.php?batch_id=<?= $batch_id ?>">View Transactions</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <br>
        <h4>Totals:</h4>
        <ul>
            <li>Total Cash: KES <?= number_format($grand_total_cash, 2) ?></li>
            <li>Total Till: KES <?= number_format($grand_total_till, 2) ?></li>
            <li>Total Debt: KES <?= number_format($grand_total_debt, 2) ?></li>
        </ul>

        <!-- Pagination -->
        <div class="pagination" style="margin-top: 20px;">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&date=<?= urlencode($filter_date) ?>&pump=<?= urlencode($filter_pump) ?>&transaction_cost=<?= urlencode($filter_cost) ?>"
                   style="margin: 0 5px; <?= $i == $page ? 'font-weight: bold;' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
