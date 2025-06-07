<?php
include 'header.php';
require '../authpage/db.php';

// Handle employee manual spares input (left side)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_spare'])) {
    $spare_name = trim($_POST['manual_spare_name']);
    $amount = floatval($_POST['manual_spare_amount']);

    if ($spare_name && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO spares_employee (name, amount, entry_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("sd", $spare_name, $amount);
        $stmt->execute();
        $_SESSION['success_manual'] = "Manual spare added.";
        header("Location: spares.php");
        exit();
    } else {
        $_SESSION['error_manual'] = "Fill manual spare name and positive amount.";
    }
}

// Handle admin spare sale (right side)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sold_spare'])) {
    $spare_id = intval($_POST['spare_id']);
    $quantity_sold = intval($_POST['quantity_sold']);

    // Fetch unit price and stock info
    $stmt = $conn->prepare("SELECT unit_price, total_quantity, sold_quantity FROM spares_admin WHERE id = ?");
    $stmt->bind_param("i", $spare_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $spare = $result->fetch_assoc();

    if ($spare && $quantity_sold > 0) {
        $available_stock = $spare['total_quantity'] - $spare['sold_quantity'];

        if ($quantity_sold <= $available_stock) {
            $total_price = $spare['unit_price'] * $quantity_sold;

            // Insert sale record
            $stmt = $conn->prepare("INSERT INTO spares_sold (spare_id, quantity_sold, total_price, sale_date) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iid", $spare_id, $quantity_sold, $total_price);
            $stmt->execute();

            // Update sold quantity in spares_admin
            $new_sold_qty = $spare['sold_quantity'] + $quantity_sold;
            $stmt = $conn->prepare("UPDATE spares_admin SET sold_quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_sold_qty, $spare_id);
            $stmt->execute();

            $_SESSION['success_sold'] = "Spare sale recorded.";
            header("Location: spares.php");
            exit();
        } else {
            $_SESSION['error_sold'] = "Insufficient stock. Available: $available_stock.";
        }
    } else {
        $_SESSION['error_sold'] = "Select a spare and enter a valid quantity.";
    }
}

// Fetch manual spares for left table
$manual_spares = $conn->query("SELECT * FROM spares_employee ORDER BY entry_date DESC");

// Fetch admin spares for select dropdown and sold spares for right table
$admin_spares = $conn->query("SELECT * FROM spares_admin ORDER BY name ASC");
$sold_spares = $conn->query("SELECT ss.*, sa.name FROM spares_sold ss JOIN spares_admin sa ON ss.spare_id = sa.id ORDER BY ss.sale_date DESC");

// Calculate totals for manual spares
$total_manual = 0;
if ($manual_spares) {
    foreach ($manual_spares as $ms) {
        $total_manual += $ms['amount'];
    }
}

// Calculate totals for sold spares
$total_sold = 0;
if ($sold_spares) {
    foreach ($sold_spares as $ss) {
        $total_sold += $ss['total_price'];
    }
}
?>

<style>
.container {
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
}
.section {
    flex: 1;
    min-width: 320px;
    border: 1px solid #ddd;
    padding: 20px;
    border-radius: 6px;
    background: #f9f9f9;
}
h3 {
    margin-top: 0;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
table, th, td {
    border: 1px solid #ccc;
}
th, td {
    padding: 8px;
    text-align: left;
}
.total-row {
    font-weight: bold;
    background-color: #eee;
}
input[readonly] {
    background: #eee;
}
.message-success {
    color: green;
    margin-bottom: 15px;
}
.message-error {
    color: red;
    margin-bottom: 15px;
}
</style>

<div class="main-content">
    <h2>Spares Management</h2>

    <div class="container">
        <!-- Left side: Manual spares input -->
        <div class="section">
            <h3>Manual Spares Entry</h3>

            <?php if (isset($_SESSION['success_manual'])): ?>
                <div class="message-success"><?php echo $_SESSION['success_manual']; unset($_SESSION['success_manual']); ?></div>
            <?php elseif (isset($_SESSION['error_manual'])): ?>
                <div class="message-error"><?php echo $_SESSION['error_manual']; unset($_SESSION['error_manual']); ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <label>Spare Name:</label><br>
                <input type="text" name="manual_spare_name" required><br><br>

                <label>Amount (KES):</label><br>
                <input type="number" step="0.01" name="manual_spare_amount" min="0.01" required><br><br>

                <button type="submit" name="add_manual_spare">Add Spare</button>
            </form>

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
                    <tr><td colspan="3">No manual spares added yet.</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total</td>
                        <td style="text-align:right;"><?php echo number_format($total_manual, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Right side: Admin spares sold -->
        <div class="section">
            <h3>Admin Spares Sold</h3>

            <?php if (isset($_SESSION['success_sold'])): ?>
                <div class="message-success"><?php echo $_SESSION['success_sold']; unset($_SESSION['success_sold']); ?></div>
            <?php elseif (isset($_SESSION['error_sold'])): ?>
                <div class="message-error"><?php echo $_SESSION['error_sold']; unset($_SESSION['error_sold']); ?></div>
            <?php endif; ?>

            <form method="POST" id="soldSpareForm" novalidate>
                <label>Select Spare:</label><br>
                <select name="spare_id" id="spareSelect" required>
                    <option value="">-- Select Spare --</option>
                    <?php foreach ($admin_spares as $spare): ?>
                        <?php
                            $available_qty = $spare['total_quantity'] - $spare['sold_quantity'];
                        ?>
                        <option 
                            value="<?php echo $spare['id']; ?>" 
                            data-price="<?php echo $spare['unit_price']; ?>"
                            data-available="<?php echo $available_qty; ?>"
                        >
                            <?php echo htmlspecialchars($spare['name']) . " (KES " . number_format($spare['unit_price'], 2) . ") - Available: $available_qty"; ?>
                        </option>
                    <?php endforeach; ?>
                </select><br><br>

                <label>Quantity Sold:</label><br>
                <input type="number" step="1" min="1" name="quantity_sold" id="quantitySold" required><br><br>

                <label>Total Price (KES):</label><br>
                <input type="text" id="totalPrice" readonly value="0.00"><br><br>

                <button type="submit" name="add_sold_spare">Record Sale</button>
            </form>

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
                <?php if ($sold_spares && $sold_spares->num_rows > 0): ?>
                    <?php foreach ($sold_spares as $sold): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sold['name']); ?></td>
                            <td style="text-align:right;"><?php echo number_format($sold['quantity_sold'], 0); ?></td>
                            <td style="text-align:right;"><?php echo number_format($sold['total_price'], 2); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($sold['sale_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">No spares sold yet.</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2">Total</td>
                        <td style="text-align:right;"><?php echo number_format($total_sold, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    const spareSelect = document.getElementById('spareSelect');
    const quantityInput = document.getElementById('quantitySold');
    const totalPriceInput = document.getElementById('totalPrice');

    function calculateTotal() {
        const selectedOption = spareSelect.options[spareSelect.selectedIndex];
        const price = parseFloat(selectedOption?.dataset?.price || 0);
        const available = parseInt(selectedOption?.dataset?.available || 0);
        let quantity = parseInt(quantityInput.value) || 0;

        // Limit quantity to available stock
        if (quantity > available) {
            quantity = available;
            quantityInput.value = quantity;
            alert(`Quantity adjusted to available stock: ${available}`);
        }
        if (quantity < 1) {
            quantity = 0;
        }

        const total = price * quantity;
        totalPriceInput.value = total.toFixed(2);
    }

    spareSelect.addEventListener('change', () => {
        quantityInput.value = '';
        totalPriceInput.value = '0.00';
    });

    quantityInput.addEventListener('input', calculateTotal);
</script>

<?php include 'footer.php'; ?>
