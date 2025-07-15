<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../register/login.php');
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="Create.css" />

  <!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
          <li><a href="Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li ><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
          <li class="active"><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
          <li><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
          <li><a href="Attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
          <li><a href="Evaluation.php"><i class="fas fa-star-half-alt"></i> Evaluation</a></li>
          <li><a href="certificates.php"><i class="fas fa-certificate"></i> Certificate</a></li>
          <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
        <div class="sign-out" style="position: absolute; bottom: 30px; left: 0; width: 100%; text-align: center;">
          <a href="/register/index.html" style="color: inherit; text-decoration: none; display: block; padding: 12px 0;">Sign Out</a>
        </div>      
      </nav>
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
          <div class="form-header">
            <h3>Create a New Program</h3>
            <div class="progress-bar">
              <div class="progress-step active" data-step="1">Basic Info</div>
              <div class="progress-step" data-step="2">Program Details</div>
              <div class="progress-step" data-step="3">Schedule</div>
              <div class="progress-step" data-step="4">Impact & Goals</div>
            </div>
          </div>
          
          <form id="program-form" class="program-form" action="create_program.php" method="POST">
            <!-- Step 1: Basic Information -->
            <div class="form-step step-1" data-step="1">
              <div class="step-header">
                <h4>📋 Basic Program Information</h4>
                <p>Let's start with the essential details of your program</p>
              </div>
              
              <div class="input-group">
                <label for="program_name">Program Name <span class="required">*</span></label>
                <div class="field-hint">Enter a descriptive name for your program</div>
                <input type="text" id="program_name" name="program_name" required 
                       placeholder="e.g., Community Health Awareness Program">
              </div>

              <div class="input-group">
                <label for="department">Department <span class="required">*</span></label>
                <div class="field-hint">Your department (auto-filled)</div>
                <input type="text" id="department" name="department" 
                       value="<?php echo htmlspecialchars($faculty_department); ?>" 
                       readonly class="readonly-field">
              </div>

              <div class="input-group">
                <label for="description">Program Description</label>
                <div class="field-hint">Provide a brief overview of your program objectives and activities</div>
                <textarea id="description" name="description" rows="4" 
                          placeholder="Describe the purpose, goals, and main activities of your program..."></textarea>
              </div>
            </div>

            <!-- Step 2: Program Details -->
            <div class="form-step step-2" data-step="2">
              <div class="step-header">
                <h4>🎯 Program Details</h4>
                <p>Define the scope and logistics of your program</p>
              </div>

              <div class="input-group">
                <label for="location">Location <span class="required">*</span></label>
                <div class="field-hint">Where will this program take place?</div>
                <div class="location-container">
                  <input type="text" id="location" name="location" required 
                         placeholder="e.g., Cavite State University Main Campus">
                  <button type="button" class="map-btn" onclick="openMapModal()">
                    <i class="fas fa-map-marker-alt"></i> Map
                  </button>
                </div>
              </div>

              <div class="capacity-container">                  <div class="capacity-field">
                  <label for="max_students">Maximum Participants <span class="required">*</span></label>
                  <div class="field-hint">Maximum number of participants (1-20)</div>
                  <div class="participant-input-container">
                    <input type="number" id="max_students" name="max_students" min="1" max="20" required value="1">
                    <div class="participant-counter">
                      <span class="counter-text">of 20 max</span>
                    </div>
                  </div>
                </div>

                <div class="gender-distribution">
                  <label>Gender Distribution (Optional)</label>
                  <div class="field-hint">Total must not exceed maximum participants</div>
                  <div class="gender-row">
                    <div class="gender-field">
                      <label for="male_count">Male Count</label>
                      <select id="male_count" name="male_count">
                        <option value="0">0</option>
                      </select>
                      <div class="gender-counter">
                        <span id="male-remaining">0 available</span>
                      </div>
                    </div>
                    <div class="gender-field">
                      <label for="female_count">Female Count</label>
                      <select id="female_count" name="female_count">
                        <option value="0">0</option>
                      </select>
                      <div class="gender-counter">
                        <span id="female-remaining">0 available</span>
                      </div>
                    </div>
                  </div>
                  <div class="gender-summary">
                    <div class="summary-item">
                      <span class="summary-label">Total Selected:</span>
                      <span id="total-selected" class="summary-value">0</span>
                    </div>
                    <div class="summary-item">
                      <span class="summary-label">Remaining:</span>
                      <span id="remaining-slots" class="summary-value">0</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="input-group">
                <label>Project Titles</label>
                <div class="field-hint">Enter up to 3 project titles for this program</div>
                <div class="project-titles-container">
                  <div class="project-input">
                    <div class="project-number">1</div>
                    <input type="text" name="project_title_1" required 
                           placeholder="First project title (required)">
                  </div>
                  <div class="project-input">
                    <div class="project-number">2</div>
                    <input type="text" name="project_title_2" 
                           placeholder="Second project title (optional)">
                  </div>
                  <div class="project-input">
                    <div class="project-number">3</div>
                    <input type="text" name="project_title_3" 
                           placeholder="Third project title (optional)">
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 3: Schedule & Timeline -->
            <div class="form-step step-3" data-step="3">
              <div class="step-header">
                <h4>📅 Schedule & Timeline</h4>
                <p>Set up your program dates and sessions</p>
              </div>

              <div class="date-row">
                <div class="date-field">
                  <label for="start_date">Start Date <span class="required">*</span></label>
                  <input type="date" id="start_date" name="start_date" required>
                </div>
                <div class="date-field">
                  <label for="previous_date">Previous Date</label>
                  <div class="field-hint">Cannot select dates before today</div>
                  <input type="date" id="previous_date" name="previous_date">
                </div>
                <div class="date-field">
                  <label for="end_date">End Date <span class="required">*</span></label>
                  <input type="date" id="end_date" name="end_date" required>
                </div>
              </div>

              <div class="input-group">
                <label>Program Sessions</label>
                <div class="field-hint">Add individual sessions for your program</div>
                <div id="sessions-container">
                  <div class="session-row">
                    <div class="session-inputs">
                      <input type="date" name="session_date[]" required placeholder="Session Date">
                      <input type="time" name="session_start[]" required placeholder="Start Time">
                      <input type="time" name="session_end[]" required placeholder="End Time">
                      <input type="text" name="session_title[]" placeholder="Session Title (optional)">
                    </div>
                    <button type="button" class="remove-session" onclick="removeSession(this)">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
                <button type="button" id="add-session-btn">
                  <i class="fas fa-plus"></i> Add Another Session
                </button>
              </div>
            </div>

            <!-- Step 4: Impact & SDGs -->
            <div class="form-step step-4" data-step="4">
              <div class="step-header">
                <h4>🌍 Impact & Sustainable Development Goals</h4>
                <p>Select which SDGs your program will address</p>
              </div>

              <div class="input-group">
                <label>Select Relevant SDGs</label>
                <div class="field-hint">Choose the Sustainable Development Goals that align with your program</div>
                <input type="hidden" name="selected_sdgs" value="[]">
                
                <div class="sdg-grid">
                  <?php
                  $sdgs = [
                    1 => "No Poverty", 2 => "Zero Hunger", 3 => "Good Health and Well-being",
                    4 => "Quality Education", 5 => "Gender Equality", 6 => "Clean Water and Sanitation",
                    7 => "Affordable and Clean Energy", 8 => "Decent Work and Economic Growth",
                    9 => "Industry, Innovation and Infrastructure", 10 => "Reduced Inequalities",
                    11 => "Sustainable Cities and Communities", 12 => "Responsible Consumption and Production",
                    13 => "Climate Action", 14 => "Life Below Water", 15 => "Life on Land",
                    16 => "Peace, Justice and Strong Institutions", 17 => "Partnerships for the Goals"
                  ];
                  
                  foreach ($sdgs as $number => $title): ?>
                    <div class="sdg-card" data-value="<?php echo $number; ?>">
                      <div class="sdg-image">
                        <img src="https://sdgs.un.org/sites/default/files/goals/E_SDG_Icons-<?php echo sprintf('%02d', $number); ?>.jpg" 
                             alt="SDG <?php echo $number; ?>" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="sdg-fallback" style="display: none;">
                          <div class="sdg-number-fallback"><?php echo $number; ?></div>
                        </div>
                      </div>
                      <div class="sdg-content">
                        <div class="sdg-title"><?php echo $title; ?></div>
                      </div>
                      <div class="sdg-overlay">
                        <i class="fas fa-check"></i>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="selected-sdgs">
                <h5>Selected SDGs:</h5>
                <div id="selected-sdgs-display">
                  <span class="no-selection">No SDGs selected yet</span>
                </div>
              </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="form-navigation">
              <button type="button" class="nav-btn prev-btn" id="prev-btn">
                <i class="fas fa-arrow-left"></i> Previous
              </button>
              <button type="button" class="nav-btn next-btn" id="next-btn">
                Next <i class="fas fa-arrow-right"></i>
              </button>
              <button type="submit" class="submit-btn" id="submit-btn">
                <i class="fas fa-check"></i> Create Program
              </button>
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

  <!-- Enhanced Map Modal with Leaflet -->
