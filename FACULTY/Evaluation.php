<?php
require_once 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../register/login.php');
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
          <li><a href="Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
          <li><a href="Attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li class="active"><a href="Evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
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
          <select id="program-select" name="program_id" onchange="window.location.href='Evaluation.php?program_id=' + this.value">
            <option value="all" <?php echo ($selected_program_id == 'all') ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
              <option value="<?php echo htmlspecialchars($program['id']); ?>" <?php echo ($selected_program_id == $program['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($program['program_name']) . ' (' . date('m/d/y', strtotime($program['start_date'])) . ')'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php 
        // Calculate program averages and quality status
        $program_stats = [];
        $program_ratings = [];
        
        foreach ($evaluations as $eval) {
          $program_name = $eval['program_name'];
          if (!isset($program_ratings[$program_name])) {
            $program_ratings[$program_name] = [];
          }
          $ratings = [$eval['content'], $eval['facilitators'], $eval['relevance'], $eval['organization'], $eval['experience']];
          $avg = array_sum($ratings) / count($ratings);
          $program_ratings[$program_name][] = $avg;
        }
        
        foreach ($program_ratings as $program => $ratings) {
          $avg = array_sum($ratings) / count($ratings);
          $status = 'excellent';
          $status_text = 'Excellent';
          $status_color = '#27ae60';
          
          if ($avg < 1.5) {
            $status = 'critical';
            $status_text = 'Critical';
            $status_color = '#e74c3c';
          } elseif ($avg < 2.5) {
            $status = 'needs-improvement';
            $status_text = 'Needs Improvement';
            $status_color = '#f39c12';
          } elseif ($avg < 4.0) {
            $status = 'good';
            $status_text = 'Good';
            $status_color = '#3498db';
          }
          
          $program_stats[$program] = [
            'average' => $avg,
            'status' => $status,
            'status_text' => $status_text,
            'status_color' => $status_color,
            'count' => count($ratings)
          ];
        }
        ?>

        <!-- Program Quality Overview -->
        <?php if (!empty($program_stats)): ?>
        <div class="quality-overview">
          <h3><i class="fas fa-chart-line"></i> Program Performance Overview</h3>
          <div class="quality-summary">
            <?php 
            $excellent = $good = $needs_improvement = $critical = 0;
            foreach ($program_stats as $stats) {
              switch ($stats['status']) {
                case 'excellent': $excellent++; break;
                case 'good': $good++; break;
                case 'needs-improvement': $needs_improvement++; break;
                case 'critical': $critical++; break;
              }
            }
            ?>
            <div class="summary-item excellent">
              <div class="count"><?php echo $excellent; ?></div>
              <div class="label">Excellent (4.0+)</div>
            </div>
            <div class="summary-item good">
              <div class="count"><?php echo $good; ?></div>
              <div class="label">Good (2.5-3.9)</div>
            </div>
            <div class="summary-item needs-improvement">
              <div class="count"><?php echo $needs_improvement; ?></div>
              <div class="label">Needs Improvement (1.5-2.4)</div>
            </div>
            <div class="summary-item critical">
              <div class="count"><?php echo $critical; ?></div>
              <div class="label">Critical (<1.5)</div>
            </div>
          </div>
        </div>

        <!-- Quality Filter Buttons -->
        <div class="quality-filters">
          <button class="filter-btn active" data-filter="all">
            <i class="fas fa-list"></i> All Programs
          </button>
          <button class="filter-btn" data-filter="excellent">
            <i class="fas fa-star"></i> Excellent
          </button>
          <button class="filter-btn" data-filter="good">
            <i class="fas fa-thumbs-up"></i> Good
          </button>
          <button class="filter-btn" data-filter="needs-improvement">
            <i class="fas fa-exclamation-triangle"></i> Needs Improvement
          </button>
          <button class="filter-btn" data-filter="critical">
            <i class="fas fa-exclamation-circle"></i> Critical
          </button>
        </div>
        <?php endif; ?>

        <!-- Stylish Evaluation Table -->
        <div class="evaluation-cards">
          <?php if (empty($evaluations)): ?>
            <div class="eval-card empty">
              <div class="empty-msg">No evaluations found.</div>
            </div>
          <?php else: ?>
            <?php foreach ($evaluations as $eval): ?>
              <?php 
              $program_name = $eval['program_name'];
              $program_info = $program_stats[$program_name] ?? null;
              $ratings = [$eval['content'], $eval['facilitators'], $eval['relevance'], $eval['organization'], $eval['experience']];
              $individual_avg = array_sum($ratings) / count($ratings);
              ?>
              <div class="eval-card" data-quality="<?php echo $program_info ? $program_info['status'] : 'unknown'; ?>">
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
                  <?php if ($program_info): ?>
                    <span class="quality-badge <?php echo $program_info['status']; ?>" 
                          style="background-color: <?php echo $program_info['status_color']; ?>;"
                          title="Program Average: <?php echo number_format($program_info['average'], 2); ?>/5.0">
                      <?php echo $program_info['status_text']; ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div class="eval-ratings">
                  <div class="eval-badge content">Content: <span><?php echo htmlspecialchars($eval['content'] ?? '-'); ?></span></div>
                  <div class="eval-badge facilitators">Facilitators: <span><?php echo htmlspecialchars($eval['facilitators'] ?? '-'); ?></span></div>
                  <div class="eval-badge relevance">Relevance: <span><?php echo htmlspecialchars($eval['relevance'] ?? '-'); ?></span></div>
                  <div class="eval-badge organization">Organization: <span><?php echo htmlspecialchars($eval['organization'] ?? '-'); ?></span></div>
                  <div class="eval-badge experience">Experience: <span><?php echo htmlspecialchars($eval['experience'] ?? '-'); ?></span></div>
                </div>
                <div class="individual-rating">
                  <strong>Individual Rating: </strong>
                  <span class="rating-value <?php echo $individual_avg >= 4 ? 'excellent' : ($individual_avg >= 2.5 ? 'good' : 'poor'); ?>">
                    <?php echo number_format($individual_avg, 2); ?>/5.0
                  </span>
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
                  <?php if ($program_info && ($program_info['status'] === 'needs-improvement' || $program_info['status'] === 'critical')): ?>
                    <button class="improvement-btn" onclick="showImprovementTips('<?php echo htmlspecialchars($program_name); ?>', <?php echo $program_info['average']; ?>)">
                      <i class="fas fa-lightbulb"></i> View Tips
                    </button>
                  <?php endif; ?>
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

    /* Quality Overview Styles */
    .quality-overview {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .quality-overview h3 {
      margin: 0 0 15px 0;
      color: #247a37;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .quality-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
    }
    .summary-item {
      background: white;
      border-radius: 8px;
      padding: 15px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      border-left: 4px solid;
    }
    .summary-item.excellent { border-left-color: #27ae60; }
    .summary-item.good { border-left-color: #3498db; }
    .summary-item.needs-improvement { border-left-color: #f39c12; }
    .summary-item.critical { border-left-color: #e74c3c; }
    .summary-item .count {
      font-size: 2em;
      font-weight: bold;
      margin-bottom: 5px;
    }
    .summary-item.excellent .count { color: #27ae60; }
    .summary-item.good .count { color: #3498db; }
    .summary-item.needs-improvement .count { color: #f39c12; }
    .summary-item.critical .count { color: #e74c3c; }
    .summary-item .label {
      font-size: 0.9em;
      color: #666;
      font-weight: 500;
    }

    /* Quality Filter Buttons */
    .quality-filters {
      display: flex;
      gap: 10px;
      margin: 20px 0;
      flex-wrap: wrap;
    }
    .filter-btn {
      background: white;
      border: 2px solid #ddd;
      border-radius: 8px;
      padding: 8px 16px;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
    }
    .filter-btn:hover {
      border-color: #247a37;
      background: #f8f9fa;
    }
    .filter-btn.active {
      background: #247a37;
      color: white;
      border-color: #247a37;
    }

    /* Quality Badges */
    .quality-badge {
      color: white;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.8em;
      font-weight: bold;
      margin-left: 8px;
    }

    /* Individual Rating */
    .individual-rating {
      margin: 8px 0;
      font-size: 0.95rem;
    }
    .rating-value {
      font-weight: bold;
      font-size: 1.1em;
    }
    .rating-value.excellent { color: #27ae60; }
    .rating-value.good { color: #3498db; }
    .rating-value.poor { color: #e74c3c; }

    /* Improvement Button */
    .improvement-btn {
      background: #f39c12;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.85em;
      margin-left: 10px;
      transition: background 0.3s;
    }
    .improvement-btn:hover {
      background: #e67e22;
    }

    /* Quality-based card styling */
    .eval-card[data-quality="critical"] {
      border-left: 4px solid #e74c3c;
      background: linear-gradient(135deg, #fdedec 80%, #fadbd8 100%);
    }
    .eval-card[data-quality="needs-improvement"] {
      border-left: 4px solid #f39c12;
      background: linear-gradient(135deg, #fef9e7 80%, #fcf3cf 100%);
    }
    .eval-card[data-quality="good"] {
      border-left: 4px solid #3498db;
      background: linear-gradient(135deg, #ebf3fd 80%, #d6eaff 100%);
    }
    .eval-card[data-quality="excellent"] {
      border-left: 4px solid #27ae60;
      background: linear-gradient(135deg, #eafaf1 80%, #d5f4e6 100%);
    }

    /* Hidden cards for filtering */
    .eval-card.hidden {
      display: none;
    }
  </style>

  <script>
    // Quality filter functionality
    document.addEventListener('DOMContentLoaded', function() {
      const filterBtns = document.querySelectorAll('.filter-btn');
      const evalCards = document.querySelectorAll('.eval-card');

      filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          const filter = this.getAttribute('data-filter');
          
          // Update active button
          filterBtns.forEach(b => b.classList.remove('active'));
          this.classList.add('active');
          
          // Filter cards
          evalCards.forEach(card => {
            if (filter === 'all') {
              card.classList.remove('hidden');
            } else {
              const cardQuality = card.getAttribute('data-quality');
              if (cardQuality === filter) {
                card.classList.remove('hidden');
              } else {
                card.classList.add('hidden');
              }
            }
          });
          
          // Update count display
          updateFilterCounts(filter);
        });
      });
    });

    function updateFilterCounts(activeFilter) {
      const evalCards = document.querySelectorAll('.eval-card:not(.empty)');
      const visibleCards = document.querySelectorAll('.eval-card:not(.hidden):not(.empty)');
      
      // You can add a count display here if needed
      console.log(`Showing ${visibleCards.length} of ${evalCards.length} evaluations`);
    }

    function showImprovementTips(programName, avgRating) {
      let modal = document.getElementById('improvementModal');
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'improvementModal';
        modal.style.position = 'fixed';
        modal.style.top = 0;
        modal.style.left = 0;
        modal.style.right = 0;
        modal.style.bottom = 0;
        modal.style.background = 'rgba(30,41,59,0.45)';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.zIndex = 1000;
        document.body.appendChild(modal);
      }
      
      const tips = generateImprovementTips(avgRating);
      
      modal.innerHTML = `
        <div style="background:#fff;max-width:600px;width:95%;border-radius:12px;padding:24px;position:relative;max-height:80vh;overflow-y:auto;">
          <button onclick="document.getElementById('improvementModal').style.display='none'" style="position:absolute;top:10px;right:16px;font-size:1.5rem;background:none;border:none;cursor:pointer;">&times;</button>
          <h2 style="margin-top:0;color:#f39c12;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-lightbulb"></i>
            Program Improvement Tips
          </h2>
          <div style="background:#f8f9fa;border-radius:8px;padding:16px;margin:16px 0;">
            <p style="margin:0;"><strong>Program:</strong> ${programName}</p>
            <p style="margin:8px 0 0 0;"><strong>Current Average:</strong> <span style="color:#f39c12;font-weight:bold;">${avgRating.toFixed(2)}/5.0</span></p>
          </div>
          <div style="background:#fff9e6;border:1px solid #ffe08a;border-radius:8px;padding:16px;">
            <h3 style="margin-top:0;color:#f39c12;">💡 Improvement Suggestions:</h3>
            <ul style="margin:0;padding-left:20px;line-height:1.6;">
              ${tips.map(tip => `<li style="margin-bottom:8px;">${tip}</li>`).join('')}
            </ul>
          </div>
          <div style="margin-top:20px;text-align:center;">
            <button onclick="document.getElementById('improvementModal').style.display='none'" style="background:#247a37;color:white;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;">Close</button>
          </div>
        </div>
      `;
      modal.style.display = 'flex';
    }

    function generateImprovementTips(avgRating) {
      const tips = [];
      
      if (avgRating < 1.5) {
        tips.push("🚨 <strong>Critical Action Required:</strong> Schedule immediate program review");
        tips.push("👥 Meet with participants to understand major concerns");
        tips.push("📚 Consider complete program restructuring");
        tips.push("🎯 Reassess program objectives and target audience");
      } else if (avgRating < 2.5) {
        tips.push("⚠️ <strong>Significant Improvements Needed:</strong>");
        tips.push("👨‍🏫 Review and enhance teaching methods");
        tips.push("📋 Incorporate more interactive activities");
        tips.push("🗣️ Improve communication and participant engagement");
        tips.push("📚 Update program content and materials");
      }
      
      // General improvement tips
      tips.push("📊 Collect feedback during program delivery for real-time adjustments");
      tips.push("🎓 Consider different learning styles in your approach");
      tips.push("📱 Explore technology tools to enhance engagement");
      tips.push("🔄 Implement follow-up sessions to reinforce learning");
      tips.push("🌟 Observe other successful programs for best practices");
      
      return tips;
    }
  </script>
</body>
</html>