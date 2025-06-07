<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$reading_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch all fuel readings for the selected date
$stmt = $conn->prepare("SELECT * FROM fuel_readings WHERE reading_date = ? ORDER BY user_email ASC");
$stmt->bind_param("s", $reading_date);
$stmt->execute();
$result = $stmt->get_result();

$readings = [];
while ($row = $result->fetch_assoc()) {
    $litres_sold = $row['evening_liters'] - $row['morning_liters'];
    $sales_total = $row['evening_sales'] - $row['morning_sales'];
    $row['litres_sold'] = $litres_sold;
    $row['sales_total'] = $sales_total;
    $readings[] = $row;
}
?>

<div class="main-content">
    <h2>Fuel Readings (Admin View) for Date:
        <input type="date" id="reading_date" value="<?php echo $reading_date; ?>"
               onchange="window.location.href='fuel_readings.php?date=' + this.value" />
    </h2>

    <?php if (empty($readings)): ?>
        <p>No readings recorded for this date.</p>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead style="background-color: #f2f2f2;">
                <tr>
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
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
