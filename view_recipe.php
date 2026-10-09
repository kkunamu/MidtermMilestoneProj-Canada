<?php
session_start();
require_once 'config/db.php';
require_once 'classes/Comment.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$recipe_id = $_GET['id'];

// 1. Handle New Comment Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (isset($_SESSION['user_id'])) {
        $new_comment = new Comment($db);
        $new_comment->recipe_id = $recipe_id;
        $new_comment->user_id = $_SESSION['user_id'];
        $new_comment->body = $_POST['body'];
        
        $new_comment->create();
        
        header("Location: view_recipe.php?id=" . $recipe_id);
        exit();
    }
}

// 2. Handle Inline Comment Edit Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit_comment') {
    if (isset($_SESSION['user_id']) && isset($_POST['comment_id'])) {
        $edit_comment = new Comment($db);
        $edit_comment->id = $_POST['comment_id'];
        $edit_comment->user_id = $_SESSION['user_id'];
        $edit_comment->body = $_POST['body'];
        
        $edit_comment->update();
        
        header("Location: view_recipe.php?id=" . $recipe_id);
        exit();
    }
}

// 3. Fetch Core Recipe Details
$query = "SELECT r.*, u.username, c.name as category_name, 
                 p.id as parent_id, p.title as parent_title
          FROM recipes r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN categories c ON r.category_id = c.id
          LEFT JOIN recipes p ON r.parent_recipe_id = p.id
          WHERE r.id = :id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $recipe_id);
$stmt->execute();
$recipe = $stmt->fetch();

if (!$recipe) {
    die("This recipe has been deleted or does not exist.");
}

// 4. Fetch AO3-Style Tags
$query_tags = "SELECT t.name FROM tags t 
               JOIN recipe_tags rt ON t.id = rt.tag_id 
               WHERE rt.recipe_id = :id";
$stmt_tags = $db->prepare($query_tags);
$stmt_tags->bindParam(':id', $recipe_id);
$stmt_tags->execute();
$tags = $stmt_tags->fetchAll();

// 5. Fetch Ingredients grouped by section
$query_ing = "SELECT i.name, ri.quantity, ri.unit, ri.preparation_note, ri.section 
              FROM ingredients i
              JOIN recipe_ingredients ri ON i.id = ri.ingredient_id
              WHERE ri.recipe_id = :id";
$stmt_ing = $db->prepare($query_ing);
$stmt_ing->bindParam(':id', $recipe_id);
$stmt_ing->execute();
$raw_ingredients = $stmt_ing->fetchAll();

$ingredients_by_section = [];
foreach ($raw_ingredients as $ing) {
    $ingredients_by_section[$ing['section']][] = $ing;
}

// 6. Fetch Dynamic Steps
$query_steps = "SELECT step_number, instruction FROM recipe_steps WHERE recipe_id = :id ORDER BY step_number ASC";
$stmt_steps = $db->prepare($query_steps);
$stmt_steps->bindParam(':id', $recipe_id);
$stmt_steps->execute();
$steps = $stmt_steps->fetchAll();

// 7. Fetch Remixes
$query_remixes = "SELECT id, title FROM recipes WHERE parent_recipe_id = :id";
$stmt_remixes = $db->prepare($query_remixes);
$stmt_remixes->bindParam(':id', $recipe_id);
$stmt_remixes->execute();
$remixes = $stmt_remixes->fetchAll();

