<?php
require_once 'db.php';

// Fix the specific program (ID 38) to have 'ongoing' status
$program_id = 38;
$update_sql = "UPDATE programs SET status = 'ongoing' WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("i", $program_id);

if ($stmt->execute()) {
    echo "Program ID $program_id status updated to 'ongoing' successfully.\n";
    
    // Verify the update
    $check_sql = "SELECT id, program_name, status FROM programs WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $program_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $program = $result->fetch_assoc();
    
    if ($program) {
        echo "Verified: Program '{$program['program_name']}' now has status '{$program['status']}'.\n";
    }
    $check_stmt->close();
} else {
    echo "Error updating program status: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
