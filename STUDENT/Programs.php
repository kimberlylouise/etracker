<?php
session_start();
$_SESSION['role'] = 'student'; // Force student role for testing
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
  <title>eTRACKER Programs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <link rel="stylesheet" href="Programs.css">
</head>
<body>
  <div class="container">
        <aside class="sidebar">
        <div class="logo">
            <img src="logo.png" alt="eTRACKER Logo" />
            <span>eTRACKER</span>
        </div>
        <nav class="nav">
            <a href="index.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="Programs.php" class="nav-item active"><i class="fas fa-list-alt"></i> Programs</a>
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
      <header class="header">
        <h1>CVSU IMUS - EXTENSION SERVICES</h1>
      </header>
      <div class="programs">
        <div class="tabs">
          <button class="tab active" data-tab="directory">Program Directory</button>
          <button class="tab" data-tab="my-programs">My Programs</button>
        </div>

        <div class="tab-content">
          <!-- PROGRAM DIRECTORY: Card-based, expandable details -->
          <section class="tab-pane active" id="directory">
            <h2>Program Directory</h2>
            <input type="text" placeholder="Search programs..." class="search-bar" id="search-programs" />
            <div class="loading" id="directory-loading">
              <span class="spinner"></span> Loading Programs...
            </div>
            <div class="program-card-list" id="program-directory-cards"></div>
            <div class="pagination" id="directory-pagination"></div>
          </section>

          <!-- MY PROGRAMS: Show enrolled, status, attendance, feedback -->
          <section class="tab-pane" id="my-programs">
            <h2>My Programs</h2>
            
            <!-- Mini Tabs for My Programs -->
            <div class="my-programs-mini-tabs">
              <button class="mini-tab active" data-filter="active">
                <i class="fas fa-play-circle"></i> Active <span class="tab-count" id="active-count">0</span>
              </button>
              <button class="mini-tab" data-filter="pending">
                <i class="fas fa-clock"></i> Pending <span class="tab-count" id="pending-count">0</span>
              </button>
              <button class="mini-tab" data-filter="completed">
                <i class="fas fa-check-circle"></i> Completed <span class="tab-count" id="completed-count">0</span>
              </button>
            </div>
            
            <div class="loading" id="my-programs-loading">
              <span class="spinner"></span> Loading Programs...
            </div>
            <p id="no-programs-message" style="display: none;">No programs found in this category</p>
            <div class="my-programs-list" id="my-programs-list"></div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
  // --- PROGRAM DIRECTORY: Card-based, expandable details ---
  let directoryPrograms = [];
  let directoryCurrentPage = 1;
  const directoryPageSize = 8;
  let enrolledPrograms = {}; // program_id: status

  function fetchEnrolledPrograms() {
    return fetch('get_my_enrollments.php')
      .then(res => res.json())
      .then((data) => {
        if (data.status === 'success') {
          enrolledPrograms = data.enrolled;
        }
      });
  }

  function renderDirectoryCards(programs, page = 1) {
    const cardList = document.getElementById('program-directory-cards');
    cardList.innerHTML = '';
    const start = (page - 1) * directoryPageSize;
    const end = start + directoryPageSize;
    // Only show active programs (not ended)
    const activePrograms = programs.filter(program =>
      program.status === 'ongoing'
    );
    const pagePrograms = activePrograms.slice(start, end);

    if (pagePrograms.length === 0) {
      cardList.innerHTML = '<div style="width:100%">No programs available</div>';
      document.getElementById('directory-pagination').innerHTML = '';
      return;
    }

    pagePrograms.forEach(program => {
      let enrollBtnHtml = '';
      if (enrolledPrograms[program.id] === 'approved') {
        enrollBtnHtml = `<button class="enroll-btn enrolled" disabled>✓ Enrolled</button>`;
      } else if (enrolledPrograms[program.id] === 'pending') {
        enrollBtnHtml = `<button class="enroll-btn pending" disabled>⏳ Pending Approval</button>`;
      } else {
        enrollBtnHtml = `<button class="enroll-btn" data-id="${program.id}">Submit Enrollment</button>`;
      }

      const status = 'active';
      const card = document.createElement('div');
      card.className = 'program-card';
      card.innerHTML = `
        <div class="card-title">${program.program_name}</div>
        <div class="card-meta">
          <b>Status:</b> ${program.status.charAt(0).toUpperCase() + program.status.slice(1)}<br>
          <b>Department:</b> ${program.department}<br>
          <b>Schedule:</b> ${program.start_date} to ${program.end_date}<br>
          <b>Location:</b> ${program.location || 'TBA'}<br>
<b>Faculty:</b> ${program.faculty_name}
        </div>
        <div class="card-actions">
          ${enrollBtnHtml}
          <span class="expand-details">View Details</span>
        </div>
        <div class="card-details">
          <b>Description:</b><br>
          <span>${program.description || 'No description.'}</span><br>
          <b>Capacity:</b> ${program.max_students} students<br>
          <b>Sessions:</b>
          <ul class="session-list">${(program.sessions || []).map(s =>
            `<li>${s.session_title} - ${s.session_date} (${s.session_start} to ${s.session_end}) @ ${s.location}</li>`
          ).join('') || '<li>No sessions listed.</li>'}</ul>
        </div>
      `;
      // Expand details handler
      card.querySelector('.expand-details').onclick = () => {
        card.classList.toggle('expanded');
        card.querySelector('.expand-details').textContent =
          card.classList.contains('expanded') ? 'Hide Details' : 'View Details';
      };
      // Enroll button handler (only if not enrolled)
      const enrollBtn = card.querySelector('.enroll-btn[data-id]');
      if (enrollBtn) {
        enrollBtn.onclick = () => {
          // Show modal as before...
          const modal = document.getElementById('enroll-modal');
          const details = document.getElementById('modal-program-details');
          details.innerHTML = `
            <h2>${program.program_name}</h2>
            <p><b>Department:</b> ${program.department}</p>
            <p><b>Schedule:</b> ${program.start_date} to ${program.end_date}</p>
            <p><b>Location:</b> ${program.location || 'TBA'}</p>
            <p><b>Faculty:</b> ${program.faculty_name || 'N/A'}</p>
            <p><b>Description:</b><br>${program.description || 'No description.'}</p>
            <b>Sessions:</b>
            <ul class="session-list">${(program.sessions || []).map(s =>
              `<li>${s.session_title} - ${s.session_date} (${s.session_start} to ${s.session_end}) @ ${s.location}</li>`
            ).join('') || '<li>No sessions listed.</li>'}</ul>
            <div style="margin-top:1em; color:#d9534f;"><b>Are you sure you want to submit your enrollment for this program?</b></div>
            <div id="modal-enroll-message" style="margin-top:0.5em; color:#28a745;"></div>
          `;
          modal.style.display = 'flex';

          // Confirm button
          document.getElementById('confirm-enroll-btn').onclick = () => {
            const message = document.getElementById('modal-enroll-message');
            const confirmBtn = document.getElementById('confirm-enroll-btn');
            const cancelBtn = document.getElementById('cancel-enroll-btn');
            
            // Disable buttons and show loading state
            confirmBtn.disabled = true;
            cancelBtn.disabled = true;
            confirmBtn.textContent = 'Submitting...';
            message.textContent = 'Processing your enrollment request...';
            message.style.color = '#007bff';
            
            fetch('/Student/enroll.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ program_id: program.id })
            })
              .then(response => response.json())
              .then(data => {
                if (data.status === 'success') {
                  message.textContent = data.message;
                  message.style.color = '#28a745';
                  // Update enrolledPrograms and re-render cards
                  enrolledPrograms[program.id] = 'pending';
                  setTimeout(() => {
                    modal.style.display = 'none';
                    renderDirectoryCards(directoryPrograms, directoryCurrentPage);
                    // Reset button states for next use
                    confirmBtn.disabled = false;
                    cancelBtn.disabled = false;
                    confirmBtn.textContent = 'Submit';
                  }, 2000);
                } else {
                  message.textContent = data.message;
                  message.style.color = '#dc3545';
                  // Re-enable buttons
                  confirmBtn.disabled = false;
                  cancelBtn.disabled = false;
                  confirmBtn.textContent = 'Submit';
                }
              })
              .catch(error => {
                message.textContent = 'Network error. Please check your connection and try again.';
                message.style.color = '#dc3545';
                // Re-enable buttons
                confirmBtn.disabled = false;
                cancelBtn.disabled = false;
                confirmBtn.textContent = 'Submit';
                console.error('Network error:', error);
              });
          };
          // Cancel/close with state reset
          const closeModal = () => {
            modal.style.display = 'none';
            // Reset button states
            const confirmBtn = document.getElementById('confirm-enroll-btn');
            const cancelBtn = document.getElementById('cancel-enroll-btn');
            const message = document.getElementById('modal-enroll-message');
            
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            confirmBtn.textContent = 'Submit';
            message.textContent = '';
          };
          
          document.getElementById('cancel-enroll-btn').onclick = closeModal;
          document.querySelector('.close-modal').onclick = closeModal;
          
          // Close modal when clicking outside
          modal.onclick = (e) => {
            if (e.target === modal) {
              closeModal();
            }
          };
        };
      }
      cardList.appendChild(card);
    });

    renderDirectoryPagination(activePrograms.length, page);
  }

  function renderDirectoryPagination(total, currentPage) {
    const pageCount = Math.ceil(total / directoryPageSize);
    const pagination = document.getElementById('directory-pagination');
    if (pageCount <= 1) {
      pagination.innerHTML = '';
      return;
    }
    let html = '';
    for (let i = 1; i <= pageCount; i++) {
      html += `<button class="page-btn${i === currentPage ? ' active' : ''}" data-page="${i}">${i}</button>`;
    }
    pagination.innerHTML = html;
    pagination.querySelectorAll('.page-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        directoryCurrentPage = parseInt(btn.dataset.page);
        renderDirectoryCards(directoryPrograms, directoryCurrentPage);
      });
    });
  }

  // Fetch programs with sessions and faculty info
  function loadProgramDirectory() {
    const cardList = document.getElementById('program-directory-cards');
    const loading = document.getElementById('directory-loading');
    const pagination = document.getElementById('directory-pagination');
    loading.style.display = 'flex';
    cardList.innerHTML = '';
    pagination.innerHTML = '';
    directoryPrograms = [];
    directoryCurrentPage = 1;

    // Fetch enrollments first, then programs
    fetchEnrolledPrograms().then(() => {
      fetch('get_all_programs.php')
        .then(response => response.json())
        .then((data) => {
          loading.style.display = 'none';
          if (data.status === 'success' && data.programs.length > 0) {
            directoryPrograms = data.programs;
            renderDirectoryCards(directoryPrograms, directoryCurrentPage);
          } else {
            cardList.innerHTML = '<div>No programs available</div>';
          }
        })
        .catch(error => {
          loading.style.display = 'none';
          cardList.innerHTML = '<div>Error loading programs</div>';
          console.error('Error:', error);
        });
    });

    // Search
    const searchInput = document.getElementById('search-programs');
    searchInput.oninput = () => {
      const filter = searchInput.value.toLowerCase();
      const filtered = directoryPrograms.filter(p =>
        p.program_name.toLowerCase().includes(filter) ||
        (p.department && p.department.toLowerCase().includes(filter)) ||
        (p.description && p.description.toLowerCase().includes(filter))
      );
      directoryCurrentPage = 1;
      renderDirectoryCards(filtered, directoryCurrentPage);
    };
  }

  // --- MY PROGRAMS: Mini tabs with filtered program management ---
  let allMyPrograms = []; // Store all programs
  let currentMyProgramsFilter = 'active'; // Default filter

  function loadMyPrograms() {
    const loading = document.getElementById('my-programs-loading');
    const noProgramsMessage = document.getElementById('no-programs-message');
    loading.style.display = 'block';
    noProgramsMessage.style.display = 'none';

    fetch('get_my_programs.php')
      .then(response => response.json())
      .then(data => {
        loading.style.display = 'none';
        if (data.status === 'success') {
          allMyPrograms = data.programs || [];
          updateProgramCounts();
          showMyProgramsTab(currentMyProgramsFilter);
        } else {
          showNoPrograms('Error loading programs');
        }
      })
      .catch(error => {
        loading.style.display = 'none';
        showNoPrograms('Network error. Please try again.');
        console.error('Error:', error);
      });
  }

  function updateProgramCounts() {
    const active = allMyPrograms.filter(p => 
      p.enrollment_status === 'approved' && p.program_status === 'ongoing'
    ).length;
    const pending = allMyPrograms.filter(p => 
      p.enrollment_status === 'pending'
    ).length;
    const completed = allMyPrograms.filter(p => 
      p.enrollment_status === 'approved' && p.program_status === 'ended'
    ).length;

    document.getElementById('active-count').textContent = active;
    document.getElementById('pending-count').textContent = pending;
    document.getElementById('completed-count').textContent = completed;
  }

  function showMyProgramsTab(filter) {
    // Update active mini tab
    document.querySelectorAll('.my-programs-mini-tabs .mini-tab').forEach(tab => {
      tab.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');

    // Filter programs based on selected tab
    let filteredPrograms = [];
    if (filter === 'active') {
      filteredPrograms = allMyPrograms.filter(p => 
        p.enrollment_status === 'approved' && p.program_status === 'ongoing'
      );
    } else if (filter === 'pending') {
      filteredPrograms = allMyPrograms.filter(p => 
        p.enrollment_status === 'pending'
      );
    } else if (filter === 'completed') {
      filteredPrograms = allMyPrograms.filter(p => 
        p.enrollment_status === 'approved' && p.program_status === 'ended'
      );
    }

    renderMyPrograms(filteredPrograms, filter);
    currentMyProgramsFilter = filter;
  }

  function renderMyPrograms(programs, type) {
    const list = document.getElementById('my-programs-list');
    const noProgramsMessage = document.getElementById('no-programs-message');
    
    list.innerHTML = '';
    
    if (programs.length === 0) {
      noProgramsMessage.style.display = 'block';
      let message = 'No programs found';
      if (type === 'active') message = 'No active programs';
      else if (type === 'pending') message = 'No pending applications';
      else if (type === 'completed') message = 'No completed programs';
      noProgramsMessage.textContent = message;
      return;
    }

    noProgramsMessage.style.display = 'none';
    programs.forEach(program => {
      const card = createEnhancedMyProgramCard(program, type);
      list.appendChild(card);
    });
  }

  function showNoPrograms(message) {
    const list = document.getElementById('my-programs-list');
    const noProgramsMessage = document.getElementById('no-programs-message');
    list.innerHTML = '';
    noProgramsMessage.textContent = message;
    noProgramsMessage.style.display = 'block';
  }

  // Mini tab event listeners
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.my-programs-mini-tabs .mini-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const filter = tab.dataset.filter;
        showMyProgramsTab(filter);
      });
    });
  });

  // Enhanced program card creation with comprehensive functionality
  function createEnhancedMyProgramCard(program, type) {
    const card = document.createElement('div');
    card.className = `enhanced-program-card ${type}`;

    // Format dates
    const startDate = program.start_date ? new Date(program.start_date).toLocaleDateString() : 'TBA';
    const endDate = program.end_date ? new Date(program.end_date).toLocaleDateString() : 'TBA';
    const enrolledDate = program.enrollment_date ? new Date(program.enrollment_date).toLocaleDateString() : '';

    // Determine status badge
    let statusBadge = '';
    if (type === 'active') {
      statusBadge = `<span class="status-badge active"><i class="fas fa-play-circle"></i> Active</span>`;
    } else if (type === 'pending') {
      statusBadge = `<span class="status-badge pending"><i class="fas fa-clock"></i> Pending Approval</span>`;
    } else if (type === 'completed') {
      statusBadge = `<span class="status-badge completed"><i class="fas fa-check-circle"></i> Completed</span>`;
    }

    // Build attendance info for active/completed programs
    let attendanceInfo = '';
    if (type === 'active' || type === 'completed') {
      const attendancePercentage = parseFloat(program.attendance_percentage) || 0;
      const sessionsAttended = parseInt(program.sessions_attended) || 0;
      const totalSessions = parseInt(program.total_sessions) || 0;
      
      let attendanceColor = 'low';
      if (attendancePercentage >= 80) attendanceColor = 'high';
      else if (attendancePercentage >= 60) attendanceColor = 'medium';

      attendanceInfo = `
        <div class="attendance-section">
          <div class="attendance-header">
            <span><i class="fas fa-calendar-check"></i> Attendance</span>
            <span class="attendance-percentage">${attendancePercentage.toFixed(1)}%</span>
          </div>
          <div class="attendance-bar">
            <div class="attendance-progress ${attendanceColor}" style="width: ${attendancePercentage}%"></div>
          </div>
          <span style="font-size: 0.8rem; color: #6c757d;">${sessionsAttended}/${totalSessions} sessions</span>
        </div>
      `;
    }

    // Build upcoming sessions for active programs
    let upcomingSessions = '';
    if (type === 'active' && program.upcoming_sessions && program.upcoming_sessions.length > 0) {
      upcomingSessions = `
        <div class="upcoming-sessions">
          <h4><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h4>
          <ul>
            ${program.upcoming_sessions.map(session => `
              <li>
                <strong>${session.session_title}</strong><br>
                <span class="session-details">
                  ${new Date(session.session_date).toLocaleDateString()} • 
                  ${session.session_start} - ${session.session_end} • 
                  ${session.location || 'TBA'}
                </span>
              </li>
            `).join('')}
          </ul>
        </div>
      `;
    }

    // Build action buttons based on type
    let actionButtons = '';
    if (type === 'active') {
      actionButtons = `
        <div class="card-actions">
          <button class="action-btn primary" onclick="viewProgramDetails(${program.id})">
            <i class="fas fa-info-circle"></i> View Details
          </button>
          <button class="action-btn secondary" onclick="markAttendance(${program.id})">
            <i class="fas fa-qr-code"></i> Mark Attendance
          </button>
        </div>
      `;
    } else if (type === 'completed') {
      actionButtons = `
        <div class="card-actions">
          <button class="action-btn primary" onclick="viewProgramDetails(${program.id})">
            <i class="fas fa-info-circle"></i> View Details
          </button>
          <button class="action-btn success" onclick="provideFeedback(${program.id})">
            <i class="fas fa-comment-dots"></i> Provide Feedback
          </button>
          <button class="action-btn info" onclick="downloadCertificate(${program.id})">
            <i class="fas fa-certificate"></i> Certificate
          </button>
        </div>
      `;
    } else if (type === 'pending') {
      actionButtons = `
        <div class="card-actions">
          <button class="action-btn secondary" onclick="viewApplicationStatus(${program.id})">
            <i class="fas fa-eye"></i> View Application
          </button>
          <span class="pending-note">
            <i class="fas fa-info-circle"></i> 
            Applied on ${enrolledDate}. Waiting for admin approval.
          </span>
        </div>
      `;
    }

    card.innerHTML = `
      <div class="card-header">
        <div class="program-title-section">
          <h3 class="program-title">${program.program_name}</h3>
          ${statusBadge}
        </div>
        <div class="program-meta">
          <span class="faculty-info">
            <i class="fas fa-user-tie"></i> ${program.faculty_name || 'TBA'}
          </span>
          <span class="schedule-info">
            <i class="fas fa-calendar"></i> ${startDate} - ${endDate}
          </span>
          ${program.location ? `<span class="location-info"><i class="fas fa-map-marker-alt"></i> ${program.location}</span>` : ''}
        </div>
      </div>
      
      <div class="card-content">
        ${program.description ? `<p class="program-description">${program.description}</p>` : ''}
        
        ${(attendanceInfo || upcomingSessions) ? `
        <div class="content-grid">
          ${attendanceInfo ? `<div>${attendanceInfo}</div>` : ''}
          ${upcomingSessions ? `<div>${upcomingSessions}</div>` : ''}
        </div>
        ` : ''}
      </div>
      
      ${actionButtons}
    `;

    return card;
  }

  // Supporting functions for enhanced My Programs functionality
  function viewProgramDetails(programId) {
    // Redirect to a detailed view or show modal with full program information
    window.location.href = `program-details.php?id=${programId}`;
  }

  function markAttendance(programId) {
    // Redirect to QR code attendance page
    window.location.href = `qr_attendance.php?program_id=${programId}`;
  }

  function provideFeedback(programId) {
    // Redirect to feedback page for the specific program
    window.location.href = `Feedback.php?program_id=${programId}`;
  }

  function downloadCertificate(programId) {
    // Download certificate for completed program
    window.open(`certificates.php?program_id=${programId}`, '_blank');
  }

  function viewApplicationStatus(programId) {
    // Show modal with application details
    const modal = document.getElementById('application-status-modal');
    if (modal) {
      modal.style.display = 'flex';
      // Load application details
      fetch(`get_application_status.php?program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            document.getElementById('application-details').innerHTML = `
              <h3>Application Status</h3>
              <p><strong>Program:</strong> ${data.application.program_name}</p>
              <p><strong>Applied:</strong> ${data.application.enrollment_date}</p>
              <p><strong>Status:</strong> <span class="status-${data.application.status}">${data.application.status}</span></p>
              ${data.application.reason ? `<p><strong>Your Message:</strong> ${data.application.reason}</p>` : ''}
            `;
          }
        });
    }
  }

  // --- ENROLL & USER INFO: as before ---

  // Tab switching (unchanged)
  const tabs = document.querySelectorAll('.tab');
  const panes = document.querySelectorAll('.tab-pane');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      panes.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');
      if (tab.dataset.tab === 'directory') {
        loadProgramDirectory();
      } else if (tab.dataset.tab === 'my-programs') {
        loadMyPrograms();
      }
    });
  });

  // Enrollment and user info functions (unchanged)
  function populateProgramDropdown() {
    const select = document.getElementById('enroll-program');
    select.innerHTML = '<option value="" disabled selected>Select a program</option>';

    fetch('/Student/get_active_programs.php')
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success' && data.programs.length > 0) {
          data.programs.forEach(program => {
            const option = document.createElement('option');
            option.value = program.id;
            option.textContent = program.program_name;
            select.appendChild(option);
          });
        } else {
          select.innerHTML = '<option value="" disabled selected>No active programs available</option>';
        }
      })
      .catch(error => {
        select.innerHTML = '<option value="" disabled selected>Error loading programs</option>';
        console.error('Error:', error);
      });
  }

  function populateUserInfo() {
    fetch('/STUDENT/get_user_info.php')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const message = document.getElementById('enroll-message');
            if (data.status === 'success') {
                document.getElementById('enroll-name').value = `${data.user.firstname} ${data.user.lastname}`;
                document.getElementById('enroll-student-id').value = data.user.student_id || 'N/A';
                message.textContent = '';
            } else {
                message.textContent = data.message || 'Error loading user info';
                console.error('Server error:', data);
            }
        })
        .catch(error => {
            document.getElementById('enroll-message').textContent = 'Error loading user info: ' + error.message;
            console.error('Fetch error:', error);
        });
  }

    // Handle enroll form submission
    const enrollForm = document.getElementById('enroll-form');
    if (enrollForm) {
      enrollForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const programId = document.getElementById('enroll-program').value;
        const reason = document.getElementById('enroll-reason').value;
        const message = document.getElementById('enroll-message');

        fetch('/Student/enroll.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ program_id: programId, reason })
        })
          .then(response => response.json())
          .then(data => {
            message.textContent = data.message;
            if (data.status === 'success') {
              document.getElementById('enroll-form').reset();
              populateUserInfo(); // Reset readonly fields
            }
          })
          .catch(error => {
            message.textContent = 'Error submitting enrollment';
            console.error('Error:', error);
          });
      });
    }

  // Load Program Directory by default on page load
  window.addEventListener('DOMContentLoaded', () => {
    loadProgramDirectory();
  });
  </script>
    <!-- Program Details Modal -->
  <div id="enroll-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <div id="modal-program-details"></div>
      <div class="modal-actions">
        <button id="confirm-enroll-btn" class="btn-confirm">Submit</button>
        <button id="cancel-enroll-btn" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Application Status Modal -->
  <div id="application-status-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
      <span class="close-modal" onclick="document.getElementById('application-status-modal').style.display='none'">&times;</span>
      <div id="application-details"></div>
      <div class="modal-actions">
        <button class="btn-cancel" onclick="document.getElementById('application-status-modal').style.display='none'">Close</button>
      </div>
    </div>
  </div>
</body>
</html>