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

// Fetch evaluations based on selected program (default to all)
$eval_query = "SELECT e.student_name, e.score, e.comments, e.eval_date, p.program_name 
               FROM evaluations e 
               JOIN programs p ON e.program_id = p.id";
if ($selected_program_id != 'all') {
    $eval_query .= " WHERE e.program_id = ?";
}
$eval_query .= " ORDER BY e.eval_date DESC";
$eval_stmt = $conn->prepare($eval_query);
if ($selected_program_id != 'all') {
    $eval_stmt->bind_param("i", $selected_program_id);
}
$eval_stmt->execute();
$eval_result = $eval_stmt->get_result();
$evaluations = [];
if ($eval_result) {
    while ($row = $eval_result->fetch_assoc()) {
        $evaluations[] = $row;
    }
    $eval_result->free();
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
  <title>eTracker Faculty Evaluation</title>
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
          <li class="active"><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
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
          <div class="role-label">Faculty Evaluation</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
          <div class="top-actions">
            <div class="search-bar">
              <input type="text" placeholder="Search" />
            </div>
            <div class="message-icon">✉️</div>
          </div>
        </header>

        <!-- Program Selection -->
        <div class="program-selection">
          <label for="program-select">Select Program</label>
          <select id="program-select" name="program_id" onchange="window.location.href='evaluation.php?program_id=' + this.value">
            <option value="all" <?php echo ($selected_program_id == 'all') ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?php echo htmlspecialchars($program['id']); ?>" <?php echo ($selected_program_id == $program['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($program['program_name']) . ' (' . date('m/d/y', strtotime($program['start_date'])) . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Evaluation Table -->
        <table class="evaluation-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Score</th>
              <th>Comments</th>
              <th>Date</th>
              <th>Program</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($evaluations)): ?>
              <tr><td colspan="5">No evaluations found.</td></tr>
            <?php else: ?>
              <?php foreach ($evaluations as $eval): ?>
                <tr>
                  <td><?php echo htmlspecialchars($eval['student_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($eval['score'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($eval['comments'] ?? 'pending evaluation'); ?></td>
                  <td><?php echo htmlspecialchars(date('m-d-y', strtotime($eval['eval_date'] ?? 'now'))); ?></td>
                  <td><?php echo htmlspecialchars($eval['program_name']); ?></td>
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
    .evaluation-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .evaluation-table th, .evaluation-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .evaluation-table th { background-color: #d2eac8; color: #1e3927; }
    .evaluation-table td { background-color: #fff; }
    .note.priority-low { border-left-color: #59a96a; }
    .note.priority-medium { border-left-color: #f1c40f; }
    .note.priority-high { border-left-color: #e74c3c; }
  </style>
</body>
</html>