<div id="mapModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3><i class="fas fa-map-marker-alt"></i> Select Program Location</h3>
      <span class="close" onclick="closeMapModal()">&times;</span>
    </div>
    <div class="modal-body">
      
      <!-- Campus Quick Select -->
      <div class="campus-quick-select">
        <h5><i class="fas fa-university"></i> CvSU Campus Locations</h5>
        <div class="campus-buttons">
          <button type="button" onclick="selectCampusLocation('CvSU Indang (Main Campus)', 14.2438, 120.8768)" class="campus-btn">
            <i class="fas fa-graduation-cap"></i> Main Campus
          </button>
          <button type="button" onclick="selectCampusLocation('CvSU Bacoor Campus', 14.4583, 120.9447)" class="campus-btn">
            <i class="fas fa-building"></i> Bacoor
          </button>
          <button type="button" onclick="selectCampusLocation('CvSU Imus Campus', 14.4297, 120.9367)" class="campus-btn">
            <i class="fas fa-building"></i> Imus
          </button>
          <button type="button" onclick="selectCampusLocation('CvSU Silang Campus', 14.2306, 121.0167)" class="campus-btn">
            <i class="fas fa-building"></i> Silang
          </button>
        </div>
      </div>
      
      <!-- Search Location -->
      <div class="location-search-container">
        <div class="search-row">
          <input type="text" id="location-search" placeholder="Search for a location (e.g., Makati City, SM Mall, etc.)" />
          <button type="button" onclick="searchLocation()" class="search-btn">
            <i class="fas fa-search"></i> Search
          </button>
        </div>
        <button type="button" onclick="getCurrentLocation()" class="location-btn">
          <i class="fas fa-crosshairs"></i> Use My Current Location
        </button>
        <div id="location-status" class="location-status"></div>
      </div>
      
      <!-- Interactive Map -->
      <div class="map-container">
        <div id="map" style="height: 400px; width: 100%; border-radius: 12px; border: 2px solid #e0e0e0;"></div>
        <div class="map-instructions">
          <i class="fas fa-info-circle"></i> 
          Click anywhere on the map or drag the marker to select a location
        </div>
      </div>
      
      <!-- Selected Location Display -->
      <div class="location-info">
        <div class="selected-location-display">
          <label><i class="fas fa-map-pin"></i> Selected Location:</label>
          <input type="text" id="selected-location" placeholder="Click on map or search to select location" readonly />
        </div>
        <div class="location-actions">
          <button type="button" onclick="confirmLocation()" class="confirm-location-btn">
            <i class="fas fa-check"></i> Confirm Location
          </button>
          <button type="button" onclick="closeMapModal()" class="cancel-btn">
            <i class="fas fa-times"></i> Cancel
          </button>
        </div>
      </div>
    </div>
  </div>  </div>

  <!-- Success Popup Modal -->
  <div id="successModal" class="success-modal" style="display: none;">
    <div class="success-modal-content">
      <div class="success-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h3>Program Created Successfully!</h3>
      <p>Your program has been created and is now active.</p>
      <div class="success-details">
        <div class="detail-item">
          <i class="fas fa-calendar"></i>
          <span>Program ID: <strong id="success-program-id">--</strong></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-globe"></i>
          <span>SDGs Selected: <strong id="success-sdg-count">0</strong></span>
        </div>
      </div>
    </div>
  </div>

  <script>
