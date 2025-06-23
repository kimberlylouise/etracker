<?php
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