<?php
require_once 'db.php';
session_start();

// Fetch user info for display (optional, for top right)
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = 'Unknown User';
$user_email = 'unknown@cvsu.edu.ph';
if ($user_id) {
    $user_sql = "SELECT firstname, lastname, mi, email FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    if ($user_row = $user_result->fetch_assoc()) {
        $user_fullname = $user_row['firstname'] . ' ' . $user_row['lastname'];
        $user_email = $user_row['email'];
    }
    $user_stmt->close();
}

// Fetch all programs for the dropdown and stats
$programs = [];
$program_ids = [];
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($faculty_id);
$stmt->fetch();
$stmt->close();

$program_query = "SELECT id, program_name, start_date, end_date, status FROM programs WHERE faculty_id = ? ORDER BY start_date DESC";
$stmt = $conn->prepare($program_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
    $program_ids[] = $row['id'];
}
$stmt->close();

// Prepare for batching
$in = implode(',', array_fill(0, count($program_ids), '?'));
$types = str_repeat('i', count($program_ids));

// Batch fetch enrollments
$enrollments = [];
if ($program_ids) {
    $stmt = $conn->prepare("SELECT program_id, COUNT(*) as cnt FROM enrollments WHERE program_id IN ($in) GROUP BY program_id");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $enrollments[$row['program_id']] = $row['cnt'];
    }
    $stmt->close();
}

// Batch fetch attendance
$attendance_present = [];
$attendance_total = [];
if ($program_ids) {
    // Present
    $stmt = $conn->prepare("SELECT program_id, COUNT(*) as cnt FROM attendance WHERE program_id IN ($in) AND status = 'Present' GROUP BY program_id");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $attendance_present[$row['program_id']] = $row['cnt'];
    }
    $stmt->close();

    // Total
    $stmt = $conn->prepare("SELECT program_id, COUNT(*) as cnt FROM attendance WHERE program_id IN ($in) GROUP BY program_id");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $attendance_total[$row['program_id']] = $row['cnt'];
    }
    $stmt->close();
}

// Batch fetch feedback
$feedback_scores_arr = [];
if ($program_ids) {
    $stmt = $conn->prepare("SELECT program_id, AVG(content + facilitators + relevance + organization + experience)/5 as avg_score FROM detailed_evaluations WHERE program_id IN ($in) GROUP BY program_id");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $feedback_scores_arr[$row['program_id']] = $row['avg_score'] ? round($row['avg_score'], 2) : 0;
    }
    $stmt->close();
}

// Batch fetch certificates
$certificates = [];
if ($program_ids) {
    $stmt = $conn->prepare("SELECT program_id, COUNT(*) as cnt FROM certificates WHERE program_id IN ($in) GROUP BY program_id");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $certificates[$row['program_id']] = $row['cnt'];
    }
    $stmt->close();
}

// Batch fetch document uploads
$doc_uploads = [];
if ($program_ids) {
    $stmt = $conn->prepare("SELECT program_id, document_type, status FROM document_uploads WHERE program_id IN ($in) ORDER BY upload_date DESC");
    $stmt->bind_param($types, ...$program_ids);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Only keep the latest status per program+type
        $key = $row['program_id'] . '_' . $row['document_type'];
        if (!isset($doc_uploads[$key])) {
            $doc_uploads[$key] = $row['status'];
        }
    }
    $stmt->close();
}

// Prepare data for display
$total_programs = count($programs);
$total_participants = array_sum($enrollments);
$total_certificates = array_sum($certificates);
$attendance_rates = [];
$feedback_scores = [];
$program_labels = [];

foreach ($programs as $program) {
    $pid = $program['id'];
    $enrolled = $enrollments[$pid] ?? 0;
    $present = $attendance_present[$pid] ?? 0;
    $total_attendance = $attendance_total[$pid] ?? 0;
    $attendance_rate = $total_attendance > 0 ? round(($present / $total_attendance) * 100, 1) : 0;
    $attendance_rates[] = $attendance_rate;
    $program_labels[] = $program['program_name'];
    $feedback_scores[] = $feedback_scores_arr[$pid] ?? 0;
}

