<?php
session_start();
header('Content-Type: application/json');

$debug_info = [
    'session_data' => $_SESSION,
    'session_id' => session_id(),
    'authenticated' => isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : false,
    'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
    'user_email' => isset($_SESSION['email']) ? $_SESSION['email'] : null,
    'user_name' => isset($_SESSION['name']) ? $_SESSION['name'] : null,
    'user_role' => isset($_SESSION['role']) ? $_SESSION['role'] : null,
    'csrf_token' => isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>