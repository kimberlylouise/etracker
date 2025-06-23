<?php
header('Content-Type: application/json');
include 'db.php';

$sql = "SELECT faculty.id, CONCAT(users.firstname, ' ', users.lastname) AS name, faculty.department, faculty.position
        FROM faculty
        JOIN users ON faculty.user_id = users.id";
$result = $conn->query($sql);

$faculty = [];
while ($row = $result->fetch_assoc()) {
    $faculty[] = $row;
}
echo json_encode($faculty);
?>