let currentStep = 1;
let selectedSDGs = [];
let map, marker;

document.addEventListener('DOMContentLoaded', function() {
  // Initialize form steps
  showStep(1);
  updateNavigation();
  
  // Initialize date restrictions
  initializeDateRestrictions();
  
  // Initialize gender distribution logic
  initializeGenderDistribution();
  
  // Initialize SDG selection
  initializeSDGSelection();
  
  // Event listeners
  const nextBtn = document.getElementById('next-btn');
  const prevBtn = document.getElementById('prev-btn');
  const form = document.getElementById('program-form');
  
  if (nextBtn) {
    nextBtn.addEventListener('click', function(e) {
      e.preventDefault();
      if (validateCurrentStep() && currentStep < 4) {
        currentStep++;
        showStep(currentStep);
        updateNavigation();
      }
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function(e) {
      e.preventDefault();
      if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
        updateNavigation();
      }
    });
  }

  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (validateCurrentStep()) {
        submitForm();
      }
    });
  }

  // Session management
  const addSessionBtn = document.getElementById('add-session-btn');
  if (addSessionBtn) {
    addSessionBtn.addEventListener('click', function() {
      const container = document.getElementById('sessions-container');
      const sessionRow = document.createElement('div');
      sessionRow.className = 'session-row';
      sessionRow.innerHTML = `
        <div class="session-inputs">
          <input type="date" name="session_date[]" required placeholder="Session Date">
          <input type="time" name="session_start[]" required placeholder="Start Time">
          <input type="time" name="session_end[]" required placeholder="End Time">
          <input type="text" name="session_title[]" placeholder="Session Title (optional)">
        </div>
        <button type="button" class="remove-session" onclick="removeSession(this)">
          <i class="fas fa-trash"></i>
        </button>
      `;
      container.appendChild(sessionRow);
    });
  }
});

