<?php
require_once 'db.php';
$today = date('Y-m-d');
$sql = "UPDATE programs SET status = 'ended' WHERE end_date < ? AND status = 'ongoing'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$stmt->close();
echo "Program statuses updated.";
?>