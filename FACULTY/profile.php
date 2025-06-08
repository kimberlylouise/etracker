<?php
session_start();
require_once 'db.php';

// Debug session
error_log("Session data: " . print_r($_SESSION, true));

// Check if user is logged in and is a faculty member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    error_log("Redirecting to ../REGISTER/index.html: user_id=" . ($_SESSION['user_id'] ?? 'not set') . ", role=" . ($_SESSION['role'] ?? 'not set'));
    header('Location: ../REGISTER/index.html');
    exit;
}

// Fetch faculty profile
$user_id = $_SESSION['user_id'];
$profile_query = "SELECT id, firstname, lastname, mi, email, role 
                 FROM users WHERE id = ? AND role = 'faculty'";
$stmt = $conn->prepare($profile_query);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error . " | Query: $profile_query");
    die("Database query error: " . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Query execution error: " . htmlspecialchars($stmt->error));
}
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no profile found, redirect (possible data issue)
if (!$profile) {
    error_log("No profile found for user_id=$user_id");
    header('Location: ../REGISTER/index.html');
    exit;
}


// Fetch programs managed by the faculty
$programs_query = "SELECT p.id, p.program_name, p.start_date, p.end_date, COUNT(pt.id) as enrolled
                  FROM programs p
                  LEFT JOIN participants pt ON p.id = pt.program_id
                  WHERE p.faculty_id = ? AND p.end_date >= CURDATE()
                  GROUP BY p.id
                  ORDER BY p.start_date";
$stmt = $conn->prepare($programs_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$programs_result = $stmt->get_result();
$programs = [];
while ($row = $programs_result->fetch_assoc()) {
    $programs[] = $row;
}
$stmt->close();

// Fetch attendance summary
$attendance_query = "SELECT p.program_name, COUNT(a.id) as total_attendance
                    FROM programs p
                    LEFT JOIN attendance a ON p.id = a.program_id
                    WHERE p.faculty_id = ? AND p.end_date >= CURDATE()
                    GROUP BY p.id";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$attendance_result = $stmt->get_result();
$attendance = [];
while ($row = $attendance_result->fetch_assoc()) {
    $attendance[] = $row;
}
$stmt->close();

// Fetch notifications
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
  <title>eTracker Faculty Profile</title>
  <link rel="stylesheet" href="profile.css" />
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
          <li><a href="dashboard.php"><i>🏠</i> Dashboard</a></li>
          <li class="active"><i>👤</i> Profile</li>
          <li><a href="programs.php"><i>📋</i> Program</a></li>
          <li><a href="attendance.php"><i>🕒</i> Attendance</a></li>
          <li><a href="evaluation.php"><i>📝</i> Evaluation</a></li>
          <li><a href="certificate.php"><i>🎓</i> Certificate</a></li>
          <li><a href="reports.php"><i>📊</i> Reports</a></li>
        </ul>
      </nav>
      <div class="sign-out" onclick="window.location.href='logout.php'">Sign Out</div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-grid">
      <div class="main-content">
        <div class="topbar">
          <div>
            <h2>Faculty Profile</h2>
            <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
          </div>
        </div>

        <!-- Profile Information -->
        <div class="profile-section">
          <h3>Personal Information</h3>
          <form id="profile-form">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            <label>First Name</label>
            <input type="text" name="firstname" value="<?php echo htmlspecialchars($profile['firstname']); ?>" required />
            <label>Last Name</label>
            <input type="text" name="lastname" value="<?php echo htmlspecialchars($profile['lastname']); ?>" required />
            <label>M.I.</label>
            <input type="text" name="mi" value="<?php echo htmlspecialchars($profile['mi'] ?? ''); ?>" maxlength="5" />
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required />
            <div class="form-buttons">
              <button type="submit" class="submit">Update Profile</button>
            </div>
          </form>
          <div id="form-message"></div>
        </div>

        <!-- Program Registrations -->
        <div class="profile-section">
          <h3>Managed Programs</h3>
          <?php if (empty($programs)) { ?>
            <p>No active programs found.</p>
          <?php } else { ?>
            <table class="profile-table">
              <thead>
                <tr>
                  <th>Program Name</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Enrolled Students</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($programs as $program) { ?>
                  <tr>
                    <td><?php echo htmlspecialchars($program['program_name']); ?></td>
                    <td><?php echo date('F j, Y', strtotime($program['start_date'])); ?></td>
                    <td><?php echo date('F j, Y', strtotime($program['end_date'])); ?></td>
                    <td><?php echo htmlspecialchars($program['enrolled']); ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div>

        <!-- Attendance Summary -->
        <div class="profile-section">
          <h3>Attendance Summary</h3>
          <?php if (empty($attendance)) { ?>
            <p>No attendance records found.</p>
          <?php } else { ?>
            <table class="profile-table">
              <thead>
                <tr>
                  <th>Program Name</th>
                  <th>Total Attendance</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($attendance as $record) { ?>
                  <tr>
                    <td><?php echo htmlspecialchars($record['program_name']); ?></td>
                    <td><?php echo htmlspecialchars($record['total_attendance']); ?></td>
                  </tr>
                <?php } ?>
              </tbody>
            </table>
          <?php } ?>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="right-panel">
        <div class="top-actions">
          <input type="text" placeholder="🔍 Search" class="search-input" />
          <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($profile['firstname'] . ' ' . $profile['lastname']); ?></div>
            <div class="email"><?php echo htmlspecialchars($profile['email']); ?></div>
          </div>
        </div>
        <div class="notifications">
          <h3>🔔 Notifications</h3>
          <?php if (empty($notifications)) { ?>
            <p class="no-notifications">No notifications at this time.</p>
          <?php } else { ?>
            <?php foreach ($notifications as $notification) { ?>
              <div class="note priority-<?php echo htmlspecialchars($notification['priority']); ?>">
                <?php echo htmlspecialchars($notification['message']); ?>
              </div>
            <?php } ?>
          <?php } ?>
        </div>
      </div>

      <!-- Profile Update Modal -->
      <div id="updateModal" class="modal">
        <div class="modal-content">
          <h3>Profile Updated</h3>
          <p>Your profile has been successfully updated.</p>
          <button onclick="closeModal()">OK</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Handle profile form submission
    document.getElementById('profile-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const messageDiv = document.getElementById('form-message');

      try {
        const response = await fetch('update_profile.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        messageDiv.style.display = 'block';
        messageDiv.className = result.status;
        messageDiv.textContent = result.message;

        if (result.status === 'success') {
          document.getElementById('updateModal').style.display = 'flex';
          setTimeout(() => {
            window.location.reload();
          }, 2000);
        }
      } catch (error) {
        messageDiv.style.display = 'block';
        messageDiv.className = 'error';
        messageDiv.textContent = 'Error updating profile: ' + error.message;
      }
    });

    // Close modal
    function closeModal() {
      document.getElementById('updateModal').style.display = 'none';
    }

    // Show notification
    function showNotification(message, type) {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()">OK</button>`;
      document.body.appendChild(notification);
      setTimeout(() => notification.remove(), 3000);
    }

    // Close modal on outside click
    document.getElementById('updateModal').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) {
        closeModal();
      }
    });
  </script>
</body>
</html>