// LEAFLET MAP FUNCTIONS - FREE & NO API KEY NEEDED
function openMapModal() {
  const modal = document.getElementById('mapModal');
  if (modal) {
    modal.classList.add('show');
    
    // Initialize map when modal opens
    setTimeout(() => {
      if (!map) {
        initializeLeafletMap();
      }
    }, 300);
  }
}

function closeMapModal() {
  const modal = document.getElementById('mapModal');
  if (modal) {
    modal.classList.remove('show');
  }
}

function initializeLeafletMap() {
  console.log('Initializing Leaflet map...');
  
  // Default location (Cavite State University)
  const defaultLat = 14.2438;
  const defaultLng = 120.8768;
  
  try {
    // Initialize map
    map = L.map('map').setView([defaultLat, defaultLng], 15);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);
    
    // Custom marker icon
    const customIcon = L.divIcon({
      html: `
        <div style="
          background: #28a745;
          width: 20px;
          height: 20px;
          border-radius: 50%;
          border: 3px solid white;
          box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        "></div>
      `,
      className: 'custom-marker',
      iconSize: [26, 26],
      iconAnchor: [13, 13]
    });
    
    // Add marker
    marker = L.marker([defaultLat, defaultLng], {
      icon: customIcon,
      draggable: true
    }).addTo(map);
    
    // Map click handler
    map.on('click', function(e) {
      const lat = e.latlng.lat;
      const lng = e.latlng.lng;
      
      // Move marker to clicked location
      marker.setLatLng([lat, lng]);
      
      // Update location display
      const locationText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
      document.getElementById('selected-location').value = locationText;
      
      // Try to get address using reverse geocoding
      reverseGeocode(lat, lng);
    });
    
    // Marker drag handler
    marker.on('dragend', function(e) {
      const lat = e.target.getLatLng().lat;
      const lng = e.target.getLatLng().lng;
      
      const locationText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
      document.getElementById('selected-location').value = locationText;
      
      reverseGeocode(lat, lng);
    });
    
    // Set initial location
    const locationText = `${defaultLat.toFixed(6)}, ${defaultLng.toFixed(6)}`;
    document.getElementById('selected-location').value = locationText;
    reverseGeocode(defaultLat, defaultLng);
    
    console.log('Leaflet map initialized successfully');
    
  } catch (error) {
    console.error('Error initializing map:', error);
    showMapError('Failed to load map. Please try again.');
  }
}

// Reverse geocoding using Nominatim (free OpenStreetMap service)
function reverseGeocode(lat, lng) {
  const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;
  
  fetch(url)
    .then(response => response.json())
    .then(data => {
      if (data && data.display_name) {
        document.getElementById('selected-location').value = data.display_name;
      }
    })
    .catch(error => {
      console.log('Reverse geocoding failed, using coordinates');
      // Keep the coordinates if reverse geocoding fails
    });
}

