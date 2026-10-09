<?php
session_start();
require_once 'config/db.php';
require_once 'classes/User.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $user = new User($db);
    $user->email = $_POST['email'];
    $user->password = $_POST['password'];

    if ($user->login()) {
        // Store user details in the session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        
        // Redirect to the main feed/archive
        header("Location: index.php");
        exit();
    } else {
        $message = "<p style='color: red;'>Invalid email or password. Please try again.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In - Barangay Bakers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Log In</h2>
    <?= $message ?>
    <form action="login.php" method="POST">
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
        <button type="submit">Log In</button>
    </form>
    <p>Need an account? <a href="register.php">Register here</a>.</p>
</body>
</html>