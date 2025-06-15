<?php
$host = 'localhost';
$username = 'root';  // your database username
$password = '';      // your database password
$database = 'sales_db'; // your database name

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?>