<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

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

$faculty_department = '';
$faculty_sql = "SELECT department FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_row = $faculty_result->fetch_assoc()) {
    $faculty_department = $faculty_row['department'];
}
$faculty_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>eTracker Faculty Dashboard</title>
  <link rel="stylesheet" href="Create.css" />
</head>
<body>
  <div class="container">
    <!-- Sidebar -->
      <aside class="sidebar">
      <div class="logo">
        <img src="logo.png" alt="Logo" class="logo-img" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

        <span class="logo-text">eTRACKER</span>
      </div>
      <nav>
        <ul>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
  <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>      </nav>
    </aside>


    <!-- Main Grid: Left (form), Right (notifications) -->
    <div class="main-grid">
      <!-- Left Column -->
      <div class="main-content">
        <div class="topbar">
          <div>
            <h2>Program</h2>
            <div class="last-login">Last login: <?php echo date('m-d-y H:i:s'); ?></div>
          </div>
        </div>

        <!-- Form -->
        <div class="form-container">
          <h3>Create a New Program</h3>
          <form id="program-form" class="program-form" action="create_program.php" method="POST">
            <label>Program Name</label>
            <input type="text" name="program_name" placeholder="enter a program name" required />

            <label>Department</label>
            <input type="text" name="department" value="<?php echo htmlspecialchars($faculty_department); ?>" readonly style="background:#f0f0f0;" />

            <label>Start Date</label>
            <input type="date" name="start_date" required />

            <label>End Date</label>
            <input type="date" name="end_date" required />

            <label>Location</label>
            <input type="text" name="location" placeholder="enter location" required />

            <label>Max Students</label>
            <input type="number" name="max_students" placeholder="enter maximum number of students" min="1" required />

            <label>Description</label>
            <textarea name="description" placeholder="describe the program"></textarea>

            <label>Sessions</label>
            <div id="sessions-container">
              <div class="session-row">
                <input type="date" name="session_date[]" required>
                <input type="time" name="session_start[]" required>
                <input type="time" name="session_end[]" required>
                <input type="text" name="session_title[]" placeholder="Session Title (optional)">
                <button type="button" class="remove-session" onclick="removeSession(this)">Remove</button>
              </div>
            </div>
            <button type="button" id="add-session-btn">Add Another Session</button>

            <div class="form-buttons">
              <button type="button" class="cancel" onclick="window.location.href='Programs.php'">Cancel</button>
                            <button type="submit" class="submit">create new program</button>
            </div>
          </form>
          <div id="form-message" style="display: none; margin-top: 10px;"></div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="right-panel">
        <div class="top-actions">
          <div class="user-info">
            <div class="name"><?php echo htmlspecialchars($user_fullname); ?></div>
            <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
          </div>
        </div>
        <div class="notifications">
          <h3>🔔 Notifications</h3>
          <?php
          // Fetch active notifications (add this PHP block at the top if not present)
          $notifications = [];
          $notifications_query = "SELECT message, priority FROM notifications WHERE is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) ORDER BY created_at DESC LIMIT 5";
          $notifications_result = $conn->query($notifications_query);
          if ($notifications_result) {
              while ($row = $notifications_result->fetch_assoc()) {
                  $notifications[] = $row;
              }
          }
          ?>
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
    document.getElementById('program-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const form = this;
      const formData = new FormData(form);
      const messageDiv = document.getElementById('form-message');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        messageDiv.style.display = 'block';
        messageDiv.style.color = result.status === 'success' ? 'green' : 'red';
        messageDiv.textContent = result.message;

        if (result.status === 'success') {
          form.reset();
          setTimeout(() => {
            messageDiv.style.display = 'none';
          }, 3000);
        }
      } catch (error) {
        messageDiv.style.display = 'block';
        messageDiv.style.color = 'red';
        messageDiv.textContent = 'An error occurred. Please try again.';
      }
    });

    document.querySelector('.cancel').addEventListener('click', function() {
      document.getElementById('program-form').reset();
      document.getElementById('form-message').style.display = 'none';
    });

    document.getElementById('add-session-btn').addEventListener('click', function() {
  const container = document.getElementById('sessions-container');
  const row = document.createElement('div');
  row.className = 'session-row';
  row.innerHTML = `
    <input type="date" name="session_date[]" required>
    <input type="time" name="session_start[]" required>
    <input type="time" name="session_end[]" required>
    <input type="text" name="session_title[]" placeholder="Session Title (optional)">
    <button type="button" class="remove-session" onclick="removeSession(this)">Remove</button>
  `;
  container.appendChild(row);
});

function removeSession(btn) {
  btn.parentElement.remove();
}
  </script>
</body>
</html>