<?php
session_start();
require_once 'config/db.php';
require_once 'classes/User.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $user->username = $_POST['username'];
    $user->email = $_POST['email'];
    $user->password = $_POST['password'];

    if ($user->register()) {
        $message = "<p style='color: green;'>Registration successful! You can now <a href='login.php'>log in</a>.</p>";
    } else {
        $message = "<p style='color: red;'>Registration failed. The username or email might already be taken.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Barangay Bakers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Join the Archive</h2>
    <?= $message ?>
    <form action="register.php" method="POST">
        <div>
            <label for="username">Username:</label><br>
            <input type="text" name="username" id="username" required>
        </div>
        <br>
        <div>
            <label for="email">Email Address:</label><br>
            <input type="email" name="email" id="email" required>
        </div>
        <br>
        <div>
            <label for="password">Password:</label><br>
            <input type="password" name="password" id="password" required>
        </div>
        <br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Log in here</a>.</p>
</body>
</html>