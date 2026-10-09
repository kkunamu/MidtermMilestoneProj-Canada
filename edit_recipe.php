<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';
require_once 'classes/Recipe.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tag.php';

$database = new Database();$db = $database->getConnection();$recipe_id = $_GET['id'];$message = '';

// Fetch Dropdown Data
$stmt = $db->prepare("SELECT id, title FROM recipes WHERE id != :id ORDER BY title ASC");
$stmt->execute([':id' => $recipe_id]);
$existing_recipes = $stmt->fetchAll();

// Fetch Existing Recipe
$stmt =$db->prepare("SELECT * FROM recipes WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $recipe_id, ':user_id' =>$_SESSION['user_id']]);
$recipe_data =$stmt->fetch();

if (!$recipe_data) {
    die("Recipe not found or you do not have permission to edit it.");
}

// Helper to parse fractions (same as post_recipe.php)
function parseFraction($fraction_str) {
    $fraction_str = trim($fraction_str);
    if (strpos($fraction_str, '/') !== false) {$parts = explode(' ', $fraction_str);$total = 0;
        foreach ($parts as$part) {
            if (strpos($part, '/') !== false) {
                list($num, $den) = explode('/',$part);
                if ($den != 0)$total += ($num / $den);
            } else {
                $total += (float)$part;
            }
        }
        return $total;
    }
    return (float)$fraction_str;
}

// HANDLE UPDATE SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction(); 

        $recipe = new Recipe($db);
        $recipe->id =$recipe_id;
        $recipe->user_id = $_SESSION['user_id'];$recipe->category_id = $_POST['category_id'];$recipe->parent_recipe_id = !empty($_POST['parent_recipe_id']) ?$_POST['parent_recipe_id'] : null;
        $recipe->title =$_POST['title'];
        $recipe->description =$_POST['description'];

        if ($recipe->update()) {$recipe->clearRelations(); // Wipe old attachments

            $ingredient_manager = new Ingredient($db);
            $tag_manager = new Tag($db);

            // 1. Re-insert Ingredients
            if (isset($_POST['ing_name']) && is_array($_POST['ing_name'])) {
                for ($i = 0; $i < count($_POST['ing_name']); $i++) {$name = $_POST['ing_name'][$i];
                    $qty = parseFraction($_POST['ing_qty'][$i]);$unit = $_POST['ing_unit'][$i];
                    $note = $_POST['ing_note'][$i];$section = !empty($_POST['ing_section'][$i]) ? $_POST['ing_section'][$i] : 'Main';
                    
                    if (!empty($name) && !empty($qty)) {
                        $ingredient_manager->addToRecipe($recipe_id, $name,$qty, $unit, $note, $section);
                    }
                }
            }

            // 2. Re-insert Steps
            if (isset($_POST['step_instruction']) && is_array($_POST['step_instruction'])) {$step_number = 1;
                foreach ($_POST['step_instruction'] as$instruction) {
                    if (!empty(trim($instruction))) {$recipe->addStep($recipe_id,$step_number, $instruction);$step_number++;
                    }
                }
            }

            // 3. Re-insert Tags
            if (!empty($_POST['tags'])) {
                $tags_array = explode(',',$_POST['tags']);
                foreach ($tags_array as$tag) {
                    $clean_tag = trim($tag);
                    if (!empty($clean_tag)) {$tag_manager->addTagToRecipe($recipe_id,$clean_tag);
                    }
                }
            }

            $db->commit(); 
            header("Location: view_recipe.php?id=" . $recipe_id);
            exit();
        }
    } catch (Exception $e) {
        $db->rollBack();$message = "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
    }
}

// Fetch Existing Tags, Ingredients, and Steps for the form
$stmt_tags =$db->prepare("SELECT t.name FROM tags t JOIN recipe_tags rt ON t.id = rt.tag_id WHERE rt.recipe_id = :id");
$stmt_tags->execute([':id' =>$recipe_id]);
$existing_tags = implode(', ', array_column($stmt_tags->fetchAll(), 'name'));

$stmt_ing =$db->prepare("SELECT i.name, ri.quantity, ri.unit, ri.preparation_note, ri.section FROM ingredients i JOIN recipe_ingredients ri ON i.id = ri.ingredient_id WHERE ri.recipe_id = :id");
$stmt_ing->execute([':id' =>$recipe_id]);
$existing_ingredients =$stmt_ing->fetchAll();

