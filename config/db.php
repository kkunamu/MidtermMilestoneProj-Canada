<?php
class Database {
    private $host = "localhost";
    private $db_name = "barangay_bakers";
    private $username = "root"; 
    private $password = "";    
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            // Set PDO to throw exceptions on errors
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Fetch associative arrays by default
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Baking disaster (Connection error): " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>