<?php
session_start();
require_once 'config/db.php';

// Tell the browser we are sending JSON data back
header('Content-Type: application/json');

// Only logged-in users can favorite
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to favorite recipes.']);
    exit();
}

// Get the raw JSON data sent by our JavaScript fetch() function
$data = json_decode(file_get_contents("php://input"), true);
$recipe_id = $data['recipe_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$recipe_id) {
    echo json_encode(['error' => 'No recipe ID provided.']);
    exit();
}

$db = (new Database())->getConnection();

// Check if the user already favorited this recipe
$stmt = $db->prepare("SELECT * FROM favorites WHERE user_id = :uid AND recipe_id = :rid");
$stmt->execute([':uid' => $user_id, ':rid' => $recipe_id]);
$exists = $stmt->fetch();

if ($exists) {
    // It exists, so UNFAVORITE it
    $delete = $db->prepare("DELETE FROM favorites WHERE user_id = :uid AND recipe_id = :rid");
    $delete->execute([':uid' => $user_id, ':rid' => $recipe_id]);
    echo json_encode(['status' => 'removed']);
} else {
    // It does not exist, so FAVORITE it
    $insert = $db->prepare("INSERT INTO favorites (user_id, recipe_id) VALUES (:uid, :rid)");
    $insert->execute([':uid' => $user_id, ':rid' => $recipe_id]);
    echo json_encode(['status' => 'added']);
}
?>