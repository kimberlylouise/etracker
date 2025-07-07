<?php
header('Content-Type: application/json');
include 'db.php';

// Check if department filtering is requested
$department_name = isset($_GET['department']) ? trim($_GET['department']) : null;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;

// Map department IDs to department names (based on your fallback departments)
$department_map = [
    1 => 'Department of Hospitality Management',
    2 => 'Department of Language and Mass Communication', 
    3 => 'Department of Physical Education',
    4 => 'Department of Social Sciences and Humanities',
    5 => 'Teacher Education Department',
    6 => 'Department of Administration - ENTREP',
    7 => 'Department of Administration - BSOA', 
    8 => 'Department of Administration - BM',
    9 => 'Department of Computer Studies'
];

if ($department_id && isset($department_map[$department_id])) {
    $department_name = $department_map[$department_id];
}

if ($department_name) {
    // Get faculty by department using department name
    $sql = "SELECT f.id, f.faculty_name, f.department, f.position, 
                   COALESCE(f.faculty_name, CONCAT(u.firstname, ' ', u.lastname)) AS name,
                   u.firstname, u.lastname, u.email
            FROM faculty f
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.department = ? OR f.department LIKE ?
            ORDER BY name";
    
    $stmt = $conn->prepare($sql);
    $dept_like = "%{$department_name}%";
    $stmt->bind_param("ss", $department_name, $dept_like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Get all faculty
    $sql = "SELECT f.id, f.faculty_name, f.department, f.position,
                   COALESCE(f.faculty_name, CONCAT(u.firstname, ' ', u.lastname)) AS name,
                   u.firstname, u.lastname, u.email
            FROM faculty f
            LEFT JOIN users u ON f.user_id = u.id
            ORDER BY f.department, name";
    
    $result = $conn->query($sql);
}

$faculty = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faculty[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'faculty_name' => $row['faculty_name'],
            'first_name' => $row['firstname'],
            'last_name' => $row['lastname'], 
            'email' => $row['email'],
            'position' => $row['position'],
            'department' => $row['department']
        ];
    }
}

echo json_encode($faculty);

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>