// Location search function
function searchLocation() {
  const searchInput = document.getElementById('location-search');
  const query = searchInput.value.trim();
  
  if (!query) {
    alert('Please enter a location to search');
    return;
  }
  
  // Use Nominatim search API (free)
  const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=PH`;
  
  fetch(url)
    .then(response => response.json())
    .then(data => {
      if (data && data.length > 0) {
        const result = data[0];
        const lat = parseFloat(result.lat);
        const lng = parseFloat(result.lon);
        
        // Center map on search result
        map.setView([lat, lng], 16);
        marker.setLatLng([lat, lng]);
        
        // Update location display
        document.getElementById('selected-location').value = result.display_name;
        
      } else {
        alert('Location not found. Please try a different search term.');
      }
    })
    .catch(error => {
      console.error('Search failed:', error);
      alert('Search failed. Please try again.');
    });
}

// Get user's current location
function getCurrentLocation() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported by this browser.');
    return;
  }
  
  const statusElement = document.getElementById('location-status');
  if (statusElement) {
    statusElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your precise location...';
  }
  
  navigator.geolocation.getCurrentPosition(
    function(position) {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      const accuracy = position.coords.accuracy;
      
      console.log(`Location found: ${lat}, ${lng} (accuracy: ${accuracy}m)`);
      
      // Center map on user location with higher zoom for accuracy
      map.setView([lat, lng], 18);
      marker.setLatLng([lat, lng]);
      
      // Update location display
      const locationText = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
      document.getElementById('selected-location').value = locationText;
      
      // Get address
      reverseGeocode(lat, lng);
      
      if (statusElement) {
        statusElement.innerHTML = `<i class="fas fa-check-circle" style="color: #28a745;"></i> Location found! (Accuracy: ${Math.round(accuracy)}m)`;
        setTimeout(() => {
          statusElement.innerHTML = '';
        }, 3000);
      }
    },
    function(error) {
      let errorMessage = 'Unable to get your location. ';
      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMessage += 'Please allow location access and try again.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMessage += 'Location information unavailable.';
          break;
        case error.TIMEOUT:
          errorMessage += 'Location request timed out. Try again.';
          break;
        default:
          errorMessage += 'Unknown error occurred.';
          break;
      }
      
      alert(errorMessage);
      
      if (statusElement) {
        statusElement.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> ${errorMessage}`;
        setTimeout(() => {
          statusElement.innerHTML = '';
        }, 5000);
      }
    },
    {
      enableHighAccuracy: true,        // Use GPS if available
      timeout: 15000,                  // 15 seconds timeout
      maximumAge: 60000                // Accept 1-minute old position
    }
  );
}

function confirmLocation() {
  const selectedLocation = document.getElementById('selected-location').value;
  
  if (!selectedLocation) {
    alert('Please select a location first');
    return;
  }
  
  // Update the main location field
  const locationField = document.getElementById('location');
  if (locationField) {
    locationField.value = selectedLocation;
  }
  
  // Close modal
  closeMapModal();
  
  // Show success message
  showNotification('Location selected successfully!', 'success');
}

function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  notification.innerHTML = `
    <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
    ${message}
  `;
  
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${type === 'success' ? '#d4edda' : '#d1ecf1'};
    color: ${type === 'success' ? '#155724' : '#0c5460'};
    padding: 15px 20px;
    border-radius: 8px;
    border: 1px solid ${type === 'success' ? '#c3e6cb' : '#bee5eb'};
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    z-index: 10000;
    min-width: 250px;
    animation: slideIn 0.3s ease;
  `;
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => {
      document.body.removeChild(notification);
    }, 300);
  }, 3000);
}

function showMapError(message) {
  const mapContainer = document.getElementById('map');
  if (mapContainer) {
    mapContainer.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: center; height: 400px; background: #f8f9fa; border-radius: 12px; flex-direction: column; text-align: center; padding: 20px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ffc107; margin-bottom: 15px;"></i>
        <h4 style="color: #495057; margin-bottom: 10px;">Map Loading Issue</h4>
        <p style="color: #6c757d; margin-bottom: 15px;">${message}</p>
        <button onclick="retryMapLoad()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
          <i class="fas fa-redo"></i> Retry
        </button>
      </div>
    `;
  }
}

