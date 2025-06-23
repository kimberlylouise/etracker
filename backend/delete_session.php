<?php
header('Content-Type: application/json');
include 'db.php';

$id = intval($_GET['id']);
$conn->query("DELETE FROM program_sessions WHERE id=$id");
echo json_encode(['success' => true]);
?>