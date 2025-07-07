<?php
session_start();
require 'db.php'; // your DB connection

$user_id = $_SESSION['user_id'] ?? null;
$faculty_profile = null;
$notifications = [];
$programs = [];
$attendance = [];

if ($user_id) {
    // Get faculty profile
    $sql = "SELECT 
                u.firstname, u.lastname, u.mi, u.email, u.phone, u.department AS user_department,
                f.faculty_name, f.faculty_id, f.department AS faculty_department, f.position
            FROM users u
            JOIN faculty f ON u.id = f.user_id
            WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $faculty_profile = $result->fetch_assoc();
    $stmt->close();

    // Get notifications for 'all' or this faculty's department
    $faculty_department = $faculty_profile['faculty_department'] ?? '';
    $stmt = $conn->prepare("SELECT message, priority, expires_at FROM notifications WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) AND (audience = 'all' OR audience = ?) ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("s", $faculty_department);
    $stmt->execute();
    $stmt->bind_result($message, $priority, $expires_at);
    while ($stmt->fetch()) {
        $notifications[] = [
            'message' => $message,
            'priority' => $priority,
            'expires_at' => $expires_at
        ];
    }
    $stmt->close();

    // Get programs assigned to this faculty (dummy example)
    // $programs = ... (your existing code)

    // Get attendance summary (dummy example)
    // $attendance = ... (your existing code)
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_personal']) && $user_id) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $mi = trim($_POST['mi']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, mi=?, email=?, phone=? WHERE id=?");
    $stmt->bind_param("sssssi", $firstname, $lastname, $mi, $email, $phone, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: profile.php?updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eTracker Faculty Profile</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="upload.php"><i class="fas fa-upload"></i> Documents </a></li>  
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>
      </nav>
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

      

       

        <!-- Profile Sections Grid -->
        <div class="profile-sections-grid">
          <!-- Personal Info Card -->
          <div class="profile-card-section">
            <div class="section-header">
              <i class="fas fa-user"></i>
              <span>Personal Info</span>
              <button class="edit-btn" onclick="editPersonalInfo()" title="Edit Personal Info">
                <i class="fas fa-edit"></i>
              </button>
            </div>
            <div class="section-content">
              <div class="info-row"><span>First Name:</span> <?php echo htmlspecialchars($faculty_profile['firstname'] ?? ''); ?></div>
              <div class="info-row"><span>Last Name:</span> <?php echo htmlspecialchars($faculty_profile['lastname'] ?? ''); ?></div>
              <div class="info-row"><span>M.I.:</span> <?php echo htmlspecialchars($faculty_profile['mi'] ?? ''); ?></div>
              <div class="info-row"><span>Email:</span> <?php echo htmlspecialchars($faculty_profile['email'] ?? ''); ?></div>
              <div class="info-row"><span>Phone:</span> <?php echo htmlspecialchars($faculty_profile['phone'] ?? ''); ?></div>
            </div>
          </div>
          <!-- Faculty Profile Card -->
          <div class="profile-card-section">
            <div class="section-header">
              <i class="fas fa-id-badge"></i>
              <span>Faculty Profile</span>
            </div>
            <div class="section-content">
              <div class="info-row"><span>Faculty Name:</span> <?php echo htmlspecialchars($faculty_profile['faculty_name'] ?? ''); ?></div>
              <div class="info-row"><span>Faculty ID:</span> <?php echo htmlspecialchars($faculty_profile['faculty_id'] ?? ''); ?></div>
              <div class="info-row"><span>Department:</span> <?php echo htmlspecialchars($faculty_profile['faculty_department'] ?? ''); ?></div>
              <div class="info-row"><span>Position:</span> <?php echo htmlspecialchars($faculty_profile['position'] ?? ''); ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="right-panel">
        <div class="top-actions">
          <div class="user-info">
            <div class="name"><?php echo htmlspecialchars(($faculty_profile['firstname'] ?? '') . ' ' . ($faculty_profile['lastname'] ?? '')); ?></div>
            <div class="email"><?php echo htmlspecialchars($faculty_profile['email'] ?? ''); ?></div>
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

      <!-- Profile Update Modal -->
      <div id="updateModal" class="modal">
        <div class="modal-content">
          <h3>Profile Updated</h3>
          <p>Your profile has been successfully updated.</p>
          <button onclick="closeModal()">OK</button>
        </div>
      </div>

      <!-- Edit Personal Info Modal -->
      <div id="editPersonalModal" class="modal-overlay">
        <div class="modal-card">
          <h3><i class="fas fa-user-edit"></i> Edit Personal Information</h3>
          <form method="POST" action="profile.php" id="editPersonalForm">
            <input type="hidden" name="edit_personal" value="1">
            <div class="modal-row">
              <label>First Name</label>
              <input type="text" name="firstname" value="<?php echo htmlspecialchars($faculty_profile['firstname'] ?? ''); ?>" required>
            </div>
            <div class="modal-row">
              <label>Last Name</label>
              <input type="text" name="lastname" value="<?php echo htmlspecialchars($faculty_profile['lastname'] ?? ''); ?>" required>
            </div>
            <div class="modal-row">
              <label>M.I.</label>
              <input type="text" name="mi" value="<?php echo htmlspecialchars($faculty_profile['mi'] ?? ''); ?>">
            </div>
            <div class="modal-row">
              <label>Email</label>
              <input type="email" name="email" value="<?php echo htmlspecialchars($faculty_profile['email'] ?? ''); ?>" required>
            </div>
            <div class="modal-row">
              <label>Phone</label>
              <input type="text" name="phone" value="<?php echo htmlspecialchars($faculty_profile['phone'] ?? ''); ?>">
            </div>
            <div class="modal-actions">
              <button type="button" onclick="closeEditModal()">Cancel</button>
              <button type="submit" class="save-btn">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
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

    function editPersonalInfo() {
      // Show your modal or form for editing personal info
      document.getElementById('editPersonalModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editPersonalModal').style.display = 'none';
    }

    // Optional: Close modal when clicking outside the card
    document.getElementById('editPersonalModal').addEventListener('click', function(e) {
      if (e.target === this) closeEditModal();
    });
  </script>
</body>
</html>



