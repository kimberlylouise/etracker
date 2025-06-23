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

// QR code generation function
function generateCode($length = 4) {
    return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>eTRACKER Attendance</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="Attendance.css" />
  <style>
/* Simple modal styles */
.manual-modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0; top: 0; width: 100vw; height: 100vh;
  background: rgba(0,0,0,0.3);
}
.manual-modal-content {
  background: #fff;
  margin: 10vh auto;
  padding: 30px 24px;
  border-radius: 12px;
  width: 90%;
  max-width: 400px;
  position: relative;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
}
.close-modal {
  position: absolute;
  right: 18px;
  top: 12px;
  font-size: 1.6em;
  cursor: pointer;
  color: #888;
}
.manual-submit-btn {
  background: #2e6e1e;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 22px;
  font-size: 1em;
  cursor: pointer;
  margin-top: 10px;
}

#qr-manual-entry {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 10px;
  margin-top: 18px;
}

#qr-manual-entry label {
  font-weight: 600;
  color: #2e6e1e;
  margin-bottom: 4px;
}

#qr-code-input {
  padding: 10px;
  border-radius: 8px;
  border: 1.5px solid #b6b6b6;
  font-size: 1em;
  outline: none;
  transition: border 0.2s;
}

#qr-code-input:focus {
  border: 1.5px solid #2e6e1e;
}

#submit-qr-code {
  background: #218c21;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 22px;
  font-size: 1em;
  cursor: pointer;
  margin-top: 6px;
  transition: background 0.2s;
}

#submit-qr-code:hover {
  background: #176b16;
}

#qr-code-message {
  margin-top: 6px;
  min-height: 18px;
  text-align: center;
  font-weight: 500;
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
            <a href="Attendance.php" class="nav-item active"><i class="fas fa-calendar-check"></i> Attendance</a>
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
      <header class="header">
        <h1>CVSU IMUS - EXTENSION SERVICES</h1>
      </header>

      <section class="content">
        <h2>Attendance Tracking</h2>
        <p>Monitor and manage attendance for all registered extension programs and services.</p>

        <div class="attendance-section">
          <div class="qr-box">
            <h3>Scan QR Code to Mark Attendance</h3>
            <button class="qr-btn" id="open-qr-modal">Show QR Code</button>
          </div>

          <div class="manual-box">
            <h3>Manual Attendance Entry</h3>
            <button class="manual-btn" id="open-manual-modal">Mark Attendance Manually</button>
          </div>
        </div>

        <div class="records-section">
          <h3>My Attendance Records</h3>
          <!-- Removed the table, now only timeline is shown -->
          <div class="attendance-timeline">
            <!-- JS will inject timeline items here -->
          </div>
          <div class="no-records-message" style="display:none; text-align:center; color:#888; padding:20px;">
            No attendance records found.
          </div>
        </div>

        <div class="summary-box">
          <h3>Attendance Summary</h3>
          <p><strong>Total Sessions:</strong> <span id="total-sessions">0</span></p>
          <p><strong>Sessions Attended:</strong> <span id="sessions-attended">0</span></p>
          <p><strong>Attendance Rate:</strong> <span id="attendance-rate">0%</span></p>
        </div>
      </section>
    </main>
  </div>

  <!-- Manual Attendance Modal -->
  <div id="manual-modal" class="manual-modal" style="display:none;">
    <div class="manual-modal-content">
      <span class="close-modal" id="close-manual-modal">&times;</span>
      <h3 style="margin-bottom:18px; color:#2e6e1e;">Manual Attendance Entry</h3>
      <form id="manual-attendance-form" style="display:flex; flex-direction:column; gap:18px;">
        <div>
          <label for="program-select" style="font-weight:600; color:#2e6e1e;">Select Program:</label>
          <select id="program-select" name="program_id" required style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #b6b6b6; margin-top:6px;">
            <option value="">Loading...</option>
          </select>
        </div>
        <div>
          <label for="manual-time-in" style="font-weight:600; color:#2e6e1e;">Time In:</label>
          <input type="time" id="manual-time-in" name="time_in" required style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #b6b6b6; margin-top:6px;">
        </div>
        <button type="submit" class="manual-submit-btn" style="margin-top:10px;">Submit Attendance</button>
      </form>
      <div id="manual-attendance-message" style="margin-top:14px; text-align:center;"></div>
    </div>
  </div>

  <!-- QR Code Modal -->
  <div id="qr-modal" class="manual-modal" style="display:none;">
    <div class="manual-modal-content">
      <span class="close-modal" id="close-qr-modal">&times;</span>
      <h3 style="margin-bottom:18px; color:#2e6e1e;">Select Program to Get QR</h3>
      <form id="qr-program-form" style="display:flex; flex-direction:column; gap:18px;">
        <div>
          <label for="qr-program-select" style="font-weight:600; color:#2e6e1e;">Select Program:</label>
          <select id="qr-program-select" required style="width:100%; padding:10px; border-radius:8px; border:1.5px solid #b6b6b6; margin-top:6px;">
            <option value="">Loading...</option>
          </select>
        </div>
        <button type="submit" class="manual-submit-btn" style="margin-top:10px;">Show QR</button>
      </form>
      <div id="qr-image-container" style="display:none; flex-direction:column; align-items:center; margin-top:18px;">
        <img id="qr-image" src="" alt="QR Code" style="width:160px; border-radius:12px; box-shadow:0 2px 8px rgba(46,110,30,0.08); background:#fff; padding:10px;">
        <p style="margin-top:10px; color:#2e6e1e;" id="qr-program-label"></p>
      </div>
      <div id="qr-manual-entry" style="margin-top:18px;">
        <label for="qr-code-input">Enter QR Code:</label>
        <input type="text" id="qr-code-input" placeholder="Paste scanned code here">
        <button id="submit-qr-code">Mark Attendance</button>
        <div id="qr-code-message"></div>
      </div>
    </div>
  </div>

  <button id="scan-qr-btn">Scan QR Code</button>
  <div id="qr-reader" style="width:300px"></div>
