<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user info for display (like in Programs.php)
$user_id = $_SESSION['user_id'];
$user_fullname = 'Unknown User';
$user_email = 'unknown@cvsu.edu.ph';

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

// Fetch faculty_id from faculty table
$faculty_id = '';
$faculty_sql = "SELECT id FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_row = $faculty_result->fetch_assoc()) {
    $faculty_id = $faculty_row['id'];
}
$faculty_stmt->close();

// Fetch all programs for this faculty for the dropdown
$program_query = "SELECT id, program_name, start_date FROM programs WHERE faculty_id = ? ORDER BY start_date";
$program_stmt = $conn->prepare($program_query);
$program_stmt->bind_param("i", $faculty_id);
$program_stmt->execute();
$program_result = $program_stmt->get_result();
$programs = [];
if ($program_result) {
    while ($row = $program_result->fetch_assoc()) {
        $programs[] = $row;
    }
    $program_result->free();
}
$program_stmt->close();

// Set default to "all" programs unless a program_id is provided via GET
$selected_program_id = isset($_GET['program_id']) && $_GET['program_id'] != 'all' ? $_GET['program_id'] : 'all';

// Fetch detailed evaluations based on selected program (default to all for this faculty)
$eval_query = "SELECT de.*, p.program_name 
               FROM detailed_evaluations de
               JOIN programs p ON de.program_id = p.id
               WHERE p.faculty_id = ?";
