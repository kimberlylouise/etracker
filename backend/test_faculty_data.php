<?php
header('Content-Type: application/json');
include 'db.php';

echo "Testing faculty data retrieval...\n\n";

// First, let's see what's in the faculty table
echo "=== Faculty Table Structure ===\n";
$result = $conn->query("DESCRIBE faculty");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n=== Sample Faculty Data ===\n";
$result = $conn->query("SELECT * FROM faculty LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
}

echo "\n=== Users Table Structure ===\n";
$result = $conn->query("DESCRIBE users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n=== Sample Users Data ===\n";
$result = $conn->query("SELECT * FROM users WHERE role = 'faculty' LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
}

echo "\n=== Faculty with User Join ===\n";
$sql = "SELECT f.id, f.faculty_name, f.department, f.position, 
               COALESCE(f.faculty_name, CONCAT(u.firstname, ' ', u.lastname)) AS name,
               u.firstname, u.lastname, u.email
        FROM faculty f
        LEFT JOIN users u ON f.user_id = u.id
        LIMIT 5";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>
