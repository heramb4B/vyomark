<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // your phpMyAdmin username
define('DB_PASS', ''); // your phpMyAdmin password (blank for XAMPP default)
define('DB_NAME', 'vyomark_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}

mysqli_set_charset($conn, 'utf8');
?>