// 8. Fetch Comments
$comment_manager = new Comment($db);
$comments = $comment_manager->readByRecipe($recipe_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($recipe['title']) ?> - Barangay Bakers</title>
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

    <!-- OWNER CONTROL BUTTONS -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['user_id']): ?>
        <div class="recipe-owner-actions">
            <a href="edit_recipe.php?id=<?= $recipe_id ?>" class="btn btn-secondary">✏️ Edit Recipe</a>
            <form action="delete_recipe.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will permanently delete this recipe, ingredients, and all comments.');">
                <input type="hidden" name="recipe_id" value="<?= $recipe_id ?>">
                <button type="submit" class="btn btn-danger">🗑️ Delete Recipe</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- AO3 METADATA CARD -->
    <div class="metadata-block">
        <div class="meta-row">
            <span class="meta-label">Category:</span> 
            <strong><?= htmlspecialchars($recipe['category_name']) ?></strong>
        </div>
        <div class="meta-row">
            <span class="meta-label">Tags:</span> 
            <span class="tag-container" style="display:inline-flex; vertical-align: middle;">
                <?php if ($tags): ?>
                    <?php foreach ($tags as $tag): ?>
                        <a href="index.php?tag=<?= urlencode($tag['name']) ?>" class="tag-pill">#<?= htmlspecialchars($tag['name']) ?></a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <em style="color: var(--text-muted);">No tags attached</em>
                <?php endif; ?>
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Author:</span> 
            <strong><?= htmlspecialchars($recipe['username']) ?></strong>
        </div>
        <div class="meta-row">
            <span class="meta-label">Posted:</span> 
            <?= date("F j, Y", strtotime($recipe['created_at'])) ?>
            <?php if (!empty($recipe['updated_at']) && strtotime($recipe['updated_at']) > strtotime($recipe['created_at'])): ?>
                <span class="tag-pill" style="background: #f3ebe0; color: var(--primary-maroon); font-weight: bold; margin-left: 8px;">✏️ Edited</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN RECIPE CARD -->
    <div class="recipe-main-card">
        <h1 class="recipe-hero-title"><?= htmlspecialchars($recipe['title']) ?></h1>

        <!-- REMIX INSPIRATION BANNERS -->
        <?php if ($recipe['parent_id']): ?>
            <div class="remix-alert">
                💡 Inspired by: <a href="view_recipe.php?id=<?= $recipe['parent_id'] ?>"><?= htmlspecialchars($recipe['parent_title']) ?></a>
            </div>
        <?php endif; ?>

        <?php if ($remixes): ?>
            <div class="remix-alert">
                <strong>Remixes of this work:</strong><br>
                <?php foreach ($remixes as $remix): ?>
                    &bull; <a href="view_recipe.php?id=<?= $remix['id'] ?>"><?= htmlspecialchars($remix['title']) ?></a><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- SUMMARY STORY -->
        <div class="summary-box">
            <strong>Summary:</strong><br>
            <?= nl2br(htmlspecialchars($recipe['description'])) ?>
        </div>

        <!-- INGREDIENTS -->
        <h3 class="section-title">Ingredients</h3>
        <?php foreach ($ingredients_by_section as $section => $ings): ?>
            <div class="ingredient-section-block">
                <div class="ingredient-group-header"><?= htmlspecialchars($section) ?></div>
                <ul class="ingredient-badge-list">
                    <?php foreach ($ings as $ing): ?>
                        <li>
                            <span class="qty-pill"><?= floatval($ing['quantity']) ?> <?= htmlspecialchars($ing['unit']) ?></span> 
                            <span><strong><?= htmlspecialchars($ing['name']) ?></strong></span>
                            <?php if (!empty($ing['preparation_note'])): ?>
                                <em style="color: var(--text-muted); font-size: 0.9em;">(<?= htmlspecialchars($ing['preparation_note']) ?>)</em>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <!-- INSTRUCTIONS -->
        <h3 class="section-title">Instructions</h3>
        <div class="step-card-list">
            <?php foreach ($steps as $index => $step): ?>
                <div class="step-card">
                    <span class="step-badge">Step <?= $index + 1 ?></span>
                    <div class="step-body">
                        <?= nl2br(htmlspecialchars($step['instruction'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- COMMENTS SECTION -->
    <div class="comments-section">
        <h2 class="page-title" style="font-size: 1.5rem;">Feedback & Comments</h2>

        <!-- FORM CARD -->
        <div class="comment-form-card">
            <?php if (isset($_SESSION['user_id'])): ?>
                <form action="view_recipe.php?id=<?= $recipe_id ?>" method="POST">
                    <input type="hidden" name="action" value="add_comment">
                    <label style="margin-bottom: 10px;">Leave a Comment as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>:</label>
                    <textarea name="body" rows="3" required placeholder="Share your results, substitutions, or praise..."></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: 12px;">Post Comment</button>
                </form>
            <?php else: ?>
                <p><em>Please <a href="login.php" style="color: var(--primary-maroon); font-weight: bold;">log in</a> or <a href="register.php" style="color: var(--primary-maroon); font-weight: bold;">register</a> to leave feedback.</em></p>
            <?php endif; ?>
        </div>

        <!-- COMMENT LIST -->
        <div class="comments-list">
            <?php if ($comments): ?>
                <?php foreach ($comments as $c): ?>
                    <div class="comment-card" id="comment-card-<?= $c['id'] ?>">
                        <div class="comment-header">
                            <strong><?= htmlspecialchars($c['username']) ?></strong> 
                            commented on <?= date("F j, Y, g:i a", strtotime($c['created_at'])) ?>
                            <?php if (!empty($c['updated_at']) && strtotime($c['updated_at']) > strtotime($c['created_at'])): ?>
                                <span class="tag-pill" style="background: #f3ebe0; color: var(--primary-maroon); font-weight: bold; font-size: 0.75rem; margin-left: 6px;">✏️ Edited</span>
                            <?php endif; ?>
                        </div>

                        <!-- DISPLAY TEXT -->
                        <div id="comment-body-<?= $c['id'] ?>" class="comment-body">
                            <?= nl2br(htmlspecialchars($c['body'])) ?>
                        </div>

                        <!-- INLINE EDIT FORM (HIDDEN BY DEFAULT) -->
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $c['user_id']): ?>
                            <form id="edit-form-<?= $c['id'] ?>" action="view_recipe.php?id=<?= $recipe_id ?>" method="POST" style="display: none; margin-top: 10px;">
                                <input type="hidden" name="action" value="edit_comment">
                                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                <textarea name="body" rows="3" required style="width: 100%; margin-bottom: 8px;"><?= htmlspecialchars($c['body']) ?></textarea>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn btn-primary" style="padding: 5px 12px; font-size: 0.85rem;">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" style="padding: 5px 12px; font-size: 0.85rem;" onclick="toggleEditComment(<?= $c['id'] ?>)">Cancel</button>
                                </div>
                            </form>

                            <div style="margin-top: 10px; font-size: 0.85em;">
                                <button type="button" onclick="toggleEditComment(<?= $c['id'] ?>)" style="background:none; border:none; color: var(--text-muted); font-weight: bold; cursor: pointer; padding: 0;">Edit</button> | 
                                <form action="delete_comment.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this comment?');">
                                    <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                    <input type="hidden" name="recipe_id" value="<?= $recipe_id ?>">
                                    <button type="submit" style="background:none; border:none; color: #8B0000; text-decoration: underline; cursor: pointer; padding: 0; font-size: inherit;">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-muted); font-style: italic;">No comments yet. Be the first to review this recipe!</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleEditComment(commentId) {
    const bodyDiv = document.getElementById('comment-body-' + commentId);
    const editForm = document.getElementById('edit-form-' + commentId);
    
    if (editForm.style.display === 'none') {
        editForm.style.display = 'block';
        bodyDiv.style.display = 'none';
    } else {
        editForm.style.display = 'none';
        bodyDiv.style.display = 'block';
    }
}
</script>

</body>
</html>