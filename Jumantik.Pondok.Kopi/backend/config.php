<?php
// Database Configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'jumantik_db';

// Base URL for assets and uploads
// For local development: http://localhost/Jumantik.Pondok.Kopi/
// For hosting: https://yourdomain.com/
$base_url = 'http://localhost/Jumantik.Pondok.Kopi/'; // Change this to your hosting URL

// Create connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die(json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal: ' . mysqli_connect_error()
    ]));
}

// Set charset to UTF-8
if (!mysqli_set_charset($conn, "utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . mysqli_error($conn));
    mysqli_set_charset($conn, "utf8");
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting for development (comment out in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
?>