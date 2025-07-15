<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../register/login.php');
    exit();
}

// Fetch user info for display (fetch from DB for more accurate info, like in profile.php)
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

// Fetch faculty department
$faculty_id = null;
$faculty_department = '';
$faculty_sql = "SELECT id, department FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_row = $faculty_result->fetch_assoc()) {
    $faculty_id = (int)$faculty_row['id'];
    $faculty_department = $faculty_row['department'];
}
$faculty_stmt->close();

// Check if faculty_id is found
if (!$faculty_id) {
    die("Error: Faculty record not found for this user. Please contact administrator.");
}

// Search handling
$search = isset($_GET['search']) ? '%' . $conn->real_escape_string(trim($_GET['search'])) . '%' : '%';

// Update program statuses if end date has passed
$today = date('Y-m-d');
$update_stmt = $conn->prepare("UPDATE programs SET status = 'ended' WHERE end_date < ? AND status = 'ongoing'");
$update_stmt->bind_param("s", $today);
$update_stmt->execute();
$update_stmt->close();

// Pagination settings
$programs_per_page = 5;

// Get current page for active programs (default to 1)
$active_page = isset($_GET['active_page']) ? (int)$_GET['active_page'] : 1;
$active_page = max(1, $active_page);

// Get current page for ended programs (default to 1)
$ended_page = isset($_GET['ended_page']) ? (int)$_GET['ended_page'] : 1;
$ended_page = max(1, $ended_page);

// Count total active programs for pagination
$active_count_query = "SELECT COUNT(*) AS total FROM programs p WHERE p.status = 'ongoing' AND p.program_name LIKE ? AND p.faculty_id = ?";
$active_count_stmt = $conn->prepare($active_count_query);
$active_count_stmt->bind_param('si', $search, $faculty_id);
$active_count_stmt->execute();
$active_count_result = $active_count_stmt->get_result();
$active_total = $active_count_result ? $active_count_result->fetch_assoc()['total'] : 0;
$active_total_pages = ceil($active_total / $programs_per_page);
$active_count_stmt->close();

// Validate active page
if ($active_page > $active_total_pages && $active_total_pages > 0) {
    $active_page = $active_total_pages;
} elseif ($active_total_pages === 0) {
    $active_page = 1;
}
$active_offset = ($active_page - 1) * $programs_per_page;

// Count total ended programs for pagination
$ended_count_query = "SELECT COUNT(*) AS total FROM programs p WHERE p.status = 'ended' AND p.program_name LIKE ?";
$ended_count_stmt = $conn->prepare($ended_count_query);
$ended_count_stmt->bind_param('s', $search);
$ended_count_stmt->execute();
$ended_count_result = $ended_count_stmt->get_result();
$ended_total = $ended_count_result ? $ended_count_result->fetch_assoc()['total'] : 0;
$ended_total_pages = ceil($ended_total / $programs_per_page);
$ended_count_stmt->close();

// Validate ended page
if ($ended_page > $ended_total_pages && $ended_total_pages > 0) {
    $ended_page = $ended_total_pages;
} elseif ($ended_total_pages === 0) {
    $ended_page = 1;
}
$ended_offset = ($ended_page - 1) * $programs_per_page;

// Fetch active programs with pagination and search
$active_query = "SELECT p.id, p.program_name, p.description, p.start_date, p.end_date, p.max_students, 
                  COUNT(e.id) AS enrolled
                 FROM programs p
                 LEFT JOIN enrollments e ON p.id = e.program_id AND e.status = 'approved'
                 WHERE p.status = 'ongoing'
                   AND p.program_name LIKE ?
                   AND p.faculty_id = ?
                 GROUP BY p.id
                 ORDER BY p.start_date
                 LIMIT ? OFFSET ?";
$active_stmt = $conn->prepare($active_query);
if ($active_stmt === false) {
    die('MySQL prepare error: ' . $conn->error);
}
$active_stmt->bind_param('siii', $search, $faculty_id, $programs_per_page, $active_offset);
$active_stmt->execute();
$active_result = $active_stmt->get_result();
$active_programs = [];
if ($active_result) {
    while ($row = $active_result->fetch_assoc()) {
        $active_programs[] = $row;
    }
    $active_result->free();
}
$active_stmt->close();

