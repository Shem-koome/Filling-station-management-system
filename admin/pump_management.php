<?php
include 'header.php';
require '../authpage/db.php';

if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login.php");
    exit();
}

// Logic remains the same (add pump, save readings, create batch, etc.)
// ... (same PHP logic as before)

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

        <!-- Starting Readings -->
        <div class="form-box">
            <h3><i class="fas fa-clock"></i> Starting Readings (Morning)</h3>
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

                <button name="save_starting_readings"><i class="fas fa-save"></i> Save Readings</button>
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

                <label>Litres:</label>
                <input type="number" step="0.01" name="start_liters" required>

                <label>Price/Litre:</label>
                <input type="number" step="0.01" name="price_per_liter" required>

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
