<?php
session_start();
require 'db.php'; // your DB connection file

// Assuming you store user id in session after login
$user_id = $_SESSION['user_id'] ?? null;
$user = null;

if ($user_id) {
    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname);
    if ($stmt->fetch()) {
        $user = ['firstname' => $firstname, 'lastname' => $lastname];
    }
    $stmt->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>eTRACKER Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
 
  <div class="container">
    <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="eTRACKER Logo" />
            <span>eTRACKER</span>
        </div>
        <nav class="nav">
            <a href="index.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="Programs.php" class="nav-item"><i class="fas fa-list-alt"></i> Programs</a>
            <a href="Attendance.php" class="nav-item"><i class="fas fa-calendar-check"></i> Attendance</a>
            <a href="Feedback.php" class="nav-item"><i class="fas fa-comment-dots"></i> Feedback</a>
            <a href="Reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="certificates.php" class="nav-item"><i class="fas fa-certificate"></i> Certificates</a>
            <a href="Profile.php" class="nav-item"><i class="fas fa-user"></i> Profile</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span>
                    <?php
                        if ($user) {
                            echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
                        } else {
                            echo "Guest";
                        }
                    ?>
                </span>
            </div>
            <a href="/register/index.html" class="btn logout-btn">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </a>
        </div>
    </aside>
    <main class="main-content">
      <div class="animated-bg-shapes" aria-hidden="true">
        <span class="shape shape1"></span>
        <span class="shape shape2"></span>
        <span class="shape shape3"></span>
        <span class="shape shape4"></span>
      </div>
      <div class="content-header">
        <h1>CVSU IMUS - EXTENSION SERVICES</h1>
      </div>
      <p style="text-align:center; color:#4b5600; font-size:1.2rem; margin-top:10px;">
        Welcome to your Extension Service Portal. Track your participation and register for programs!
      </p>

      <?php
      // Attendance Rate
      $attendance_rate = 0;
      $total_sessions = 0;
      $attended_sessions = 0;
      if ($user_id) {
          // Get student name in the exact format used in attendance table
          $stmt = $conn->prepare("SELECT firstname, lastname, mi FROM users WHERE id = ?");
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $stmt->bind_result($fn, $ln, $mi);
          $stmt->fetch();
          $stmt->close();

          // Adjust this to match your DB format exactly!
          $student_name = $fn;
          if ($mi) $student_name .= ' ' . strtoupper(substr($mi,0,1)) . '.';
          $student_name .= ' ' . $ln;
          $student_name = trim($student_name);

          // Now use this for attendance queries
          $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE student_name = ?");
          $stmt->bind_param("s", $student_name);
          $stmt->execute();
          $stmt->bind_result($total_sessions);
          $stmt->fetch();
          $stmt->close();

          $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE student_name = ? AND status IN ('Present','Late')");
          $stmt->bind_param("s", $student_name);
          $stmt->execute();
          $stmt->bind_result($attended_sessions);
          $stmt->fetch();
          $stmt->close();

          $attendance_rate = $total_sessions > 0 ? round(($attended_sessions / $total_sessions) * 100) : 0;
      }

      // Active Programs
      $active_programs = 0;
      if ($user_id) {
          $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments e JOIN programs p ON e.program_id = p.id WHERE e.user_id = ? AND e.status = 'approved' AND p.end_date >= CURDATE()");
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $stmt->bind_result($active_programs);
          $stmt->fetch();
          $stmt->close();
      }

      // Certificates Earned
      $certificates_earned = 0;
      if ($user_id) {
          $stmt = $conn->prepare("SELECT COUNT(*) FROM participants WHERE student_name = ? AND certificate_issued = 1");
          $stmt->bind_param("s", $student_name);
          $stmt->execute();
          $stmt->bind_result($certificates_earned);
          $stmt->fetch();
          $stmt->close();
      }

      // Pending Feedback
      $pending_feedback = 0;
      if ($user_id) {
          $stmt = $conn->prepare("
              SELECT COUNT(*) 
              FROM enrollments e 
              JOIN programs p ON e.program_id = p.id 
              WHERE e.user_id = ? 
                AND e.status = 'approved' 
                AND NOT EXISTS (
                  SELECT 1 FROM detailed_evaluations de 
                  WHERE de.program_id = e.program_id AND de.student_id = ?
                )
          ");
          $stmt->bind_param("ii", $user_id, $user_id);
          $stmt->execute();
          $stmt->bind_result($pending_feedback);
          $stmt->fetch();
          $stmt->close();
      }

      // Upcoming Programs (next 3)
      $upcoming_programs = [];
      if ($user_id) {
          // Get all program IDs the student is already enrolled in
          $enrolled_ids = [];
          $stmt = $conn->prepare("SELECT program_id FROM enrollments WHERE user_id = ?");
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $stmt->bind_result($pid);
          while ($stmt->fetch()) {
              $enrolled_ids[] = $pid;
          }
          $stmt->close();

          // Build NOT IN clause
          $not_in = '';
          if (!empty($enrolled_ids)) {
              $placeholders = implode(',', array_fill(0, count($enrolled_ids), '?'));
              $not_in = "AND p.id NOT IN ($placeholders)";
          }

          // Prepare SQL
          $sql = "SELECT p.program_name, p.start_date 
                  FROM programs p
                  WHERE p.start_date >= CURDATE() $not_in
                  ORDER BY p.start_date ASC LIMIT 3";

          $stmt = $conn->prepare($sql);

          // Bind params if needed
          if (!empty($enrolled_ids)) {
              $types = str_repeat('i', count($enrolled_ids));
              $stmt->bind_param($types, ...$enrolled_ids);
          }

          $stmt->execute();
          $stmt->bind_result($pname, $pstart);
          while ($stmt->fetch()) {
              $upcoming_programs[] = ['program_name' => $pname, 'start_date' => $pstart];
          }
          $stmt->close();
      }
      ?>

      <section class="student-progress" style="margin:30px 0; display:flex; gap:30px; flex-wrap:wrap;">
        <div class="card" style="flex:1;">
          <h2 style="font-size:2.5rem; color:#aad97f;"><?php echo $attendance_rate; ?>%</h2>
          <p>Attendance Rate</p>
          <div style="background:#fff1a5; border-radius:10px; height:10px; margin-top:10px;">
            <div style="width:<?php echo $attendance_rate; ?>%; height:10px; background:#aad97f; border-radius:10px;"></div>
          </div>
        </div>
        <div class="card" style="flex:1;">
          <h2 style="font-size:2.5rem; color:#3ab6c6;"><?php echo $active_programs; ?></h2>
          <p>Active Programs</p>
        </div>
        <div class="card" style="flex:1;">
          <h2 style="font-size:2.5rem; color:#578d86;"><?php echo $certificates_earned; ?></h2>
          <p>Certificates Earned</p>
        </div>
        <div class="card" style="flex:1;">
          <h2 style="font-size:2.5rem; color:#fce373;"><?php echo $pending_feedback; ?></h2>
          <p>Pending Feedback</p>
        </div>
      </section>

      <section class="quick-actions" style="display:flex; gap:20px; justify-content:center; margin-bottom:30px;">
        <button class="btn register" style="min-width:160px;" onclick="window.location.href='Programs.php'">Register for Program</button>
        <button class="btn" style="background:#3ab6c6; color:#fff; min-width:160px;" onclick="window.location.href='Attendance.php'">View Attendance</button>
        <button class="btn" style="background:#fce373; color:#333; min-width:160px;" onclick="window.location.href='Feedback.php'">Submit Feedback</button>
        <button class="btn" style="background:#578d86; color:#fff; min-width:160px;" onclick="window.location.href='certificates.php'">Download Certificate</button>
      </section>

      <section class="upcoming-programs" style="margin-bottom:30px;">
        <h2 style="color:#4b5600; margin-bottom:15px;">Upcoming Extension Programs</h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <?php if (empty($upcoming_programs)): ?>
            <div class="card" style="min-width:220px; flex:1;">
              <h3 style="color:#005c00;">No upcoming programs</h3>
            </div>
          <?php else: ?>
            <?php foreach ($upcoming_programs as $up): ?>
              <div class="card" style="min-width:220px; flex:1;">
                <h3 style="color:#005c00;"><?php echo htmlspecialchars($up['program_name']); ?></h3>
                <p style="color:#555;">Starts: <?php echo date('F d, Y', strtotime($up['start_date'])); ?></p>
                <button class="btn register" style="margin-top:10px;" onclick="window.location.href='Programs.php'">View</button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

     
    </main>
  </div>
  

</body>
</html>
