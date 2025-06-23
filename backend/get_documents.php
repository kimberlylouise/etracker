<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
include '../FACULTY/db.php';

if (!$conn) {
    die('Database connection failed');
}

$sql = "SELECT * FROM document_uploads ORDER BY upload_date DESC";
$res = $conn->query($sql);
if (!$res) {
    die('SQL Error: ' . $conn->error);
}
$docs = [];
while ($row = $res->fetch_assoc()) {
    $docs[] = $row;
}
echo json_encode($docs);
?>