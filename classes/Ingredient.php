<?php
class Ingredient {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Finds an existing ingredient or creates it in the master list, returning the ID
    public function getOrCreate($ingredient_name) {
        $ingredient_name = trim(strtolower($ingredient_name));
        
        $check_query = "SELECT id FROM ingredients WHERE name = :name LIMIT 1";
        $stmt = $this->conn->prepare($check_query);
        $stmt->bindParam(':name', $ingredient_name);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $stmt->fetch()['id'];
        }

        $insert_query = "INSERT INTO ingredients (name) VALUES (:name)";
        $stmt = $this->conn->prepare($insert_query);
        $stmt->bindParam(':name', $ingredient_name);
        $stmt->execute();
        
        return $this->conn->lastInsertId();
    }

    // Links the master ingredient to a specific recipe
    public function addToRecipe($recipe_id, $ingredient_name, $quantity, $unit, $prep_note, $section = 'Main') {
        $ingredient_id = $this->getOrCreate($ingredient_name);

        $query = "INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit, preparation_note, section) 
                  VALUES (:recipe_id, :ingredient_id, :quantity, :unit, :prep_note, :section)";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':recipe_id', $recipe_id);
        $stmt->bindParam(':ingredient_id', $ingredient_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':unit', $unit);
        $stmt->bindParam(':prep_note', $prep_note);
        $stmt->bindParam(':section', $section);
        
        return $stmt->execute();
    }
}
?>