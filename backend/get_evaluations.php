<?php
header('Content-Type: application/json');
include 'db.php';

// Get all evaluations, join with programs for program name
$sql = "SELECT de.*, p.program_name 
        FROM detailed_evaluations de
        LEFT JOIN programs p ON de.program_id = p.id
        ORDER BY de.eval_date DESC";
$result = $conn->query($sql);

$evaluations = [];
while ($row = $result->fetch_assoc()) {
    $evaluations[] = $row;
}
echo json_encode($evaluations);
?>