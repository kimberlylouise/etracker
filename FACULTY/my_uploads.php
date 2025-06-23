<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

$user_id = $_SESSION['user_id'];

// Get faculty_id for this user
$faculty_id = null;
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($faculty_id);
$stmt->fetch();
$stmt->close();

if (!$faculty_id) {
    die('Faculty not found.');
}

// Fetch uploads for this faculty
$sql = "SELECT du.*, p.program_name 
        FROM document_uploads du
        JOIN programs p ON du.program_id = p.id
        WHERE du.faculty_id = ?
        ORDER BY du.upload_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Uploaded Documents</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f7faf7; color: #1e3927; }
    .uploads-table { width: 100%; border-collapse: collapse; margin: 40px auto; max-width: 1100px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(36,122,55,0.10);}
    .uploads-table th, .uploads-table td { padding: 12px 10px; border-bottom: 1px solid #e0e0e0; text-align: left; }
    .uploads-table th { background: #d2eac8; color: #247a37; }
    .uploads-table tr:last-child td { border-bottom: none; }
    .status { padding: 4px 12px; border-radius: 10px; font-weight: 600; font-size: 0.98em; }
    .status.pending { background: #fffbe4; color: #bfa600; }
    .status.approved { background: #eafbe7; color: #247a37; }
    .status.rejected { background: #ffeaea; color: #b30000; }
    .remarks { color: #b30000; font-size: 0.97em; }
    .download-link { color: #247a37; text-decoration: none; font-weight: 500; }
    .download-link:hover { text-decoration: underline; }
    h2 { margin: 40px auto 18px auto; text-align: center; color: #247a37; }
  </style>
</head>
<body>
  <h2>My Uploaded Documents</h2>
  <table class="uploads-table">
    <thead>
      <tr>
        <th>Program</th>
        <th>Type</th>
        <th>File</th>
        <th>Uploaded</th>
        <th>Status</th>
        <th>Admin Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" style="text-align:center;">No uploads found.</td></tr>
      <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['program_name']); ?></td>
            <td><?php echo ucfirst(htmlspecialchars($row['document_type'])); ?></td>
            <td>
              <a class="download-link" href="uploads/<?php echo urlencode($row['file_path']); ?>" target="_blank">
                <i class="fas fa-file"></i> <?php echo htmlspecialchars($row['original_filename']); ?>
              </a>
            </td>
            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['upload_date']))); ?></td>
            <td>
              <span class="status <?php echo htmlspecialchars($row['status']); ?>">
                <?php echo ucfirst($row['status']); ?>
              </span>
            </td>
            <td class="remarks"><?php echo htmlspecialchars($row['admin_remarks']); ?></td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>