function retryMapLoad() {
  const mapContainer = document.getElementById('map');
  if (mapContainer) {
    mapContainer.innerHTML = '<div style="height: 400px; width: 100%;"></div>';
    setTimeout(() => {
      map = null;
      marker = null;
      initializeLeafletMap();
    }, 1000);
  }
}

// Campus preset locations
function selectCampusLocation(campusName, lat, lng) {
  if (map && marker) {
    map.setView([lat, lng], 16);
    marker.setLatLng([lat, lng]);
    document.getElementById('selected-location').value = campusName;
  }
}

// DATE RESTRICTION FUNCTIONS
function initializeDateRestrictions() {
  const today = new Date();
  const todayString = today.toISOString().split('T')[0];
  
  // Set minimum date to today for all date inputs
  const startDateInput = document.getElementById('start_date');
  const previousDateInput = document.getElementById('previous_date');
  const endDateInput = document.getElementById('end_date');
  
  if (startDateInput) {
    startDateInput.min = todayString;
  }
  
  if (previousDateInput) {
    previousDateInput.min = todayString;
    // Add visual feedback when user tries to select past date
    previousDateInput.addEventListener('change', function() {
      const selectedDate = new Date(this.value);
      if (selectedDate < today) {
        this.value = '';
        showNotification('Cannot select dates before today!', 'error');
      }
    });
  }
  
  if (endDateInput) {
    endDateInput.min = todayString;
    
    // Ensure end date is after start date
    if (startDateInput) {
      startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
          endDateInput.value = '';
          showNotification('End date must be after start date!', 'warning');
        }
      });
    }
  }
  
  // Set minimum date for session dates as well
  const sessionDates = document.querySelectorAll('input[name="session_date[]"]');
  sessionDates.forEach(input => {
    input.min = todayString;
  });
  
  // Add minimum date to dynamically added session dates
  const addSessionBtn = document.getElementById('add-session-btn');
  if (addSessionBtn) {
    const originalAddFunction = addSessionBtn.onclick;
    addSessionBtn.onclick = function() {
      if (originalAddFunction) originalAddFunction.call(this);
      
      // Set min date for newly added session date inputs
      setTimeout(() => {
        const newSessionDates = document.querySelectorAll('input[name="session_date[]"]:not([min])');
        newSessionDates.forEach(input => {
          input.min = todayString;
        });
      }, 100);
    };
  }
}

// FORM STEP FUNCTIONS
function showStep(stepNumber) {
  // Hide all form steps
  const allFormSteps = document.querySelectorAll('.form-step');
  allFormSteps.forEach(step => {
    step.style.display = 'none';
    step.classList.remove('active');
  });
  
  // Show current step using data-step attribute
  const currentFormStep = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
  if (currentFormStep) {
    currentFormStep.style.display = 'block';
    currentFormStep.classList.add('active');
  }
  
  // Update progress bar
  const progressSteps = document.querySelectorAll('.progress-step');
  progressSteps.forEach((step, index) => {
    if (index + 1 <= stepNumber) {
      step.classList.add('active');
    } else {
      step.classList.remove('active');
    }
  });
}

function updateNavigation() {
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  const submitBtn = document.getElementById('submit-btn');
  
  if (prevBtn) {
    prevBtn.style.display = currentStep === 1 ? 'none' : 'flex';
  }
  
  if (nextBtn) {
    nextBtn.style.display = currentStep === 4 ? 'none' : 'flex';
  }
  
  if (submitBtn) {
    submitBtn.style.display = currentStep === 4 ? 'flex' : 'none';
  }
}

function validateCurrentStep() {
  const currentFormStep = document.querySelector(`.form-step[data-step="${currentStep}"]`);
  if (!currentFormStep) return false;
  
  const requiredFields = currentFormStep.querySelectorAll('input[required], textarea[required], select[required]');
  let isValid = true;
  
  requiredFields.forEach(field => {
    if (!field.value.trim()) {
      field.classList.add('error');
      isValid = false;
      
      // Remove error class after animation
      setTimeout(() => {
        field.classList.remove('error');
      }, 500);
    } else {
      field.classList.remove('error');
    }
  });
  
  // Additional validation for step 4 (SDGs)
  if (currentStep === 4 && selectedSDGs.length === 0) {
    alert('Please select at least one SDG for your program.');
    return false;
  }
  
  return isValid;
}