// Fetch ended programs with pagination and search
$ended_query = "SELECT p.id, p.program_name, p.description, p.start_date, p.end_date, p.max_students,
                COUNT(e.id) AS enrolled
                FROM programs p
                LEFT JOIN enrollments e ON p.id = e.program_id AND e.status = 'approved'
                WHERE p.status = 'ended' AND p.program_name LIKE ? AND p.faculty_id = ?
                GROUP BY p.id
                ORDER BY p.start_date
                LIMIT ? OFFSET ?";
$ended_stmt = $conn->prepare($ended_query);
if ($ended_stmt === false) {
    die('MySQL prepare error: ' . $conn->error);
}
$ended_stmt->bind_param('siii', $search, $faculty_id, $programs_per_page, $ended_offset);
$ended_stmt->execute();
$ended_result = $ended_stmt->get_result();
$ended_programs = [];
if ($ended_result) {
    while ($row = $ended_result->fetch_assoc()) {
        $ended_programs[] = $row;
    }
    $ended_result->free();
}
$ended_stmt->close();

// Fetch active notifications with all columns
$notifications_query = "SELECT id, message, priority, created_at, expires_at
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
  <title>eTracker Faculty Dashboard</title>
  <link rel="stylesheet" href="Programs.css" />
  <link rel="stylesheet" href="ParticipantsModal.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
