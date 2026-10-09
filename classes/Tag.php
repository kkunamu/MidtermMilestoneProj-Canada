<?php
class Tag {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getOrCreate($tag_name) {
        $tag_name = trim(strtolower($tag_name));
        
        $check_query = "SELECT id FROM tags WHERE name = :name LIMIT 1";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bindParam(':name', $tag_name);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch()['id'];
        }

        $insert_query = "INSERT INTO tags (name) VALUES (:name)";
        $stmt = $this->conn->prepare($insert_query);
        $stmt->bindParam(':name', $tag_name);
        $stmt->execute();
        
        return $this->conn->lastInsertId();
    }

    public function addTagToRecipe($recipe_id, $tag_name) {
        $tag_id = $this->getOrCreate($tag_name);

        $query = "INSERT IGNORE INTO recipe_tags (recipe_id, tag_id) VALUES (:recipe_id, :tag_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->bindParam(':tag_id', $tag_id);
        
        return $stmt->execute();
    }
}
?>