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
            <div class="loading" id="my-programs-loading">
              <span class="spinner"></span> Loading Programs...
            </div>
            <p id="no-programs-message" style="display: none;">Not enrolled in any programs</p>
            <div class="my-programs-list" id="my-programs-list"></div>
          </section>
        </div>
      </div>

      <!-- Mini Tabs for Program List -->
      <div id="program-list"></div>
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
        enrollBtnHtml = `<button class="enroll-btn" disabled>Enrolled</button>`;
      } else if (enrolledPrograms[program.id] === 'pending') {
        enrollBtnHtml = `<button class="enroll-btn" disabled>Pending</button>`;
      } else {
        enrollBtnHtml = `<button class="enroll-btn" data-id="${program.id}">Enroll</button>`;
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
            <div style="margin-top:1em; color:#d9534f;"><b>Are you sure you want to enroll in this program?</b></div>
            <div id="modal-enroll-message" style="margin-top:0.5em; color:#28a745;"></div>
          `;
          modal.style.display = 'flex';

          // Confirm button
          document.getElementById('confirm-enroll-btn').onclick = () => {
            const message = document.getElementById('modal-enroll-message');
            message.textContent = 'Submitting...';
            fetch('/Student/enroll.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ program_id: program.id })
            })
              .then(response => response.json())
              .then(data => {
                message.textContent = data.message;
                if (data.status === 'success') {
                  // Update enrolledPrograms and re-render cards
                  enrolledPrograms[program.id] = 'pending';
                  setTimeout(() => {
                    modal.style.display = 'none';
                    renderDirectoryCards(directoryPrograms, directoryCurrentPage);
                  }, 1200);
                }
              })
              .catch(() => {
                message.textContent = 'Error submitting enrollment';
              });
          };
          // Cancel/close
          document.getElementById('cancel-enroll-btn').onclick =
          document.querySelector('.close-modal').onclick = () => {
            modal.style.display = 'none';
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

  // --- MY PROGRAMS: Show enrolled, status, attendance, feedback ---
  function loadMyPrograms() {
  const list = document.getElementById('my-programs-list');
  const loading = document.getElementById('my-programs-loading');
  const noProgramsMessage = document.getElementById('no-programs-message');
  loading.style.display = 'block';
  list.innerHTML = '';
  noProgramsMessage.style.display = 'none';

  fetch('get_my_programs.php')
    .then(response => response.json())
    .then(data => {
      loading.style.display = 'none';
      if (data.status === 'success' && data.programs.length > 0) {
        // Separate active and ended programs based on BOTH enrollment and program status
        const active = data.programs.filter(
          p => p.status === 'approved' && p.program_status === 'ongoing'
        );
        const ended = data.programs.filter(
          p => p.status === 'approved' && p.program_status === 'ended'
        );

        if (active.length > 0) {
          const activeHeader = document.createElement('h3');
          activeHeader.textContent = 'Active Programs';
          list.appendChild(activeHeader);
          active.forEach(program => {
            const card = createMyProgramCard(program);
            list.appendChild(card);
          });
        }

        if (ended.length > 0) {
          const endedHeader = document.createElement('h3');
          endedHeader.textContent = 'Ended Programs';
          endedHeader.style.marginTop = '2em';
          list.appendChild(endedHeader);
          ended.forEach(program => {
            const card = createMyProgramCard(program);
            list.appendChild(card);
          });
        }

        if (active.length === 0 && ended.length === 0) {
          noProgramsMessage.style.display = 'block';
        }
      } else {
        noProgramsMessage.style.display = 'block';
      }
    })
    .catch(error => {
      loading.style.display = 'none';
      noProgramsMessage.style.display = 'block';
      console.error('Error:', error);
    });
}

  // Helper function to create a program card (reuse your existing card code)
  function createMyProgramCard(program) {
  const card = document.createElement('div');
  card.className = 'my-program-card';

  // Determine display status and color
  let displayStatus = '';
  let statusClass = '';
  if (program.program_status === 'ongoing') {
    displayStatus = 'Active';
    statusClass = 'status-active';
  } else if (program.program_status === 'ended') {
    displayStatus = 'Ended';
    statusClass = 'status-ended';
  } else {
    displayStatus = program.program_status;
    statusClass = '';
  }

  card.innerHTML = `
    <div class="card-title">${program.program_name}</div>
    <div class="card-meta">
      <span class="program-status ${statusClass}">${displayStatus}</span>
      <br>
      <span class="program-schedule">${program.start_date || ''} ${program.end_date ? ' - ' + program.end_date : ''}</span>
    </div>
  `;
  return card;
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

  // --- Mini Tabs Functionality ---
  let allDirectoryPrograms = []; // Store all fetched programs

function fetchDirectoryPrograms() {
  fetch('get_all_programs.php')
    .then(res => res.json())
    .then(data => {
      allDirectoryPrograms = data.programs || [];
      showDirectoryTab('all');
    });
}

function showDirectoryTab(tab) {
  // Remove active class from all buttons
  document.querySelectorAll('#directory-mini-tabs button').forEach(btn => btn.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');

  let filtered = [];
  if (tab === 'all') {
    filtered = allDirectoryPrograms.filter(
      p => p.program_status === 'ongoing' && p.enrollment_status === 'none'
    );
  } else if (tab === 'pending') {
    filtered = allDirectoryPrograms.filter(
      p => p.program_status === 'ongoing' && p.enrollment_status === 'pending'
    );
  } else if (tab === 'enrolled') {
    filtered = allDirectoryPrograms.filter(
      p => p.program_status === 'ongoing' && p.enrollment_status === 'enrolled'
    );
  }
  renderProgramList(filtered);
}

function renderProgramList(programs) {
  const list = document.getElementById('program-list');
  list.innerHTML = '';
  programs.forEach(program => {
    const card = document.createElement('div');
    card.className = 'program-card';
    card.innerHTML = `
      <div class="card-title">${program.program_name}</div>
      <div class="card-meta">
        <b>Department:</b> ${program.department}<br>
        <b>Schedule:</b> ${program.start_date} to ${program.end_date}<br>
        <b>Location:</b> ${program.location}<br>
        <b>Faculty:</b> ${program.faculty_name}
      </div>
    `;
    list.appendChild(card);
  });
}

// Example card rendering (customize as needed)
function renderDirectoryCard(program) {
  const card = document.createElement('div');
  card.className = 'program-card';
  let actionHTML = '';
  if (program.enrollment_status === 'none') {
    actionHTML = `<button class="enroll-btn">Enroll</button>`;
  } else if (program.enrollment_status === 'pending') {
    actionHTML = `<span class="status-badge pending">Pending</span>`;
  } else if (program.enrollment_status === 'enrolled') {
    actionHTML = `<span class="status-badge enrolled">Enrolled</span>`;
  }
  card.innerHTML = `
    <div class="card-title">${program.program_name}</div>
    <div class="card-meta">
      <b>Department:</b> ${program.department}<br>
      <b>Schedule:</b> ${program.start_date} to ${program.end_date}
    </div>
    <div class="card-actions">
      ${actionHTML}
    </div>
  `;
  return card;
}

// Tab event listeners
document.getElementById('tab-all').onclick = () => showDirectoryTab('all');
document.getElementById('tab-pending').onclick = () => showDirectoryTab('pending');
document.getElementById('tab-enrolled').onclick = () => showDirectoryTab('enrolled');

// Fetch programs on page load
window.addEventListener('DOMContentLoaded', fetchDirectoryPrograms);
  </script>
    <!-- Program Details Modal -->
  <div id="enroll-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
      <span class="close-modal">&times;</span>
      <div id="modal-program-details"></div>
      <div class="modal-actions">
        <button id="confirm-enroll-btn" class="btn-confirm">Confirm Enrollment</button>
        <button id="cancel-enroll-btn" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>
</body>
</html>