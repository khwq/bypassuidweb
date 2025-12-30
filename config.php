<?php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";          // XAMPP mặc định là rỗng
$DB_NAME = "uid_system";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
