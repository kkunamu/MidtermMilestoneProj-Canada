<?php
session_start();
require_once 'config/db.php';
require_once 'classes/Recipe.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['recipe_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify that the logged-in user actually owns this recipe
$stmt = $db->prepare("SELECT user_id FROM recipes WHERE id = :id");
$stmt->execute([':id' => $_POST['recipe_id']]);
$recipe_data = $stmt->fetch();

if ($recipe_data && $recipe_data['user_id'] == $_SESSION['user_id']) {
    $recipe = new Recipe($db);
    $recipe->id = $_POST['recipe_id'];
    $recipe->delete();
}

// Redirect back to the feed
header("Location: index.php");
exit();
?>