</body>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
document.addEventListener('DOMContentLoaded', function() {
  // Attendance records and summary (existing code)
  fetch('get_attendance.php')
    .then(res => res.json())
    .then(data => {
      const noRecords = document.querySelector('.no-records-message');
      const totalSessionsEl = document.getElementById('total-sessions');
      const sessionsAttendedEl = document.getElementById('sessions-attended');
      const attendanceRateEl = document.getElementById('attendance-rate');

      // Attendance timeline
      const timeline = document.querySelector('.attendance-timeline');
      timeline.innerHTML = '';
      let attended = 0;
      if (data.status === 'success' && data.records.length > 0) {
        noRecords.style.display = 'none';
        data.records.forEach(rec => {
          if (rec.status === 'Present' || rec.status === 'Late') attended++;
          timeline.innerHTML += `
            <div class="timeline-item">
              <div class="timeline-dot status-${rec.status.toLowerCase()}"></div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <span class="timeline-program">${rec.program_name}</span>
                  <span class="timeline-status status-${rec.status.toLowerCase()}">${rec.status}</span>
                </div>
                <div class="timeline-details">
                  <span><i class="fa fa-calendar"></i> ${rec.date}</span>
                  <span><i class="fa fa-clock"></i> ${rec.time_in ? rec.time_in : '-'}</span>
                </div>
              </div>
            </div>
          `;
        });
        // Attendance summary
        totalSessionsEl.textContent = data.records.length;
        sessionsAttendedEl.textContent = attended;
        attendanceRateEl.textContent = data.records.length > 0 ? Math.round((attended / data.records.length) * 100) + '%' : '0%';
      } else {
        timeline.innerHTML = '';
        noRecords.style.display = 'block';
        totalSessionsEl.textContent = '0';
        sessionsAttendedEl.textContent = '0';
        attendanceRateEl.textContent = '0%';
      }
    })
    .catch(() => {
      const noRecords = document.querySelector('.no-records-message');
      const timeline = document.querySelector('.attendance-timeline');
      const totalSessionsEl = document.getElementById('total-sessions');
      const sessionsAttendedEl = document.getElementById('sessions-attended');
      const attendanceRateEl = document.getElementById('attendance-rate');
      timeline.innerHTML = '';
      noRecords.style.display = 'block';
      totalSessionsEl.textContent = '0';
      sessionsAttendedEl.textContent = '0';
      attendanceRateEl.textContent = '0%';
    });

  // Manual Attendance Modal logic
  const modal = document.getElementById('manual-modal');
  const openBtn = document.getElementById('open-manual-modal');
  const closeBtn = document.getElementById('close-manual-modal');
  const programSelect = document.getElementById('program-select');
  const manualForm = document.getElementById('manual-attendance-form');
  const manualMsg = document.getElementById('manual-attendance-message');
  const timeInInput = document.getElementById('manual-time-in');

  openBtn.onclick = function() {
    modal.style.display = 'block';
    manualMsg.textContent = '';
    // Set default time-in to current time
    const now = new Date();
    timeInInput.value = now.toTimeString().slice(0,5);
    // Fetch approved programs for the user
    fetch('get_my_programs.php')
      .then(res => res.json())
      .then(data => {
        programSelect.innerHTML = '';
        if (data.status === 'success' && data.programs.length > 0) {
          let hasApproved = false;
          data.programs.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; // Use program_id for reliability
            opt.textContent = p.program_name;
            programSelect.appendChild(opt);
          });
        } else {
          programSelect.innerHTML = '<option value="">No approved programs</option>';
        }
      });
  };
  closeBtn.onclick = function() {
    modal.style.display = 'none';
  };
  window.onclick = function(event) {
    if (event.target === modal) modal.style.display = 'none';
  };

  manualForm.onsubmit = function(e) {
    e.preventDefault();
    manualMsg.textContent = 'Submitting...';
    manualMsg.style.color = '#2e6e1e';
    // When submitting manual attendance:
    fetch('mark_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        program_id: programSelect.value, // <-- use program_id, not name
        time_in: timeInInput.value
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'success') {
        manualMsg.style.color = 'green';
        manualMsg.textContent = 'Attendance marked successfully!';
        setTimeout(() => { modal.style.display = 'none'; location.reload(); }, 1000);
      } else {
        manualMsg.style.color = 'red';
        manualMsg.textContent = data.message || 'Failed to mark attendance.';
      }
    })
    .catch(() => {
      manualMsg.style.color = 'red';
      manualMsg.textContent = 'Failed to mark attendance.';
    });
  };

  // QR Modal logic
  const qrModal = document.getElementById('qr-modal');
  const openQrBtn = document.getElementById('open-qr-modal');
  const closeQrBtn = document.getElementById('close-qr-modal');
  const qrProgramSelect = document.getElementById('qr-program-select');
  const qrProgramForm = document.getElementById('qr-program-form');
  const qrImageContainer = document.getElementById('qr-image-container');
  const qrImage = document.getElementById('qr-image');
  const qrProgramLabel = document.getElementById('qr-program-label');

  openQrBtn.onclick = function() {
        qrModal.style.display = 'block';
        qrImageContainer.style.display = 'none';
        // Fetch approved programs for the user
        fetch('get_my_programs.php')
          .then(res => res.json())
          .then(data => {
            qrProgramSelect.innerHTML = '';
            if (data.status === 'success' && data.programs.length > 0) {
              let hasApproved = false;
              data.programs.forEach(p => {
                if (p.status === 'approved') {
                  hasApproved = true;
                  const opt = document.createElement('option');
                  opt.value = p.id; // Use program_id for QR value
                  opt.textContent = p.program_name;
                  opt.setAttribute('data-name', p.program_name);
                  qrProgramSelect.appendChild(opt);
                }
              });
              if (!hasApproved) {
                qrProgramSelect.innerHTML = '<option value="">No approved programs</option>';
              }
            } else {
              qrProgramSelect.innerHTML = '<option value="">No approved programs</option>';
            }
          });
      };

      // Show QR after selecting program
      qrProgramForm.onsubmit = function(e) {
        e.preventDefault();
        const programId = qrProgramSelect.value;
        const programName = qrProgramSelect.options[qrProgramSelect.selectedIndex].textContent;
        if (!programId) return;
        // Request a new code from the backend
        fetch('generate_qr_code.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'program_id=' + encodeURIComponent(programId)
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            const qrData = data.code;
            qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrData)}`;
            qrProgramLabel.textContent = programName;
            qrImageContainer.style.display = 'flex';
          } else {
            qrProgramLabel.textContent = data.message || "Failed to generate QR code.";
            qrImageContainer.style.display = 'flex';
          }
        });
      };

  // END of openQrBtn.onclick

  document.getElementById('scan-qr-btn').onclick = function() {
    document.getElementById('qr-reader').style.display = 'block';
    const qrReader = new Html5Qrcode("qr-reader");
    qrReader.start(
      { facingMode: "environment" }, // Use rear camera if available
      { fps: 10, qrbox: 250 },
      qrCodeMessage => {
        // Send scanned data to your backend to mark attendance
        fetch('mark_attendance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ qr_data: qrCodeMessage })
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message || "Attendance marked!");
          qrReader.stop();
          document.getElementById('qr-reader').style.display = 'none';
        })
        .catch(() => {
          alert("Failed to mark attendance.");
          qrReader.stop();
          document.getElementById('qr-reader').style.display = 'none';
        });
      },
      errorMessage => {
        // Optionally handle scan errors
      }
    ).catch(err => {
      // Handle camera start errors
      alert("Unable to start QR scanner: " + err);
      document.getElementById('qr-reader').style.display = 'none';
    });
  }; // <-- Make sure this closes the onclick function

// QR Code manual entry
document.getElementById('submit-qr-code').onclick = function() {
  const code = document.getElementById('qr-code-input').value.trim();
  const qrModal = document.getElementById('qr-modal');
  const qrCodeMessage = document.getElementById('qr-code-message');
  if (!code) {
    qrCodeMessage.innerText = "Please enter the code.";
    return;
  }
  fetch('mark_attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ qr_data: code })
  })
  .then(res => res.json())
  .then(data => {
    qrCodeMessage.innerText = data.message;
    if (data.status === 'success') {
      setTimeout(() => {
        qrModal.style.display = 'none';
        location.reload();
      }, 1000);
    }
  })
  .catch(() => {
    qrCodeMessage.innerText = "Error marking attendance.";
  });
};

}); // <-- Add this to close the DOMContentLoaded event listener

// QR Code manual entry
document.getElementById('submit-qr-code').onclick = function() {
  const code = document.getElementById('qr-code-input').value.trim();
  const qrModal = document.getElementById('qr-modal');
  const qrCodeMessage = document.getElementById('qr-code-message');
  if (!code) {
    qrCodeMessage.innerText = "Please enter the code.";
    return;
  }
  fetch('mark_attendance.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ qr_data: code })
  })
  .then(res => res.json())
  .then(data => {
    qrCodeMessage.innerText = data.message;
    if (data.status === 'success') {
      setTimeout(() => {
        qrModal.style.display = 'none';
        location.reload();
      }, 1000);
    }
  })
  .catch(() => {
    qrCodeMessage.innerText = "Error marking attendance.";
  });
};

</script>
</body>
</html>