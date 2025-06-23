<?php
header('Content-Type: application/json');
require_once 'db.php';

// Fetch users by role
if (isset($_GET['role'])) {
    $role = $_GET['role'] === 'faculty' ? 'faculty' : 'student';
    $result = $conn->prepare("SELECT * FROM users WHERE role = ?");
    $result->bind_param("s", $role);
    $result->execute();
    $res = $result->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    foreach ($users as &$user) {
        $stmt2 = $conn->prepare("SELECT p.program_name FROM user_programs up JOIN programs p ON up.program_id = p.id WHERE up.user_id = ?");
        $stmt2->bind_param("i", $user['id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $programs = [];
        while ($row2 = $res2->fetch_assoc()) {
            $programs[] = $row2['program_name'];
        }
        $user['programs'] = $programs;
    }
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// Fetch single user by id
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        // Fetch program IDs for this user
        $stmt2 = $conn->prepare("SELECT program_id FROM user_programs WHERE user_id = ?");
        $stmt2->bind_param("i", $row['id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $program_ids = [];
        while ($row2 = $res2->fetch_assoc()) {
            $program_ids[] = $row2['program_id'];
        }
        $row['program_ids'] = $program_ids;
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

// Add user (student or faculty)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_user') {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $mi = $_POST['mi'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $comm_preference = $_POST['comm_preference'];
    $verification_status = 'unverified';

    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, mi, email, password, role, phone, department, comm_preference, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $firstname, $lastname, $mi, $email, $password, $role, $phone, $department, $comm_preference, $verification_status);
    $stmt->execute();
    $user_id = $conn->insert_id;
    // Save programs
    if (isset($_POST['programs']) && is_array($_POST['programs'])) {
        foreach ($_POST['programs'] as $program_id) {
            $stmt2 = $conn->prepare("INSERT INTO user_programs (user_id, program_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $user_id, $program_id);
            $stmt2->execute();
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete_user') {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// Update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_user') {
    $id = intval($_POST['id']);
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $mi = $_POST['mi'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $phone = $_POST['phone'];
    $comm_preference = $_POST['comm_preference'];
    $role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, mi=?, email=?, department=?, phone=?, comm_preference=?, role=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $firstname, $lastname, $mi, $email, $department, $phone, $comm_preference, $role, $id);
    $stmt->execute();
    // Remove old programs
    $conn->query("DELETE FROM user_programs WHERE user_id = $id");
    // Save new programs
    if (isset($_POST['programs']) && is_array($_POST['programs'])) {
        foreach ($_POST['programs'] as $program_id) {
            $stmt2 = $conn->prepare("INSERT INTO user_programs (user_id, program_id) VALUES (?, ?)");
            $stmt2->bind_param("ii", $id, $program_id);
            $stmt2->execute();
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Fetch all active programs for dropdowns
if (isset($_GET['action']) && $_GET['action'] === 'get_programs') {
    $now = date('Y-m-d');
    $result = $conn->prepare("SELECT id, program_name FROM programs WHERE start_date <= ? AND end_date >= ?");
    $result->bind_param("ss", $now, $now);
    $result->execute();
    $res = $result->get_result();
    $programs = [];
    while ($row = $res->fetch_assoc()) {
        $programs[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $programs]);
    exit;
}

