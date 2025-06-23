<?php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    // Fetch faculty info
    $faculty_sql = "SELECT id, department FROM faculty WHERE user_id = ?";
    $faculty_stmt = $conn->prepare($faculty_sql);
    $faculty_stmt->bind_param("i", $user_id);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();
    $faculty_row = $faculty_result->fetch_assoc();
    $faculty_id = $faculty_row['id'] ?? null;
    $department = $faculty_row['department'] ?? '';
    $faculty_stmt->close();

    $program_name = $_POST['program_name'] ?? '';
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
        // Prepare and execute the insert statement for the program
        $stmt = $conn->prepare("INSERT INTO programs (program_name, department, start_date, end_date, location, max_students, description, faculty_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $program_name, $department, $start_date, $end_date, $location, $max_students, $description, $faculty_id);

        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Program created successfully'];
            // After successful program insert
            $program_id = $conn->insert_id;
            if (!empty($_POST['session_date'])) {
                foreach ($_POST['session_date'] as $i => $date) {
                    $start = $_POST['session_start'][$i] ?? null;
                    $end = $_POST['session_end'][$i] ?? null;
                    $title = $_POST['session_title'][$i] ?? null;
                    // Use a different variable for the session statement
                    $session_stmt = $conn->prepare("INSERT INTO program_sessions (program_id, session_title, session_date, session_start, session_end) VALUES (?, ?, ?, ?, ?)");
                    $session_stmt->bind_param("issss", $program_id, $title, $date, $start, $end);
                    $session_stmt->execute();
                    $session_stmt->close();
                }
            }
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