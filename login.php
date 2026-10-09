<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier'] ?? ''); // Can be email or username
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $database = new Database();
        $db = $database->getConnection();

        // Search for a matching email OR username
        $stmt = $db->prepare("SELECT id, username, email, password FROM users WHERE username = :identifier OR email = :identifier LIMIT 1");
        $stmt->execute([':identifier' => $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Barangay Bakers</title>
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
        <h2 class="auth-title">Welcome Back!</h2>
        <p class="auth-subtitle">Log in to view recipes, comment, and save favorites.</p>

        <?php if ($error): ?>
            <div class="alert-message alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert-message alert-success">Account created successfully! You can now log in.</div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="identifier">Username or Email Address:</label>
                <input type="text" id="identifier" name="identifier" required placeholder="Enter username or email" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
            </div>

            <button type="submit" style="width: 100%; padding: 12px; margin-top: 10px; font-size: 1rem;">
                ☕ Log In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Join the Barangay</a>
        </div>
    </div>
</div>

</body>
</html>