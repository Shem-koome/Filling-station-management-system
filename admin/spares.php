<?php
include 'header.php';
require '../authpage/db.php';

// Add new spare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_spare'])) {
    $name = trim($_POST['spare_name']);
    $unit_price = floatval($_POST['unit_price']);
    $total_quantity = intval($_POST['total_quantity']);

    if ($name && $unit_price > 0 && $total_quantity > 0) {
        $stmt = $conn->prepare("INSERT INTO spares_admin (name, unit_price, total_quantity, sold_quantity) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("sdi", $name, $unit_price, $total_quantity);
        $stmt->execute();
        $_SESSION['success_spare'] = "Spare added successfully.";
        header("Location: spares.php");
        exit();
    } else {
        $_SESSION['error_spare'] = "Fill in all fields with valid values.";
    }
}

// Restock spare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_spare'])) {
    $spare_id = intval($_POST['restock_spare_id']);
    $restock_qty = intval($_POST['restock_quantity']);

    if ($restock_qty > 0) {
        $stmt = $conn->prepare("UPDATE spares_admin SET total_quantity = total_quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $restock_qty, $spare_id);
        $stmt->execute();
        $_SESSION['success_restock'] = "Spare restocked successfully.";
        header("Location: spares.php");
        exit();
    } else {
        $_SESSION['error_restock'] = "Enter a valid restock quantity.";
    }
}

// Fetch all spares
$spares = $conn->query("SELECT * FROM spares_admin ORDER BY name ASC");

// Fetch sold spares
$sold_spares = $conn->query("SELECT ss.*, sa.name FROM spares_sold ss JOIN spares_admin sa ON ss.spare_id = sa.id ORDER BY ss.sale_date DESC");
?>

<style>
.container { padding: 20px; }
.section { margin-bottom: 40px; padding: 20px; border: 1px solid #ccc; background: #f9f9f9; border-radius: 8px; }
h3 { margin-top: 0; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
.success { color: green; margin-bottom: 15px; }
.error { color: red; margin-bottom: 15px; }
.restock-needed { background-color: #ffe6e6; }
.sufficient-stock { background-color: #e6ffe6; }
input[type="number"] { width: 100px; }
</style>

<div class="main-content">
    <h2>Admin - Spares Management</h2>
    <div class="container">

        <!-- Add Spare -->
        <div class="section">
            <h3>Add New Spare</h3>

            <?php if (isset($_SESSION['success_spare'])): ?>
                <div class="success"><?php echo $_SESSION['success_spare']; unset($_SESSION['success_spare']); ?></div>
            <?php elseif (isset($_SESSION['error_spare'])): ?>
                <div class="error"><?php echo $_SESSION['error_spare']; unset($_SESSION['error_spare']); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Spare Name:</label><br>
                <input type="text" name="spare_name" required><br><br>

                <label>Unit Price (KES):</label><br>
                <input type="number" step="0.01" name="unit_price" required><br><br>

                <label>Total Quantity:</label><br>
                <input type="number" step="1" name="total_quantity" required><br><br>

                <button type="submit" name="add_spare">Add Spare</button>
            </form>
        </div>

        <!-- View Spares with Restock Option -->
        <div class="section">
            <h3>Available Spares</h3>

            <?php if (isset($_SESSION['success_restock'])): ?>
                <div class="success"><?php echo $_SESSION['success_restock']; unset($_SESSION['success_restock']); ?></div>
            <?php elseif (isset($_SESSION['error_restock'])): ?>
                <div class="error"><?php echo $_SESSION['error_restock']; unset($_SESSION['error_restock']); ?></div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Unit Price (KES)</th>
                        <th>Total Quantity</th>
                        <th>Sold Quantity</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Restock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spares as $spare): 
                        $remaining = $spare['total_quantity'] - $spare['sold_quantity'];
                        $isLow = $remaining < ($spare['total_quantity'] * 0.25);
                    ?>
                    <tr class="<?php echo $isLow ? 'restock-needed' : 'sufficient-stock'; ?>">
                        <td><?php echo htmlspecialchars($spare['name']); ?></td>
                        <td><?php echo number_format($spare['unit_price'], 2); ?></td>
                        <td><?php echo $spare['total_quantity']; ?></td>
                        <td><?php echo $spare['sold_quantity']; ?></td>
                        <td><?php echo $remaining; ?></td>
                        <td><?php echo $isLow ? '⚠️ Restock Needed' : '✅ Sufficient'; ?></td>
                        <td>
                            <?php if ($isLow): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="restock_spare_id" value="<?php echo $spare['id']; ?>">
                                    <input type="number" name="restock_quantity" min="1" required>
                                    <button type="submit" name="restock_spare">Restock</button>
                                </form>
                            <?php else: ?>
                                <span>N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sold Spares -->
        <div class="section">
            <h3>Sold Spares</h3>

            <table>
                <thead>
                    <tr>
                        <th>Spare Name</th>
                        <th>Quantity Sold</th>
                        <th>Total Price (KES)</th>
                        <th>Sale Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sold_spares->num_rows > 0): ?>
                        <?php foreach ($sold_spares as $sold): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sold['name']); ?></td>
                                <td><?php echo $sold['quantity_sold']; ?></td>
                                <td><?php echo number_format($sold['total_price'], 2); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($sold['sale_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No spares sold yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Manual Spares Entered by Employees -->
<div class="section">
    <h3>Manual Spares (Employee Input)</h3>

    <?php
    $manual_spares = $conn->query("SELECT * FROM spares_employee ORDER BY entry_date DESC");
    $total_manual = 0;
    if ($manual_spares) {
        foreach ($manual_spares as $ms) {
            $total_manual += $ms['amount'];
        }
    }
    ?>

    <table>
        <thead>
            <tr>
                <th>Spare Name</th>
                <th>Amount (KES)</th>
                <th>Entry Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($manual_spares && $manual_spares->num_rows > 0): ?>
                <?php foreach ($manual_spares as $ms): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ms['name']); ?></td>
                        <td style="text-align:right;"><?php echo number_format($ms['amount'], 2); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($ms['entry_date'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3">No manual spares entered by employees.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="success">
                <td><strong>Total</strong></td>
                <td style="text-align:right;"><strong><?php echo number_format($total_manual, 2); ?></strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>


    </div>
</div>


<?php include 'footer.php'; ?>