.create-search-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 16px;
}
.search-container {
  display: flex;
  align-items: center;
  gap: 12px;
  max-width: 400px;
  flex: 1;
}
.search-wrapper {
  position: relative;
  width: 100%;
}
.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
  font-size: 16px;
}
.search-container input {
  width: 100%;
  padding: 10px 16px 10px 40px;
  border: 1px solid #ced4da;
  border-radius: 8px;
  font-size: 14px;
  background-color: #e6f4ea;
  color: #212529;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
.search-container input:focus {
  outline: none;
  border-color: #28a745;
  box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
}
.search-container input::placeholder {
  color: #6c757d;
}
.search-container .search-btn {
  padding: 10px 24px;
  background-color: #28a745;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.1s ease;
}
.search-container .search-btn:hover {
  background-color: #218838;
}
.search-container .search-btn:active {
  transform: scale(0.98);
}
.create-btn {
  padding: 10px 24px;
  background-color: rgb(50, 175, 66);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.3s ease, transform 0.1s ease;
}
.create-btn:hover {
  background-color: rgb(109, 239, 107);
}
.create-btn:active {
  transform: scale(0.98);
}
@media (max-width: 768px) {
  .create-search-container {
    flex-direction: column;
    align-items: stretch;
  }
  .search-container {
    max-width: 100%;
  }
  .create-btn {
    width: 100%;
    text-align: center;
  }
}
    /* Tab and pagination styles (unchanged) */
    .tab-container {
      display: flex;
      border-bottom: 2px solid #ddd;
      margin-bottom: 20px;
    }
    .tab {
      padding: 10px 20px;
      cursor: pointer;
      font-size: 16px;
      font-weight: bold;
      color: #333;
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
    }
    .tab.active {
      color: rgb(76, 221, 107);
      border-bottom: 3px solid rgb(59, 213, 87);
    }
    .tab:hover {
      background-color: #f5f5f5;
    }
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
    }
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-top: 20px;
      gap: 10px;
    }
    .pagination button {
      padding: 8px 16px;
      border: 1px solid #ddd;
      background-color: rgb(26, 68, 19);
      cursor: pointer;
      border-radius: 4px;
      transition: background-color 0.3s;
    }
    .pagination button:hover {
      background-color: rgb(62, 154, 94);
      color: white;
    }
    .pagination button:disabled {
      background-color: #e0e0e0;
      cursor: not-allowed;
    }
    .pagination span {
      font-size: 16px;
    }
    /* Notification styles */
    .notifications {
      padding: 10px;
    }
    .note {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 8px;
      font-size: 14px;
      position: relative;
      background: #fafdff;
      box-shadow: 0 1px 4px rgba(59,183,126,0.06);
      border-left: 5px solid #3bb77e;
    }
    .note.high { border-left: 5px solid #e53935; background: #f8d7da; color: #721c24; }
.note.medium { border-left: 5px solid #fbc02d; background: #fff3cd; color: #856404; }
.note.low { border-left: 5px solid #43a047; background: #d4edda; color: #155724; }
.notif-icon { font-size: 1.1em; }
.notif-label { font-weight: 600; margin-right: 5px; font-size: 0.97em; }
.timestamp { font-size: 12px; color: #555; margin-left: auto; }
.dismiss-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  background: none;
  border: none;
  cursor: pointer;
  color: #555;
}
.dismiss-btn:hover { color: #000; }
  </style>
</head>
<body>
  <div class="container">
    <!-- User info at top right -->
    
    <!-- Sidebar (unchanged) -->
    <aside class="sidebar">
      <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
        <span class="logo-text">eTRACKER</span>
      </div>
      <nav>
        <ul>
          <li><a href="Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li class="active"><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
                    <li><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>

          <li><a href="Attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="Evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>  
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
  <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>      </nav>
    </aside>

    <div class="main-grid">
      <!-- Main Content Area (unchanged except for notifications) -->
      <div class="main-content">
        <div class="topbar">
          <div>
            <h2>Program</h2>
            <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
          </div>
        </div>
        <div class="create-search-container">
  <div class="search-container">
    <div class="search-wrapper">
      <i class="fas fa-search search-icon"></i>
      <input type="text" id="search-input" placeholder="Search programs..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
    </div>
    <button onclick="applySearch()" class="search-btn">Search</button>
  </div>
  <button class="create-btn" onclick="window.location.href='Create.php'">Create New Program</button>
</div>
        <div class="tab-container">
          <div class="tab active" data-tab="active">Active Programs</div>
          <div class="tab" data-tab="ended">Ended Programs</div>
        </div>
        <div id="notification" class="notification">
          <span id="notification-message"></span>
          <button onclick="closeNotification()">OK</button>
        </div>
        <div id="active-programs" class="tab-content active">
          <?php if (empty($active_programs)): ?>
            <p>No active programs found.</p>
          <?php else: ?>
            <?php foreach ($active_programs as $program): ?>
              <div class="program-card" data-program-id="<?php echo htmlspecialchars($program['id']); ?>">
                <div class="program-info">
                  <div class="program-header">
                    <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                    <div class="program-badges">
                      <!-- Badges removed since columns don't exist in database -->
                    </div>
                  </div>
                  
                  <p><strong>Description:</strong> <?php echo htmlspecialchars($program['description'] ?: 'No description provided.'); ?></p>
                  
                  <div class="program-details-grid">
                    <div class="detail-item">
                      <strong>Dates:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($program['start_date']))); ?> - <?php echo htmlspecialchars(date('F j, Y', strtotime($program['end_date']))); ?>
                    </div>
                  </div>
                  
                  <p><strong>Schedule:</strong></p>
<div class="sessions-list">
  <?php
  $session_stmt = $conn->prepare("SELECT * FROM program_sessions WHERE program_id = ?");
  $session_stmt->bind_param("i", $program['id']);
  $session_stmt->execute();
  $sessions_result = $session_stmt->get_result();
  if ($sessions_result->num_rows > 0) {
      while ($session = $sessions_result->fetch_assoc()) {
          echo '<div class="session-pill">';
          echo '<span class="session-date"><i class="fa-regular fa-calendar"></i> ' . htmlspecialchars(date('M d, Y', strtotime($session['session_date']))) . '</span>';
          echo '<span class="session-time"><i class="fa-regular fa-clock"></i> ' . htmlspecialchars(substr($session['session_start'], 0, 5)) . ' - ' . htmlspecialchars(substr($session['session_end'], 0, 5)) . '</span>';
          if (!empty($session['session_title'])) {
              echo '<span class="session-title"><i class="fa-solid fa-chalkboard"></i> ' . htmlspecialchars($session['session_title']) . '</span>';
          }
          echo '</div>';
      }
  } else {
      echo '<div class="session-pill session-empty">To be defined</div>';
  }
  $session_stmt->close();
  ?>
</div>
                  <p><strong>Enrolled Students:</strong> 
                    <?php echo $program['enrolled'] > 0 
                      ? htmlspecialchars($program['enrolled']) . ' out of ' . htmlspecialchars($program['max_students'])
                      : 'No participants as of the moment'; ?>
                  </p>
                  <p><strong>Status:</strong> Ongoing</p>
                </div>
                <div class="program-actions">
                  <button class="edit">Edit Program</button>
                  <button class="view">View Participants</button>
                  <button class="pending" onclick="showPendingEnrollments(<?php echo $program['id']; ?>)">Pending Requests</button>
                  <button class="end">End Program</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <div class="pagination">
            <button onclick="goToPage('active', <?php echo $active_page - 1; ?>)" <?php echo $active_page <= 1 ? 'disabled' : ''; ?>>Previous</button>
            <span>Page <?php echo $active_page; ?> of <?php echo $active_total_pages; ?></span>
            <button onclick="goToPage('active', <?php echo $active_page + 1; ?>)" <?php echo $active_page >= $active_total_pages ? 'disabled' : ''; ?>>Next</button>
          </div>
        </div>
        <div id="ended-programs" class="tab-content">
          <?php if (empty($ended_programs)): ?>
            <p>No ended programs found.</p>
          <?php else: ?>
            <?php foreach ($ended_programs as $program): ?>
              <div class="program-card" data-program-id="<?php echo htmlspecialchars($program['id']); ?>">
                <div class="program-info">
                  <h3><?php echo htmlspecialchars($program['program_name']); ?></h3>
                  <p><strong>Description:</strong> <?php echo htmlspecialchars($program['description'] ?: 'No description provided.'); ?></p>
                  <p><strong>Dates:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($program['start_date']))); ?> - <?php echo htmlspecialchars(date('F j, Y', strtotime($program['end_date']))); ?></p>
                  <p><strong>Schedule:</strong> To be defined</p>
                  <p><strong>Enrolled Students:</strong> 
                    <?php echo $program['enrolled'] > 0 
                      ? htmlspecialchars($program['enrolled']) . ' out of ' . htmlspecialchars($program['max_students'])
                      : 'No participants as of the moment'; ?>
                  </p>
                  <p><strong>Status:</strong> Ended</p>
                </div>
                <div class="program-actions">
                  <button class="view">View Participants</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <div class="pagination">
            <button onclick="goToPage('ended', <?php echo $ended_page - 1; ?>)" <?php echo $ended_page <= 1 ? 'disabled' : ''; ?>>Previous</button>
            <span>Page <?php echo $ended_page; ?> of <?php echo $ended_total_pages; ?></span>
            <button onclick="goToPage('ended', <?php echo $ended_page + 1; ?>)" <?php echo $ended_page >= $ended_total_pages ? 'disabled' : ''; ?>>Next</button>
          </div>
        </div>
      </div>

      <!-- Right Panel with Updated Notifications -->
      <div class="right-panel">
  <div class="top-actions">
    <div class="user-info">
      <div class="name"><?php echo htmlspecialchars($user_fullname); ?></div>
      <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
    </div>
  </div>
  <div class="notifications">
    <h3>🔔 Notifications</h3>
    <?php if (empty($notifications)) { ?>
      <p class="no-notifications">No notifications at this time.</p>
    <?php } else { ?>
      <?php foreach ($notifications as $notification) { 
        // Icon, label, and class for priority
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
          <?php if ($notification['expires_at']): ?>
            <div class="notif-date">Expires: <?php echo htmlspecialchars($notification['expires_at']); ?></div>
          <?php endif; ?>
        </div>
      <?php } ?>
    <?php } ?>
  </div>
