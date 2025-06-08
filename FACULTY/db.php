<?php
$host = "extension.c5i2m2mgkbh2.ap-southeast-2.rds.amazonaws.com";
$user = "admin";
$pass = "etrackerextension";
$dbname = "etracker";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
