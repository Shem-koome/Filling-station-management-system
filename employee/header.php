<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['email'])) {
    header("Location: ../authpage/login_out.php");
    exit();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Employee Dashboard - Shalom</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />
    <style>
        .alert-box { padding: 10px; margin: 10px 0; border-radius: 5px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }

        /* Collapsed sidebar style */
        .sidebar.collapsed {
            width: 0;
            overflow: hidden;
            transition: width 0.3s ease;
        }
        #sidebarToggle {
            cursor: pointer;
            font-size: 24px;
            z-index: 1001;
        }
    </style>
</head>
<body>

<!-- Alert box -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert-box alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert-box alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Header -->
<header class="dashboard-header">
    <div id="sidebarToggle"><i class="fas fa-bars"></i></div>
    <h2 class="center-title">Shalom Filling Station</h2>
    <div class="logout">
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <ul>
        <li class="<?= $currentPage == 'fuel_readings.php' ? 'active' : '' ?>">
            <a href="fuel_readings.php"><i class="fas fa-gas-pump"></i><span>Fuel Readings</span></a>
        </li>
        <li class="<?= $currentPage == 'cash_deposits.php' ? 'active' : '' ?>">
            <a href="cash_deposits.php"><i class="fas fa-cash-register"></i><span>Cash Deposits</span></a>
        </li>
        <li class="<?= $currentPage == 'till_money.php' ? 'active' : '' ?>">
            <a href="till_money.php"><i class="fas fa-money-check-alt"></i><span>Till Money</span></a>
        </li>
        <li class="<?= $currentPage == 'expenses.php' ? 'active' : '' ?>">
            <a href="expenses.php"><i class="fas fa-receipt"></i><span>Expenses</span></a>
        </li>
        <li class="<?= $currentPage == 'debts.php' ? 'active' : '' ?>">
            <a href="debts.php"><i class="fas fa-user-clock"></i><span>Debts</span></a>
        </li>
        <li class="<?= $currentPage == 'spares.php' ? 'active' : '' ?>">
            <a href="spares.php"><i class="fas fa-tools"></i><span>Spares</span></a>
        </li>
    </ul>
</div>

<!-- Sidebar Toggle Script -->
<script>
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
</script>