// SDG SELECTION FUNCTIONS
function initializeSDGSelection() {
  // Add click listeners to all SDG cards
  const sdgCards = document.querySelectorAll('.sdg-card');
  sdgCards.forEach(card => {
    card.addEventListener('click', function() {
      const sdgNumber = parseInt(this.getAttribute('data-value'));
      toggleSDG(sdgNumber);
    });
  });
}

function toggleSDG(sdgNumber) {
  const index = selectedSDGs.indexOf(sdgNumber);
  const card = document.querySelector(`[data-value="${sdgNumber}"]`);
  
  if (index === -1) {
    // Add to selection
    selectedSDGs.push(sdgNumber);
    card.classList.add('selected');
  } else {
    // Remove from selection
    selectedSDGs.splice(index, 1);
    card.classList.remove('selected');
  }
  
  // Update hidden input with proper JSON format
  const hiddenInput = document.querySelector('input[name="selected_sdgs"]');
  if (hiddenInput) {
    hiddenInput.value = JSON.stringify(selectedSDGs);
    console.log('SDGs updated:', selectedSDGs, 'JSON:', hiddenInput.value);
  }
  
  updateSelectedSDGsDisplay();
}

function updateSelectedSDGsDisplay() {
  const display = document.getElementById('selected-sdgs-display');
  if (!display) return;
  
  if (selectedSDGs.length === 0) {
    display.innerHTML = '<span class="no-selection">No SDGs selected yet</span>';
  } else {
    const sdgTags = selectedSDGs.map(sdg => {
      const sdgCard = document.querySelector(`[data-value="${sdg}"]`);
      const title = sdgCard ? sdgCard.querySelector('.sdg-title').textContent : `SDG ${sdg}`;
      return `<span class="selected-sdg-tag">SDG ${sdg}: ${title}</span>`;
    }).join('');
    display.innerHTML = sdgTags;
  }
  
  console.log('Selected SDGs display updated:', selectedSDGs);
}

// GENDER DISTRIBUTION FUNCTIONS
function initializeGenderDistribution() {
  const maxStudentsInput = document.getElementById('max_students');
  if (maxStudentsInput) {
    maxStudentsInput.addEventListener('input', function() {
      enforceMaxParticipants();
      updateGenderOptions();
    });
    
    // Initialize gender dropdowns
    updateGenderOptions();
    
    // Add event listeners to gender dropdowns
    const maleSelect = document.getElementById('male_count');
    const femaleSelect = document.getElementById('female_count');
    
    if (maleSelect) {
      maleSelect.addEventListener('change', updateGenderSummary);
    }
    if (femaleSelect) {
      femaleSelect.addEventListener('change', updateGenderSummary);
    }
  }
}

function enforceMaxParticipants() {
  const maxStudentsInput = document.getElementById('max_students');
  let value = parseInt(maxStudentsInput.value);
  
  // Enforce minimum of 1
  if (value < 1 || isNaN(value)) {
    maxStudentsInput.value = 1;
    value = 1;
  }
  
  // Enforce maximum of 20
  if (value > 20) {
    maxStudentsInput.value = 20;
    value = 20;
    
    // Show feedback to user
    const container = maxStudentsInput.closest('.participant-input-container');
    if (container) {
      container.style.borderColor = '#ff6b6b';
      setTimeout(() => {
        container.style.borderColor = '';
      }, 2000);
    }
  }
  
  // Update counter text
  const counterText = document.querySelector('.counter-text');
  if (counterText) {
    counterText.textContent = `${value} of 20 max`;
    
    // Add visual feedback for approaching limit
    if (value >= 18) {
      counterText.style.color = '#ff6b6b';
    } else if (value >= 15) {
      counterText.style.color = '#ff9f40';
    } else {
      counterText.style.color = '#28a745';
    }
  }
}

function updateGenderOptions() {
  const maxStudents = parseInt(document.getElementById('max_students').value) || 1;
  const maleSelect = document.getElementById('male_count');
  const femaleSelect = document.getElementById('female_count');
  
  if (!maleSelect || !femaleSelect) return;
  
  // Clear existing options
  maleSelect.innerHTML = '';
  femaleSelect.innerHTML = '';
  
  // Add options from 0 to maxStudents
  for (let i = 0; i <= maxStudents; i++) {
    maleSelect.innerHTML += `<option value="${i}">${i}</option>`;
    femaleSelect.innerHTML += `<option value="${i}">${i}</option>`;
  }
  
  updateGenderSummary();
}

