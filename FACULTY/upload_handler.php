<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

$user_id = $_SESSION['user_id'];
// Fetch faculty_id from faculty table using user_id
$faculty_id = null;
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($faculty_id);
$stmt->fetch();
$stmt->close();

if (!$faculty_id) {
    die('Faculty ID not found for this user.');
}
$program_id = $_POST['program_id'];
$document_type = $_POST['document_type'];

if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
    die('File upload error');
}

// Validate file type and size
$allowed_types = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];
$max_size = 10 * 1024 * 1024; // 10MB
$ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_types)) {
    die('Invalid file type');
}
if ($_FILES['document_file']['size'] > $max_size) {
    die('File too large');
}

// Prepare file info
$original_filename = $_FILES['document_file']['name'];
$file_content = file_get_contents($_FILES['document_file']['tmp_name']); // <-- BEFORE move_uploaded_file()

// Move file
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
$unique_name = uniqid() . '_' . basename($_FILES['document_file']['name']);
$target_path = $upload_dir . $unique_name;
if (!move_uploaded_file($_FILES['document_file']['tmp_name'], $target_path)) {
    die('Failed to move file');
}
$file_path = 'uploads/' . $unique_name; // Save relative path for DB

// Insert into DB
$sql = "INSERT INTO document_uploads (program_id, faculty_id, document_type, file_path, original_filename, file_blob, upload_date, status, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending', ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iisssbi', $program_id, $faculty_id, $document_type, $file_path, $original_filename, $file_content, $user_id);
$stmt->send_long_data(5, $file_content); // 5 is the index of file_blob (0-based)
$stmt->execute();
$stmt->close();

echo "Upload successful!";
?>