<?php
header('Content-Type: application/json');
include 'db.php';

$result = $conn->query("SELECT * FROM programs");
$programs = [];
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}
echo json_encode($programs);
?>