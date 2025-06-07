<?php
session_start();
include 'db.php'; // database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['email'] = $user['email'];
            $_SESSION['success'] = "Welcome, {$user['email']}!";

            if ($user['role'] === 'admin') {
                header("Location: ../admin/header.php");
            } else {
                header("Location: ../employee/header.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Incorrect password.";
            header("Location: login_out.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Email not found.";
        header("Location: login_out.php");
        exit();
    }
}
?>
