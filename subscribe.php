<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

require_once 'config.php';

$email = trim(mysqli_real_escape_string($conn, $_POST['email'] ?? ''));

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

// Check for duplicate
$check = mysqli_query($conn, "SELECT id FROM subscriptions WHERE email = '$email'");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'This email is already subscribed.']);
    exit();
}

$sql = "INSERT INTO subscriptions (email) VALUES ('$email')";
if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true, 'message' => 'Successfully Subscribed!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to subscribe: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>