<?php
session_start();
require 'db.php';

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

// Check if a specific program_id is requested
$filter_program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : null;

// Fetch certificates for this student
$certificates = [];
if ($user_id && $user) {
    // Get student_name from users table
    $stmt = $conn->prepare("SELECT firstname, mi, lastname FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($fn, $mi, $ln);
    $stmt->fetch();
    $stmt->close();
    $student_name = trim($fn . ' ' . ($mi ? $mi . '. ' : '') . $ln);

    // Query the participants table for certificates
    $where_clause = "p.student_name = ? AND p.status = 'accepted'";
    $params = [$student_name];
    $param_types = "s";
    
    if ($filter_program_id) {
        $where_clause .= " AND p.program_id = ?";
        $params[] = $filter_program_id;
        $param_types .= "i";
    }
    
    $stmt = $conn->prepare("
        SELECT p.id, p.program_id, p.student_name, p.issued_on as certificate_date, 
               CASE WHEN p.certificate_issued = 1 THEN 'generated' ELSE 'pending' END as status,
               p.certificate_file, pr.program_name
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE $where_clause
        ORDER BY p.issued_on DESC
    ");
    if (!$stmt) {
        die("SQL error: " . $conn->error);
    }
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Only show if certificate is issued or if there's a file
        if ($row['status'] === 'generated' || !empty($row['certificate_file'])) {
            $certificates[] = $row;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eTRACKER Certificates</title>
  <link rel="stylesheet" href="certificates.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .cert-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
    .cert-table th, .cert-table td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; }
    .cert-table th { background: #d2eac8; color: #247a37; }
    .cert-table td { background: #fff; }
    .status-generated { color: green; font-weight: bold; }
    .status-pending { color: #f1c40f; font-weight: bold; }
    .download-btn {
      background: #59a96a;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 6px 16px;
      font-size: 1em;
      cursor: pointer;
      transition: background 0.18s;
    }
    .download-btn:hover { background: #247a37; }
    .cert-cards { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px; }
    .cert-card {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 20px;
      flex: 1 1 calc(33.333% - 40px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .cert-card-empty {
      text-align: center;
      padding: 40px;
      color: #aaa;
    }
    .cert-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    .cert-program { font-weight: bold; font-size: 1.1em; }
    .cert-status {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.9em;
    }
    .cert-status.generated { background: #d4edda; color: #155724; }
    .cert-status.pending { background: #fff3cd; color: #856404; }
    .cert-card-body { margin-bottom: 10px; }
    .cert-date { font-size: 0.9em; color: #666; }
    .cert-card-footer {
      text-align: right;
      margin-top: 10px;
    }
    .cert-btn {
      display: inline-block;
      background: #59a96a;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 4px 12px;
      font-size: 0.95em;
      margin-left: 6px;
      margin-right: 0;
      text-decoration: none;
      transition: background 0.18s;
      cursor: pointer;
    }
    .cert-btn i {
      margin-right: 4px;
    }
    .cert-btn.view-btn {
      background: #3498db;
    }
    .cert-btn.view-btn:hover {
      background: #217dbb;
    }
    .cert-btn.download-btn {
      background: #59a96a;
    }
    .cert-btn.download-btn:hover {
      background: #247a37;
    }
    .certificate-look {
      position: relative;
      background: #f9f9f9;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s;
    }
    .certificate-look:hover {
      transform: translateY(-2px);
    }
    .cert-seal {
      position: absolute;
      top: -20px;
      right: 20px;
      font-size: 2.5em;
      color: #ffd700;
    }
    .cert-content {
      padding-top: 20px;
    }
    .cert-title {
      font-size: 1.4em;
      font-weight: bold;
      margin-bottom: 10px;
    }
    .cert-name {
      font-size: 1.2em;
      margin: 10px 0;
    }
    .cert-date-row {
      font-size: 0.9em;
      color: #666;
      margin: 10px 0;
    }
    .cert-date-label {
      font-weight: bold;
    }
    .cert-status {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 4px;
      font-size: 0.9em;
      margin-top: 10px;
    }
    .cert-status.generated { background: #d4edda; color: #155724; }
    .cert-status.pending { background: #fff3cd; color: #856404; }
  </style>
</head>
<body>
  <div class="container">
    <!-- Sidebar (unchanged) -->
    <aside class="sidebar">
      <div class="logo">
        <img src="logo.png" alt="eTRACKER Logo" />
        <span>eTRACKER</span>
      </div>
      <nav class="nav">
        <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
        <a href="Programs.php" class="nav-item"><i class="fas fa-list-alt"></i> Programs</a>
        <a href="Attendance.php" class="nav-item"><i class="fas fa-calendar-check"></i> Attendance</a>
        <a href="Feedback.php" class="nav-item"><i class="fas fa-comment-dots"></i> Feedback</a>
        <a href="Reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="certificates.php" class="nav-item active"><i class="fas fa-certificate"></i> Certificates</a>
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

      <?php if ($filter_program_id): ?>
        <div style="background: #e8f5e8; padding: 15px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #59a96a;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <i class="fas fa-filter"></i>
              <strong>Showing certificates for a specific program</strong>
            </div>
            <a href="certificates.php" style="background: #59a96a; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9em;">
              <i class="fas fa-times"></i> Show All Certificates
            </a>
          </div>
        </div>
      <?php endif; ?>

      <section>
        <div class="cert-cards">
          <?php if (empty($certificates)): ?>
            <div class="cert-card cert-card-empty">
              <i class="fas fa-certificate"></i>
              <div>
                <h3>No certificates available yet.</h3>
                <p>Complete extension programs to earn certificates!</p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($certificates as $cert): ?>
              <div class="cert-card certificate-look">
                <div class="cert-seal">
                  <i class="fas fa-award"></i>
                </div>
                <div class="cert-content">
                  <div class="cert-title">Certificate of Completion</div>
                  <div class="cert-program"><?php echo htmlspecialchars($cert['program_name']); ?></div>
                  <div class="cert-name">Awarded to<br><span><?php echo htmlspecialchars($cert['student_name']); ?></span></div>
                  <div class="cert-date-row">
                    <span class="cert-date-label"><i class="fas fa-calendar-alt"></i> Date:</span>
                    <span class="cert-date-value">
                      <?php echo htmlspecialchars($cert['certificate_date'] ? date('M d, Y', strtotime($cert['certificate_date'])) : '-'); ?>
                    </span>
                  </div>
                  <div class="cert-status status-<?php echo strtolower(htmlspecialchars($cert['status'])); ?>">
                    <?php echo htmlspecialchars(ucfirst($cert['status'])); ?>
                  </div>
                  <div class="cert-card-footer">
                    <?php if ($cert['status'] === 'generated' && !empty($cert['certificate_file'])): ?>
                      <a href="/<?php echo htmlspecialchars($cert['certificate_file']); ?>" class="cert-btn view-btn" target="_blank">
                        <i class="fas fa-eye"></i> View
                      </a>
                      <a href="/<?php echo htmlspecialchars($cert['certificate_file']); ?>" class="cert-btn download-btn" download>
                        <i class="fas fa-download"></i> Download
                      </a>
                    <?php elseif ($cert['status'] === 'pending'): ?>
                      <span class="not-available" style="color: #856404; font-style: italic;">
                        <i class="fas fa-clock"></i> Certificate being processed
                      </span>
                    <?php else: ?>
                      <span class="not-available" style="color: #6c757d; font-style: italic;">
                        <i class="fas fa-info-circle"></i> Not available
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
