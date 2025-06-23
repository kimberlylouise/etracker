<?php

require_once '../FACULTY/db.php';

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT original_filename, file_blob FROM document_uploads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($filename, $file_blob);

if ($stmt->fetch() && $file_blob) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    if ($ext === 'pdf') $mime = 'application/pdf';
    elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    elseif ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    header("Content-Type: $mime");
    header("Content-Disposition: inline; filename=\"" . basename($filename) . "\"");
    echo $file_blob;
} else {
    http_response_code(404);
    echo "File not found.";
}
$stmt->close();
?>