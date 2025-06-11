<?php
session_start();
include 'db.php'; // database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Securely hash password

    // 1. Check if email already exists
    $checkQuery = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $checkResult = $stmt->get_result();

    if ($checkResult->num_rows > 0) {
        $_SESSION['error'] = "Email already registered. Please proceed to login.";
        header("Location: login_out.php");
        exit();
    }

    // 2. If email not registered, insert new user
    $query = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'employee')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $name, $email, $password);

    if ($stmt->execute()) {
        $_SESSION['email'] = $email;
        $_SESSION['success'] = "Account created successfully!";
        header("Location: ../employee/header.php");
        exit();
    } else {
        $_SESSION['error'] = "Error creating account. Please try again.";
        header("Location: login_out.php");
        exit();
    }
}
?>
