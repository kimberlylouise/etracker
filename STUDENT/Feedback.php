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
  <title>eTRACKER Feedback</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="Feedback.css" />
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
            <a href="index.php" class="nav-item "><i class="fas fa-home"></i> Dashboard</a>
            <a href="Programs.php" class="nav-item"><i class="fas fa-list-alt"></i> Programs</a>
            <a href="Attendance.php" class="nav-item"><i class="fas fa-calendar-check"></i> Attendance</a>
            <a href="Feedback.php" class="nav-item active"><i class="fas fa-comment-dots"></i> Feedback</a>
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
      <header class="header">
        <h1>CVSU IMUS - EXTENSION SERVICES</h1>
      </header>

      <!-- Evaluation Overview -->
      <section class="eval-overview">
        <div class="overview-box">
          <span>Total Evaluations: <span id="total-evals">0</span></span>
          <a href="#" id="view-all-evals">view all evaluation</a>
        </div>
      </section>

      <!-- Program Specific Evaluation Table -->
      <section class="program-eval-section">
        <h2>Program Specific-Evaluation</h2>
        <div class="table-wrapper">
          <table class="eval-table">
            <thead>
              <tr>
                <th>Programs</th>
                <th>Status</th>
                <th>Submitted Date</th>
                <th>Ratings</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="eval-table-body">
              <!-- Rows will be injected by JS -->
            </tbody>
          </table>
        </div>
      </section>

      <!-- All Evaluations Table (initially hidden) -->
      <section class="all-evals-section" style="display:none;">
        <h2>All Evaluations</h2>
        <div class="table-wrapper">
          <table class="eval-table">
            <thead>
              <tr>
                <th>Program</th>
                <th>Date Evaluated</th>
                <th>Content</th>
                <th>Facilitators</th>
                <th>Relevance</th>
                <th>Organization</th>
                <th>Experience</th>
                <th>Suggestion</th>
                <th>Recommend</th>
              </tr>
            </thead>
            <tbody id="all-evals-table-body">
              <!-- Rows will be injected by JS -->
            </tbody>
          </table>
        </div>
      </section>

      <!-- Evaluation Modal (hidden by default) -->
      <div id="detailed-eval-modal" class="eval-modal" style="display:none;">
        <div class="eval-modal-content" style="max-width:600px;">
          <span class="close-modal" id="close-detailed-eval-modal">&times;</span>
          <h2>Evaluation Form: <span id="modal-program-title">Program Name</span></h2>
          <div class="eval-instructions">
            <b>Instructions:</b> Please complete the evaluation by rating each aspect of the program on a scale of 1 to 5 (1 = Poor, 5 = Excellent) and provide any additional comments.
          </div>
          <form id="detailed-eval-form" style="background:#fff3c4; padding:24px 32px; border-radius:16px; border:2px solid #b48cff; margin-top:0;">
            <input type="hidden" id="detailed-program-id" name="program_id" />
            <div class="eval-group">
              <label><b>Quality of Content</b></label>
              <div class="eval-radio-group">
                
                <label><input type="radio" name="content" value="1" required>1</label>
                <label><input type="radio" name="content" value="2">2</label>
                <label><input type="radio" name="content" value="3">3</label>
                <label><input type="radio" name="content" value="4">4</label>
                <label><input type="radio" name="content" value="5">5</label>
              </div>
            </div>
            <div class="eval-group">
              <label><b>Effectiveness of Facilitators</b></label>
              <div class="eval-radio-group">
                <label><input type="radio" name="facilitators" value="1" required>1</label>
                <label><input type="radio" name="facilitators" value="2">2</label>
                <label><input type="radio" name="facilitators" value="3">3</label>
                <label><input type="radio" name="facilitators" value="4">4</label>
                <label><input type="radio" name="facilitators" value="5">5</label>
              </div>
            </div>
            <div class="eval-group">
              <label><b>Relevance to Community Service Goals</b></label>
              <div class="eval-radio-group">
                <label><input type="radio" name="relevance" value="1" required>1</label>
                <label><input type="radio" name="relevance" value="2">2</label>
                <label><input type="radio" name="relevance" value="3">3</label>
                <label><input type="radio" name="relevance" value="4">4</label>
                <label><input type="radio" name="relevance" value="5">5</label>
              </div>
            </div>
            <div class="eval-group">
              <label><b>Organization and Schedule</b></label>
              <div class="eval-radio-group">
                <label><input type="radio" name="organization" value="1" required>1</label>
                <label><input type="radio" name="organization" value="2">2</label>
                <label><input type="radio" name="organization" value="3">3</label>
                <label><input type="radio" name="organization" value="4">4</label>
                <label><input type="radio" name="organization" value="5">5</label>
              </div>
            </div>
            <div class="eval-group">
              <label><b>Overall Experience</b></label>
              <div class="eval-radio-group">
                <label><input type="radio" name="experience" value="1" required>1</label>
                <label><input type="radio" name="experience" value="2">2</label>
                <label><input type="radio" name="experience" value="3">3</label>
                <label><input type="radio" name="experience" value="4">4</label>
                <label><input type="radio" name="experience" value="5">5</label>
              </div>
            </div>
            <div class="eval-group" style="margin-top:18px;">
              <b>ADDITIONAL FEEDBACK</b>
              <div style="margin-top:8px;">
                <label>Suggestion for the improvement</label>
                <input type="text" name="suggestion" style="width:60%;padding:6px;border-radius:4px;border:1px solid #ccc;background:#fffbe6;">
              </div>
              <div style="margin-top:12px;">
                <label>Would you Recommend this Program?</label>
                <input type="radio" name="recommend" value="yes" required> yes
                <input type="radio" name="recommend" value="no"> no
              </div>
            </div>
            <button type="submit" style="margin-top:22px;padding:10px 40px;font-size:1.1em;background:#fffbe6;border:1.5px solid #b48cff;border-radius:6px;">SUBMIT</button>
            <div id="detailed-eval-message" style="margin-top:12px;text-align:center;"></div>
          </form>
        </div>
      </div>

      <!-- All Evaluations Modal (hidden by default) -->
      <div id="all-evals-modal" class="eval-modal" style="display:none;">
        <div class="eval-modal-content">
          <span class="close-modal" id="close-all-evals-modal">&times;</span>
          <h2>All Evaluations</h2>
          <div class="table-wrapper">
            <table class="eval-table">
              <thead>
                <tr>
                  <th>Program</th>
                  <th>Date Evaluated</th>
                  <th>Content</th>
                  <th>Facilitators</th>
                  <th>Relevance</th>
                  <th>Organization</th>
                  <th>Experience</th>
                  <th>Suggestion</th>
                  <th>Recommend</th>
                </tr>
              </thead>
              <tbody id="all-evals-table-body">
                <!-- Rows will be injected by JS -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="feedback.js"></script>
</body>
</html>
