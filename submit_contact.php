<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

require_once 'config.php';

// Get and sanitize input
$name = trim(mysqli_real_escape_string($conn, $_POST['name'] ?? ''));
$email = trim(mysqli_real_escape_string($conn, $_POST['email'] ?? ''));
$phone = trim(mysqli_real_escape_string($conn, $_POST['phone'] ?? ''));
$message = trim(mysqli_real_escape_string($conn, $_POST['message'] ?? ''));

// Validate
if (empty($name) || empty($email) || empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

// Insert into DB
$sql = "INSERT INTO contacts (name, email, phone, message) VALUES ('$name', '$email', '$phone', '$message')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
}
else {
    echo json_encode(['success' => false, 'message' => 'Failed to save message: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>