</div>
    </div>

    <!-- Edit Program Modal -->
    <div id="editModal" class="modal">
      <div class="modal-content">
        <h3>Edit Program</h3>
        <form id="edit-program-form">
          <input type="hidden" name="program_id" id="edit-program-id" />
          <label>Program Name</label>
          <input type="text" name="program_name" id="edit-program-name" required />

          <label>Department</label>
          <input type="text" name="department" id="edit-department" value="<?php echo htmlspecialchars($faculty_department); ?>" readonly style="background:#f0f0f0;" />

          <label>Start Date</label>
          <input type="date" name="start_date" id="edit-start-date" required />

          <label>End Date</label>
          <input type="date" name="end_date" id="edit-end-date" required />

          <label>Location</label>
          <input type="text" name="location" id="edit-location" required />

          <label>Max Students</label>
          <input type="number" name="max_students" id="edit-max-students" min="1" required />

          <label>Description</label>
          <textarea name="description" id="edit-description"></textarea>

          <label>Sessions</label>
          <div id="edit-sessions-container"></div>
          <button type="button" id="edit-add-session-btn" style="margin-top:8px;">Add Another Session</button>

          <div class="form-buttons">
            <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
            <button type="submit" class="submit">Update Program</button>
          </div>
        </form>
        <div id="form-message"></div>
      </div>
    </div>

    <!-- Participants Modal (unchanged) -->
    <div id="participantsModal" class="participants-modal">
      <div class="participants-modal-content">
        <h3>Participants</h3>
        <div id="participants-list">
          <table id="participants-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Enrolled Date</th>
              </tr>
            </thead>
            <tbody id="participants-table-body"></tbody>
          </table>
          <p id="no-participants-message" style="display: none;">No participants enrolled.</p>
        </div>
        <div class="modal-buttons">
          <button type="button" class="close-participants" onclick="closeParticipantsModal()">Close</button>
        </div>
      </div>
    </div>

    <!-- Pending Participants Modal -->
    <div id="pendingModal" class="participants-modal">
      <div class="participants-modal-content">
        <h3>Pending Enrollment Requests</h3>
        <div id="pending-list">
          <table id="pending-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Enrolled Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="pending-table-body"></tbody>
          </table>
          <p id="no-pending-message" style="display: none;">No pending requests.</p>
        </div>
        <div class="modal-buttons">
          <button type="button" onclick="closePendingModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function applySearch() {
  const search = document.getElementById('search-input').value.trim();
  const url = new URL(window.location);
  // Set or clear search parameter
  if (search) {
    url.searchParams.set('search', search);
  } else {
    url.searchParams.delete('search');
  }
  // Always reset pagination to page 1
  url.searchParams.set('active_page', 1);
  url.searchParams.set('ended_page', 1);
  window.location.href = url.toString();
}
    // Tab switching logic (unchanged)
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const tabId = tab.dataset.tab;
        document.getElementById(`${tabId}-programs`).classList.add('active');
      });
    });

    // Pagination navigation (unchanged)
    function goToPage(tab, page) {
      const url = new URL(window.location);
      url.searchParams.set(`${tab}_page`, page);
      window.location.href = url.toString();
    }

    // Dismiss notification
    function dismissNotification(notificationId) {
      if (confirm('Are you sure you want to dismiss this notification?')) {
        fetch('dismiss_notification.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
          showNotification(data.message, data.status);
          if (data.status === 'success') {
            document.querySelector(`.note[data-notification-id="${notificationId}"]`).remove();
            if (!document.querySelector('.note')) {
              document.querySelector('.notifications').innerHTML += '<p class="no-notifications">No notifications at this time.</p>';
            }
          }
        })
        .catch(error => {
          showNotification('Error dismissing notification: ' + error.message, 'error');
        });
      }
    }

    // Handle Create New Program button (unchanged)
    document.querySelector('.create-btn').addEventListener('click', () => {
      window.location.href = 'Create.php';
    });

    // Handle Edit, View, and End buttons (unchanged)
    document.querySelectorAll('.program-card').forEach(card => {
      const programId = card.dataset.programId;
      const editButton = card.querySelector('.edit');
      if (editButton) {
        editButton.addEventListener('click', () => {
          fetch(`get_program.php?id=${programId}`)
            .then(response => response.json())
            .then(data => {
              if (data.status === 'success') {
                const program = data.data;
                document.getElementById('edit-program-id').value = programId;
                document.getElementById('edit-program-name').value = program.program_name;
                document.getElementById('edit-department').value = "<?php echo htmlspecialchars($faculty_department); ?>";
                document.getElementById('edit-start-date').value = program.start_date;
                document.getElementById('edit-end-date').value = program.end_date;
                document.getElementById('edit-location').value = program.location;
                document.getElementById('edit-max-students').value = program.max_students;
                document.getElementById('edit-description').value = program.description || '';
                // Load sessions
                const sessionsContainer = document.getElementById('edit-sessions-container');
                sessionsContainer.innerHTML = '';
                if (program.sessions && program.sessions.length > 0) {
                  program.sessions.forEach((session, index) => {
                    const sessionRow = document.createElement('div');
                    sessionRow.className = 'session-row';
                    sessionRow.innerHTML = `
                      <input type="hidden" name="session_id[]" value="${session.id}">
                      <input type="text" name="session_title[]" value="${session.session_title || ''}" placeholder="Session Title">
                      <input type="date" name="session_date[]" value="${session.session_date || ''}">
                      <input type="time" name="session_start[]" value="${session.session_start || ''}">
                      <input type="time" name="session_end[]" value="${session.session_end || ''}">
                      <button type="button" class="remove-session" onclick="this.parentElement.remove()">Remove</button>
                    `;
                    sessionsContainer.appendChild(sessionRow);
                  });
                } else {
                  sessionsContainer.innerHTML = '<p>No sessions found. Please add sessions.</p>';
                }
                document.getElementById('editModal').style.display = 'flex';
                document.getElementById('form-message').style.display = 'none';
              } else {
                showNotification(data.message, 'error');
              }
            })
            .catch(error => {
              showNotification('Error fetching program: ' + error.message, 'error');
            });
        });
      }
      card.querySelector('.view').addEventListener('click', () => {
        fetch(`get_participants.php?id=${programId}`)
          .then(response => response.json())
          .then(data => {
            if (data.status === 'success') {
              const participants = data.data;
              const tableBody = document.getElementById('participants-table-body');
              const noParticipantsMessage = document.getElementById('no-participants-message');
              tableBody.innerHTML = '';
              if (participants.length > 0) {
                participants.forEach(participant => {
                  const row = document.createElement('tr');
                  row.innerHTML = `
                    <td>${participant.student_name}</td>
                    <td>${participant.student_email}</td>
                    <td>${new Date(participant.enrollment_date).toLocaleDateString()}</td>
                  `;
                  tableBody.appendChild(row);
                });
                tableBody.parentElement.style.display = 'table';
                noParticipantsMessage.style.display = 'none';
              } else {
                tableBody.parentElement.style.display = 'none';
                noParticipantsMessage.style.display = 'block';
              }
              document.getElementById('participantsModal').style.display = 'flex';
            } else {
              showNotification(data.message, 'error');
            }
          })
          .catch(error => {
            showNotification('Error fetching participants: ' + error.message, 'error');
          });
      });
      const endButton = card.querySelector('.end');
      if (endButton) {
        endButton.addEventListener('click', () => {
          if (confirm(`Are you sure you want to end program ID ${programId}?`)) {
            fetch('end_program.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ program_id: programId })
            })
            .then(response => response.json())
            .then(data => {
              showNotification(data.message, data.status);
              if (data.status === 'success') {
                setTimeout(() => {
                  window.location.reload();
                }, 3000);
              }
            })
            .catch(error => {
              showNotification('Error ending program: ' + error.message, 'error');
            });
          }
        });
      }
    });

    // Close edit modal (unchanged)
    function closeModal() {
      document.getElementById('editModal').style.display = 'none';
      document.getElementById('edit-program-form').reset();
      document.getElementById('form-message').style.display = 'none';
    }

    // Close participants modal (unchanged)
    function closeParticipantsModal() {
      document.getElementById('participantsModal').style.display = 'none';
      document.getElementById('participants-table-body').innerHTML = '';
      document.getElementById('participants-table').style.display = 'table';
      document.getElementById('no-participants-message').style.display = 'none';
    }

    // Close pending participants modal
    function closePendingModal() {
      document.getElementById('pendingModal').style.display = 'none';
      document.getElementById('pending-table-body').innerHTML = '';
    }

    // Show pending enrollments for a program
    function showPendingEnrollments(programId) {
      fetch(`get_pending_enrollments.php?id=${programId}`)
        .then(response => response.json())
        .then(data => {
          const tableBody = document.getElementById('pending-table-body');
          const noPendingMessage = document.getElementById('no-pending-message');
          tableBody.innerHTML = '';
          if (data.status === 'success' && data.data.length > 0) {
            data.data.forEach(enrollment => {
              const row = document.createElement('tr');
              row.innerHTML = `
                <td>${enrollment.student_name}</td>
                <td>${enrollment.student_email}</td>
                <td>${new Date(enrollment.enrollment_date).toLocaleDateString()}</td>
                <td>
                  <div class="pending-actions">
                    <button class="approve-btn" onclick="updateEnrollmentStatus(${enrollment.id}, 'approved', this)">Approve</button>

                    <button class="reject-btn" onclick="showRejectDropdown(this, ${enrollment.id})">Reject</button>
                  </div>
                </td>
              `;
              tableBody.appendChild(row);
            });
            tableBody.parentElement.style.display = 'table';
            noPendingMessage.style.display = 'none';
          } else {
            tableBody.parentElement.style.display = 'none';
            noPendingMessage.style.display = 'block';
          }
          document.getElementById('pendingModal').style.display = 'flex';
        })
        .catch(error => {
          alert('Error fetching pending enrollments: ' + error.message);
        });
    }

    // Approve or reject an enrollment
    function updateEnrollmentStatus(enrollmentId, status, btn) {
  let reason = '';
  if (status === 'rejected') {
    reason = btn.previousElementSibling.value;
    if (!reason) {
      alert('Please select a reason for rejection.');
      return;
    }
  }
  fetch('update_enrollment_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: enrollmentId, status: status, reason: reason })
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    // Optionally refresh the list here
  });
}

    // Show notification (unchanged)
    function showNotification(message, type) {
      const notification = document.getElementById('notification');
      const messageSpan = document.getElementById('notification-message');
      messageSpan.textContent = message;
      notification.className = `notification ${type}`;
      notification.style.display = 'block';
      setTimeout(() => {
        notification.style.display = 'none';
      }, 3000);
    }

    // Close notification (unchanged)
    function closeNotification() {
      document.getElementById('notification').style.display = 'none';
    }

    // Handle edit form submission (unchanged)
    document.getElementById('edit-program-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = this;
      const formData = new FormData(form);
      const messageDiv = document.getElementById('form-message');
      try {
        const response = await fetch('update_program.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        messageDiv.style.display = 'block';
        messageDiv.className = result.status;
        messageDiv.textContent = result.message;
        if (result.status === 'success') {
          setTimeout(() => {
            closeModal();
            window.location.reload();
          }, 2000);
        }
      } catch (error) {
        messageDiv.style.display = 'block';
        messageDiv.className = 'error';
        messageDiv.textContent = 'Error updating program: ' + error.message;
      }
    });

    // Close modals when clicking outside (unchanged)
    document.getElementById('editModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });
    document.getElementById('participantsModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeParticipantsModal();
      }
    });
    document.getElementById('pendingModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closePendingModal();
      }
    });
    
    document.getElementById('edit-add-session-btn').addEventListener('click', function() {
      const container = document.getElementById('edit-sessions-container');
      const row = document.createElement('div');
      row.className = 'session-row';
      row.innerHTML = `
        <input type="hidden" name="session_id[]" value="">
        <input type="text" name="session_title[]" placeholder="Session Title">
        <input type="date" name="session_date[]">
        <input type="time" name="session_start[]">
        <input type="time" name="session_end[]">
        <button type="button" class="remove-session" onclick="this.parentElement.remove()">Remove</button>
      `;
      container.appendChild(row);
    });

    function showRejectDropdown(btn, enrollmentId) {
  const actionsDiv = btn.parentElement;
  if (actionsDiv.querySelector('.reject-reason')) return;

  // Hide the original Reject button
  btn.style.display = 'none';

  // Create dropdown
  const select = document.createElement('select');
  select.className = 'reject-reason';
  select.innerHTML = `
    <option value="">Select reason</option>
    <option value="Incomplete requirements">Incomplete requirements</option>
    <option value="Not eligible">Not eligible</option>
    <option value="Program full">Program full</option>
    <option value="Other">Other</option>
  `;

  // Create confirm button
  const confirmBtn = document.createElement('button');
  confirmBtn.className = 'confirm-reject-btn';
  confirmBtn.textContent = 'Confirm';
  confirmBtn.onclick = function() {
    if (!select.value) {
      alert('Please select a reason for rejection.');
      return;
    }
    updateEnrollmentStatus(enrollmentId, 'rejected', confirmBtn);
  };

  // Create cancel button
  const cancelBtn = document.createElement('button');
  cancelBtn.className = 'cancel-reject-btn';
  cancelBtn.textContent = 'Cancel';
  cancelBtn.onclick = function() {
    select.remove();
    confirmBtn.remove();
    cancelBtn.remove();
    btn.style.display = ''; // Show the original Reject button again
  };

  actionsDiv.appendChild(select);
  actionsDiv.appendChild(confirmBtn);
  actionsDiv.appendChild(cancelBtn);
}
  </script>
</body>
</html>