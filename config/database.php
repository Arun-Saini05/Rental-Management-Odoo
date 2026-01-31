<?php
class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "rental_management";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password);
        
        // Check if database exists, create if not
        $this->conn->query("CREATE DATABASE IF NOT EXISTS $this->database");
        $this->conn->select_db($this->database);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }

    public function getLastId() {
        return $this->conn->insert_id;
    }

    public function close() {
        $this->conn->close();
    }
}
?>
