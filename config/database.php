<?php
class Database {
    private $host = "localhost";
    private $db_name = "loja_som_automotivo";
    private $username = "root";
    private $password = "Boaventura";
    public $conn;   
    public $error;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username, 
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Erro: " . $this->error = $e->getMessage();
        }
        return $this->conn;
    }
}
?>