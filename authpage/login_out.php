<?php
session_start(); // Must be at the very top
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shalom</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- ALERT BOX -->
    <div id="alert-box" class="alert-box" style="display: none;"></div>

    <div class="container" id="container">
        <!-- Sign Up Form -->
        <div class="form-container sign-up">
            <form action="create.php" method="POST">
                <h1>Create Account</h1>
                <div class="social-icons">
                    <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email for registration</span>
                <input type="text" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <div class="input-wrapper">
                <input type="password" name="password" placeholder="Password" class="password-field" required>
                <i class="fa-solid fa-eye toggle-password"></i>
                </div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit">Sign Up</button>
            </form>
        </div>

        <!-- Sign In Form -->
        <div class="form-container sign-in">
            <form action="login.php" method="POST">
                <h1>Sign In</h1>
                <div class="social-icons">
                    <a href="#" class="icon"><i class="fa-brands fa-google-plus-g"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-github"></i></a>
                    <a href="#" class="icon"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
                <span>or use your email and password</span>
                <input type="email" name="email" placeholder="Email" required>
                <div class="input-wrapper">
                <input type="password" name="password" placeholder="Password" class="password-field" required>
                <i class="fa-solid fa-eye toggle-password"></i>
                </div>
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <a href="#">Forgot Your Password?</a>
                <button type="submit">Sign In</button>
            </form>
        </div>

        <!-- Toggle Panels -->
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <h1>Welcome Back!</h1>
                    <p>If you already have an account, click Sign In</p>
                    <button class="hidden" id="login">Sign In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <h1>Hello, Friend!</h1>
                    <p>If you don't have an account, create one here</p>
                    <button class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Alert Function First -->
    <script>
    function showAlert(message, type = 'success') {
        const alertBox = document.getElementById('alert-box');
        alertBox.innerText = message;
        alertBox.className = 'alert-box ' + (type === 'error' ? 'alert-error' : 'alert-success');
        alertBox.style.display = 'block';

        setTimeout(() => {
            alertBox.style.display = 'none';
        }, 3000);
    }
    </script>

    <!-- Now Trigger the Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <script>
            showAlert("<?php echo addslashes($_SESSION['success']); ?>", "success");
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            showAlert("<?php echo addslashes($_SESSION['error']); ?>", "error");
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <script src="script.js"></script>
</body>
</html>
