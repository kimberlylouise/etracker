<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user info for display
$user_id = $_SESSION['user_id'];
$user_fullname = 'Unknown User';
$user_email = 'unknown@cvsu.edu.ph';

$user_sql = "SELECT firstname, lastname, email FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_row = $user_result->fetch_assoc()) {
    $user_fullname = $user_row['firstname'] . ' ' . $user_row['lastname'];
    $user_email = $user_row['email'];
}
$user_stmt->close();

// Fetch faculty_id and department from faculty table
$faculty_id = '';
$faculty_department = '';
$faculty_sql = "SELECT id, department FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_row = $faculty_result->fetch_assoc()) {
    $faculty_id = $faculty_row['id'];
    $faculty_department = $faculty_row['department'];
}
$faculty_stmt->close();

$program_query = "SELECT id, program_name, start_date 
                  FROM programs 
                  WHERE faculty_id = ?
                  ORDER BY start_date";
$program_stmt = $conn->prepare($program_query);
$program_stmt->bind_param("i", $faculty_id);
$program_stmt->execute();
$program_result = $program_stmt->get_result();
$programs = [];
while ($row = $program_result->fetch_assoc()) {
    $programs[] = $row;
}
$program_stmt->close();

// Set default to "all" programs unless a program_id is provided via GET
$selected_program_id = isset($_GET['program_id']) && $_GET['program_id'] != 'all' ? $_GET['program_id'] : 'all';

// Only show attendance if a specific program is selected
$attendance_records = [];
if ($selected_program_id == 'all') {
    // Show all attendance for all programs under this faculty
    $attendance_query = "SELECT a.student_name, a.status, a.time_in, a.time_out, a.date, p.program_name 
                         FROM attendance a 
                         JOIN programs p ON a.program_id = p.id
                         WHERE p.faculty_id = ?
                         ORDER BY a.date DESC, a.time_in DESC";
    $attendance_stmt = $conn->prepare($attendance_query);
    $attendance_stmt->bind_param("i", $faculty_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    if ($attendance_result) {
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $attendance_result->free();
    }
} else {
    // Show attendance for the selected program only
    $attendance_query = "SELECT a.student_name, a.status, a.time_in, a.time_out, a.date, p.program_name 
                         FROM attendance a 
                         JOIN programs p ON a.program_id = p.id
                         WHERE a.program_id = ?
                         ORDER BY a.date DESC, a.time_in DESC";
    $attendance_stmt = $conn->prepare($attendance_query);
    $attendance_stmt->bind_param("i", $selected_program_id);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    if ($attendance_result) {
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[] = $row;
        }
        $attendance_result->free();
    }
}

