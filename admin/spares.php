<?php
include 'header.php';
require '../authpage/db.php';

date_default_timezone_set('Africa/Nairobi');

// Get filter date or default to today
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch spares
$spares = $conn->query("SELECT * FROM spares_admin ORDER BY name ASC");

// Fetch sold spares filtered by date
$sold_stmt = $conn->prepare("SELECT ss.*, sa.name 
    FROM spares_sold ss 
    JOIN spares_admin sa ON ss.spare_id = sa.id 
    WHERE DATE(ss.sale_date) = ?
    ORDER BY ss.sale_date DESC");
$sold_stmt->bind_param("s", $filter_date);
$sold_stmt->execute();
$sold_spares = $sold_stmt->get_result();

// Total for the selected day
$day_total_stmt = $conn->prepare("SELECT SUM(total_price) as day_total 
    FROM spares_sold WHERE DATE(sale_date) = ?");
$day_total_stmt->bind_param("s", $filter_date);
$day_total_stmt->execute();
$day_total_result = $day_total_stmt->get_result();
$day_total = $day_total_result->fetch_assoc()['day_total'] ?? 0;

// Grand total all time
$grand_total_result = $conn->query("SELECT SUM(total_price) as grand_total FROM spares_sold");
$grand_total = $grand_total_result->fetch_assoc()['grand_total'] ?? 0;


// Fetch manual spares (not filtered)
$manual_spares = $conn->query("SELECT * FROM spares_employee ORDER BY entry_date DESC");
$total_manual = 0;
if ($manual_spares) {
    foreach ($manual_spares as $ms) {
        $total_manual += $ms['amount'];
    }
}
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
            <div style="margin-top: 20px;">
    <strong>Total for <?php echo $filter_date; ?>:</strong> KES <?php echo number_format($day_total, 2); ?><br>
    <strong>Grand Total (All Time):</strong> KES <?php echo number_format($grand_total, 2); ?>
</div>
        </div>

        <!-- Sold Spares -->
        <div class="section">
            <h3>Sold Spares</h3>
            <label for="date_filter">Select Date:</label>
            <input type="date" id="date_filter" value="<?php echo $filter_date; ?>">

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
                        <tr><td colspan="4">No spares sold on this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Manual Spares -->
        <div class="section">
            <h3>Manual Spares (Employee Input)</h3>
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

<script>
document.getElementById('date_filter').addEventListener('change', function() {
    const selectedDate = this.value;
    if (selectedDate) {
        window.location.href = '?date=' + selectedDate;
    }
});
</script>

<?php include 'footer.php'; ?>
