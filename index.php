<?php
session_start();
require_once 'config/db.php';

$database = new Database();
$db = $database->getConnection();

// 1. Capture Search and Filter inputs from the URL
$search_query = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$tag_filter = $_GET['tag'] ?? '';

// Fetch all categories for the dropdown filter
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// 2. Build the Dynamic SQL Query
$query = "SELECT r.*, u.username, c.name as category_name 
          FROM recipes r
          JOIN users u ON r.user_id = u.id
          JOIN categories c ON r.category_id = c.id
          WHERE 1=1"; // 1=1 is a trick to make appending "AND" clauses easy

$params = [];

if ($search_query) {
    // Search Title, Username, or check if the recipe has a matching Tag
    $query .= " AND (r.title LIKE :search 
                     OR u.username LIKE :search 
                     OR r.id IN (SELECT recipe_id FROM recipe_tags rt JOIN tags t ON rt.tag_id = t.id WHERE t.name LIKE :search))";
    $params[':search'] = '%' . $search_query . '%';
}

if ($category_id) {
    $query .= " AND r.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if ($tag_filter) {
    // Strict match if they clicked a tag link
    $query .= " AND r.id IN (SELECT recipe_id FROM recipe_tags rt JOIN tags t ON rt.tag_id = t.id WHERE t.name = :tag)";
    $params[':tag'] = $tag_filter;
}

$query .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

// 3. Fetch User's Favorites so we know which heart to show (filled or empty)
$user_favorites = [];
if (isset($_SESSION['user_id'])) {
    $fav_stmt = $db->prepare("SELECT recipe_id FROM favorites WHERE user_id = :uid");
    $fav_stmt->execute([':uid' => $_SESSION['user_id']]);
    $user_favorites = $fav_stmt->fetchAll(PDO::FETCH_COLUMN); // Returns an array of just the recipe IDs
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Bakers - Archive</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 0 auto; padding: 20px; }
        .recipe-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fafafa; }
        .recipe-meta { font-size: 0.9em; color: #666; margin-bottom: 10px; }
        .tag-link { background: #e0e0e0; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; text-decoration: none; color: #333; margin-right: 5px; display: inline-block; }
        .tag-link:hover { background: #ccc; }
        .search-bar { background: #eee; padding: 15px; border-radius: 5px; margin-bottom: 30px; }
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

    <!-- SEARCH & FILTER FORM -->
    <div class="search-card">
        <form action="index.php" method="GET" class="search-form">
            <input type="text" name="search" placeholder="Search titles, authors, or tags..." value="<?= htmlspecialchars($search_query) ?>">
            
            <select name="category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">Search</button>
            
            <?php if ($search_query || $category_id || $tag_filter): ?>
                <a href="index.php" class="btn btn-secondary">Clear Filters</a>
            <?php endif; ?>
        </form>
        
        <?php if ($tag_filter): ?>
            <p style="margin-top: 12px; margin-bottom: 0; font-size: 0.9em; color: var(--text-muted);">
                <strong>Filtering by tag:</strong> #<?= htmlspecialchars($tag_filter) ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- RECIPE FEED -->
    <div>
        <?php if ($recipes): ?>
            <?php foreach ($recipes as $r): ?>
                <div class="recipe-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h2 style="margin: 0 0 5px 0;">
                            <a href="view_recipe.php?id=<?= $r['id'] ?>" style="text-decoration: none; color: #8B0000;">
                                <?= htmlspecialchars($r['title']) ?>
                            </a>
                        </h2>
                        
                        <!-- AJAX FAVORITE BUTTON -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php $is_fav = in_array($r['id'], $user_favorites); ?>
                            <button type="button" 
                                    class="fav-btn <?= $is_fav ? 'active' : '' ?>" 
                                    onclick="toggleFavorite(<?= $r['id'] ?>, this)">
                                <?= $is_fav ? '❤️ Favorited' : '🤍 Save' ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="recipe-meta">
                        By <strong><?= htmlspecialchars($r['username']) ?></strong> | 
                        Category: <?= htmlspecialchars($r['category_name']) ?> | 
                        Posted: <?= date("M j, Y", strtotime($r['created_at'])) ?>
                    </div>

                    <!-- FETCH AND DISPLAY TAGS FOR THIS RECIPE -->
                    <?php
                        $t_stmt = $db->prepare("SELECT t.name FROM tags t JOIN recipe_tags rt ON t.id = rt.tag_id WHERE rt.recipe_id = :id");
                        $t_stmt->execute([':id' => $r['id']]);
                        $recipe_tags = $t_stmt->fetchAll();
                    ?>
                    <?php if ($recipe_tags): ?>
                        <div style="margin-bottom: 10px;">
                            <?php foreach ($recipe_tags as $t): ?>
                                <!-- Clicking a tag passes it exactly into the URL filter -->
                                <a href="index.php?tag=<?= urlencode($t['name']) ?>" class="tag-link">#<?= htmlspecialchars($t['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p style="margin-bottom: 0;"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No recipes found matching your criteria. Try adjusting your search!</p>
        <?php endif; ?>
    </div>

    <!-- AJAX JAVASCRIPT -->
    <script>
    function toggleFavorite(recipeId, buttonElement) {
        // Use Fetch API to send data without reloading
        fetch('toggle_favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ recipe_id: recipeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            // Update the button visuals based on what the server did
            if (data.status === 'added') {
                buttonElement.innerHTML = '❤️ Favorited';
                buttonElement.classList.add('active');
            } else if (data.status === 'removed') {
                buttonElement.innerHTML = '🤍 Save';
                buttonElement.classList.remove('active');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Something went wrong processing your request.");
        });
    }
    </script>
</body>
</html>