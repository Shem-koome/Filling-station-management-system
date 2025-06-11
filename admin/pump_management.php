<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login.php");
    exit();
}

// Add Pump
if (isset($_POST['add_pump'])) {
    $pump_number = trim($_POST['pump_number']);
    $fuel_type = $_POST['fuel_type'];

    if ($pump_number && $fuel_type) {
        $stmt = $conn->prepare("INSERT INTO pumps (pump_number, fuel_type) VALUES (?, ?)");
        $stmt->bind_param("ss", $pump_number, $fuel_type);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Pump added successfully.";
    } else {
        $_SESSION['error'] = "All pump fields are required.";
    }
    header("Location: pump_management.php");
    exit();
}

// Save Morning Readings
if (isset($_POST['save_starting_readings'])) {
    $pump_id = intval($_POST['pump_id']);
    $date = $_POST['date'];
    $liters = floatval($_POST['morning_liters']);
    $sales = floatval($_POST['morning_sales']);

    $stmt = $conn->prepare("INSERT INTO fuel_readings (pump_id, reading_date, morning_liters, evening_liters, morning_sales, evening_sales) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddd", $pump_id, $date, $liters, $liters, $sales, $sales);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Starting readings saved.";
    header("Location: pump_management.php");
    exit();
}

// Create Batch
if (isset($_POST['create_batch'])) {
    $pump_id = intval($_POST['pump_id']);
    $date = $_POST['start_date'];
    $liters = floatval($_POST['start_liters']);
    $price = floatval($_POST['price_per_liter']);

    // Create new batch
    $stmt = $conn->prepare("INSERT INTO fuel_batches (pump_id, start_date, start_liters, price_per_liter, remaining_liters) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issdd", $pump_id, $date, $liters, $price, $liters);
    $stmt->execute();
    $new_batch_id = $stmt->insert_id; // get the ID of the newly created batch
    $stmt->close();

    // Transfer unpaid debts from last closed batch (for the same pump)
    $last_closed_batch = $conn->query("SELECT id FROM fuel_batches WHERE pump_id = $pump_id AND is_closed = 1 ORDER BY start_date DESC LIMIT 1")->fetch_assoc();
    if ($last_closed_batch) {
        $old_batch_id = $last_closed_batch['id'];

        // Reassign debts
        $conn->query("UPDATE debts SET batch_id = $new_batch_id WHERE batch_id = $old_batch_id AND paid = 0");

        // Reassign transactions
        $conn->query("UPDATE transactions SET batch_id = $new_batch_id WHERE batch_id = $old_batch_id AND type = 'debt'");
    }

    $_SESSION['success'] = "Fuel batch created successfully.";
    header("Location: pump_management.php");
    exit();
}


// Auto Deduct Liters from Batch (based on latest evening readings)
$readings = $conn->query("SELECT * FROM fuel_readings ORDER BY reading_date DESC") or die($conn->error);
while ($reading = $readings->fetch_assoc()) {
    $pump_id = $reading['pump_id'];
    $sales_liters = $reading['morning_liters'] - $reading['evening_liters'];
    if ($sales_liters <= 0) continue;

    $batches = $conn->query("SELECT * FROM fuel_batches WHERE pump_id = $pump_id AND is_closed = 0 ORDER BY start_date ASC") or die($conn->error);
    while ($batch = $batches->fetch_assoc()) {
        if ($sales_liters <= 0) break;

        $batch_id = $batch['id'];
        $remaining = $batch['remaining_liters'];

        $deduct = min($sales_liters, $remaining);
        $new_remaining = $remaining - $deduct;

        $conn->query("UPDATE fuel_batches SET remaining_liters = $new_remaining WHERE id = $batch_id");

        if ($new_remaining <= 0.01) {
            $conn->query("UPDATE fuel_batches SET is_closed = 1 WHERE id = $batch_id");
        }

        $sales_liters -= $deduct;
    }
    break; // Only process latest reading
}

// Fetch Pumps and Open Batches
$pumps = $conn->query("SELECT * FROM pumps ORDER BY pump_number ASC")->fetch_all(MYSQLI_ASSOC);
$batches = $conn->query("SELECT fb.*, p.pump_number, p.fuel_type FROM fuel_batches fb JOIN pumps p ON fb.pump_id = p.id WHERE fb.is_closed = 0 ORDER BY fb.start_date DESC")
    ->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <h2>Pump Management</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <h3>Add Pump</h3>
    <form method="POST">
        <label>Pump Number:</label>
        <input type="text" name="pump_number" required>
        <label>Fuel Type:</label>
        <select name="fuel_type" required>
            <option value="">Select</option>
            <option value="Petrol">Petrol</option>
            <option value="Diesel">Diesel</option>
        </select>
        <button name="add_pump">Add Pump</button>
    </form>

    <h3>Starting Readings (Morning)</h3>
    <form method="POST">
        <label>Pump:</label>
        <select name="pump_id" required>
            <?php foreach ($pumps as $pump): ?>
                <option value="<?= $pump['id'] ?>"><?= $pump['pump_number'] ?> (<?= $pump['fuel_type'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <label>Date:</label>
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
        <label>Litres:</label>
        <input type="number" step="0.01" name="morning_liters" required>
        <label>Sales:</label>
        <input type="number" step="0.01" name="morning_sales" required>
        <button name="save_starting_readings">Save</button>
    </form>

    <h3>Create Fuel Batch</h3>
    <form method="POST">
        <label>Pump:</label>
        <select name="pump_id" required>
            <?php foreach ($pumps as $pump): ?>
                <option value="<?= $pump['id'] ?>"><?= $pump['pump_number'] ?> (<?= $pump['fuel_type'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <label>Start Date:</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
        <label>Litres:</label>
        <input type="number" step="0.01" name="start_liters" required>
        <label>Price/Litre:</label>
        <input type="number" step="0.01" name="price_per_liter" required>
        <button name="create_batch">Create</button>
    </form>

    <h3>Open Fuel Batches</h3>
    <table border="1">
        <tr>
            <th>Batch ID</th>
            <th>Pump</th>
            <th>Fuel Type</th>
            <th>Start Date</th>
            <th>Litres</th>
            <th>Price/Litre</th>
            <th>Remaining</th>
        </tr>
        <?php foreach ($batches as $batch): ?>
            <tr>
                <td><?= $batch['id'] ?></td>
                <td><?= $batch['pump_number'] ?></td>
                <td><?= $batch['fuel_type'] ?></td>
                <td><?= $batch['start_date'] ?></td>
                <td><?= number_format($batch['start_liters'], 2) ?></td>
                <td><?= number_format($batch['price_per_liter'], 2) ?></td>
                <td><?= number_format($batch['remaining_liters'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php include 'footer.php'; ?>