// Handle manual attendance submission
if (isset($_POST['submit_manual_attendance'])) {
    $program_id = $_POST['program_id'];
    $student_name = $_POST['student_name'];
    $status = $_POST['status'];
    $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : NULL;
    $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : NULL;
    $date = $_POST['date'];

    $insert_query = "INSERT INTO attendance (student_name, program_id, status, time_in, time_out, date) 
                     VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("sissss", $student_name, $program_id, $status, $time_in, $time_out, $date);
    $insert_stmt->execute();
    $insert_stmt->close();

    // Redirect to refresh with "all" programs
    header("Location: attendance.php?program_id=all");
    exit;
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

// Calculate attendance summary for the selected program
$summary = [];
if ($selected_program_id != 'all') {
    $students_query = "SELECT DISTINCT student_name FROM attendance WHERE program_id = ?";
    $students_stmt = $conn->prepare($students_query);
    $students_stmt->bind_param("i", $selected_program_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    while ($student = $students_result->fetch_assoc()) {
        $name = $student['student_name'];
        $total_query = "SELECT COUNT(*) as total FROM attendance WHERE program_id = ? AND student_name = ?";
        $present_query = "SELECT COUNT(*) as present FROM attendance WHERE program_id = ? AND student_name = ? AND status = 'Present'";
        $total_stmt = $conn->prepare($total_query);
        $total_stmt->bind_param("is", $selected_program_id, $name);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result()->fetch_assoc();
        $total = $total_result['total'];
        $total_stmt->close();

        $present_stmt = $conn->prepare($present_query);
        $present_stmt->bind_param("is", $selected_program_id, $name);
        $present_stmt->execute();
        $present_result = $present_stmt->get_result()->fetch_assoc();
        $present = $present_result['present'];
        $present_stmt->close();

        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
        $summary[] = [
            'student_name' => $name,
            'present' => $present,
            'total' => $total,
            'percentage' => $percentage
        ];
    }
    $students_stmt->close();
}

// Fetch enrolled students for the selected program
$enrolled_students = [];
if ($selected_program_id != 'all') {
    $enrolled_query = "SELECT student_name FROM participants WHERE program_id = ? AND status = 'accepted'";
    $enrolled_stmt = $conn->prepare($enrolled_query);
    $enrolled_stmt->bind_param("i", $selected_program_id);
    $enrolled_stmt->execute();
    $enrolled_result = $enrolled_stmt->get_result();
    while ($row = $enrolled_result->fetch_assoc()) {
        $enrolled_students[] = $row['student_name'];
    }
    $enrolled_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eTracker Faculty Attendance</title>
  <link rel="stylesheet" href="sample.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
    <aside class="sidebar">
       <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
        <span class="logo-text">eTRACKER</span>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li class="active"><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li ><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>  
          <li><a href="reports.PHP"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
 <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>      </nav>
    </aside>

    <!-- Main Grid -->
    <div class="main-grid">
      <!-- Center Content -->
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Attendance</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
</header>

        <!-- Program Selection and Attendance Controls -->
        <div class="program-selection">
          <label for="program-select">Select Program</label>
          <select id="program-select" name="program_id" onchange="window.location.href='attendance.php?program_id=' + this.value">
            <option value="all" <?php echo ($selected_program_id == 'all') ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?php echo htmlspecialchars($program['id']); ?>" <?php echo ($selected_program_id == $program['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($program['program_name']) . ' (' . date('m/d/y', strtotime($program['start_date'])) . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="attendance-controls">
          <button class="btn" onclick="openManualAttendanceModal()">Mark Attendance Manually</button>
        </div>

        <!-- Attendance Table -->
        <table class="attendance-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Status</th>
              <th>Time-In</th>
              <th>Date</th>
              <th>Program</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($attendance_records)): ?>
              <tr><td colspan="5">No attendance records found.</td></tr>
            <?php else: ?>
              <?php foreach ($attendance_records as $record): ?>
                <tr>
                  <td><?php echo htmlspecialchars($record['student_name'] ?? 'N/A'); ?></td>
                  <td class="status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($record['status'] ?? 'Absent'))); ?>">
                    <?php echo htmlspecialchars($record['status'] ?? 'Absent'); ?>
                  </td>
                  <td><?php echo htmlspecialchars($record['time_in'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars(date('m-d-y', strtotime($record['date'] ?? 'now'))); ?></td>
                  <td><?php echo htmlspecialchars($record['program_name']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>

        <?php if ($selected_program_id != 'all'): ?>
          <h3>Attendance Summary</h3>
          <table class="attendance-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Present</th>
                <th>Total Sessions</th>
                <th>Attendance %</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($summary)): ?>
                <tr><td colspan="4">No attendance summary available.</td></tr>
              <?php else: ?>
                <?php foreach ($summary as $row): ?>
                  <tr<?php if ($row['percentage'] < 80): ?> style="background:#ffeaea;"<?php endif; ?>>
                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                    <td><?php echo $row['present']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['percentage']; ?>%</td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Right Side -->
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

  <!-- Modal for Manual Attendance -->
  <div id="manualAttendanceModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeManualAttendanceModal()">×</span>
      <h2>Mark Attendance Manually</h2>
      <form action="attendance.php" method="POST">
        <input type="hidden" name="program_id" value="<?php echo htmlspecialchars($selected_program_id != 'all' ? $selected_program_id : (empty($programs) ? '' : $programs[0]['id'])); ?>">
        <div class="form-group">
          <label for="student_name">Student Name</label>
          <?php if ($selected_program_id != 'all' && !empty($enrolled_students)): ?>
            <select id="student_name" name="student_name" required>
              <option value="">Select student</option>
              <?php foreach ($enrolled_students as $student): ?>
                <option value="<?php echo htmlspecialchars($student); ?>"><?php echo htmlspecialchars($student); ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="text" id="student_name" name="student_name" required>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <option value="Present">Present</option>
            <option value="Late">Late</option>
            <option value="Absent">Absent</option>
          </select>
        </div>
        <div class="form-group">
          <label for="time_in">Time-In</label>
          <input type="time" id="time_in" name="time_in" value="<?php echo date('H:i'); ?>">
        </div>
        <div class="form-group">
          <label for="time_out">Time-Out</label>
          <input type="time" id="time_out" name="time_out">
        </div>
        <div class="form-group">
          <label for="date">Date</label>
          <input type="date" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <button type="submit" name="submit_manual_attendance" class="btn">Submit</button>
      </form>
    </div>
  </div>

  <script>
    function openManualAttendanceModal() {
      document.getElementById('manualAttendanceModal').style.display = 'block';
    }

    function closeManualAttendanceModal() {
      document.getElementById('manualAttendanceModal').style.display = 'none';
    }

    window.onclick = function(event) {
      const modal = document.getElementById('manualAttendanceModal');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>

  <style>
    .attendance-controls {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      align-items: center;
    }
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
    .attendance-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    .attendance-table th,
    .attendance-table td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: left;
    }
    .attendance-table th {
      background-color: #d2eac8;
      color: #1e3927;
    }
    .attendance-table td {
      background-color: #fff;
    }
    .status-present { color: green; font-weight: bold; }
    .status-late { color: #f1c40f; font-weight: bold; }
    .status-absent { color: #e74c3c; font-weight: bold; }
    .note.priority-low { border-left-color: #59a96a; }
    .note.priority-medium { border-left-color: #f1c40f; }
    .note.priority-high { border-left-color: #e74c3c; }
    .modal {
      display: none;
      position: fixed;
      z-index: 1;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    .modal-content {
      background-color: #fff;
      margin: 15% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 500px;
      border-radius: 15px;
    }
    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
    }
    .close:hover,
    .close:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      color: #247a37;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
  </style>
</body>
</html>