<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch only recipes favorited by the logged-in user
$query = "SELECT r.*, u.username, c.name as category_name 
          FROM recipes r
          JOIN users u ON r.user_id = u.id
          JOIN categories c ON r.category_id = c.id
          JOIN favorites f ON r.id = f.recipe_id
          WHERE f.user_id = :uid
          ORDER BY f.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([':uid' => $user_id]);
$favorites = $stmt->fetchAll();

// We still need this array to render the active ❤️ correctly
$user_favorites = array_column($favorites, 'id');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Favorites - Barangay Bakers</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
        .recipe-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .recipe-meta { font-size: 0.9em; color: #666; margin-bottom: 10px; }
        .fav-btn { cursor: pointer; background: none; border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; }
        .fav-btn.active { color: red; border-color: red; }
    </style>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
    <header class="site-header">
        <a href="index.php" class="site-title">🥖 Barangay Bakers Archive</a>
        <nav class="nav-links">
            <a href="index.php">Archive Feed</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="my_favorites.php">❤️ My Favorites</a>
                <a href="post_recipe.php" class="btn btn-primary" style="color:white;">+ Post Recipe</a>
                <a href="logout.php" style="color:#888;">Logout (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
            <?php else: ?>
                <a href="login.php">Log In</a>
                <a href="register.php" class="btn btn-primary" style="color:white;">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <div>
        <?php if ($favorites): ?>
            <?php foreach ($favorites as $r): ?>
                <div class="recipe-card" id="recipe-card-<?= $r['id'] ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h2 style="margin: 0 0 5px 0;">
                            <a href="view_recipe.php?id=<?= $r['id'] ?>" style="text-decoration: none; color: #8B0000;">
                                <?= htmlspecialchars($r['title']) ?>
                            </a>
                        </h2>
                        
                        <!-- AJAX FAVORITE BUTTON -->
                        <button type="button" 
                                class="fav-btn active" 
                                onclick="toggleFavorite(<?= $r['id'] ?>, this)">
                            ❤️ Favorited
                        </button>
                    </div>

                    <div class="recipe-meta">
                        By <strong><?= htmlspecialchars($r['username']) ?></strong> | 
                        Category: <?= htmlspecialchars($r['category_name']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>You haven't saved any recipes yet! Go back to the <a href="index.php">Archive</a> to find some.</p>
        <?php endif; ?>
    </div>

    <script>
    function toggleFavorite(recipeId, buttonElement) {
        fetch('toggle_favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: recipeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'removed') {
                // If they remove it from this page, visually hide the card so it feels clean
                document.getElementById('recipe-card-' + recipeId).style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>