<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login.php");
    exit();
}

// Handle Pump Creation
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

// Handle Starting Readings
if (isset($_POST['save_starting_readings'])) {
    $pump_id = intval($_POST['pump_id']);
    $date = $_POST['date'];
    $liters = floatval($_POST['morning_liters']);
    $sales = floatval($_POST['morning_sales']);

    $stmt = $conn->prepare("INSERT INTO fuel_readings (pump_id, reading_date, morning_liters, evening_liters, morning_sales, evening_sales) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddd", $pump_id, $date, $liters, $liters, $sales, $sales); // morning and evening start the same
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Starting readings saved and used as morning readings.";
    header("Location: pump_management.php");
    exit();
}

// Handle Batch Creation
if (isset($_POST['create_batch'])) {
    $pump_id = intval($_POST['pump_id']);
    $date = $_POST['start_date'];
    $liters = floatval($_POST['start_liters']);
    $price = floatval($_POST['price_per_liter']);

    $stmt = $conn->prepare("INSERT INTO fuel_batches (pump_id, start_date, start_liters, price_per_liter, remaining_liters) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issdd", $pump_id, $date, $liters, $price, $liters);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Fuel batch created successfully.";
    header("Location: pump_management.php");
    exit();
}

// Fetch Pumps
$pumps = $conn->query("SELECT * FROM pumps ORDER BY pump_number ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch Open Batches
$batches = $conn->query("
    SELECT fb.*, p.pump_number, p.fuel_type 
    FROM fuel_batches fb 
    JOIN pumps p ON fb.pump_id = p.id 
    WHERE fb.is_closed = 0
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <h2>Pump Management</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div style="color: green;"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Add Pump -->
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

    <!-- Starting Readings -->
    <h3>Starting Readings (Morning Readings)</h3>
    <form method="POST">
        <label>Select Pump:</label>
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
        <button name="save_starting_readings">Save Readings</button>
    </form>

    <!-- Create Batch -->
    <h3>Create Fuel Batch</h3>
    <form method="POST">
        <label>Select Pump:</label>
        <select name="pump_id" required>
            <?php foreach ($pumps as $pump): ?>
                <option value="<?= $pump['id'] ?>"><?= $pump['pump_number'] ?> (<?= $pump['fuel_type'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <label>Start Date:</label>
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
        <label>Litres Refilled:</label>
        <input type="number" step="0.01" name="start_liters" required>
        <label>Price per Litre:</label>
        <input type="number" step="0.01" name="price_per_liter" required>
        <button name="create_batch">Create Batch</button>
    </form>

    <!-- Pumps Table -->
    <h3>All Pumps</h3>
    <table border="1">
        <tr>
            <th>Pump Number</th>
            <th>Fuel Type</th>
        </tr>
        <?php foreach ($pumps as $pump): ?>
            <tr>
                <td><?= $pump['pump_number'] ?></td>
                <td><?= $pump['fuel_type'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Open Batches -->
    <h3>Open Fuel Batches</h3>
    <table border="1">
        <tr>
            <th>Batch ID</th>
            <th>Pump</th>
            <th>Fuel Type</th>
            <th>Start Date</th>
            <th>Litres</th>
            <th>Price per Litre</th>
        </tr>
        <?php foreach ($batches as $batch): ?>
            <tr>
                <td><?= $batch['id'] ?></td>
                <td><?= $batch['pump_number'] ?></td>
                <td><?= $batch['fuel_type'] ?></td>
                <td><?= $batch['start_date'] ?></td>
                <td><?= number_format($batch['start_liters'], 2) ?></td>
                <td><?= number_format($batch['price_per_liter'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php include 'footer.php'; ?>