// Fetch notifications (optional)
$notifications = [];
$notifications_query = "SELECT message, priority FROM notifications WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY created_at DESC LIMIT 5";
$notifications_result = $conn->query($notifications_query);
if ($notifications_result) {
    while ($row = $notifications_result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notifications_result->free();
}

$faculty_id = null;
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($faculty_id);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eTracker Faculty Reports</title>
  <link rel="stylesheet" href="sample.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .dashboard-cards {
      display: flex;
      gap: 24px;
      margin: 30px 0 20px 0;
      flex-wrap: wrap;
    }
    .dashboard-card {
      flex: 1 1 200px;
      background: linear-gradient(135deg, #eafbe7 80%, #fffde4 100%);
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(36,122,55,0.10);
      padding: 24px 18px;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-width: 180px;
      max-width: 260px;
      text-align: center;
      font-size: 1.1rem;
      font-weight: 600;
      color: #247a37;
      position: relative;
    }
    .dashboard-card i {
      font-size: 2.2rem;
      margin-bottom: 10px;
      color: #59a96a;
    }
    .dashboard-card .stat {
      font-size: 2rem;
      font-weight: bold;
      color: #1e3927;
    }
    .dashboard-card .label {
      font-size: 1rem;
      color: #247a37;
      margin-top: 4px;
    }
    .charts-section {
      display: flex;
      flex-wrap: wrap;
      gap: 32px;
      margin-bottom: 30px;
    }
    .chart-container {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(36,122,55,0.10);
      padding: 24px 18px;
      flex: 1 1 350px;
      min-width: 320px;
      max-width: 600px;
    }
    .chart-title {
      font-size: 1.1rem;
      color: #247a37;
      margin-bottom: 10px;
      font-weight: 600;
    }
    .documents-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 24px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(36,122,55,0.10);
      overflow: hidden;
    }
    .documents-table th, .documents-table td {
      padding: 10px 8px;
      border-bottom: 1px solid #e0e0e0;
      text-align: left;
    }
    .documents-table th {
      background: #d2eac8;
      color: #247a37;
    }
    .documents-table tr:last-child td { border-bottom: none; }
    .badge {
      padding: 4px 10px;
      border-radius: 10px;
      font-size: 0.98em;
      font-weight: 600;
      color: #fff;
      display: inline-block;
    }
    .badge.submitted { background: #59a96a; }
    .badge.pending { background: #f1c40f; color: #856404; }
    .badge.missing { background: #e74c3c; }
    .export-btn {
      background: linear-gradient(90deg, #59a96a 60%, #247a37 100%);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 10px 24px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      margin: 18px 0 0 0;
      transition: background 0.18s, transform 0.18s;
    }
    .export-btn:hover {
      background: linear-gradient(90deg, #247a37 60%, #59a96a 100%);
      transform: translateY(-2px) scale(1.03);
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar (unchanged) -->
    <aside class="sidebar">
      <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
        <span class="logo-text">eTRACKER</span>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard.html"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>
          <li class="active"><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>
      </nav>
    </aside>
    <div class="main-grid">
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Reports</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
        </header>

        <!-- Quick Stats -->
        <div class="dashboard-cards">
          <div class="dashboard-card">
            <i class="fas fa-chalkboard-teacher"></i>
            <div class="stat"><?php echo $total_programs; ?></div>
            <div class="label">Programs</div>
          </div>
          <div class="dashboard-card">
            <i class="fas fa-users"></i>
            <div class="stat"><?php echo $total_participants; ?></div>
            <div class="label">Participants</div>
          </div>
          <div class="dashboard-card">
            <i class="fas fa-certificate"></i>
            <div class="stat"><?php echo $total_certificates; ?></div>
            <div class="label">Certificates</div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
          <div class="chart-container">
            <div class="chart-title"><i class="fas fa-chart-line"></i> Attendance Rate by Program</div>
            <canvas id="attendanceChart"></canvas>
          </div>
          <div class="chart-container">
            <div class="chart-title"><i class="fas fa-star"></i> Average Feedback Score by Program</div>
            <canvas id="feedbackChart"></canvas>
          </div>
        </div>

        <button class="export-btn" onclick="window.print()"><i class="fas fa-file-export"></i> Export as PDF</button>
      </div>
      <!-- Right Panel (optional, for notifications/user info) -->
      <div class="right-panel">
        <div class="user-info">
          <div class="name"><?php echo htmlspecialchars($user_fullname); ?></div>
          <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
        </div>
        <div class="notifications">
          <h3>🔔 Notifications</h3>
          <?php if (empty($notifications)): ?>
            <div class="note no-notifications">No notifications at this time.</div>
          <?php else: ?>
            <?php foreach ($notifications as $notification): 
              // Priority icon, label, and class
              switch ($notification['priority']) {
                case 'high':
                  $icon = '<i class="fas fa-exclamation-circle" style="color:#e53935;"></i>';
                  $label = 'Urgent';
                  $class = 'notif-high';
                  break;
                case 'medium':
                  $icon = '<i class="fas fa-exclamation-triangle" style="color:#fbc02d;"></i>';
                  $label = 'Reminder';
                  $class = 'notif-medium';
                  break;
                default:
                  $icon = '<i class="fas fa-check-circle" style="color:#43a047;"></i>';
                  $label = 'FYI';
                  $class = 'notif-low';
              }
            ?>
              <div class="note <?php echo $class; ?>">
                <span class="notif-icon"><?php echo $icon; ?></span>
                <span class="notif-label"><?php echo $label; ?></span>
                <?php echo htmlspecialchars($notification['message']); ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Attendance Chart
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attendanceCtx, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($program_labels); ?>,
        datasets: [{
          label: 'Attendance Rate (%)',
          data: <?php echo json_encode($attendance_rates); ?>,
          backgroundColor: '#59a96a'
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100 } }
      }
    });

    // Feedback Chart
    const feedbackCtx = document.getElementById('feedbackChart').getContext('2d');
    new Chart(feedbackCtx, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($program_labels); ?>,
        datasets: [{
          label: 'Avg Feedback Score',
          data: <?php echo json_encode($feedback_scores); ?>,
          backgroundColor: '#247a37'
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 5 } }
      }
    });
  </script>
</body>
</html>