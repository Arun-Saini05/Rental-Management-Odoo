<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'rental_management';

// Create connection without database first
$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database if it doesn't exist
$create_db = "CREATE DATABASE IF NOT EXISTS rental_management";
if (!mysqli_query($conn, $create_db)) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select the database
mysqli_select_db($conn, $database);

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions - only declare if not already declared
if (!function_exists('sanitize')) {
    function sanitize($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('redirectIfNotLoggedIn')) {
    function redirectIfNotLoggedIn() {
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }
}
?>
