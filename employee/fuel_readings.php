<?php include 'header.php'; ?>
<?php
require '../authpage/db.php';
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pump_id = $_POST['pump_id'];
    $reading_date = $_POST['reading_date'];
    $morning_liters = floatval($_POST['morning_liters']);
    $evening_liters = floatval($_POST['evening_liters']);
    $morning_sales = floatval($_POST['morning_sales']);
    $evening_sales = floatval($_POST['evening_sales']);
    $user_email = $_SESSION['user_email'];

    // Validation
    if ($morning_liters < 0 || $evening_liters < 0 || $morning_sales < 0 || $evening_sales < 0) {
        echo "<script>alert('Negative values are not allowed.');</script>";
    } else {
        // Insert reading
        $stmt = $conn->prepare("INSERT INTO fuel_readings (pump_id, reading_date, morning_liters, evening_liters, morning_sales, evening_sales, user_email, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issdddds", $pump_id, $reading_date, $morning_liters, $evening_liters, $morning_sales, $evening_sales, $user_email);
        $stmt->execute();

        // Update remaining liters in batch
        $liters_sold = $morning_liters - $evening_liters;
        $update_batch = $conn->prepare("UPDATE fuel_batches SET remaining_liters = remaining_liters - ? WHERE pump_id = ? AND is_closed = 0");
        $update_batch->bind_param("di", $liters_sold, $pump_id);
        $update_batch->execute();

        // Auto-close batch if needed
        $check_batch = $conn->prepare("SELECT id, remaining_liters, start_liters FROM fuel_batches WHERE pump_id = ? AND is_closed = 0");
        $check_batch->bind_param("i", $pump_id);
        $check_batch->execute();
        $batch_result = $check_batch->get_result();

        if ($batch_row = $batch_result->fetch_assoc()) {
            $remaining = $batch_row['remaining_liters'];
            $start_liters = $batch_row['start_liters'];
            $batch_id = $batch_row['id'];

            if ($remaining <= 0) {
                $conn->query("UPDATE fuel_batches SET is_closed = 1 WHERE id = $batch_id");
                echo "<script>alert('Batch auto-closed: fuel depleted.');</script>";
            } elseif ($remaining < 0.25 * $start_liters) {
                echo "<script>alert('Warning: Fuel level below 25% for this batch!');</script>";
            }
        }

        echo "<script>alert('Fuel reading recorded successfully.');</script>";
    }
}

// Fetch pumps for dropdown
$pumps = $conn->query("SELECT id, pump_number, fuel_type FROM pumps");
?>

<div class="main-content">
    <h2>Record Fuel Readings</h2>

    <form method="post" style="max-width: 700px; margin: auto; background: #f9f9f9; padding: 20px; border-radius: 10px;">
        <label>Pump:</label>
        <select name="pump_id" required style="width: 100%; padding: 8px;">
            <option value="">Select Pump</option>
            <?php while ($pump = $pumps->fetch_assoc()): ?>
                <option value="<?= $pump['id'] ?>">
                    <?= htmlspecialchars($pump['pump_number'] . " - " . $pump['fuel_type']) ?>
                </option>
            <?php endwhile; ?>
        </select><br><br>

        <label>Reading Date:</label>
        <input type="date" name="reading_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 8px;"><br><br>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label>Morning Litres:</label>
                <input type="number" name="morning_liters" step="0.01" min="0" required style="width: 100%; padding: 8px;">
            </div>
            <div>
                <label>Evening Litres:</label>
                <input type="number" name="evening_liters" step="0.01" min="0" required style="width: 100%; padding: 8px;">
            </div>
            <div>
                <label>Morning Sales (KES):</label>
                <input type="number" name="morning_sales" step="0.01" min="0" required style="width: 100%; padding: 8px;">
            </div>
            <div>
                <label>Evening Sales (KES):</label>
                <input type="number" name="evening_sales" step="0.01" min="0" required style="width: 100%; padding: 8px;">
            </div>
        </div>

        <br>
        <button type="submit" style="padding: 10px 20px;">Submit Reading</button>
    </form>
</div>

<?php include 'footer.php'; ?>
