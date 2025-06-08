
<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = $_POST['program_id'] ?? null;
    $program_name = $_POST['program_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $max_students = $_POST['max_students'] ?? '';
    $description = $_POST['description'] ?? '';

    // Basic validation
    $errors = [];
    if (!$program_id || !is_numeric($program_id)) $errors[] = "Invalid program ID";
    if (empty($program_name)) $errors[] = "Program name is required";
    if (empty($department)) $errors[] = "Department is required";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($end_date)) $errors[] = "End date is required";
    if (empty($location)) $errors[] = "Location is required";
    if (empty($max_students) || !is_numeric($max_students) || $max_students <= 0) {
        $errors[] = "Valid maximum number of students is required";
    }

    if (empty($errors)) {
        // Update the program
        $stmt = $conn->prepare("UPDATE programs SET program_name = ?, department = ?, start_date = ?, end_date = ?, location = ?, max_students = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssssisi", $program_name, $department, $start_date, $end_date, $location, $max_students, $description, $program_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update program: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>