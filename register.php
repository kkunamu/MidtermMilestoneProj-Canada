<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please complete all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        // Check if username OR email is already taken
        $check = $db->prepare("SELECT id, username, email FROM users WHERE username = :username OR email = :email LIMIT 1");
        $check->execute([':username' => $username, ':email' => $email]);
        $existing = $check->fetch();

        if ($existing) {
            if ($existing['username'] === $username) {
                $error = "That username is already taken. Please pick another!";
            } else {
                $error = "That email address is already registered. Try logging in!";
            }
        } else {
            // Hash password securely
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $insert = $db->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
            if ($insert->execute([':username' => $username, ':email' => $email, ':password' => $hashed_password])) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Barangay Bakers</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="auth-container">
    <div style="text-align: center; margin-bottom: 20px;">
        <a href="index.php" class="site-title" style="justify-content: center; font-size: 1.8rem;">
            🥖 Barangay Bakers
        </a>
        <span class="filipino-badge" style="margin-top: 6px; display: inline-block;">Merienda & Panaderia Archive</span>
    </div>

    <div class="auth-card">
        <h2 class="auth-title">Create an Account</h2>
        <p class="auth-subtitle">Share your home recipes and secret panaderia bakes!</p>

        <?php if ($error): ?>
            <div class="alert-message alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required placeholder="Pick a username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required placeholder="e.g. baker@barangay.ph" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; margin-top: 10px; font-size: 1rem;">
                🥐 Register Account
            </button>
        </form>

        <div class="auth-footer">
            Already a member? <a href="login.php">Log in here</a>
        </div>
    </div>
</div>

</body>
</html>