<?php
header('Content-Type: application/json');
include 'db.php';

$program_id = intval($_GET['program_id']);
$result = $conn->query("SELECT * FROM program_sessions WHERE program_id=$program_id");
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
echo json_encode($sessions);
?>