function updateGenderSummary() {
  const maxStudents = parseInt(document.getElementById('max_students').value) || 0;
  const maleParticipants = parseInt(document.getElementById('male_count').value) || 0;
  const femaleParticipants = parseInt(document.getElementById('female_count').value) || 0;
  
  const totalSelected = maleParticipants + femaleParticipants;
  const remaining = maxStudents - totalSelected;
  
  const totalElement = document.getElementById('total-selected');
  const remainingElement = document.getElementById('remaining-slots');
  
  if (totalElement) {
    totalElement.textContent = totalSelected;
    totalElement.className = 'summary-value';
  }
  
  if (remainingElement) {
    remainingElement.textContent = remaining;
    remainingElement.className = 'summary-value';
  }
  
  // Add color coding
  if (totalSelected > maxStudents) {
    if (totalElement) totalElement.classList.add('error');
    if (remainingElement) remainingElement.classList.add('error');
  } else if (remaining <= 2 && remaining > 0) {
    if (totalElement) totalElement.classList.add('warning');
    if (remainingElement) remainingElement.classList.add('warning');
  }
}

// SESSION MANAGEMENT
function removeSession(button) {
  const sessionRow = button.closest('.session-row');
  if (sessionRow) {
    sessionRow.remove();
  }
}

// FORM SUBMISSION
function submitForm() {
  console.log('Submitting form...');
  
  // Show loading state on submit button
  const submitBtn = document.getElementById('submit-btn');
  const originalText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Program...';
  submitBtn.disabled = true;
  
  // Ensure SDG data is properly set before submission
  const hiddenInput = document.querySelector('input[name="selected_sdgs"]');
  if (hiddenInput) {
    hiddenInput.value = JSON.stringify(selectedSDGs);
    console.log('Final SDG data being submitted:', hiddenInput.value);
  }
  
  const formData = new FormData(document.getElementById('program-form'));
  
  // Log all form data for debugging
  console.log('Form data being submitted:');
  for (let [key, value] of formData.entries()) {
    console.log(key + ':', value);
  }
  
  fetch('create_program.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    console.log('Server response:', data);
    
    if (data.status === 'success') {
      // Show success modal
      showSuccessModal(data.program_id, selectedSDGs.length);
      
      // Redirect after 2.5 seconds (modal shows for 2 seconds)
      setTimeout(() => {
        window.location.href = 'Programs.php';
      }, 2500);
    } else {
      // Show error message
      const messageDiv = document.getElementById('form-message');
      if (messageDiv) {
        messageDiv.style.display = 'block';
        messageDiv.className = 'error-message';
        messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message}`;
      } else {
        alert('Error: ' + data.message);
      }
    }
  })
  .catch(error => {
    console.error('Error:', error);
    const messageDiv = document.getElementById('form-message');
    if (messageDiv) {
      messageDiv.style.display = 'block';
      messageDiv.className = 'error-message';
      messageDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Network error occurred while creating the program.';
    } else {
      alert('Network error occurred while creating the program.');
    }
  })
  .finally(() => {
    // Restore submit button state
    submitBtn.innerHTML = originalText;
    submitBtn.disabled = false;
  });
}

function showSuccessModal(programId, sdgCount) {
  const modal = document.getElementById('successModal');
  const programIdElement = document.getElementById('success-program-id');
  const sdgCountElement = document.getElementById('success-sdg-count');
  
  if (programIdElement) {
    programIdElement.textContent = programId || 'N/A';
  }
  if (sdgCountElement) {
    sdgCountElement.textContent = sdgCount || 0;
  }
  
  if (modal) {
    modal.style.display = 'flex'; // Use flex for centering
    
    // Close modal after exactly 2 seconds with fade out animation
    setTimeout(() => {
      modal.style.animation = 'fadeOut 0.3s ease forwards';
      setTimeout(() => {
        modal.style.display = 'none';
        modal.style.animation = ''; // Reset animation
      }, 300);
    }, 2000);
  }
}
  </script>
</body>
</html>