<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = $_POST['program_id'] ?? null;
    $program_name = $_POST['program_name'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $location = $_POST['location'] ?? '';
    $max_students = $_POST['max_students'] ?? '';
    $description = $_POST['description'] ?? '';

    // Get department from faculty table based on logged-in user
    $department = '';
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $faculty_sql = "SELECT department FROM faculty WHERE user_id = ?";
        $faculty_stmt = $conn->prepare($faculty_sql);
        $faculty_stmt->bind_param("i", $user_id);
        $faculty_stmt->execute();
        $faculty_result = $faculty_stmt->get_result();
        if ($faculty_row = $faculty_result->fetch_assoc()) {
            $department = $faculty_row['department'];
        }
        $faculty_stmt->close();
    }

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
        // Update the program (department is always set from faculty, not from user input)
        $stmt = $conn->prepare("UPDATE programs SET program_name = ?, department = ?, start_date = ?, end_date = ?, location = ?, max_students = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $program_name, $department, $start_date, $end_date, $location, $max_students, $description, $program_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Program updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update program: ' . $conn->error]);
        }
        $stmt->close();

        // After updating the program...
        if (isset($_POST['session_date'])) {
            // 1. Collect all session IDs from the form
            $form_session_ids = array_filter($_POST['session_id'], fn($id) => !empty($id));
            // 2. Get all existing session IDs for this program
            $existing_ids = [];
            $stmt = $conn->prepare("SELECT id FROM program_sessions WHERE program_id = ?");
            $stmt->bind_param("i", $program_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $existing_ids[] = $row['id'];
            }
            $stmt->close();
            // 3. Delete removed sessions
            $to_delete = array_diff($existing_ids, $form_session_ids);
            if ($to_delete) {
                $ids = implode(',', array_map('intval', $to_delete));
                $conn->query("DELETE FROM program_sessions WHERE id IN ($ids)");
            }
            // 4. Update or insert sessions
            foreach ($_POST['session_date'] as $i => $date) {
                $sid = $_POST['session_id'][$i];
                $start = $_POST['session_start'][$i] ?? null;
                $end = $_POST['session_end'][$i] ?? null;
                $title = $_POST['session_title'][$i] ?? null;
                if ($sid) {
                    // Update
                    $stmt = $conn->prepare("UPDATE program_sessions SET session_date=?, session_start=?, session_end=?, session_title=? WHERE id=?");
                    $stmt->bind_param("ssssi", $date, $start, $end, $title, $sid);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert
                    $stmt = $conn->prepare("INSERT INTO program_sessions (program_id, session_title, session_date, session_start, session_end) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $program_id, $title, $date, $start, $end);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => implode(', ', $errors)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>