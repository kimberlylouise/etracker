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

// Fetch summary data based on selected program (default to all)
$summary_query = "
    SELECT 
        p.program_name,
        COUNT(DISTINCT a.id) as attendance_count,
        AVG(CAST(REPLACE(e.score, '%', '') AS DECIMAL(5,2))) as avg_score,
        COUNT(DISTINCT c.id) as certificate_count
    FROM programs p
    LEFT JOIN attendance a ON p.id = a.program_id
    LEFT JOIN evaluations e ON p.id = e.program_id
    LEFT JOIN certificates c ON p.id = c.program_id";
if ($selected_program_id != 'all') {
    $summary_query .= " WHERE p.id = ?";
}
$summary_query .= " GROUP BY p.id, p.program_name
                    ORDER BY p.start_date DESC";
$summary_stmt = $conn->prepare($summary_query);
if ($selected_program_id != 'all') {
    $summary_stmt->bind_param("i", $selected_program_id);
}
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary_data = [];
if ($summary_result) {
    while ($row = $summary_result->fetch_assoc()) {
        $summary_data[] = $row;
    }
    $summary_result->free();
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
  <title>eTracker Faculty Reports</title>
  <link rel="stylesheet" href="sample.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .program-selection { margin: 20px 0; display: flex; align-items: center; gap: 10px; }
    .program-selection label { font-weight: bold; color: #247a37; }
    .program-selection select { padding: 10px; border: 1px solid #ccc; border-radius: 25px; background: white; font-size: 14px; width: 300px; cursor: pointer; transition: all 0.3s ease; }
    .program-selection select:hover { border-color: #247a37; box-shadow: 0 0 5px rgba(36, 122, 55, 0.3); }

    .report-controls { display: flex; gap: 15px; margin: 20px 0; }
    .btn { padding: 12px 25px; background: linear-gradient(90deg, #59a96a, #247a37); border: none; border-radius: 25px; color: white; font-weight: bold; cursor: pointer; transition: transform 0.2s, background 0.3s; }
    .btn:hover { transform: scale(1.05); background: linear-gradient(90deg, #247a37, #59a96a); }

    .note.priority-low { border-left-color: #59a96a; }
    .note.priority-medium { border-left-color: #f1c40f; }
    .note.priority-high { border-left-color: #e74c3c; }

    /* Reports Section: Interactive Dashboard Cards */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .card {
      background: linear-gradient(135deg, #ffffff, #f4f7f6);
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }
    .card h3 {
      color: #247a37;
      margin: 0 0 15px;
      font-size: 18px;
    }
    .stat {
      margin-bottom: 15px;
    }
    .stat span:first-child {
      color: #666;
      font-size: 14px;
      display: block;
    }
    .stat span:last-child {
      color: #247a37;
      font-weight: bold;
      font-size: 20px;
    }
    .progress-bar {
      height: 8px;
      background: #e0e0e0;
      border-radius: 4px;
      margin: 5px 0;
      overflow: hidden;
    }
    .progress {
      height: 100%;
      background: linear-gradient(90deg, #59a96a, #247a37);
      transition: width 0.3s ease;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar (Reverted to sample.css) -->
    <aside class="sidebar">
      <div class="logo">eTRACKER</div>
      <nav>
        <ul>
          <li><a href="dashboard.html"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li class="active"><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out">Sign Out</div>
      </nav>
    </aside>

    <!-- Main Grid -->
    <div class="main-grid">
      <!-- Center Content -->
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Reports</div>
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
          <select id="program-select" name="program_id" onchange="window.location.href='reports.php?program_id=' + this.value">
            <option value="all" <?php echo ($selected_program_id == 'all') ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?php echo htmlspecialchars($program['id']); ?>" <?php echo ($selected_program_id == $program['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($program['program_name']) . ' (' . date('m/d/y', strtotime($program['start_date'])) . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="report-controls">
          <button class="btn" onclick="exportReport()">Export Report</button>
          <button class="btn" onclick="openChartPanel()">View Chart</button>
        </div>

        <!-- Reports Section: Interactive Dashboard Cards (Unchanged) -->
        <div class="dashboard-grid">
          <?php if (!empty($summary_data)): ?>
            <?php foreach ($summary_data as $data): ?>
              <div class="card" onclick="openChartPanel('<?php echo htmlspecialchars($data['program_name']); ?>')">
                <h3><?php echo htmlspecialchars($data['program_name']); ?></h3>
                <div class="stat">
                  <span>Attendance</span>
                  <div class="progress-bar">
                    <div class="progress" style="width: <?php echo ($data['attendance_count'] / max(array_column($summary_data, 'attendance_count')) * 100); ?>%;"></div>
                  </div>
                  <span><?php echo htmlspecialchars($data['attendance_count'] ?? '0'); ?></span>
                </div>
                <div class="stat">
                  <span>Avg Score</span>
                  <span><?php echo htmlspecialchars($data['avg_score'] ? number_format($data['avg_score'], 2) . '%' : '-'); ?></span>
                </div>
                <div class="stat">
                  <span>Certificates</span>
                  <div class="progress-bar">
                    <div class="progress" style="width: <?php echo ($data['certificate_count'] / max(array_column($summary_data, 'certificate_count')) * 100); ?>%;"></div>
                  </div>
                  <span><?php echo htmlspecialchars($data['certificate_count'] ?? '0'); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="card" style="text-align: center;">
              <p>No data available.</p>
            </div>
          <?php endif; ?>
        </div>
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

  <script>
    function exportReport() {
      alert('Export functionality to be implemented. Add logic to export data (e.g., CSV) here.');
      // Placeholder: Replace with actual export logic
    }

    function openChartPanel(program) {
      const chartCode = `
        const ctx = document.createElement('canvas').getContext('2d');
        new Chart(ctx, {
          type: 'bar',
          data: {
            labels: ['${program}'],
            datasets: [{
              label: 'Attendance Count',
              data: [<?php echo json_encode(array_column(array_filter($summary_data, fn($d) => $d['program_name'] === $program), 'attendance_count')); ?>[0] ?? 0],
              backgroundColor: '#59a96a',
              borderColor: '#247a37',
              borderWidth: 1
            }, {
              label: 'Certificate Count',
              data: [<?php echo json_encode(array_column(array_filter($summary_data, fn($d) => $d['program_name'] === $program), 'certificate_count')); ?>[0] ?? 0],
              backgroundColor: '#d2eac8',
              borderColor: '#1e3927',
              borderWidth: 1
            }]
          },
          options: {
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { labels: { color: '#1e3927' } } }
          }
        });
      `;
      eval(chartCode); // Placeholder; system will render in canvas panel
    }
  </script>
</body>
</html>