$stmt_steps =$db->prepare("SELECT instruction FROM recipe_steps WHERE recipe_id = :id ORDER BY step_number ASC");
$stmt_steps->execute([':id' =>$recipe_id]);
$existing_steps =$stmt_steps->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Recipe - Barangay Bakers</title>
    <style>
        .ingredient-row { margin-bottom: 10px; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; }
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
    <h2>Edit Recipe</h2>
    <?= $message ?>
    <form action="edit_recipe.php?id=<?= $recipe_id ?>" method="POST">
        
        <div class="section">
            <h3>Basic Info</h3>
            <label>Title:</label><br>
            <input type="text" name="title" required style="width: 300px;" value="<?= htmlspecialchars($recipe_data['title']) ?>"><br><br>

            <label>Category:</label><br>
            <select name="category_id" required>
                <?php foreach ($categories as$cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $recipe_data['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Inspired By (Remix):</label><br>
            <select name="parent_recipe_id">
                <option value="">None (Original Work)</option>
                <?php foreach ($existing_recipes as$er): ?>
                    <option value="<?= $er['id'] ?>" <?= ($er['id'] == $recipe_data['parent_recipe_id']) ? 'selected' : '' ?>><?= htmlspecialchars($er['title']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Summary/Description:</label><br>
            <textarea name="description" required rows="3" cols="50"><?= htmlspecialchars($recipe_data['description']) ?></textarea>
        </div>

        <div class="section">
            <h3>Tags</h3>
            <label>Additional Tags (comma separated):</label><br>
            <input type="text" name="tags" style="width: 400px;" value="<?= htmlspecialchars($existing_tags) ?>">
        </div>

        <div class="section" id="ingredients-section">
            <h3>Ingredients</h3>
            <div id="ingredient-list">
                <?php foreach ($existing_ingredients as$ing): ?>
                    <div class="ingredient-row">
                        <input type="text" name="ing_section[]" value="<?= htmlspecialchars($ing['section']) ?>" style="width: 120px;">
                        <input type="text" name="ing_qty[]" value="<?= floatval($ing['quantity']) ?>" required style="width: 80px;">
                        <select name="ing_unit[]">
                            <?php 
                            $units = ['cups', 'grams', 'tbsp', 'tsp', 'pieces', 'ml', 'oz'];
                            foreach ($units as $u) {$selected = ($u ==$ing['unit']) ? 'selected' : '';
                                echo "<option value='$u' $selected>$u</option>";
                            }
                            ?>
                        </select>
                        <input type="text" name="ing_name[]" value="<?= htmlspecialchars($ing['name']) ?>" required>
                        <input type="text" name="ing_note[]" value="<?= htmlspecialchars($ing['preparation_note']) ?>">
                        <button type="button" onclick="this.parentElement.remove()">X</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addIngredientRow()">+ Add Another Ingredient</button>
        </div>

        <div class="section" id="steps-section">
            <h3>Instructions</h3>
            <div id="steps-list">
                <?php foreach ($existing_steps as $index =>$step): ?>
                    <div class="step-row" style="margin-bottom: 10px;">
                        <span class="step-num" style="font-weight: bold; margin-right: 10px;">Step <?= $index + 1 ?></span>
                        <textarea name="step_instruction[]" required rows="2" cols="60"><?= htmlspecialchars($step['instruction']) ?></textarea>
                        <button type="button" onclick="this.parentElement.remove(); renumberSteps();">X</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addStepRow()">+ Add Another Step</button>
        </div>

        <button type="submit" style="padding: 10px 20px; font-size: 16px;">Save Changes</button>
        <a href="view_recipe.php?id=<?= $recipe_id ?>" style="margin-left: 15px; color: red;">Cancel</a>
    </form>

    <script>
    function addIngredientRow() {
        const container = document.getElementById('ingredient-list');
        const row = document.createElement('div');
        row.className = 'ingredient-row';
        
        row.innerHTML = `
            <input type="text" name="ing_section[]" placeholder="Section" style="width: 120px;">
            <input type="text" name="ing_qty[]" placeholder="Qty" required style="width: 80px;">
            <select name="ing_unit[]">
                <option value="cups">cups</option>
                <option value="grams">grams</option>
                <option value="tbsp">tbsp</option>
                <option value="tsp">tsp</option>
                <option value="pieces">pieces</option>
                <option value="ml">ml</option>
                <option value="oz">oz</option>
            </select>
            <input type="text" name="ing_name[]" placeholder="Ingredient" required>
            <input type="text" name="ing_note[]" placeholder="Note">
            <button type="button" onclick="this.parentElement.remove()">X</button>
        `;
        container.appendChild(row);
    }

    function addStepRow() {
        const container = document.getElementById('steps-list');
        const stepCount = container.children.length + 1;
        
        const row = document.createElement('div');
        row.className = 'step-row';
        row.style.marginBottom = '10px';
        
        row.innerHTML = `
            <span class="step-num" style="font-weight: bold; margin-right: 10px;">Step ${stepCount}</span>
            <textarea name="step_instruction[]" required rows="2" cols="60" placeholder="Describe this step..."></textarea>
            <button type="button" onclick="this.parentElement.remove(); renumberSteps();">X</button>
        `;
        container.appendChild(row);
    }

    function renumberSteps() {
        const allLabels = document.querySelectorAll('#steps-list .step-num');
        allLabels.forEach((lbl, index) => {
            lbl.textContent = 'Step ' + (index + 1);
        });
    }
    </script>
</body>
</html>