if ($selected_program_id != 'all') {
    $eval_query .= " AND de.program_id = ?";
}
$eval_query .= " ORDER BY de.eval_date DESC";
$eval_stmt = $conn->prepare($eval_query);
if ($selected_program_id != 'all') {
    $eval_stmt->bind_param("ii", $faculty_id, $selected_program_id);
} else {
    $eval_stmt->bind_param("i", $faculty_id);
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
$eval_stmt->close();

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
 <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
        <span class="logo-text">eTRACKER</span>
      </div>      <nav>
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li class="active"><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
           <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>  
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
 <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>      </nav>
    </aside>

    <!-- Main Grid: main-content and right-panel as siblings -->
    <div class="main-grid">
      <!-- Center Content -->
      <div class="main-content">
        <header class="topbar">
          <div class="role-label">Faculty Evaluation</div>
          <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
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

        <!-- Stylish Evaluation Table -->
        <div class="evaluation-cards">
          <?php if (empty($evaluations)): ?>
            <div class="eval-card empty">
              <div class="empty-msg">No evaluations found.</div>
            </div>
          <?php else: ?>
            <?php foreach ($evaluations as $eval): ?>
              <div class="eval-card">
                <div class="eval-header">
                  <div class="eval-name">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($eval['student_name'] ?? 'N/A'); ?>
                  </div>
                  <div class="eval-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo htmlspecialchars(date('M d, Y', strtotime($eval['eval_date'] ?? 'now'))); ?>
                  </div>
                </div>
                <div class="eval-program">
                  <i class="fas fa-tasks"></i>
                  <?php echo htmlspecialchars($eval['program_name']); ?>
                </div>
                <div class="eval-ratings">
                  <div class="eval-badge content">Content: <span><?php echo htmlspecialchars($eval['content'] ?? '-'); ?></span></div>
                  <div class="eval-badge facilitators">Facilitators: <span><?php echo htmlspecialchars($eval['facilitators'] ?? '-'); ?></span></div>
                  <div class="eval-badge relevance">Relevance: <span><?php echo htmlspecialchars($eval['relevance'] ?? '-'); ?></span></div>
                  <div class="eval-badge organization">Organization: <span><?php echo htmlspecialchars($eval['organization'] ?? '-'); ?></span></div>
                  <div class="eval-badge experience">Experience: <span><?php echo htmlspecialchars($eval['experience'] ?? '-'); ?></span></div>
                </div>
                <div class="eval-suggestion">
                  <strong>Suggestion:</strong>
                  <span><?php echo htmlspecialchars($eval['suggestion'] ?? '-'); ?></span>
                </div>
                <div class="eval-recommend">
                  <span class="recommend-badge <?php echo strtolower($eval['recommend'] ?? ''); ?>">
                    <i class="fas fa-thumbs-up"></i>
                    <?php echo htmlspecialchars($eval['recommend'] ?? '-'); ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
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
    .evaluation-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
      gap: 28px;
      margin-top: 32px;
      animation: fadeInUp 1.1s;
    }
    .eval-card {
      background: linear-gradient(135deg, #eafbe7 80%, #fffde4 100%);
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(36, 122, 55, 0.10), 0 1.5px 6px rgba(36, 122, 55, 0.06);
      padding: 24px 22px 18px 22px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      position: relative;
      opacity: 0;
      transform: translateY(30px);
      animation: cardFadeIn 0.7s forwards;
    }
    .eval-card:nth-child(1) { animation-delay: 0.05s; }
    .eval-card:nth-child(2) { animation-delay: 0.15s; }
    .eval-card:nth-child(3) { animation-delay: 0.25s; }
    .eval-card:nth-child(4) { animation-delay: 0.35s; }
    .eval-card:nth-child(5) { animation-delay: 0.45s; }
    @keyframes cardFadeIn {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(40px);}
      to { opacity: 1; transform: translateY(0);}
    }
    .eval-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-weight: 600;
      font-size: 1.1rem;
      color: #247a37;
      margin-bottom: 2px;
    }
    .eval-header .eval-name i {
      color: #59a96a;
      margin-right: 7px;
      font-size: 1.2em;
    }
    .eval-header .eval-date i {
      color: #b2b2b2;
      margin-right: 5px;
    }
    .eval-program {
      font-size: 1.02rem;
      color: #1e3927;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 7px;
    }
    .eval-program i {
      color: #247a37;
    }
    .eval-ratings {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 16px;
      margin-bottom: 6px;
    }
    .eval-badge {
      background: #fff;
      border-radius: 12px;
      padding: 6px 14px;
      font-size: 0.98rem;
      color: #247a37;
      box-shadow: 0 1px 4px rgba(36, 122, 55, 0.07);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background 0.2s;
    }
    .eval-badge span {
      font-weight: bold;
      color: #59a96a;
      font-size: 1.08em;
    }
    .eval-suggestion {
      font-size: 0.97rem;
      color: #1e3927;
      background: #f7fff4;
      border-left: 4px solid #59a96a;
      border-radius: 7px;
      padding: 7px 12px;
      margin-bottom: 2px;
      min-height: 32px;
    }
    .eval-recommend {
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .recommend-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #59a96a;
      color: #fff;
      padding: 6px 16px;
      border-radius: 16px;
      font-weight: 600;
      font-size: 1.01rem;
      box-shadow: 0 1px 4px rgba(36, 122, 55, 0.07);
      letter-spacing: 0.5px;
      animation: fadeInUp 0.7s;
    }
    .recommend-badge.no {
      background: #e74c3c;
    }
    .recommend-badge.yes {
      background: #59a96a;
    }
    .eval-card.empty {
      background: #fffbe4;
      color: #aaa;
      text-align: center;
      font-size: 1.1rem;
      box-shadow: none;
      border: 2px dashed #e0e0e0;
      padding: 40px 0;
    }
    .empty-msg {
      margin: 0 auto;
      color: #aaa;
      font-size: 1.1rem;
      letter-spacing: 1px;
    }
    .eval-card:hover {
      box-shadow: 0 8px 32px rgba(36, 122, 55, 0.18), 0 2px 8px rgba(36, 122, 55, 0.10);
      transform: translateY(-4px) scale(1.012);
      transition: box-shadow 0.25s, transform 0.22s;
      background: linear-gradient(135deg, #d2eac8 80%, #fffde4 100%);
    }
    @media (max-width: 900px) {
      .evaluation-cards {
        grid-template-columns: 1fr;
      }
    }
  </style>
</body>
</html>