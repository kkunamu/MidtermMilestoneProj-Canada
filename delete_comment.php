<?php
session_start();
require_once 'config/db.php';
require_once 'classes/Comment.php';

if (isset($_SESSION['user_id']) && isset($_POST['comment_id'])) {
    $db = (new Database())->getConnection();
    $comment = new Comment($db);
    $comment->id = $_POST['comment_id'];
    $comment->user_id = $_SESSION['user_id'];
    $comment->delete();
}

// Redirect back to the recipe page it came from
header("Location: view_recipe.php?id=" . $_POST['recipe_id']);
exit();
?>