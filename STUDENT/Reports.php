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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eTRACKER Reports</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="Reports.css" />
  <style>
    .summary-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 32px;
      justify-content: center;
      margin-top: 32px;
    }
    .report-type-card {
      background: linear-gradient(135deg, #fff8cc 60%, #e6f9e6 100%);
      border-radius: 20px;
      box-shadow: 0 6px 24px rgba(46,110,30,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
      padding: 32px 36px;
      min-width: 240px;
      max-width: 320px;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: transform 0.25s cubic-bezier(.4,2,.6,1), box-shadow 0.25s;
      opacity: 0;
      transform: translateY(40px) scale(0.97);
      animation: cardIn 0.7s forwards;
    }
    .report-type-card:nth-child(2) { animation-delay: 0.1s; }
    .report-type-card:nth-child(3) { animation-delay: 0.2s; }
    .report-type-card:nth-child(4) { animation-delay: 0.3s; }
    @keyframes cardIn {
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    .report-type-card:hover {
      box-shadow: 0 12px 32px rgba(46,110,30,0.16), 0 2px 8px rgba(0,0,0,0.06);
      transform: translateY(-8px) scale(1.03);
    }
    .report-type-card img {
      width: 56px;
      height: 56px;
      margin-bottom: 18px;
      filter: drop-shadow(0 2px 4px rgba(46,110,30,0.08));
    }
    .report-type-card h3 {
      color: #2e6e1e;
      font-size: 1.35rem;
      margin-bottom: 12px;
      letter-spacing: 0.5px;
    }
    .report-type-card p {
      font-size: 1.08rem;
      color: #333;
      margin: 4px 0;
      font-weight: 500;
      letter-spacing: 0.1px;
    }
    @media (max-width: 900px) {
      .summary-cards { flex-direction: column; align-items: center; }
      .report-type-card { width: 90vw; max-width: 400px; }
    }
    
    .reports-container h2 {
      color: #2e6e1e;
      font-size: 1.5rem;
      margin-bottom: 10px;
      text-align: center;
      letter-spacing: 0.5px;
    }
  </style>
</head>
<body>
  <div class="container">
       <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="eTRACKER Logo" />
            <span>eTRACKER</span>
        </div>
        <nav class="nav">
            <a href="index.php" class="nav-item "><i class="fas fa-home"></i> Dashboard</a>
            <a href="Programs.php" class="nav-item"><i class="fas fa-list-alt"></i> Programs</a>
            <a href="Attendance.php" class="nav-item"><i class="fas fa-calendar-check"></i> Attendance</a>
            <a href="Feedback.php" class="nav-item"><i class="fas fa-comment-dots"></i> Feedback</a>

            <a href="Reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Reports</a>
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
      <header class="header">
        <h1>CVSU IMUS - EXTENSION SERVICES</h1>
      </header>

      <section class="reports-container">
        <h2>Reports & Analytics</h2>
        <div class="summary-cards" id="summaryCards">
          <!-- Cards will be filled by JS -->
        </div>
      </section>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Promise.all([
        fetch('get_attendance_report.php').then(res => res.json()),
        fetch('get_participation_report.php').then(res => res.json()),
        fetch('get_feedback_report.php').then(res => res.json()),
        fetch('get_certificates.php').then(res => res.json())
      ]).then(([attendance, participation, feedback, certificates]) => {
        document.getElementById('summaryCards').innerHTML = `
          <div class="report-type-card">
            <!-- Attendance Icon (fa-calendar-check) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="#2e6e1e" viewBox="0 0 448 512" width="56" height="56">
              <path d="M152 64c0-13.3-10.7-24-24-24s-24 10.7-24 24v24H56C25.1 88 0 113.1 0 144v48h448v-48c0-30.9-25.1-56-56-56h-48V64c0-13.3-10.7-24-24-24s-24 10.7-24 24v24H152V64zM448 464c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V208h448v256zm-96-136c0-13.3-10.7-24-24-24s-24 10.7-24 24v40c0 13.3 10.7 24 24 24s24-10.7 24-24v-40z"/>
            </svg>
            <h3>Attendance</h3>
            <p><strong>Sessions Attended:</strong> ${attendance.attended ?? 0}</p>
            <p><strong>Total Sessions:</strong> ${attendance.total_sessions ?? 0}</p>
            <p><strong>Attendance Rate:</strong> ${attendance.attendance_rate ?? 0}%</p>
          </div>
          <div class="report-type-card">
            <!-- Participation Icon (fa-users) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="#2e6e1e" viewBox="0 0 640 512" width="56" height="56">
              <path d="M96 128a64 64 0 1 1 128 0 64 64 0 1 1-128 0zm224 64a80 80 0 1 0 0-160 80 80 0 1 0 0 160zm112-64a64 64 0 1 1 128 0 64 64 0 1 1-128 0zM320 304c-57.3 0-160 28.7-160 86v42c0 13.3 10.7 24 24 24h272c13.3 0 24-10.7 24-24v-42c0-57.3-102.7-86-160-86zm224 32c-35.3 0-96 17.7-96 53.3V432c0 8.8 7.2 16 16 16h160c8.8 0 16-7.2 16-16v-42.7C576 353.7 515.3 336 480 336z"/>
            </svg>
            <h3>Participation</h3>
            <p><strong>Enrolled Programs:</strong> ${participation.total ?? 0}</p>
            <p><strong>Active:</strong> ${participation.active ?? 0}</p>
            <p><strong>Completed:</strong> ${participation.completed ?? 0}</p>
            <p><strong>Pending:</strong> ${participation.pending ?? 0}</p>
          </div>
          <div class="report-type-card">
            <!-- Feedback Icon (fa-comment-dots) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="#2e6e1e" viewBox="0 0 512 512" width="56" height="56">
              <path d="M256 32C114.6 32 0 125.1 0 240c0 49.6 24.6 95.1 65.7 130.1C56.2 426.7 27.7 446.7 27.4 447c-2.1 1.5-2.9 4.2-2 6.6c.9 2.4 3.1 3.9 5.6 3.9c66.2 0 116.5-31.6 139.1-48.2c27.1 7.6 56.2 11.7 86.9 11.7c141.4 0 256-93.1 256-208S397.4 32 256 32zm-96 208c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32zm96 0c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32zm96 0c-17.7 0-32-14.3-32-32s14.3-32 32-32s32 14.3 32 32s-14.3 32-32 32z"/>
            </svg>
            <h3>Feedback</h3>
            <p><strong>Feedback Submitted:</strong> ${feedback.total ?? 0}</p>
            <p><strong>Avg. Satisfaction:</strong> ${feedback.avg_satisfaction ?? 0} / 5</p>
          </div>
          <div class="report-type-card">
            <!-- Certificate Icon (fa-certificate) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="#2e6e1e" viewBox="0 0 512 512" width="56" height="56">
              <path d="M458.1 334.1l-42.7-6.2c-7.6-1.1-14.1-6.6-16.5-14l-19.1-41.1c-2.4-5.2-7.6-8.6-13.3-8.6s-10.9 3.4-13.3 8.6l-19.1 41.1c-2.4 7.4-8.9 12.9-16.5 14l-42.7 6.2c-7.6 1.1-13.3 7.4-13.3 15.1c0 7.7 5.7 14 13.3 15.1l42.7 6.2c7.6 1.1 14.1 6.6 16.5 14l19.1 41.1c2.4 5.2 7.6 8.6 13.3 8.6s10.9-3.4 13.3-8.6l19.1-41.1c2.4-7.4 8.9-12.9 16.5-14l42.7-6.2c7.6-1.1 13.3-7.4 13.3-15.1c0-7.7-5.7-14-13.3-15.1zM256 32C114.6 32 0 125.1 0 240c0 49.6 24.6 95.1 65.7 130.1C56.2 426.7 27.7 446.7 27.4 447c-2.1 1.5-2.9 4.2-2 6.6c.9 2.4 3.1 3.9 5.6 3.9c66.2 0 116.5-31.6 139.1-48.2c27.1 7.6 56.2 11.7 86.9 11.7c141.4 0 256-93.1 256-208S397.4 32 256 32z"/>
            </svg>
            <h3>Certificates</h3>
            <p><strong>Certificates Earned:</strong> ${certificates.total ?? 0}</p>
          </div>
        `;
      });
    });
  </script>
</body>
</html>
