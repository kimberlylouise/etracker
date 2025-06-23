<?php

session_start();
require 'db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$program_id = $_POST['program_id'] ?? null;

if (!$user_id || !$program_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user or program']);
    exit;
}

// Generate a unique 4-character code
function generateCode($length = 4) {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}

// Ensure code is unique
do {
    $code = generateCode();
    $stmt = $conn->prepare("SELECT code FROM qr_codes WHERE code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->store_result();
} while ($stmt->num_rows > 0);

// Store code in qr_codes table
$stmt = $conn->prepare("INSERT INTO qr_codes (code, user_id, program_id, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param('sii', $code, $user_id, $program_id);
$stmt->execute();

echo json_encode(['status' => 'success', 'code' => $code]);
?>