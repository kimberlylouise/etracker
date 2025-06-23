<?php
require_once 'db.php';
session_start();

// Get logged-in user and faculty info
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit();
}
$faculty_id = null;
$stmt = $conn->prepare("SELECT id FROM faculty WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($faculty_id);
$stmt->fetch();
$stmt->close();

// Fetch user info for display (top right)
$user_fullname = 'Unknown User';
$user_email = 'unknown@cvsu.edu.ph';
$stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $email);
if ($stmt->fetch()) {
    $user_fullname = $firstname . ' ' . $lastname;
    $user_email = $email;
}
$stmt->close();

// Fetch only programs managed by this faculty, including certificate info
$programs = [];
$program_query = "SELECT id, program_name, start_date, faculty_certificate_issued, faculty_certificate_file, faculty_certificate_issued_on
                  FROM programs
                  WHERE faculty_id = ?
                  ORDER BY start_date";
$stmt = $conn->prepare($program_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $programs[] = $row;
}
$stmt->close();

// Set default to "all" programs unless a program_id is provided via GET
$selected_program_id = isset($_GET['program_id']) && $_GET['program_id'] != 'all' ? $_GET['program_id'] : 'all';

// Fetch certificates for these programs only
$certificates = [];
if (!empty($programs)) {
    $program_ids = array_column($programs, 'id');
    if ($selected_program_id != 'all' && in_array($selected_program_id, $program_ids)) {
        // Filter by selected program
        $certificate_query = "SELECT c.student_name, c.certificate_date, c.status, p.program_name 
                              FROM certificates c 
                              JOIN programs p ON c.program_id = p.id
                              WHERE c.program_id = ?
                              ORDER BY c.certificate_date DESC";
        $stmt = $conn->prepare($certificate_query);
        $stmt->bind_param("i", $selected_program_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Show all certificates for all programs managed by this faculty
        $in = implode(',', array_fill(0, count($program_ids), '?'));
        $types = str_repeat('i', count($program_ids));
        $certificate_query = "SELECT c.student_name, c.certificate_date, c.status, p.program_name 
                              FROM certificates c 
                              JOIN programs p ON c.program_id = p.id
                              WHERE c.program_id IN ($in)
                              ORDER BY c.certificate_date DESC";
        $stmt = $conn->prepare($certificate_query);
        $stmt->bind_param($types, ...$program_ids);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    while ($row = $result->fetch_assoc()) {
        $certificates[] = $row;
    }
    $stmt->close();
}

// Fetch active notifications
$notifications = [];
$notifications_query = "SELECT message, priority
                       FROM notifications
                       WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE())
                       ORDER BY created_at DESC
                       LIMIT 5";
$notifications_result = $conn->query($notifications_query);
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
      <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
        <span class="logo-text">eTRACKER</span>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li class="active"><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>  
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>
      </nav>
    </aside>

    <!-- Main Grid -->
    <div class="main-grid">
      <!-- Center Content -->
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Certificates</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
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
              <th>Program</th>
              <th>Issued On</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($programs)): ?>
              <tr><td colspan="4">No programs found.</td></tr>
            <?php else: ?>
              <?php foreach ($programs as $program): ?>
                <tr>
                  <td><?php echo htmlspecialchars($program['program_name']); ?></td>
                  <td><?php echo $program['faculty_certificate_issued_on'] ? date('m-d-Y', strtotime($program['faculty_certificate_issued_on'])) : '-'; ?></td>
                  <td class="<?php echo $program['faculty_certificate_issued'] ? 'status-generated' : 'status-pending'; ?>">
                    <?php echo $program['faculty_certificate_issued'] ? 'Issued' : 'Pending'; ?>
                  </td>
                  <td>
                    <?php if ($program['faculty_certificate_issued'] && !empty($program['faculty_certificate_file'])): ?>
                      <a href="/<?php echo htmlspecialchars($program['faculty_certificate_file']); ?>" class="btn" target="_blank">View</a>
                      <a href="/<?php echo htmlspecialchars($program['faculty_certificate_file']); ?>" class="btn" download>Download</a>
                    <?php else: ?>
                      <span>Not available</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
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