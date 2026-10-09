<?php
class Recipe {
    private $conn;
    private $table_name = "recipes";

    public $id;
    public $user_id;
    public $category_id;
    public $parent_recipe_id; 
    public $title;
    public $description;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        // FIXED: Removed the trailing comma after :description
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, category_id, parent_recipe_id, title, description) 
                  VALUES (:user_id, :category_id, :parent_recipe_id, :title, :description)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":parent_recipe_id", $this->parent_recipe_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getRemixes($recipe_id) {
        $query = "SELECT id, title FROM " . $this->table_name . " WHERE parent_recipe_id = :recipe_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function addStep($recipe_id, $step_number, $instruction) {
        $query = "INSERT INTO recipe_steps (recipe_id, step_number, instruction) 
                  VALUES (:recipe_id, :step_number, :instruction)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":recipe_id", $recipe_id);
        $stmt->bindParam(":step_number", $step_number);
        $stmt->bindParam(":instruction", $instruction);
        
        return $stmt->execute();
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                SET category_id = :category_id, parent_recipe_id = :parent_recipe_id, title = :title, description = :description, updated_at = NOW() 
                WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":parent_recipe_id", $this->parent_recipe_id);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        
        return $stmt->execute();
    }

    public function clearRelations() {
        // Deletes old steps, ingredients, and tags before we re-insert the updated ones
        $stmt1 = $this->conn->prepare("DELETE FROM recipe_steps WHERE recipe_id = :id");
        $stmt1->execute([':id' => $this->id]);

        $stmt2 = $this->conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = :id");
        $stmt2->execute([':id' => $this->id]);

        $stmt3 = $this->conn->prepare("DELETE FROM recipe_tags WHERE recipe_id = :id");
        $stmt3->execute([':id' => $this->id]);
    }

    public function delete() {
        try {
            // Start transaction: if one delete fails, they all cancel to prevent orphaned data
            $this->conn->beginTransaction();

            // 1. Delete associated comments
            $stmt = $this->conn->prepare("DELETE FROM comments WHERE recipe_id = :id");
            $stmt->execute([':id' => $this->id]);

            // 2. Delete associated tags
            $stmt = $this->conn->prepare("DELETE FROM recipe_tags WHERE recipe_id = :id");
            $stmt->execute([':id' => $this->id]);

            // 3. Delete associated ingredients
            $stmt = $this->conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = :id");
            $stmt->execute([':id' => $this->id]);

            // 4. Delete associated steps
            $stmt = $this->conn->prepare("DELETE FROM recipe_steps WHERE recipe_id = :id");
            $stmt->execute([':id' => $this->id]);

            // 5. Delete the main recipe
            $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = :id");
            $stmt->execute([':id' => $this->id]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}
?>