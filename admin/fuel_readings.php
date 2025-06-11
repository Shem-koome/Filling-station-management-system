<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

// Get selected date and pump from query parameters
$reading_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_pump = isset($_GET['pump']) ? $_GET['pump'] : '';

// Fetch available pump numbers
$pump_stmt = $conn->query("
    SELECT DISTINCT p.pump_number 
    FROM fuel_readings fr 
    JOIN pumps p ON fr.pump_id = p.id 
    ORDER BY p.pump_number ASC
");

$pump_numbers = [];
while ($row = $pump_stmt->fetch_assoc()) {
    $pump_numbers[] = $row['pump_number'];
}

// Prepare SQL with optional pump filter
$pump_filter_sql = '';
$params = [$reading_date];

if (!empty($selected_pump)) {
    $pump_filter_sql = " AND p.pump_number = ?";
    $params[] = $selected_pump;
}

// Prepare and execute readings query
$sql = "
    SELECT fr.*, p.pump_number 
    FROM fuel_readings fr
    JOIN pumps p ON fr.pump_id = p.id
    WHERE fr.reading_date = ? $pump_filter_sql
    ORDER BY p.pump_number ASC
";

$stmt = $conn->prepare($sql);

// Bind dynamic params
$type_string = str_repeat("s", count($params));
$stmt->bind_param($type_string, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$readings = [];
$total_litres_sold = 0;
$total_sales = 0;

while ($row = $result->fetch_assoc()) {
    $litres_sold = $row['evening_liters'] - $row['morning_liters'];
    $sales_total = $row['evening_sales'] - $row['morning_sales'];
    $row['litres_sold'] = $litres_sold;
    $row['sales_total'] = $sales_total;

    $total_litres_sold += $litres_sold;
    $total_sales += $sales_total;

    $readings[] = $row;
}
?>

<div class="main-content">
    <h2>Fuel Readings (Admin View)</h2>

    <form method="GET" style="margin-bottom: 20px;">
        <label for="reading_date">Select Date: </label>
        <input type="date" id="reading_date" name="date" value="<?php echo htmlspecialchars($reading_date); ?>" />

        <label for="pump">Filter by Pump:</label>
        <select name="pump" id="pump">
            <option value="">-- All Pumps --</option>
            <?php foreach ($pump_numbers as $pump): ?>
                <option value="<?php echo htmlspecialchars($pump); ?>" <?php echo $pump == $selected_pump ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pump); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
    </form>

    <?php if (empty($readings)): ?>
        <p>No readings recorded for this date<?php echo $selected_pump ? " and pump" : ""; ?>.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: #f2f2f2;">
                <tr>
                    <th>Pump Number</th>
                    <th>Employee Email</th>
                    <th>Morning Litres</th>
                    <th>Evening Litres</th>
                    <th>Litres Sold</th>
                    <th>Morning Sales (KES)</th>
                    <th>Evening Sales (KES)</th>
                    <th>Total Sales (KES)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($readings as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['pump_number']); ?></td>
                        <td><?php echo htmlspecialchars($entry['user_email']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['morning_liters'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['evening_liters'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['litres_sold'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['morning_sales'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['evening_sales'], 2); ?></td>
                        <td style="text-align: right;"><?php echo number_format($entry['sales_total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background-color: #dff0d8;">
                    <td colspan="4" style="text-align: right;">Totals:</td>
                    <td style="text-align: right;"><?php echo number_format($total_litres_sold, 2); ?></td>
                    <td colspan="2"></td>
                    <td style="text-align: right;"><?php echo number_format($total_sales, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
