<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true);

// Check if the request contains the initial registration data
if (isset($data['firstname']) && isset($data['lastname']) && isset($data['email']) && isset($data['password'])) {
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $mi = $data['mi'] ?? '';
    $email = $data['email'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT); // Hash the password
    $role = $data['role'];

    // Insert into the users table
    $sql = "INSERT INTO users (firstname, lastname, mi, email, password, role) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $firstname, $lastname, $mi, $email, $password, $role);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Initial registration successful", "user_id" => $stmt->insert_id, "role" => $role]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to register user"]);
    }

    $stmt->close();
}

// Handle role-specific form submission (Student or Faculty)
if (isset($data['user_id']) && isset($data['role'])) {
    $user_id = $data['user_id'];
    $role = $data['role'];

    if ($role === 'student' && isset($data['student_id']) && isset($data['course']) && isset($data['contact_no']) && isset($data['emergency_contact'])) {
        $student_id = $data['student_id'];
        $course = $data['course'];
        $contact_no = $data['contact_no'];
        $emergency_contact = $data['emergency_contact'];

        $sql = "INSERT INTO students (user_id, student_id, course, contact_no, emergency_contact) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $user_id, $student_id, $course, $contact_no, $emergency_contact);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Student registration completed"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to register student details"]);
        }

        $stmt->close();
    } elseif ($role === 'faculty' && isset($data['faculty_name']) && isset($data['faculty_id']) && isset($data['department_id']) && isset($data['position'])) {
        $faculty_name = $data['faculty_name'];
        $faculty_id = $data['faculty_id'];
        $department_id = intval($data['department_id']); // Use department_id instead of department text
        $position = $data['position'];

        // Get department name for backward compatibility (optional)
        $dept_name = '';
        $dept_sql = "SELECT name FROM departments WHERE id = ?";
        $dept_stmt = $conn->prepare($dept_sql);
        $dept_stmt->bind_param("i", $department_id);
        $dept_stmt->execute();
        $dept_result = $dept_stmt->get_result();
        if ($dept_row = $dept_result->fetch_assoc()) {
            $dept_name = $dept_row['name'];
        }
        $dept_stmt->close();

        $sql = "INSERT INTO faculty (user_id, faculty_name, faculty_id, department, department_id, position) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssis", $user_id, $faculty_name, $faculty_id, $dept_name, $department_id, $position);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success", 
                "message" => "Faculty registration completed",
                "department" => $dept_name,
                "department_id" => $department_id
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to register faculty details"]);
        }

        $stmt->close();
    }
}

$conn->close();
?>