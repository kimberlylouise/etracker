<?php
require_once 'db.php';

// Fetch all programs for the dropdown
$program_query = "SELECT id, program_name, start_date 
                  FROM programs 
                  ORDER BY start_date";
$program_result = $conn->query($program_query);
$programs = [];
if ($program_result) {
    while ($row = $program_result->fetch_assoc()) {
        $programs[] = $row;
    }
    $program_result->free();
}

// Set default to "all" programs unless a program_id is provided via GET
$selected_program_id = isset($_GET['program_id']) && $_GET['program_id'] != 'all' ? $_GET['program_id'] : 'all';

// Fetch certificates based on selected program (default to all)
$certificate_query = "SELECT c.student_name, c.certificate_date, c.status, p.program_name 
                      FROM certificates c 
                      JOIN programs p ON c.program_id = p.id";
if ($selected_program_id != 'all') {
    $certificate_query .= " WHERE c.program_id = ?";
}
$certificate_query .= " ORDER BY c.certificate_date DESC";
$certificate_stmt = $conn->prepare($certificate_query);
if ($selected_program_id != 'all') {
    $certificate_stmt->bind_param("i", $selected_program_id);
}
$certificate_stmt->execute();
$certificate_result = $certificate_stmt->get_result();
$certificates = [];
if ($certificate_result) {
    while ($row = $certificate_result->fetch_assoc()) {
        $certificates[] = $row;
    }
    $certificate_result->free();
}

// Fetch active notifications
$notifications_query = "SELECT message, priority
                       FROM notifications
                       WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
                       ORDER BY created_at DESC
                       LIMIT 5";
$notifications_result = $conn->query($notifications_query);
$notifications = [];
if ($notifications_result) {
    while ($row = $notifications_result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notifications_result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eTracker Faculty Certificates</title>
  <link rel="stylesheet" href="sample.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="logo">eTRACKER</div>
      <nav>
        <ul>
          <li><a href="dashboard.html"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li class="active"><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out">Sign Out</div>
      </nav>
    </aside>

    <!-- Main Grid -->
    <div class="main-grid">
      <!-- Center Content -->
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Certificates</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
          <div class="top-actions">
           
          </div>
        </header>

        <!-- Program Selection -->
        <div class="program-selection">
          <label for="program-select">Select Program</label>
          <select id="program-select" name="program_id" onchange="window.location.href='certificates.php?program_id=' + this.value">
            <option value="all" <?php echo ($selected_program_id == 'all') ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?php echo htmlspecialchars($program['id']); ?>" <?php echo ($selected_program_id == $program['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($program['program_name']) . ' (' . date('m/d/y', strtotime($program['start_date'])) . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      

        <!-- Certificates Table -->
        <table class="certificate-table">
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Certificate Date</th>
              <th>Status</th>
              <th>Program</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($certificates)): ?>
              <tr><td colspan="4">No certificates found.</td></tr>
            <?php else: ?>
              <?php foreach ($certificates as $certificate): ?>
                <tr>
                  <td><?php echo htmlspecialchars($certificate['student_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($certificate['certificate_date'] ? date('m-d-y', strtotime($certificate['certificate_date'])) : '-'); ?></td>
                  <td class="status-<?php echo strtolower(htmlspecialchars($certificate['status'] ?? 'Pending')); ?>">
                    <?php echo htmlspecialchars($certificate['status'] ?? 'Pending'); ?>
                  </td>
                  <td><?php echo htmlspecialchars($certificate['program_name']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Right Side -->
      <div class="right-panel">
        <div class="user-info">
          <div class="name">Full Name</div>
          <div class="email">email@cvsu.edu.ph</div>
        </div>
        <div class="notifications">
          <h3>🔔 Notification</h3>
          <?php if (empty($notifications)): ?>
            <div class="note">No notifications at this time.</div>
          <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
              <div class="note priority-<?php echo htmlspecialchars($notification['priority']); ?>">
                <?php echo htmlspecialchars($notification['message']); ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  
  <style>
    .program-selection { margin: 20px 0; display: flex; align-items: center; gap: 10px; }
    .program-selection label { font-weight: bold; color: #247a37; }
    .program-selection select { padding: 5px; border-radius: 15px; border: 1px solid #ccc; width: 300px; }
  
    .btn {
      padding: 8px 16px;
      background-color: #d2eac8;
      border: none;
      border-radius: 15px;
      cursor: pointer;
      color: #1e3927;
    }
    .btn:hover {
      background-color: #247a37;
      color: #fff;
    }
    .certificate-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .certificate-table th,
    .certificate-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    .certificate-table th {
      background-color: #d2eac8;
      color: #1e3927;
    }
    .certificate-table td {
      background-color: #fff;
    }
    .status-generated { color: green; font-weight: bold; }
    .status-pending { color: #f1c40f; font-weight: bold; }
    .note.priority-low { border-left-color: #59a96a; }
    .note.priority-medium { border-left-color: #f1c40f; }
    .note.priority-high { border-left-color: #e74c3c; }
  </style>
</body>
</html>