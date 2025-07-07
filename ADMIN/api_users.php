<?php
header('Content-Type: application/json');
require_once 'db.php';

// Fetch users by role
if (isset($_GET['role'])) {
    $role = $_GET['role'] === 'faculty' ? 'faculty' : 'student';
    
    if ($role === 'faculty') {
        // Fetch faculty with additional details
        $sql = "SELECT u.*, f.faculty_id, f.faculty_name, f.position 
                FROM users u 
                LEFT JOIN faculty f ON u.id = f.user_id 
                WHERE u.role = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        // Fetch students with additional details
        $sql = "SELECT u.*, s.student_id, s.course, s.contact_no, s.emergency_contact 
                FROM users u 
                LEFT JOIN students s ON u.id = s.user_id 
                WHERE u.role = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// Fetch single user by id
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        // Get additional details based on role
        if ($row['role'] === 'faculty') {
            $stmt2 = $conn->prepare("SELECT * FROM faculty WHERE user_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $row['id']);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($faculty_data = $res2->fetch_assoc()) {
                    $row = array_merge($row, $faculty_data);
                }
            }
        } else {
            $stmt2 = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $row['id']);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($student_data = $res2->fetch_assoc()) {
                    $row = array_merge($row, $student_data);
                }
            }
        }
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// Add user (student or faculty)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_user') {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $mi = $_POST['mi'] ?? '';
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $phone = $_POST['phone'] ?? '';
    $department = $_POST['department'];
    $comm_preference = $_POST['comm_preference'] ?? 'email';
    $verification_status = 'unverified';

    // Insert into users table
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, mi, email, password, role, phone, department, comm_preference, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssssssssss", $firstname, $lastname, $mi, $email, $password, $role, $phone, $department, $comm_preference, $verification_status);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Insert into role-specific table
        if ($role === 'faculty') {
            $faculty_name = $_POST['fullname'] ?? $firstname . ' ' . $lastname;
            $faculty_id = $_POST['faculty_id'] ?? '';
            $position = $_POST['position'] ?? '';
            
            $stmt2 = $conn->prepare("INSERT INTO faculty (user_id, faculty_name, faculty_id, department, position) VALUES (?, ?, ?, ?, ?)");
            if ($stmt2) {
                $stmt2->bind_param("issss", $user_id, $faculty_name, $faculty_id, $department, $position);
                $stmt2->execute();
            }
        } else {
            // For students, we'll generate a student_id if not provided
            $student_id = $_POST['student_id'] ?? 'STU' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $course = $department; // Using department as course for students
            $contact_no = $phone;
            
            $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_id, course, contact_no) VALUES (?, ?, ?, ?)");
            if ($stmt2) {
                $stmt2->bind_param("isss", $user_id, $student_id, $course, $contact_no);
                $stmt2->execute();
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to insert user']);
    }
    exit;
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_user') {
    $id = intval($_POST['id']);
    
    // First delete from role-specific tables
    $conn->query("DELETE FROM faculty WHERE user_id = $id");
    $conn->query("DELETE FROM students WHERE user_id = $id");
    
    // Then delete from users table
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete user']);
    }
    exit;
}

// Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_user') {
    $id = intval($_POST['id']);
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $mi = $_POST['mi'] ?? '';
    $email = $_POST['email'];
    $department = $_POST['department'];
    $phone = $_POST['phone'] ?? '';
    $comm_preference = $_POST['comm_preference'] ?? 'email';
    $role = $_POST['role'];
    
    // Update users table
    $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, mi=?, email=?, department=?, phone=?, comm_preference=?, role=? WHERE id=?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssssssssi", $firstname, $lastname, $mi, $email, $department, $phone, $comm_preference, $role, $id);
    
    if ($stmt->execute()) {
        // Update role-specific table
        if ($role === 'faculty') {
            $faculty_name = $firstname . ' ' . $lastname;
            $faculty_id = $_POST['faculty_id'] ?? '';
            $position = $_POST['position'] ?? '';
            
            // Check if faculty record exists
            $check = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt2 = $conn->prepare("UPDATE faculty SET faculty_name=?, faculty_id=?, department=?, position=? WHERE user_id=?");
                $stmt2->bind_param("ssssi", $faculty_name, $faculty_id, $department, $position, $id);
            } else {
                // Insert new record
                $stmt2 = $conn->prepare("INSERT INTO faculty (user_id, faculty_name, faculty_id, department, position) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $id, $faculty_name, $faculty_id, $department, $position);
            }
            if ($stmt2) $stmt2->execute();
            
        } else {
            $course = $department;
            $contact_no = $phone;
            
            // Check if student record exists
            $check = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
            $check->bind_param("i", $id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt2 = $conn->prepare("UPDATE students SET course=?, contact_no=? WHERE user_id=?");
                $stmt2->bind_param("ssi", $course, $contact_no, $id);
            } else {
                // Insert new record
                $student_id = 'STU' . str_pad($id, 6, '0', STR_PAD_LEFT);
                $stmt2 = $conn->prepare("INSERT INTO students (user_id, student_id, course, contact_no) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("isss", $id, $student_id, $course, $contact_no);
            }
            if ($stmt2) $stmt2->execute();
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update user']);
    }
    exit;
}

// Fetch all active programs for dropdowns (simplified since we don't have programs table)
if (isset($_GET['action']) && $_GET['action'] === 'get_programs') {
    // Return empty array since programs table structure is not defined
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

// Get user statistics
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $stats = [];
    
    // Count total students
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stats['total_students'] = $result->fetch_assoc()['count'];
    
    // Count total faculty
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'faculty'");
    $stats['total_faculty'] = $result->fetch_assoc()['count'];
    
    // Count verified users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE verification_status = 'verified'");
    $stats['verified_users'] = $result->fetch_assoc()['count'];
    
    // Count unverified users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE verification_status = 'unverified'");
    $stats['unverified_users'] = $result->fetch_assoc()['count'];
    
    echo json_encode(['success' => true, 'data' => $stats]);
    exit;
}

