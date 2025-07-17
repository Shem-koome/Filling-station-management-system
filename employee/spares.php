<?php
include 'header.php';
require '../authpage/db.php';

date_default_timezone_set('Africa/Nairobi');
$filter_date = $_GET['date'] ?? date('Y-m-d');

// Handle employee manual spares input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual_spare'])) {
    $spare_name = trim($_POST['manual_spare_name']);
    $amount = floatval($_POST['manual_spare_amount']);

    if ($spare_name && $amount > 0) {
        $stmt = $conn->prepare("INSERT INTO spares_employee (name, amount, entry_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("sd", $spare_name, $amount);
        $stmt->execute();
        $_SESSION['success_manual'] = "Manual spare added.";
        header("Location: spares.php?date=".$filter_date);
        exit();
    } else {
        $_SESSION['error_manual'] = "Fill manual spare name and positive amount.";
    }
}

// Handle admin spare sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sold_spare'])) {
    $spare_id = intval($_POST['spare_id']);
    $quantity_sold = intval($_POST['quantity_sold']);

    $stmt = $conn->prepare("SELECT unit_price, total_quantity, sold_quantity FROM spares_admin WHERE id = ?");
    $stmt->bind_param("i", $spare_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $spare = $result->fetch_assoc();

    if ($spare && $quantity_sold > 0) {
        $available_stock = $spare['total_quantity'] - $spare['sold_quantity'];

        if ($quantity_sold <= $available_stock) {
            $total_price = $spare['unit_price'] * $quantity_sold;

            $stmt = $conn->prepare("INSERT INTO spares_sold (spare_id, quantity_sold, total_price, sale_date) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iid", $spare_id, $quantity_sold, $total_price);
            $stmt->execute();

            $new_sold_qty = $spare['sold_quantity'] + $quantity_sold;
            $stmt = $conn->prepare("UPDATE spares_admin SET sold_quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_sold_qty, $spare_id);
            $stmt->execute();

            $_SESSION['success_sold'] = "Spare sale recorded.";
            header("Location: spares.php?date=".$filter_date);
            exit();
        } else {
            $_SESSION['error_sold'] = "Insufficient stock. Available: $available_stock.";
        }
    } else {
        $_SESSION['error_sold'] = "Select a spare and enter a valid quantity.";
    }
}

// Fetch manual spares for selected date
$manual_stmt = $conn->prepare("SELECT * FROM spares_employee WHERE DATE(entry_date) = ? ORDER BY entry_date DESC");
$manual_stmt->bind_param("s", $filter_date);
$manual_stmt->execute();
$manual_spares = $manual_stmt->get_result();

// Fetch admin spares and sold spares for selected date
$admin_spares = $conn->query("SELECT * FROM spares_admin ORDER BY name ASC");
$sold_stmt = $conn->prepare("
    SELECT ss.*, sa.name 
    FROM spares_sold ss 
    JOIN spares_admin sa ON ss.spare_id = sa.id 
    WHERE DATE(ss.sale_date) = ? 
    ORDER BY ss.sale_date DESC
");
$sold_stmt->bind_param("s", $filter_date);
$sold_stmt->execute();
$sold_spares = $sold_stmt->get_result();

// Calculate totals
$total_manual = 0;
if ($manual_spares) foreach ($manual_spares as $ms) $total_manual += $ms['amount'];

$total_sold = 0;
if ($sold_spares) foreach ($sold_spares as $ss) $total_sold += $ss['total_price'];
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
.filter-bar {
    position: fixed;
    top: 30px; /* moved further down */
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.8); /* slightly more transparent */
    padding: 4px 8px; /* reduced padding */
    border: 1px solid #ccc;
    border-radius: 4px;
    z-index: 100;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

.filter-bar input[type="date"] {
    padding: 3px;
    font-size: 13px;
    width: 140px;
}
.filter-bar label {
    font-size: 13px;
    margin-right: 4px;
}
</style>

<div class="main-content">
    <h2>Spares Management</h2>

    <!-- Date filter on top right -->
    <div class="filter-bar">
        <form method="GET" onchange="this.submit()" style="display: inline;">
            <label>Filter by Date:</label>
            <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
        </form>
    </div>

    <div class="container">
        <!-- Left side -->
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
                <thead><tr><th>Spare Name</th><th>Amount</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if ($manual_spares->num_rows > 0): ?>
                        <?php foreach ($manual_spares as $ms): ?>
                            <tr>
                                <td><?= htmlspecialchars($ms['name']) ?></td>
                                <td style="text-align:right;"><?= number_format($ms['amount'], 2) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($ms['entry_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No manual spares added yet.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td>Total</td>
                        <td style="text-align:right;"><?= number_format($total_manual, 2) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Right side -->
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
                        <?php $available_qty = $spare['total_quantity'] - $spare['sold_quantity']; ?>
                        <option 
                            value="<?= $spare['id'] ?>"
                            data-price="<?= $spare['unit_price'] ?>"
                            data-available="<?= $available_qty ?>"
                        >
                            <?= htmlspecialchars($spare['name'])." (KES ".number_format($spare['unit_price'],2).") - Avail: ".$available_qty ?>
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
                <thead><tr><th>Name</th><th>Qty</th><th>Total Price</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if ($sold_spares->num_rows > 0): ?>
                        <?php foreach ($sold_spares as $sold): ?>
                            <tr>
                                <td><?= htmlspecialchars($sold['name']) ?></td>
                                <td style="text-align:right;"><?= $sold['quantity_sold'] ?></td>
                                <td style="text-align:right;"><?= number_format($sold['total_price'],2) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($sold['sale_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No spares sold yet.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2">Total</td>
                        <td style="text-align:right;"><?= number_format($total_sold,2) ?></td>
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

        if (quantity > available) {
            quantity = available;
            quantityInput.value = quantity;
            alert(`Quantity adjusted to available stock: ${available}`);
        }
        if (quantity < 1) quantity = 0;
        totalPriceInput.value = (price * quantity).toFixed(2);
    }

    spareSelect.addEventListener('change', () => {
        quantityInput.value = '';
        totalPriceInput.value = '0.00';
    });
    quantityInput.addEventListener('input', calculateTotal);
</script>

<?php include 'footer.php'; ?>
