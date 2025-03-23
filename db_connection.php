<?php
// Database connection file
// This file will be included in other PHP files that need database access

// Database credentials
$db_host = "localhost"; // Usually "localhost" for local development
$db_name = "mental_space";
$db_user = "root";      // Change to your MySQL username
$db_pass = "";          // Change to your MySQL password

// Create connection
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If connection fails
    die("Connection failed: " . $e->getMessage());
}
?>