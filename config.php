<?php
// Database configuration
$host = "localhost:3306";
$username = "root";
$password = "";
$database = "login_system";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set charset
$conn->set_charset("utf8mb4");

// Timezone setting
date_default_timezone_set("Asia/Jakarta");
?>