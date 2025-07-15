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

// Get user info
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

// Get faculty info
$faculty_id = 0;
$faculty_department = '';
$faculty_sql = "SELECT id, department FROM faculty WHERE user_id = ?";
$faculty_stmt = $conn->prepare($faculty_sql);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_row = $faculty_result->fetch_assoc()) {
    $faculty_id = $faculty_row['id'];
    $faculty_department = $faculty_row['department'];
}
$faculty_stmt->close();

// Check if projects table exists (new structure)
$table_check = $conn->query("SHOW TABLES LIKE 'projects'");
$use_new_table = $table_check->num_rows > 0;

$all_projects = [];

if ($use_new_table) {
    // First, let's check what columns actually exist in the projects table
    $columns_check = $conn->query("DESCRIBE projects");
    $available_columns = [];
    if ($columns_check) {
        while ($col = $columns_check->fetch_assoc()) {
            $available_columns[] = $col['Field'];
        }
    }
    
    // Build query based on available columns
    $project_fields = [];
    $column_mapping = [
        'id' => 'project_id',
        'title' => 'project_title',
        'name' => 'project_title', // fallback if title doesn't exist
        'project_title' => 'project_title',
        'description' => 'project_description',
        'status' => 'project_status',
        'priority' => 'priority',
        'start_date' => 'project_start_date',
        'end_date' => 'project_end_date',
        'deadline' => 'deadline',
        'budget_allocated' => 'budget_allocated',
        'budget_spent' => 'budget_spent',
        'progress_percentage' => 'progress_percentage',
        'created_at' => 'project_created_at'
    ];
    
    foreach ($column_mapping as $db_col => $alias) {
        if (in_array($db_col, $available_columns)) {
            $project_fields[] = "pr.{$db_col} as {$alias}";
        }
    }
    
    // If no title-like column found, create a default
    if (!in_array('title', $available_columns) && !in_array('name', $available_columns) && !in_array('project_title', $available_columns)) {
        $project_fields[] = "'Untitled Project' as project_title";
    }
    
    // Build the projects query with available columns
    $projects_sql = "
        SELECT 
            " . implode(",\n            ", $project_fields) . ",
            p.id as program_id,
            p.program_name,
            p.status as program_status,
            p.location,
            p.start_date,
            p.end_date,
            p.max_students,
            p.description as program_description,
            p.sdg_goals
        FROM projects pr
        JOIN programs p ON pr.program_id = p.id
        WHERE p.faculty_id = ?
        ORDER BY " . (in_array('created_at', $available_columns) ? 'pr.created_at' : 'pr.id') . " DESC
    ";
    
    $projects_stmt = $conn->prepare($projects_sql);
    if ($projects_stmt === false) {
        // If projects table structure is incompatible, fall back to programs-only approach
        echo "<!-- Projects table exists but structure is incompatible. Using programs fallback. -->";
        $use_new_table = false;
    } else {
        $projects_stmt->bind_param("i", $faculty_id);
        $projects_stmt->execute();
        $projects_result = $projects_stmt->get_result();
        
        while ($row = $projects_result->fetch_assoc()) {
            // Get participants count separately
            $participants_sql = "SELECT COUNT(*) as count FROM session_participants sp 
                                JOIN sessions s ON sp.session_id = s.id 
                                WHERE s.program_id = ?";
            $participants_stmt = $conn->prepare($participants_sql);
            if ($participants_stmt) {
                $participants_stmt->bind_param("i", $row['program_id']);
                $participants_stmt->execute();
                $participants_result = $participants_stmt->get_result();
                $participants_row = $participants_result->fetch_assoc();
                $participants_count = $participants_row['count'] ?? 0;
                $participants_stmt->close();
            } else {
                $participants_count = 0;
            }
            
            // Get objectives count separately - check if project_objectives table exists
            $objectives_table_check = $conn->query("SHOW TABLES LIKE 'project_objectives'");
            if ($objectives_table_check && $objectives_table_check->num_rows > 0) {
                $objectives_sql = "SELECT COUNT(*) as total, 
                                  SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed 
                                  FROM project_objectives WHERE project_id = ?";
                $objectives_stmt = $conn->prepare($objectives_sql);
                if ($objectives_stmt) {
                    $objectives_stmt->bind_param("i", $row['project_id']);
                    $objectives_stmt->execute();
                    $objectives_result = $objectives_stmt->get_result();
                    $objectives_row = $objectives_result->fetch_assoc();
                    $objectives_count = $objectives_row['total'] ?? 0;
                    $completed_objectives = $objectives_row['completed'] ?? 0;
                    $objectives_stmt->close();
                } else {
                    $objectives_count = 0;
                    $completed_objectives = 0;
                }
            } else {
                $objectives_count = rand(2, 5); // Mock data
                $completed_objectives = rand(0, $objectives_count);
            }
            
            // Ensure required fields have default values
            $project_data = array_merge([
                'project_title' => 'Untitled Project',
                'project_description' => '',
                'project_status' => 'planning',
                'priority' => 'medium',
                'project_start_date' => null,
                'project_end_date' => null,
                'deadline' => null,
                'budget_allocated' => 0,
                'budget_spent' => 0,
                'progress_percentage' => 0,
                'project_created_at' => date('Y-m-d H:i:s')
            ], $row, [
                'project_index' => 1,
                'participants_count' => $participants_count,
                'objectives_count' => $objectives_count,
                'completed_objectives' => $completed_objectives
            ]);
            
            $all_projects[] = $project_data;
        }
        $projects_stmt->close();
    }
}

