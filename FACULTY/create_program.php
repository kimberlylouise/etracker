<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_name = $_POST['program_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $max_students = $_POST['max_students'] ?? '';
    $description = $_POST['description'] ?? '';

    // Basic validation
    $errors = [];
    if (empty($program_name)) $errors[] = "Program name is required";
    if (empty($department)) $errors[] = "Department is required";
    if (empty($start_date)) $errors[] = "Start date is required";
    if (empty($end_date)) $errors[] = "End date is required";
    if (empty($location)) $errors[] = "Location is required";
    if (empty($max_students) || !is_numeric($max_students) || $max_students <= 0) {
        $errors[] = "Valid maximum number of students is required";
    }

    if (empty($errors)) {
        // Prepare and execute the insert statement
        $stmt = $conn->prepare("INSERT INTO programs (program_name, department, start_date, end_date, location, max_students, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssis", $program_name, $department, $start_date, $end_date, $location, $max_students, $description);

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Program created successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to create program: ' . $conn->error];
        }
        $stmt->close();
    } else {
        $response = ['status' => 'error', 'message' => implode(', ', $errors)];
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>