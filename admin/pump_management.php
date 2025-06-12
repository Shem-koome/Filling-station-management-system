<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login.php");
    exit();
}

// Handle Add Pump
if (isset($_POST['add_pump'])) {
    $pump_number = $_POST['pump_number'];
    $fuel_type = $_POST['fuel_type'];
    $stmt = $conn->prepare("INSERT INTO pumps (pump_number, fuel_type) VALUES (?, ?)");
    $stmt->bind_param("ss", $pump_number, $fuel_type);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Pump added successfully.";
    } else {
        $_SESSION['error'] = "Error adding pump.";
    }
    header("Location: pump_management.php");
    exit();
}

// Handle Save Starting Readings
if (isset($_POST['save_starting_readings'])) {
    $pump_id = $_POST['pump_id'];
    $date = $_POST['date'];
    $morning_liters = $_POST['morning_liters'];
    $morning_sales = $_POST['morning_sales'];
    $user_email = $_SESSION['email'];

    $stmt = $conn->prepare("INSERT INTO fuel_readings (pump_id, reading_date, morning_liters, morning_sales, user_email, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issds", $pump_id, $date, $morning_liters, $morning_sales, $user_email);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Starting readings saved.";
    } else {
        $_SESSION['error'] = "Error saving readings.";
    }
    header("Location: pump_management.php");
    exit();
}

// Handle Create Batch and Insert Morning Readings
if (isset($_POST['create_batch'])) {
    $pump_id = $_POST['pump_id'];
    $start_date = $_POST['start_date'];
    $start_liters = $_POST['start_liters'];
    $price_per_liter = $_POST['price_per_liter'];
    $morning_liters = $_POST['batch_morning_liters'];
    $morning_sales = $_POST['batch_morning_sales'];
    $user_email = $_SESSION['email'];

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO fuel_batches (pump_id, start_date, start_liters, remaining_liters, price_per_liter, is_closed) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("isddd", $pump_id, $start_date, $start_liters, $start_liters, $price_per_liter);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO fuel_readings (pump_id, reading_date, morning_liters, morning_sales, user_email, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issds", $pump_id, $start_date, $morning_liters, $morning_sales, $user_email);
        $stmt->execute();

        $conn->commit();
        $_SESSION['success'] = "Batch created and starting readings saved.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error creating batch or saving readings.";
    }
    header("Location: pump_management.php");
    exit();
}

// Fetch Pumps and Open Batches
$pumps = $conn->query("SELECT * FROM pumps ORDER BY pump_number ASC")->fetch_all(MYSQLI_ASSOC);
$batches = $conn->query("SELECT fb.*, p.pump_number, p.fuel_type FROM fuel_batches fb JOIN pumps p ON fb.pump_id = p.id WHERE fb.is_closed = 0 ORDER BY fb.start_date DESC")
    ->fetch_all(MYSQLI_ASSOC);
?>

<!-- Font Awesome CDN for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Custom Styles -->
<style>
    .main-content {
        padding: 20px;
        font-family: Arial, sans-serif;
    }
    .form-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }
    .form-box {
        flex: 1;
        min-width: 300px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    .form-box h3 {
        margin-bottom: 15px;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
    }
    label {
        display: block;
        margin-top: 10px;
    }
    input, select {
        width: 100%;
        padding: 8px;
        margin-top: 5px;
        box-sizing: border-box;
    }
    button {
        margin-top: 15px;
        padding: 10px;
        width: 100%;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0056b3;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: center;
    }
    th {
        background-color: #f1f1f1;
    }
    .success {
        color: green;
        margin-bottom: 10px;
    }
    .error {
        color: red;
        margin-bottom: 10px;
    }
</style>

<div class="main-content">
    <h2><i class="fas fa-gas-pump"></i> Pump Management</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="form-grid">
        <!-- Add Pump -->
        <div class="form-box">
            <h3><i class="fas fa-plus-square"></i> Add Pump</h3>
            <form method="POST">
                <label>Pump Number:</label>
                <input type="text" name="pump_number" required>

                <label>Fuel Type:</label>
                <select name="fuel_type" required>
                    <option value="">Select</option>
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                </select>

                <button name="add_pump"><i class="fas fa-plus"></i> Add Pump</button>
            </form>
        </div>

        <!-- Create Batch -->
        <div class="form-box">
            <h3><i class="fas fa-box"></i> Create Fuel Batch</h3>
            <form method="POST">
                <label>Pump:</label>
                <select name="pump_id" required>
                    <?php foreach ($pumps as $pump): ?>
                        <option value="<?= $pump['id'] ?>"><?= $pump['pump_number'] ?> (<?= $pump['fuel_type'] ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label>Start Date:</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>

                <label>Litres (Batch):</label>
                <input type="number" step="0.01" name="start_liters" required>

                <label>Price/Litre:</label>
                <input type="number" step="0.01" name="price_per_liter" required>

                <label>Starting Litres (Morning Reading):</label>
                <input type="number" step="0.01" name="batch_morning_liters" required>

                <label>Starting Sales (Morning Reading):</label>
                <input type="number" step="0.01" name="batch_morning_sales" required>

                <button name="create_batch"><i class="fas fa-gas-pump"></i> Create Batch</button>
            </form>
        </div>
    </div>

    <h3><i class="fas fa-fire"></i> Open Fuel Batches</h3>
    <table>
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