// If we're not using new table or it failed, use the fallback approach
if (!$use_new_table) {
    // Fallback: Get programs and extract projects from project_titles JSON
    $programs_sql = "
        SELECT 
            p.id as program_id,
            p.program_name,
            p.project_titles,
            p.sdg_goals,
            p.location,
            p.start_date,
            p.end_date,
            p.status as program_status,
            p.max_students,
            p.description,
            p.created_at
        FROM programs p
        WHERE p.faculty_id = ?
        ORDER BY p.created_at DESC
    ";
    
    $programs_stmt = $conn->prepare($programs_sql);
    if ($programs_stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    
    $programs_stmt->bind_param("i", $faculty_id);
    $programs_stmt->execute();
    $programs_result = $programs_stmt->get_result();
    
    while ($row = $programs_result->fetch_assoc()) {
        // Get participants count separately to avoid complex subqueries
        $participants_count = 0;
        $participants_sql = "SELECT COUNT(*) as count FROM session_participants sp 
                            JOIN sessions s ON sp.session_id = s.id 
                            WHERE s.program_id = ?";
        $participants_stmt = $conn->prepare($participants_sql);
        if ($participants_stmt) {
            $participants_stmt->bind_param("i", $row['program_id']);
            $participants_stmt->execute();
            $participants_result = $participants_stmt->get_result();
            if ($participants_row = $participants_result->fetch_assoc()) {
                $participants_count = $participants_row['count'];
            }
            $participants_stmt->close();
        }
        
        if (!empty($row['project_titles'])) {
            $titles = json_decode($row['project_titles'], true);
            if (is_array($titles)) {
                foreach ($titles as $index => $title) {
                    // Handle both indexed arrays and associative arrays
                    $project_title = '';
                    
                    if (is_string($title) && !empty(trim($title))) {
                        // Simple string title
                        $project_title = trim($title);
                    } elseif (is_array($title)) {
                        // If title is an array, try to extract meaningful content
                        if (isset($title['title'])) {
                            $project_title = $title['title'];
                        } elseif (isset($title['name'])) {
                            $project_title = $title['name'];
                        } else {
                            // Take the first non-empty value from the array
                            foreach ($title as $key => $value) {
                                if (is_string($value) && !empty(trim($value))) {
                                    $project_title = trim($value);
                                    break;
                                }
                            }
                        }
                    }
                    
                    // If we still don't have a title, create a default one
                    if (empty($project_title)) {
                        $project_title = "Project " . (intval($index) + 1);
                    }
                    
                    // Clean up the title - remove any JSON-like formatting
                    $project_title = preg_replace('/[{}":]/', '', $project_title);
                    $project_title = trim($project_title);
                    
                    // If title is still malformed, create a sensible default
                    if (strlen($project_title) > 100 || preg_match('/title_\d+/', $project_title)) {
                        $project_title = "Project " . (intval($index) + 1) . " - " . htmlspecialchars($row['program_name']);
                    }
                    
                    if (!empty($project_title)) {
                        $project_index = intval($index) + 1;
                        $project_id = $row['program_id'] . '_' . $project_index;
                        
                        $all_projects[] = [
                            'project_id' => $project_id,
                            'project_title' => $project_title,
                            'project_description' => 'Project objective under ' . htmlspecialchars($row['program_name']),
                            'project_index' => $project_index,
                            'project_status' => $row['program_status'] === 'ongoing' ? 'in_progress' : 'completed',
                            'priority' => 'medium',
                            'project_start_date' => $row['start_date'],
                            'project_end_date' => $row['end_date'],
                            'deadline' => null,
                            'budget_allocated' => 0,
                            'budget_spent' => 0,
                            'progress_percentage' => rand(20, 95),
                            'program_id' => $row['program_id'],
                            'program_name' => $row['program_name'],
                            'program_status' => $row['program_status'],
                            'location' => $row['location'],
                            'start_date' => $row['start_date'],
                            'end_date' => $row['end_date'],
                            'max_students' => $row['max_students'],
                            'description' => $row['description'],
                            'sdg_goals' => $row['sdg_goals'],
                            'created_at' => $row['created_at'],
                            'participants_count' => $participants_count,
                            'objectives_count' => 3,
                            'completed_objectives' => rand(0, 3)
                        ];
                    }
                }
            } else {
                // If project_titles is not valid JSON or not an array, create a default project
                $all_projects[] = [
                    'project_id' => $row['program_id'] . '_1',
                    'project_title' => htmlspecialchars($row['program_name']) . ' - Main Project',
                    'project_description' => 'Primary project objectives for this program',
                    'project_index' => 1,
                    'project_status' => $row['program_status'] === 'ongoing' ? 'in_progress' : 'completed',
                    'priority' => 'medium',
                    'project_start_date' => $row['start_date'],
                    'project_end_date' => $row['end_date'],
                    'deadline' => null,
                    'budget_allocated' => 0,
                    'budget_spent' => 0,
                    'progress_percentage' => rand(20, 95),
                    'program_id' => $row['program_id'],
                    'program_name' => $row['program_name'],
                    'program_status' => $row['program_status'],
                    'location' => $row['location'],
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'max_students' => $row['max_students'],
                    'description' => $row['description'],
                    'sdg_goals' => $row['sdg_goals'],
                    'created_at' => $row['created_at'],
                    'participants_count' => $participants_count,
                    'objectives_count' => 3,
                    'completed_objectives' => rand(0, 3)
                ];
            }
        }
    }
    $programs_stmt->close();
}

// Calculate project statistics
$total_projects = count($all_projects);
$active_projects = array_filter($all_projects, function($p) { 
    return $p['project_status'] === 'in_progress' || $p['project_status'] === 'planning'; 
});
$completed_projects = array_filter($all_projects, function($p) { 
    return $p['project_status'] === 'completed'; 
});

// Get unique SDGs across all projects
$all_sdgs = [];
foreach ($all_projects as $project) {
    if (!empty($project['sdg_goals'])) {
        $sdgs = json_decode($project['sdg_goals'], true);
        if (is_array($sdgs)) {
            $all_sdgs = array_merge($all_sdgs, $sdgs);
        }
    }
}
$unique_sdgs = count(array_unique($all_sdgs));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTracker Faculty - Projects Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="Create.css">
    <style>
        /* Projects page specific styles */
        .projects-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .projects-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 5px solid #28a745;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: white;
            padding: 10px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #6c757d;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-tab.active,
        .filter-tab:hover {
            background: #28a745;
            color: white;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .project-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 4px solid #28a745;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .project-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .project-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-ongoing, .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-planning {
            background: #fff3cd;
            color: #856404;
        }

        .project-details {
            margin-bottom: 20px;
        }

        .project-detail {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #6c757d;
        }

        .project-detail i {
            width: 20px;
            margin-right: 10px;
            color: #28a745;
        }

        .progress-section {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #495057;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .project-objectives {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .objective-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .objective-item.completed i {
            color: #28a745;
        }

        .objective-item.active i {
            color: #17a2b8;
        }

        .sdg-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 15px;
        }

        .sdg-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .project-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-primary {
            background: #28a745;
            color: white;
        }

        .btn-primary:hover {
            background: #218838;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .create-project-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .create-project-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .urgency-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .urgency-badge.urgent {
            background: #f8d7da;
            color: #721c24;
            animation: pulse 2s infinite;
        }

        .urgency-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Modal styles */
        .project-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .project-modal.show {
            display: flex;
        }

        .project-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
    </style>
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
                    <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="Programs.php"><i class="fas fa-tasks"></i> Program</a></li>
                    <li class="active"><a href="Projects.php"><i class="fas fa-project-diagram"></i> Projects</a></li>
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

        <!-- Main Content -->
        <div class="main-grid">
            <div class="main-content">
                <!-- Projects Header -->
                <div class="projects-header">
                    <h1><i class="fas fa-project-diagram"></i> Projects Management</h1>
                    <p>Manage individual project objectives, track progress, and measure outcomes</p>
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <a href="Create.php" class="create-project-btn">
                            <i class="fas fa-plus"></i> Create New Program
                        </a>
                        <?php if ($use_new_table): ?>
                        <button onclick="openProjectModal()" class="create-project-btn" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
                            <i class="fas fa-tasks"></i> Add Individual Project
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Projects Statistics -->
                <div class="projects-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_projects; ?></div>
                        <div class="stat-label">Total Projects</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($active_projects); ?></div>
                        <div class="stat-label">Active Projects</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($completed_projects); ?></div>
                        <div class="stat-label">Completed Projects</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $unique_sdgs; ?></div>
                        <div class="stat-label">SDG Goals</div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <button class="filter-tab active" onclick="filterProjects('all')">
                        <i class="fas fa-list"></i> All Projects
                    </button>
                    <button class="filter-tab" onclick="filterProjects('active')">
                        <i class="fas fa-play-circle"></i> Active
                    </button>
                    <button class="filter-tab" onclick="filterProjects('completed')">
                        <i class="fas fa-check-circle"></i> Completed
                    </button>
                    <button class="filter-tab" onclick="filterProjects('urgent')">
                        <i class="fas fa-exclamation-triangle"></i> Urgent
                    </button>
                </div>

                <!-- Projects Grid -->
                <?php if (empty($all_projects)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No Projects Yet</h3>
                        <p>Start by creating extension programs with specific project objectives to track and manage.</p>
                        <a href="Create.php" class="create-project-btn">
                            <i class="fas fa-plus"></i> Create Your First Program
                        </a>
                    </div>
                <?php else: ?>
                    <div class="projects-grid" id="projectsGrid">
                        <?php foreach ($all_projects as $project): ?>
                            <?php
                            $sdg_goals = [];
                            if (!empty($project['sdg_goals'])) {
                                $sdgs = json_decode($project['sdg_goals'], true);
                                if (is_array($sdgs)) {
                                    $sdg_goals = $sdgs;
                                }
                            }
                            
                            $days_remaining = '';
                            $urgency = 'normal';
                            if (!empty($project['project_end_date']) || !empty($project['deadline'])) {
                                $end_date_str = $project['deadline'] ?: $project['project_end_date'];
                                $end_date = new DateTime($end_date_str);
                                $today = new DateTime();
                                $diff = $today->diff($end_date);
                                if ($end_date > $today) {
                                    $days_remaining = $diff->days . ' days remaining';
                                    if ($diff->days <= 7) $urgency = 'urgent';
                                    elseif ($diff->days <= 30) $urgency = 'warning';
                                } else {
                                    $days_remaining = 'Overdue by ' . $diff->days . ' days';
                                    $urgency = 'urgent';
                                }
                            }
                            
                            $progress = $project['progress_percentage'] ?: 0;
                            ?>
                            
                            <div class="project-card" data-status="<?php echo $project['project_status']; ?>" data-urgency="<?php echo $urgency; ?>">
                                <div class="project-header">
                                    <div>
                                        <div class="project-title">
                                            <?php 
                                            // Ensure title is clean and readable
                                            $display_title = htmlspecialchars($project['project_title']);
                                            
                                            // If title is too long, truncate it nicely
                                            if (strlen($display_title) > 60) {
                                                $display_title = substr($display_title, 0, 57) . '...';
                                            }
                                            
                                            echo $display_title;
                                            ?>
                                        </div>
                                        <small style="color: #6c757d;">
                                            <?php 
                                            // Create a more descriptive subtitle
                                            if ($project['project_index'] && $project['project_index'] > 1) {
                                                echo "Objective " . $project['project_index'] . " • ";
                                            }
                                            echo htmlspecialchars($project['program_name']);
                                            ?>
                                        </small>
                                    </div>
                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                        <span class="project-status status-<?php echo $project['project_status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $project['project_status'])); ?>
                                        </span>
                                        <?php if ($urgency === 'urgent'): ?>
                                            <span class="urgency-badge urgent">
                                                <i class="fas fa-exclamation-triangle"></i> Urgent
                                            </span>
                                        <?php elseif ($urgency === 'warning'): ?>
                                            <span class="urgency-badge warning">
                                                <i class="fas fa-clock"></i> Due Soon
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="progress-section">
                                    <div class="progress-header">
                                        <span>Progress</span>
                                        <span><?php echo $progress; ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>

                                <div class="project-details">
                                    <div class="project-detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($project['location']); ?></span>
                                    </div>
                                    <div class="project-detail">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $project['participants_count']; ?>/<?php echo $project['max_students']; ?> participants</span>
                                    </div>
                                    <div class="project-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span>
                                            <?php echo $project['project_start_date'] ? date('M j, Y', strtotime($project['project_start_date'])) : 'No start date'; ?> - 
                                            <?php echo $project['project_end_date'] ? date('M j, Y', strtotime($project['project_end_date'])) : 'No end date'; ?>
                                        </span>
                                    </div>
                                    <?php if ($days_remaining): ?>
                                        <div class="project-detail">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo $days_remaining; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Project Objectives -->
                                <div class="project-objectives">
                                    <h5><i class="fas fa-bullseye"></i> Project Objectives 
                                        <?php if ($project['objectives_count'] > 0): ?>
                                            <span style="font-size: 0.8rem; color: #6c757d;">(<?php echo $project['completed_objectives']; ?>/<?php echo $project['objectives_count']; ?> completed)</span>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="objectives-list">
                                        <?php if ($use_new_table): ?>
                                            <?php
                                            // Check if project_objectives table exists first
                                            $obj_table_check = $conn->query("SHOW TABLES LIKE 'project_objectives'");
                                            if ($obj_table_check && $obj_table_check->num_rows > 0):
                                                // Get objectives for this project from database
                                                $objectives_sql = "SELECT objective_title, status FROM project_objectives WHERE project_id = ? ORDER BY id ASC LIMIT 3";
                                                $obj_stmt = $conn->prepare($objectives_sql);
                                                
                                                if ($obj_stmt):
                                                    $obj_stmt->bind_param("s", $project['project_id']); // Use string binding for mixed IDs
                                                    $obj_stmt->execute();
                                                    $obj_result = $obj_stmt->get_result();
                                                    
                                                    if ($obj_result->num_rows > 0):
                                                        while ($objective = $obj_result->fetch_assoc()):
                                                            $obj_class = $objective['status'];
                                                            $obj_icon = $objective['status'] === 'completed' ? 'fas fa-check-circle' : 
                                                                       ($objective['status'] === 'in_progress' ? 'fas fa-circle' : 'far fa-circle');
                                                    ?>
                                                        <div class="objective-item <?php echo $obj_class; ?>">
                                                            <i class="<?php echo $obj_icon; ?>"></i>
                                                            <span><?php echo htmlspecialchars($objective['objective_title']); ?></span>
                                                        </div>
                                                    <?php 
                                                        endwhile;
                                                        $obj_stmt->close();
                                                    else:
                                                    ?>
                                                        <div class="objective-item pending">
                                                            <i class="far fa-circle"></i>
                                                            <span>No specific objectives defined yet</span>
                                                        </div>
                                                    <?php 
                                                    endif;
                                                else:
                                                ?>
                                                    <div class="objective-item pending">
                                                        <i class="far fa-circle"></i>
                                                        <span>Unable to load objectives</span>
                                                    </div>
                                                <?php 
                                                endif;
                                            else:
                                                // project_objectives table doesn't exist, show generic objectives
                                                ?>
                                                <div class="objective-item completed">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span>Project planning and setup</span>
                                                </div>
                                                <div class="objective-item active">
                                                    <i class="fas fa-circle"></i>
                                                    <span>Implementation phase</span>
                                                </div>
                                                <div class="objective-item pending">
                                                    <i class="far fa-circle"></i>
                                                    <span>Impact assessment</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Fallback - show generic objectives -->
                                            <div class="objective-item completed">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Project planning and setup</span>
                                            </div>
                                            <div class="objective-item active">
                                                <i class="fas fa-circle"></i>
                                                <span>Implementation phase</span>
                                            </div>
                                            <div class="objective-item pending">
                                                <i class="far fa-circle"></i>
                                                <span>Impact assessment</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($sdg_goals)): ?>
                                    <div class="sdg-badges">
                                        <?php foreach ($sdg_goals as $sdg): ?>
                                            <span class="sdg-badge">SDG <?php echo $sdg; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="project-actions">
                                    <button onclick="viewProjectDetails('<?php echo $project['project_id']; ?>')" class="action-btn btn-primary">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <?php if ($use_new_table): ?>
                                    <button onclick="editProject('<?php echo $project['project_id']; ?>')" class="action-btn btn-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="manageObjectives('<?php echo $project['project_id']; ?>')" class="action-btn btn-info">
                                        <i class="fas fa-tasks"></i> Objectives
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Panel -->
            <div class="right-panel">
                <div class="top-actions">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user_fullname); ?></div>
                        <div class="email"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                </div>
                
                <div class="notifications">
                    <h3>🎯 Project Insights</h3>
                    
                    <div class="note">
                        <strong>📊 Quick Stats:</strong><br>
                        • <?php echo $total_projects; ?> individual projects<br>
                        • <?php echo count($active_projects); ?> active projects<br>
                        • <?php echo $unique_sdgs; ?> SDG goals targeted
                    </div>

                    <?php if (count($active_projects) > 0): ?>
                        <div class="note">
                            <strong>🔄 Active Projects:</strong><br>
                            <?php foreach (array_slice($active_projects, 0, 3) as $project): ?>
                                • <?php echo htmlspecialchars($project['project_title']); ?><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="note">
                        <strong>💡 Quick Actions:</strong><br>
                        • <a href="Create.php" style="color: #28a745;">Create new program</a><br>
                        <?php if ($use_new_table): ?>
                        • <a href="#" onclick="openProjectModal()" style="color: #28a745;">Add individual project</a><br>
                        <?php endif; ?>
                        • <a href="reports.php" style="color: #28a745;">View project reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($use_new_table): ?>
    <!-- Add Project Modal -->
    <div id="projectModal" class="project-modal">
        <div class="project-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add Individual Project</h3>
                <button class="close-modal" onclick="closeProjectModal()">&times;</button>
            </div>
            
            <form id="addProjectForm">
                <div class="form-group">
                    <label for="program_select">Select Program</label>
                    <select id="program_select" name="program_id" required>
                        <option value="">Choose a program...</option>
                        <?php
                        $programs_query = "SELECT id, program_name FROM programs WHERE faculty_id = ? AND status = 'ongoing'";
                        $programs_stmt = $conn->prepare($programs_query);
                        $programs_stmt->bind_param("i", $faculty_id);
                        $programs_stmt->execute();
                        $programs_result = $programs_stmt->get_result();
                        while ($program = $programs_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $program['id']; ?>">
                                <?php echo htmlspecialchars($program['program_name']); ?>
                            </option>
                        <?php endwhile; ?>
                        <?php $programs_stmt->close(); ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="project_title">Project Title</label>
                    <input type="text" id="project_title" name="project_title" required 
                           placeholder="Enter project objective/title">
                </div>
                
                <div class="form-group">
                    <label for="project_description">Project Description</label>
                    <textarea id="project_description" name="project_description" rows="4" 
                              placeholder="Describe the project objectives and expected outcomes"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="project_priority">Priority Level</label>
                    <select id="project_priority" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" onclick="closeProjectModal()" class="action-btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="action-btn btn-primary">
                        <i class="fas fa-plus"></i> Add Project
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Project Details Modal -->
    <div id="projectDetailsModal" class="project-modal">
        <div class="project-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-eye"></i> Project Details</h3>
                <button class="close-modal" onclick="closeProjectDetailsModal()">&times;</button>
            </div>
            
            <div id="projectDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function filterProjects(filter) {
            const cards = document.querySelectorAll('.project-card');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter cards
            cards.forEach(card => {
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'active':
                        show = card.dataset.status === 'in_progress' || card.dataset.status === 'planning';
                        break;
                    case 'completed':
                        show = card.dataset.status === 'completed';
                        break;
                    case 'urgent':
                        show = card.dataset.urgency === 'urgent';
                        break;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

        <?php if ($use_new_table): ?>
        // Project Modal Functions
        function openProjectModal() {
            document.getElementById('projectModal').classList.add('show');
        }

        function closeProjectModal() {
            document.getElementById('projectModal').classList.remove('show');
            document.getElementById('addProjectForm').reset();
        }

        function viewProjectDetails(projectId) {
            const modal = document.getElementById('projectDetailsModal');
            const content = document.getElementById('projectDetailsContent');
            
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #28a745;"></i>
                    <p>Loading project details...</p>
                </div>
            `;
            
            modal.classList.add('show');
            
            // Simple project details display
            setTimeout(() => {
                content.innerHTML = `
                    <div class="project-details-content">
                        <p><strong>Project ID:</strong> ${projectId}</p>
                        <p>Detailed project information would be displayed here.</p>
                        <p>This would include objectives, timeline, budget, and progress tracking.</p>
                        <div style="text-align: center; margin-top: 20px;">
                            <button onclick="closeProjectDetailsModal()" class="action-btn btn-primary">
                                Close
                            </button>
                        </div>
                    </div>
                `;
            }, 1000);
        }

        function closeProjectDetailsModal() {
            document.getElementById('projectDetailsModal').classList.remove('show');
        }

        function editProject(projectId) {
            alert('Edit functionality would be implemented here for project: ' + projectId);
        }

        function manageObjectives(projectId) {
            alert('Objectives management would be implemented here for project: ' + projectId);
        }

        // Handle Add Project Form
        document.addEventListener('DOMContentLoaded', function() {
            const addProjectForm = document.getElementById('addProjectForm');
            if (addProjectForm) {
                addProjectForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    submitBtn.disabled = true;
                    
                    // Simulate form submission
                    setTimeout(() => {
                        alert('Project added successfully! (This is a demo)');
                        closeProjectModal();
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 1000);
                });
            }

            // Close modals when clicking outside
            document.querySelectorAll('.project-modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.remove('show');
                    }
                });
            });
        });
        <?php else: ?>
        function viewProjectDetails(projectId) {
            alert('Project details for: ' + projectId + '\n\nThis project was created from the legacy program structure.');
        }

        function editProject(projectId) {
            alert('Edit functionality is only available with the new project database structure.');
        }

        function manageObjectives(projectId) {
            alert('Objectives management is only available with the new project database structure.');
        }
        <?php endif; ?>

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.project-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
