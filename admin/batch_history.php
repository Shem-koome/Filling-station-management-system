<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
date_default_timezone_set('Africa/Nairobi');

// Fetch all batches with pump info
$sql = "SELECT b.*, p.pump_number, p.fuel_type
        FROM fuel_batches b
        JOIN pumps p ON b.pump_id = p.id
        ORDER BY b.start_date DESC";
$result = $conn->query($sql);

$batches = [];
while ($row = $result->fetch_assoc()) {
    $batch_id = $row['id'];
    $pump_id = $row['pump_id'];
    $start_date = $row['start_date'];

    // Total litres sold
    $stmt = $conn->prepare("SELECT SUM(evening_liters - morning_liters) AS litres_sold
                            FROM fuel_readings
                            WHERE pump_id = ? AND reading_date >= ?");
    $stmt->bind_param("is", $pump_id, $start_date);
    $stmt->execute();
    $litres_result = $stmt->get_result()->fetch_assoc();
    $total_litres_sold = $litres_result['litres_sold'] ?? 0;

    // Remaining litres
    $remaining_litres = $row['start_liters'] - $total_litres_sold;

    // Total cash deposits
    $cash_stmt = $conn->prepare("SELECT COALESCE(SUM(cash_at_hand),0) AS total_cash FROM cash_deposits WHERE batch_id = ?");
    $cash_stmt->bind_param("i", $batch_id);
    $cash_stmt->execute();
    $total_cash = $cash_stmt->get_result()->fetch_assoc()['total_cash'] ?? 0;

    // Total till money
    $till_stmt = $conn->prepare("SELECT COALESCE(SUM(total),0) AS total_till FROM till_money WHERE batch_id = ?");
    $till_stmt->bind_param("i", $batch_id);
    $till_stmt->execute();
    $total_till = $till_stmt->get_result()->fetch_assoc()['total_till'] ?? 0;

    // Total expenses
    $exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total_expenses FROM expenses WHERE batch_id = ?");
    $exp_stmt->bind_param("i", $batch_id);
    $exp_stmt->execute();
    $total_expenses = $exp_stmt->get_result()->fetch_assoc()['total_expenses'] ?? 0;

    $batches[] = [
        'batch_id' => $batch_id,
        'pump_number' => $row['pump_number'],
        'fuel_type' => $row['fuel_type'],
        'start_liters' => $row['start_liters'],
        'remaining_litres' => $remaining_litres,
        'price_per_litre' => $row['price_per_liter'],
        'total_litres_sold' => $total_litres_sold,
        'total_cash' => $total_cash,
        'total_till' => $total_till,
        'total_expenses' => $total_expenses,
    ];
}
?>

<div class="main-content">
    <h2>Fuel Batch History Overview</h2>

    <?php if (empty($batches)): ?>
        <p>No batch records found.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th>Batch ID</th>
                    <th>Pump</th>
                    <th>Fuel Type</th>
                    <th>Start Litres</th>
                    <th>Remaining Litres</th>
                    <th>Price Per Litre</th>
                    <th>Total Fuel Sold (Litres)</th>
                    <th>Total Cash Deposited</th>
                    <th>Total Till Money</th>
                    <th>Total Expenses</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $b): ?>
                <tr>
                    <td><?= $b['batch_id'] ?></td>
                    <td><?= htmlspecialchars($b['pump_number']) ?></td>
                    <td><?= htmlspecialchars($b['fuel_type']) ?></td>
                    <td style="text-align:right;"><?= number_format($b['start_liters'], 2) ?></td>
                    <?php
                    $color = ($b['remaining_litres'] <= 100) ? 'red' :
                             (($b['remaining_litres'] <= 500) ? 'orange' : 'green');
                    ?>
                    <td style="text-align:right; color:<?= $color ?>; font-weight:bold;">
                        <?= number_format($b['remaining_litres'], 2) ?>
                    </td>
                    <td style="text-align:right;"><?= number_format($b['price_per_litre'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($b['total_litres_sold'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($b['total_cash'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($b['total_till'], 2) ?></td>
                    <td style="text-align:right;"><?= number_format($b['total_expenses'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
