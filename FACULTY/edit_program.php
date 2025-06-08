<?php
require_once 'db.php';
$program_id = $_GET['id'] ?? null;
if ($program_id && is_numeric($program_id)) {
    $stmt = $conn->prepare("SELECT * FROM programs WHERE id = ?");
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $program = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
// Render a form pre-filled with $program data
?>