<?php
header('Content-Type: application/json');
include 'db.php';

$id = intval($_GET['id'] ?? 0);

// Before deleting from DB:
$res = $conn->query("SELECT file_path FROM document_uploads WHERE id=$id");
if ($row = $res->fetch_assoc()) {
    $file = '../FACULTY/' . $row['file_path'];
    if (file_exists($file)) unlink($file);
}

$conn->query("DELETE FROM document_uploads WHERE id=$id");
echo json_encode(['success' => true]);
?>