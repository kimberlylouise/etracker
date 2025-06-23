<?php
header('Content-Type: application/json');
include 'db.php';

$result = $conn->query("SELECT * FROM program_sessions");
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}
echo json_encode($sessions);
?>