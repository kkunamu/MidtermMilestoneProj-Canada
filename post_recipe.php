<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';
require_once 'classes/Recipe.php';
require_once 'classes/Ingredient.php';
require_once 'classes/Tag.php';

$database = new Database();$db = $database->getConnection();$message = '';

// Fetch categories for the dropdown
$stmt_cat =$db->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories =$stmt_cat->fetchAll();

// Fetch all recipes for the 'Inspired By' dropdown
$stmt_recipes =$db->query("SELECT id, title FROM recipes ORDER BY title ASC");
$existing_recipes =$stmt_recipes->fetchAll();

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction(); 

        $recipe = new Recipe($db);$recipe->user_id = $_SESSION['user_id'];$recipe->category_id = $_POST['category_id'];$recipe->parent_recipe_id = !empty($_POST['parent_recipe_id']) ?$_POST['parent_recipe_id'] : null;
        $recipe->title =$_POST['title'];
        $recipe->description =$_POST['description'];

        if ($recipe->create()) {
            $recipe_id =$recipe->id;
            $ingredient_manager = new Ingredient($db);
            $tag_manager = new Tag($db);

            // Process Ingredients
            if (isset($_POST['ing_name']) && is_array($_POST['ing_name'])) {
                for ($i = 0; $i < count($_POST['ing_name']); $i++) {$name = $_POST['ing_name'][$i];
                    $qty = parseFraction($_POST['ing_qty'][$i]);$unit = $_POST['ing_unit'][$i];
                    $note = $_POST['ing_note'][$i];$section = !empty($_POST['ing_section'][$i]) ? $_POST['ing_section'][$i] : 'Main';
                    
                    if (!empty($name) && !empty($qty)) {
                        $ingredient_manager->addToRecipe($recipe_id, $name,$qty, $unit, $note, $section);
                    }
                }
            }

            // Process Instructions
            if (isset($_POST['step_instruction']) && is_array($_POST['step_instruction'])) {$step_number = 1;
                foreach ($_POST['step_instruction'] as$instruction) {
                    if (!empty(trim($instruction))) {$recipe->addStep($recipe_id,$step_number, $instruction);$step_number++;
                    }
                }
            }

            // Process AO3-Style Tags
            if (!empty($_POST['tags'])) {
                $tags_array = explode(',',$_POST['tags']);
                foreach ($tags_array as$tag) {
                    $clean_tag = trim($tag);
                    if (!empty($clean_tag)) {$tag_manager->addTagToRecipe($recipe_id,$clean_tag);
                    }
                }
            }

            $db->commit();$message = "<div style='color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 12px; border-radius: 6px; margin-bottom: 20px;'>Recipe archived successfully! <a href='index.php' style='color: #0b2e13; font-weight: bold;'>Back to feed</a>.</div>";
        }
    } catch (Exception $e) {
        $db->rollBack();$message = "<div style='color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 6px; margin-bottom: 20px;'>Database Error: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Work - Barangay Bakers</title>
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
        
        <h2 class="page-title">Post a New Recipe</h2>
        <?= $message ?>
        
        <form action="post_recipe.php" method="POST">
            
            <div class="form-section">
                <h3>Basic Info</h3>
                
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="title" required placeholder="e.g. Classic Soft Ube Pandesal">
                </div>

                <div class="form-group">
                    <label>Category:</label>
                    <select name="category_id" required>
                        <option value="">Select a Category...</option>
                        <?php foreach ($categories as$cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Inspired By (Remix):</label>
                    <select name="parent_recipe_id">
                        <option value="">None (Original Work)</option>
                        <?php foreach ($existing_recipes as$er): ?>
                            <option value="<?= $er['id'] ?>"><?= htmlspecialchars($er['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Summary/Description:</label>
                    <textarea name="description" required rows="3" placeholder="A short description of this bake..."></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Tags</h3>
                <div class="form-group">
                    <label>Additional Tags (comma separated):</label>
                    <input type="text" name="tags" placeholder="e.g. Sourdough Discard, Beginner Friendly, Vegan">
                </div>
            </div>

            <div class="form-section" id="ingredients-section">
                <h3>Ingredients</h3>
                <div id="ingredient-list">
                    <div class="ingredient-row">
                        <input type="text" name="ing_section[]" placeholder="Section (e.g. Cake)">
                        <input type="text" name="ing_qty[]" placeholder="Qty (e.g. 1 1/2)" required>
                        <select name="ing_unit[]">
                            <option value="cups">cups</option>
                            <option value="grams">grams</option>
                            <option value="tbsp">tbsp</option>
                            <option value="tsp">tsp</option>
                            <option value="pieces">pieces</option>
                            <option value="ml">ml</option>
                            <option value="oz">oz</option>
                        </select>
                        <input type="text" name="ing_name[]" placeholder="Ingredient (e.g. Flour)" required>
                        <input type="text" name="ing_note[]" placeholder="Note (e.g. sifted, cold)">
                    </div>
                </div>
                <button type="button" class="btn btn-add" onclick="addIngredientRow()">+ Add Another Ingredient</button>
            </div>

            <div class="form-section" id="steps-section">
                <h3>Instructions</h3>
                <div id="steps-list">
                    <div class="step-row">
                        <span class="step-num">Step 1</span>
                        <textarea name="step_instruction[]" required rows="2" placeholder="Describe this step..."></textarea>
                    </div>
                </div>
                <button type="button" class="btn btn-add" onclick="addStepRow()">+ Add Another Step</button>
            </div>

            <div style="margin-bottom: 40px;">
                <button type="submit" class="btn btn-primary" style="font-size: 1.05rem; padding: 12px 28px;">Post Recipe</button>
            </div>
        </form>
    </div>

    <script>
    function addIngredientRow() {
        const container = document.getElementById('ingredient-list');
        const row = document.createElement('div');
        row.className = 'ingredient-row';
        
        row.innerHTML = `
            <input type="text" name="ing_section[]" placeholder="Section (e.g. Frosting)">
            <input type="text" name="ing_qty[]" placeholder="Qty (e.g. 1)" required>
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
            <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
        `;
        container.appendChild(row);
    }

    function addStepRow() {
        const container = document.getElementById('steps-list');
        const stepCount = container.children.length + 1;
        
        const row = document.createElement('div');
        row.className = 'step-row';
        
        row.innerHTML = `
            <span class="step-num">Step ${stepCount}</span>
            <textarea name="step_instruction[]" required rows="2" placeholder="Describe this step..."></textarea>
            <button type="button" class="btn-remove" onclick="this.parentElement.remove(); renumberSteps();">✕</button>
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