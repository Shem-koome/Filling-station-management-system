<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login.php");
    exit();
}

$user_email = $_SESSION['email'];
$current_date = date('Y-m-d');

// Get open batch
$batch_stmt = $conn->prepare("
    SELECT fb.*, p.pump_number, p.fuel_type 
    FROM fuel_batches fb 
    JOIN pumps p ON fb.pump_id = p.id 
    WHERE fb.is_closed = 0 AND fb.remaining_liters > 0 
    ORDER BY fb.start_date ASC 
    LIMIT 1
");
$batch_stmt->execute();
$batch_result = $batch_stmt->get_result();
$batch = $batch_result->fetch_assoc();

if (!$batch) {
    echo "<div style='padding:20px; color:red;'>No open fuel batch available.</div>";
    include 'footer.php';
    exit();
}

// Auto-close batch if remaining_liters <= 0
if ($batch['remaining_liters'] <= 0) {
    $close_stmt = $conn->prepare("UPDATE fuel_batches SET is_closed = 1 WHERE id = ?");
    $close_stmt->bind_param("i", $batch['id']);
    $close_stmt->execute();
    echo "<div style='padding:20px; color:red;'>Batch automatically closed. No remaining fuel.</div>";
    include 'footer.php';
    exit();
}

// Low fuel alert
$low_threshold = 0.25 * $batch['start_liters'];
$low_fuel_alert = ($batch['remaining_liters'] < $low_threshold);

$pump_id = $batch['pump_id'];
$batch_start_date = $batch['start_date'];

// Fetch today's reading if already exists
$today_stmt = $conn->prepare("SELECT * FROM fuel_readings WHERE pump_id = ? AND reading_date = ?");
$today_stmt->bind_param("is", $pump_id, $current_date);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_reading = $today_result->fetch_assoc();

$morning_liters = "";
$morning_sales = "";

// Determine morning readings
if ($today_reading) {
    $morning_liters = $today_reading['morning_liters'];
    $morning_sales = $today_reading['morning_sales'];
} else {
    if ($current_date == $batch_start_date) {
        $start_stmt = $conn->prepare("SELECT morning_liters, morning_sales FROM fuel_readings WHERE pump_id = ? AND reading_date = ?");
        $start_stmt->bind_param("is", $pump_id, $batch_start_date);
        $start_stmt->execute();
        $start_result = $start_stmt->get_result();
        if ($start = $start_result->fetch_assoc()) {
            $morning_liters = $start['morning_liters'];
            $morning_sales = $start['morning_sales'];
        }
    } else {
        $prev_date = date('Y-m-d', strtotime('-1 day', strtotime($current_date)));
        $prev_stmt = $conn->prepare("SELECT evening_liters, evening_sales FROM fuel_readings WHERE pump_id = ? AND reading_date = ?");
        $prev_stmt->bind_param("is", $pump_id, $prev_date);
        $prev_stmt->execute();
        $prev_result = $prev_stmt->get_result();
        if ($prev = $prev_result->fetch_assoc()) {
            $morning_liters = $prev['evening_liters'];
            $morning_sales = $prev['evening_sales'];
        }
    }
}

// Handle submission
if (isset($_POST['submit_readings'])) {
    $evening_liters = floatval($_POST['evening_liters']);
    $evening_sales = floatval($_POST['evening_sales']);

    if ($evening_liters < 0 || $evening_sales < 0) {
        $_SESSION['error'] = "Values cannot be negative.";
    } else {
        if ($today_reading) {
            $stmt = $conn->prepare("UPDATE fuel_readings SET evening_liters=?, evening_sales=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ddi", $evening_liters, $evening_sales, $today_reading['id']);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO fuel_readings 
                (pump_id, reading_date, morning_liters, morning_sales, evening_liters, evening_sales, user_email, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issdddd", $pump_id, $current_date, $morning_liters, $morning_sales, $evening_liters, $evening_sales, $user_email);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Readings saved successfully.";

            // INSERT into cash_deposits
            $sales_made = $evening_sales - $morning_sales;
            if ($sales_made < 0) $sales_made = 0;

            $cd_stmt = $conn->prepare("
                INSERT INTO cash_deposits (pump_id, batch_id, user_email, fuel, deposit_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $cd_stmt->bind_param("iisd", $pump_id, $batch['id'], $user_email, $sales_made);
            $cd_stmt->execute();
        } else {
            $_SESSION['error'] = "Failed to save readings.";
        }
    }
    header("Location: fuel_readings.php");
    exit();
}
?>

<div class="main-content">
    <h2>Fuel Readings - <?= htmlspecialchars($batch['pump_number']) ?> (<?= htmlspecialchars($batch['fuel_type']) ?>)</h2>

    <?php if ($low_fuel_alert): ?>
        <div style="padding: 10px; background-color: yellow; color: red; font-weight: bold;">
            ⚠️ Alert: Fuel is below 25%. Please notify admin.
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Reading Date:</label>
        <input type="date" name="reading_date" value="<?= $current_date ?>" readonly>

        <label>Morning Litres:</label>
        <input type="number" step="0.01" name="morning_liters" value="<?= htmlspecialchars($morning_liters) ?>" readonly>

        <label>Morning Sales:</label>
        <input type="number" step="0.01" name="morning_sales" value="<?= htmlspecialchars($morning_sales) ?>" readonly>

        <label>Evening Litres:</label>
        <input type="number" step="0.01" name="evening_liters" required value="<?= $today_reading['evening_liters'] ?? '' ?>">

        <label>Evening Sales:</label>
        <input type="number" step="0.01" name="evening_sales" required value="<?= $today_reading['evening_sales'] ?? '' ?>">

        <br><br>
        <button type="submit" name="submit_readings">Save Readings</button>
    </form>

    <hr>
    <h3>📋 Reading History (Current Batch)</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Date</th>
            <th>Morning Liters</th>
            <th>Evening Liters</th>
            <th>Liters Sold</th>
            <th>Morning Sales</th>
            <th>Evening Sales</th>
            <th>Sales Made</th>
        </tr>
        <?php
        $history_stmt = $conn->prepare("SELECT * FROM fuel_readings WHERE pump_id = ? ORDER BY reading_date DESC LIMIT 14");
        $history_stmt->bind_param("i", $pump_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();

        while ($row = $history_result->fetch_assoc()):
            $liters_sold = $row['evening_liters'] - $row['morning_liters'];
            $sales_made = $row['evening_sales'] - $row['morning_sales'];
        ?>
        <tr>
            <td><?= htmlspecialchars($row['reading_date']) ?></td>
            <td><?= htmlspecialchars($row['morning_liters']) ?></td>
            <td><?= htmlspecialchars($row['evening_liters']) ?></td>
            <td><?= htmlspecialchars($liters_sold) ?></td>
            <td><?= htmlspecialchars($row['morning_sales']) ?></td>
            <td><?= htmlspecialchars($row['evening_sales']) ?></td>
            <td><?= htmlspecialchars($sales_made) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

<?php include 'footer.php'; ?>
