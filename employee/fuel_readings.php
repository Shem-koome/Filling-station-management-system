<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';

$user_email = $_SESSION['email'];
$pump_id = 1;
$reading_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$prev_date = date('Y-m-d', strtotime($reading_date . ' -1 day'));

// Fetch previous day's evening readings
$stmt = $conn->prepare("SELECT evening_liters, evening_sales FROM fuel_readings WHERE user_email = ? AND pump_id = ? AND reading_date = ?");
$stmt->bind_param("sis", $user_email, $pump_id, $prev_date);
$stmt->execute();
$prev_evening = $stmt->get_result()->fetch_assoc();

// Fetch today's readings
$stmt = $conn->prepare("SELECT * FROM fuel_readings WHERE user_email = ? AND pump_id = ? AND reading_date = ?");
$stmt->bind_param("sis", $user_email, $pump_id, $reading_date);
$stmt->execute();
$today_reading = $stmt->get_result()->fetch_assoc();

// Autofill logic
$morning_liters  = $today_reading['morning_liters'] ?? $prev_evening['evening_liters'] ?? 0;
$morning_sales   = $today_reading['morning_sales'] ?? $prev_evening['evening_sales'] ?? 0;
$evening_liters  = $today_reading['evening_liters'] ?? '';
$evening_sales   = $today_reading['evening_sales'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $morning_liters_post  = floatval($_POST['morning_liters']);
    $morning_sales_post   = floatval($_POST['morning_sales']);
    $evening_liters_post  = floatval($_POST['evening_liters']);
    $evening_sales_post   = floatval($_POST['evening_sales']);

    if ($evening_liters_post < $morning_liters_post || $evening_sales_post < $morning_sales_post) {
        $_SESSION['error'] = "Evening readings cannot be less than morning readings.";
        header("Location: fuel_readings.php?date=$reading_date");
        exit();
    }

    if ($today_reading) {
        $stmt = $conn->prepare("UPDATE fuel_readings SET morning_liters=?, morning_sales=?, evening_liters=?, evening_sales=? WHERE id=?");
        $stmt->bind_param("ddddi", $morning_liters_post, $morning_sales_post, $evening_liters_post, $evening_sales_post, $today_reading['id']);
        $stmt->execute();
        $_SESSION['success'] = "Readings updated successfully!";
    } else {
        $stmt = $conn->prepare("INSERT INTO fuel_readings (user_email, pump_id, reading_date, morning_liters, morning_sales, evening_liters, evening_sales) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisdidd", $user_email, $pump_id, $reading_date, $morning_liters_post, $morning_sales_post, $evening_liters_post, $evening_sales_post);
        $stmt->execute();
        $_SESSION['success'] = "Readings saved successfully!";
    }

    header("Location: fuel_readings.php?date=$reading_date");
    exit();
}

// Calculate litres sold and total sales
$litres_sold = '';
$total_sales = '';
if ($evening_liters !== '' && $evening_sales !== '') {
    $litres_sold = floatval($evening_liters) - floatval($morning_liters);
    $total_sales = floatval($evening_sales) - floatval($morning_sales);
}
?>

<div class="main-content">
    <h2>Fuel Readings for Date:
        <input type="date" id="reading_date" value="<?php echo $reading_date; ?>"
               onchange="window.location.href='fuel_readings.php?date=' + this.value" />
    </h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div style="display: flex; gap: 50px;">
        <!-- Left side: the form -->
        <form method="POST" style="flex: 1;">
            <label>Morning Litres:</label>
            <input type="number" step="0.01" name="morning_liters" value="<?php echo $morning_liters; ?>" required><br>

            <label>Morning Sales:</label>
            <input type="number" step="0.01" name="morning_sales" value="<?php echo $morning_sales; ?>" required><br>

            <label>Evening Litres:</label>
            <input type="number" step="0.01" name="evening_liters" value="<?php echo $evening_liters; ?>" required><br>

            <label>Evening Sales:</label>
            <input type="number" step="0.01" name="evening_sales" value="<?php echo $evening_sales; ?>" required><br>

            <button type="submit">Save Readings</button>
        </form>

        <!-- Right side: display calculated results -->
        <div style="flex: 1;">
            <h3>Summary for the Day</h3>
            <p><strong>Litres Sold:</strong> <?php echo $litres_sold !== '' ? number_format($litres_sold, 2) : 'N/A'; ?></p>
            <p><strong>Total Sales:</strong> <?php echo $total_sales !== '' ? 'KES' . number_format($total_sales, 2) : 'N/A'; ?></p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
