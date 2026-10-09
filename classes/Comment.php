<?php
class Comment {
    private $conn;
    private $table_name = "comments";

    public $recipe_id;
    public $user_id;
    public $body;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (recipe_id, user_id, body) VALUES (:recipe_id, :user_id, :body)";
        $stmt = $this->conn->prepare($query);

        $this->body = htmlspecialchars(strip_tags($this->body));

        $stmt->bindParam(":recipe_id", $this->recipe_id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":body", $this->body);

        return $stmt->execute();
    }

    public function readByRecipe($recipe_id) {
        $query = "SELECT c.id, c.body, c.created_at, c.updated_at, u.username, c.user_id 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.recipe_id = :recipe_id 
                ORDER BY c.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update() {
        $query = "UPDATE comments SET body = :body, updated_at = NOW() WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':body', $this->body);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM comments WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':user_id', $this->user_id);
        return $stmt->execute();
    }
}
?>