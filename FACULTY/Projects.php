<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Check if projects table exists, if not fall back to old method
$table_check = $conn->query("SHOW TABLES LIKE 'projects'");
$use_new_table = ($table_check->num_rows > 0);

if ($use_new_table) {
    // Get all projects from the new projects table
    $projects_sql = "
        SELECT 
            pr.id as project_id,
            pr.project_title,
            pr.project_description,
            pr.project_index,
            pr.status as project_status,
            pr.priority,
            pr.start_date as project_start_date,
            pr.end_date as project_end_date,
            pr.deadline,
            pr.budget_allocated,
            pr.budget_spent,
            pr.progress_percentage,
            pr.created_at as project_created_at,
            pr.updated_at,
            p.id as program_id,
            p.program_name,
            p.location,
            p.start_date as program_start_date,
            p.end_date as program_end_date,
            p.status as program_status,
            p.max_students,
            p.description as program_description,
            p.sdg_goals,
            p.created_at as program_created_at,
            COUNT(pt.id) as participants_count,
            COUNT(po.id) as objectives_count,
            COUNT(CASE WHEN po.status = 'completed' THEN 1 END) as completed_objectives
        FROM projects pr
        JOIN programs p ON pr.program_id = p.id
        LEFT JOIN participants pt ON p.id = pt.program_id
        LEFT JOIN project_objectives po ON pr.id = po.project_id
        WHERE p.faculty_id = ?
        GROUP BY pr.id
        ORDER BY pr.created_at DESC
    ";
    $projects_stmt = $conn->prepare($projects_sql);
    $projects_stmt->bind_param("i", $faculty_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
} else {
    // Fall back to old method - parsing from project_titles JSON
    $projects_sql = "
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
            p.created_at,
            COUNT(pt.id) as participants_count
        FROM programs p
        LEFT JOIN participants pt ON p.id = pt.program_id
        WHERE p.faculty_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ";
    $projects_stmt = $conn->prepare($projects_sql);
    $projects_stmt->bind_param("i", $faculty_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
}

// Fetch all projects with real database data or fallback
$all_projects = [];
while ($row = $projects_result->fetch_assoc()) {
    if ($use_new_table) {
        // Using new projects table
        $all_projects[] = [
            'project_id' => $row['project_id'],
            'project_title' => $row['project_title'],
            'project_description' => $row['project_description'],
            'project_index' => $row['project_index'],
            'project_status' => $row['project_status'],
            'priority' => $row['priority'],
            'project_start_date' => $row['project_start_date'],
            'project_end_date' => $row['project_end_date'],
            'deadline' => $row['deadline'],
            'budget_allocated' => $row['budget_allocated'],
            'budget_spent' => $row['budget_spent'],
            'progress_percentage' => $row['progress_percentage'],
            'program_id' => $row['program_id'],
            'program_name' => $row['program_name'],
            'program_status' => $row['program_status'],
            'location' => $row['location'],
            'start_date' => $row['program_start_date'],
            'end_date' => $row['program_end_date'],
            'max_students' => $row['max_students'],
            'description' => $row['program_description'],
            'sdg_goals' => $row['sdg_goals'],
            'created_at' => $row['project_created_at'],
            'participants_count' => $row['participants_count'],
            'objectives_count' => $row['objectives_count'],
            'completed_objectives' => $row['completed_objectives']
        ];
    } else {
        // Fallback to old method - parse JSON
        if (!empty($row['project_titles'])) {
            $titles = json_decode($row['project_titles'], true);
            if (is_array($titles)) {
                foreach ($titles as $index => $title) {
                    if (!empty(trim($title))) {
                        $project_index = intval($index) + 1;
                        $project_id = $row['program_id'] . '_' . strval($project_index);
                        
                        $all_projects[] = [
                            'project_id' => $project_id,
                            'project_title' => trim($title),
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
                            'participants_count' => $row['participants_count'],
                            'objectives_count' => 0,
                            'completed_objectives' => 0
                        ];
                    }
                }
            }
        }
    }
}
$projects_stmt->close();

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
        /* Additional styles for Projects page */
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

        .status-ongoing {
            background: #d4edda;
            color: #155724;
        }

        .status-ended {
            background: #f8d7da;
            color: #721c24;
        }

        .status-planning {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-on_hold {
            background: #f8d7da;
            color: #721c24;
        }

        .status-cancelled {
            background: #f5c6cb;
            color: #721c24;
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

        .project-titles {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .project-titles h5 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-item {
            background: white;
            padding: 8px 12px;
            margin-bottom: 5px;
            border-radius: 5px;
            border-left: 3px solid #28a745;
            font-size: 0.9rem;
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

        .btn-secondary:hover {
            background: #545b62;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
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

        .empty-state h3 {
            color: #495057;
            margin-bottom: 15px;
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

        /* Enhanced Project Management Styles */
        .urgency-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 3px;
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

        .project-objectives h5 {
            color: #495057;
            margin-bottom: 12px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .objectives-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .objective-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .objective-item:hover {
            background: #e9ecef;
        }

        .objective-item.completed {
            color: #155724;
            border-left: 3px solid #28a745;
        }

        .objective-item.completed i {
            color: #28a745;
        }

        .objective-item.active {
            color: #0c5460;
            border-left: 3px solid #17a2b8;
        }

        .objective-item.active i {
            color: #17a2b8;
        }

        .objective-item.pending {
            color: #6c757d;
            border-left: 3px solid #dee2e6;
        }

        .objective-item.pending i {
            color: #dee2e6;
        }

        /* Project Modal Styles */
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
            backdrop-filter: blur(5px);
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .modal-header h3 {
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: #f8f9fa;
            color: #495057;
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
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        /* Additional styles for objectives manager */
        .objectives-manager .objective-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .objectives-manager .objective-item.completed {
            border-left-color: #28a745;
            background: #f8fff9;
        }

        .objectives-manager .objective-item.in_progress {
            border-left-color: #17a2b8;
            background: #f0fcff;
        }

        .objectives-manager .objective-item.delayed {
            border-left-color: #dc3545;
            background: #fff5f5;
        }

        .objective-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .objective-title {
            font-weight: 500;
            color: #2c3e50;
        }

        .objective-desc {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .objective-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .status-select {
            padding: 4px 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.8rem;
            background: white;
        }

        .edit-objective-btn,
        .delete-objective-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .edit-objective-btn {
            background: #17a2b8;
            color: white;
        }

        .edit-objective-btn:hover {
            background: #138496;
        }

        .delete-objective-btn {
            background: #dc3545;
            color: white;
        }

        .delete-objective-btn:hover {
            background: #c82333;
        }

        .form-row {
            display: grid;
            gap: 15px;
            margin-bottom: 15px;
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
                        <button onclick="openProjectModal()" class="create-project-btn" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
                            <i class="fas fa-tasks"></i> Add Individual Project
                        </button>
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
                            if ($project['project_end_date'] || $project['deadline']) {
                                $end_date = new DateTime($project['deadline'] ?: $project['project_end_date']);
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
                                            <?php echo htmlspecialchars($project['project_title']); ?>
                                        </div>
                                        <small style="color: #6c757d;">
                                            Project <?php echo $project['project_index']; ?> of <?php echo htmlspecialchars($project['program_name']); ?>
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
                                        <span><?php echo $project['project_start_date'] ? date('M j, Y', strtotime($project['project_start_date'])) : 'No start date'; ?> - <?php echo $project['project_end_date'] ? date('M j, Y', strtotime($project['project_end_date'])) : 'No end date'; ?></span>
                                    </div>
                                    <?php if ($days_remaining): ?>
                                        <div class="project-detail">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo $days_remaining; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Project Objectives/Tasks -->
                                <div class="project-objectives">
                                    <h5><i class="fas fa-bullseye"></i> Project Objectives 
                                        <?php if ($project['objectives_count'] > 0): ?>
                                            <span style="font-size: 0.8rem; color: #6c757d;">(<?php echo $project['completed_objectives']; ?>/<?php echo $project['objectives_count']; ?> completed)</span>
                                        <?php endif; ?>
                                    </h5>
                                    <div class="objectives-list">
                                        <?php
                                        if ($use_new_table) {
                                            // Get objectives for this project from database
                                            $objectives_sql = "SELECT objective_title, status FROM project_objectives WHERE project_id = ? ORDER BY priority ASC LIMIT 3";
                                            $obj_stmt = $conn->prepare($objectives_sql);
                                            $obj_stmt->bind_param("i", $project['project_id']);
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
                                            <?php endif;
                                        } else {
                                            // Fallback - show generic objectives
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
                                        <?php } ?>
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
                                    <button onclick="editProject('<?php echo $project['project_id']; ?>')" class="action-btn btn-secondary">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button onclick="manageObjectives('<?php echo $project['project_id']; ?>')" class="action-btn btn-info">
                                        <i class="fas fa-tasks"></i> Objectives
                                    </button>
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
                        • <a href="#" onclick="openProjectModal()" style="color: #28a745;">Add individual project</a><br>
                        • <a href="reports.php" style="color: #28a745;">View project reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Individual Project Modal -->
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

    <!-- Edit Project Modal -->
    <div id="editProjectModal" class="project-modal">
        <div class="project-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Project</h3>
                <button class="close-modal" onclick="closeEditProjectModal()">&times;</button>
            </div>
            
            <div id="editProjectContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Objectives Management Modal -->
    <div id="objectivesModal" class="project-modal">
        <div class="project-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tasks"></i> Manage Objectives</h3>
                <button class="close-modal" onclick="closeObjectivesModal()">&times;</button>
            </div>
            
            <div id="objectivesContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

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
                        show = card.dataset.urgency === 'urgent' || card.dataset.urgency === 'warning';
                        break;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }

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
            
            // Check if new table structure exists first
            if (typeof window.useNewTable !== 'undefined' && !window.useNewTable) {
                content.innerHTML = `
                    <div class="project-details-content">
                        <h4>Project Details</h4>
                        <p><strong>Note:</strong> This is using the legacy project system.</p>
                        <p>For full project details functionality, please set up the new project database structure.</p>
                        <p><strong>Project ID:</strong> ${projectId}</p>
                        
                        <div class="timeline-section">
                            <h5>Actions Available:</h5>
                            <p>• Contact administrator to set up new project tables</p>
                            <p>• View basic project information in the main grid</p>
                            <p>• Create new programs with project objectives</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            // Load project data from API
            fetch(`api_projects.php?id=${projectId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.project) {
                        const project = data.project;
                        const completionRate = project.objectives_count > 0 ? 
                            Math.round((project.completed_objectives / project.objectives_count) * 100) : 0;
                        
                        content.innerHTML = `
                            <div class="project-details-content">
                                <h4>${project.project_title}</h4>
                                <p><strong>Program:</strong> ${project.program_name}</p>
                                <p><strong>Status:</strong> <span class="status-${project.status}">${project.status.replace('_', ' ').toUpperCase()}</span></p>
                                <p><strong>Priority:</strong> ${project.priority.toUpperCase()}</p>
                                <p><strong>Progress:</strong> ${project.progress_percentage || 0}%</p>
                                
                                ${project.project_description ? `<p><strong>Description:</strong><br>${project.project_description}</p>` : ''}
                                
                                <div class="objectives-section">
                                    <h5>Project Objectives (${project.completed_objectives || 0}/${project.objectives_count || 0} completed):</h5>
                                    ${project.objectives && project.objectives.length > 0 ? 
                                        '<ul>' + project.objectives.map(obj => 
                                            `<li class="objective-${obj.status}">${obj.objective_title} - ${obj.status.toUpperCase()}</li>`
                                        ).join('') + '</ul>' :
                                        '<p>No objectives defined yet.</p>'
                                    }
                                </div>
                                
                                <div class="timeline-section">
                                    <h5>Timeline:</h5>
                                    <div class="timeline-item">
                                        <span class="timeline-date">Start Date:</span>
                                        <span>${project.start_date || 'Not set'}</span>
                                    </div>
                                    <div class="timeline-item">
                                        <span class="timeline-date">End Date:</span>
                                        <span>${project.end_date || 'Not set'}</span>
                                    </div>
                                    <div class="timeline-item">
                                        <span class="timeline-date">Deadline:</span>
                                        <span>${project.deadline || 'Not set'}</span>
                                    </div>
                                </div>
                                
                                ${project.budget_allocated > 0 ? `
                                    <div class="budget-section">
                                        <h5>Budget:</h5>
                                        <p>Allocated: ₱${parseFloat(project.budget_allocated).toLocaleString()}</p>
                                        <p>Spent: ₱${parseFloat(project.budget_spent || 0).toLocaleString()}</p>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    } else {
                        content.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #dc3545;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Error loading project details: ${data.error || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading project details. Please try again.</p>
                            <small>Error: ${error.message}</small>
                        </div>
                    `;
                });
        }

        function closeProjectDetailsModal() {
            document.getElementById('projectDetailsModal').classList.remove('show');
        }

        function editProject(projectId) {
            const modal = document.getElementById('editProjectModal');
            const content = document.getElementById('editProjectContent');
            
            // Show loading
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #28a745;"></i>
                    <p>Loading project details...</p>
                </div>
            `;
            
            modal.classList.add('show');
            
            // Load project data
            fetch(`api_projects.php?id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.project) {
                        const project = data.project;
                        content.innerHTML = `
                            <form id="editProjectForm" data-project-id="${projectId}">
                                <div class="form-group">
                                    <label for="edit_project_title">Project Title</label>
                                    <input type="text" id="edit_project_title" name="project_title" 
                                           value="${project.project_title}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_project_description">Project Description</label>
                                    <textarea id="edit_project_description" name="project_description" rows="4">${project.project_description || ''}</textarea>
                                </div>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label for="edit_status">Status</label>
                                        <select id="edit_status" name="status">
                                            <option value="planning" ${project.status === 'planning' ? 'selected' : ''}>Planning</option>
                                            <option value="in_progress" ${project.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                            <option value="completed" ${project.status === 'completed' ? 'selected' : ''}>Completed</option>
                                            <option value="on_hold" ${project.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                                            <option value="cancelled" ${project.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_priority">Priority</label>
                                        <select id="edit_priority" name="priority">
                                            <option value="low" ${project.priority === 'low' ? 'selected' : ''}>Low</option>
                                            <option value="medium" ${project.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                            <option value="high" ${project.priority === 'high' ? 'selected' : ''}>High</option>
                                            <option value="urgent" ${project.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label for="edit_start_date">Start Date</label>
                                        <input type="date" id="edit_start_date" name="start_date" 
                                               value="${project.start_date || ''}">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_end_date">End Date</label>
                                        <input type="date" id="edit_end_date" name="end_date" 
                                               value="${project.end_date || ''}">
                                    </div>
                                </div>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label for="edit_deadline">Deadline</label>
                                        <input type="date" id="edit_deadline" name="deadline" 
                                               value="${project.deadline || ''}">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_progress">Progress (%)</label>
                                        <input type="number" id="edit_progress" name="progress_percentage" 
                                               min="0" max="100" value="${project.progress_percentage || 0}">
                                    </div>
                                </div>
                                
                                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label for="edit_budget_allocated">Budget Allocated (₱)</label>
                                        <input type="number" id="edit_budget_allocated" name="budget_allocated" 
                                               step="0.01" min="0" value="${project.budget_allocated || 0}">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_budget_spent">Budget Spent (₱)</label>
                                        <input type="number" id="edit_budget_spent" name="budget_spent" 
                                               step="0.01" min="0" value="${project.budget_spent || 0}">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" onclick="closeEditProjectModal()" class="action-btn btn-secondary">
                                        Cancel
                                    </button>
                                    <button type="submit" class="action-btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        `;
                        
                        // Add form submission handler
                        document.getElementById('editProjectForm').addEventListener('submit', handleEditProjectSubmit);
                    } else {
                        content.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #dc3545;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Error loading project: ${data.error || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading project. Please try again.</p>
                        </div>
                    `;
                });
        }

        function manageObjectives(projectId) {
            const modal = document.getElementById('objectivesModal');
            const content = document.getElementById('objectivesContent');
            
            // Show loading
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #28a745;"></i>
                    <p>Loading objectives...</p>
                </div>
            `;
            
            modal.classList.add('show');
            
            // Load project objectives
            fetch(`api_projects.php?id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.project) {
                        const project = data.project;
                        const objectives = project.objectives || [];
                        
                        content.innerHTML = `
                            <div class="objectives-manager">
                                <div class="project-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                    <h5 style="margin: 0; color: #495057;">${project.project_title}</h5>
                                    <small style="color: #6c757d;">Program: ${project.program_name}</small>
                                </div>
                                
                                <div class="objectives-list">
                                    <h5>Current Objectives:</h5>
                                    <div id="objectivesList">
                                        ${objectives.length > 0 ? 
                                            objectives.map(obj => `
                                                <div class="objective-item ${obj.status}" data-objective-id="${obj.id}">
                                                    <div class="objective-content">
                                                        <i class="${obj.status === 'completed' ? 'fas fa-check-circle' : 
                                                                   obj.status === 'in_progress' ? 'fas fa-circle' : 'far fa-circle'}"></i>
                                                        <span class="objective-title">${obj.objective_title}</span>
                                                        ${obj.objective_description ? `<small class="objective-desc">${obj.objective_description}</small>` : ''}
                                                    </div>
                                                    <div class="objective-actions">
                                                        <select class="status-select" onchange="updateObjectiveStatus(${obj.id}, this.value)">
                                                            <option value="not_started" ${obj.status === 'not_started' ? 'selected' : ''}>Not Started</option>
                                                            <option value="in_progress" ${obj.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                                            <option value="completed" ${obj.status === 'completed' ? 'selected' : ''}>Completed</option>
                                                            <option value="delayed" ${obj.status === 'delayed' ? 'selected' : ''}>Delayed</option>
                                                        </select>
                                                        <button onclick="editObjective(${obj.id}, '${obj.objective_title}', '${obj.objective_description || ''}')" 
                                                                class="edit-objective-btn">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="deleteObjective(${obj.id})" class="delete-objective-btn">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            `).join('') :
                                            '<p style="text-align: center; color: #6c757d; padding: 20px;">No objectives defined yet.</p>'
                                        }
                                    </div>
                                </div>
                                
                                <div class="add-objective" style="border-top: 1px solid #e9ecef; padding-top: 20px; margin-top: 20px;">
                                    <h5>Add New Objective:</h5>
                                    <form id="addObjectiveForm" data-project-id="${projectId}">
                                        <div class="form-group">
                                            <input type="text" id="new_objective_title" placeholder="Enter objective title..." 
                                                   style="margin-bottom: 10px;" required>
                                        </div>
                                        <div class="form-group">
                                            <textarea id="new_objective_description" placeholder="Enter objective description (optional)..." 
                                                      rows="2" style="margin-bottom: 10px;"></textarea>
                                        </div>
                                        <button type="submit" class="action-btn btn-primary">
                                            <i class="fas fa-plus"></i> Add Objective
                                        </button>
                                    </form>
                                </div>
                            </div>
                        `;
                        
                        // Add form handler
                        document.getElementById('addObjectiveForm').addEventListener('submit', handleAddObjective);
                    } else {
                        content.innerHTML = `
                            <div style="text-align: center; padding: 20px; color: #dc3545;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Error loading objectives: ${data.error || 'Unknown error'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #dc3545;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading objectives. Please try again.</p>
                        </div>
                    `;
                });
        }

        function closeObjectivesModal() {
            document.getElementById('objectivesModal').classList.remove('show');
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.remove('show');
        }

        // Handle edit project form submission
        function handleEditProjectSubmit(e) {
            e.preventDefault();
            
            const form = e.target;
            const projectId = form.dataset.projectId;
            const formData = new FormData(form);
            
            const projectData = {
                project_id: projectId,
                project_title: formData.get('project_title'),
                project_description: formData.get('project_description'),
                status: formData.get('status'),
                priority: formData.get('priority'),
                start_date: formData.get('start_date'),
                end_date: formData.get('end_date'),
                deadline: formData.get('deadline'),
                progress_percentage: parseInt(formData.get('progress_percentage')),
                budget_allocated: parseFloat(formData.get('budget_allocated')),
                budget_spent: parseFloat(formData.get('budget_spent'))
            };
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;
            
            fetch('api_projects.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(projectData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Project updated successfully!');
                    closeEditProjectModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to update project'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating project. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Handle add objective form submission
        function handleAddObjective(e) {
            e.preventDefault();
            
            const form = e.target;
            const projectId = form.dataset.projectId;
            const title = document.getElementById('new_objective_title').value;
            const description = document.getElementById('new_objective_description').value;
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            fetch('api_objectives.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    objective_title: title,
                    objective_description: description
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the objectives list
                    manageObjectives(projectId);
                } else {
                    alert('Error: ' + (data.error || 'Failed to add objective'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding objective. Please try again.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Update objective status
        function updateObjectiveStatus(objectiveId, status) {
            fetch('api_objectives.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    objective_id: objectiveId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the visual status
                    const objectiveItem = document.querySelector(`[data-objective-id="${objectiveId}"]`);
                    if (objectiveItem) {
                        objectiveItem.className = `objective-item ${status}`;
                        const icon = objectiveItem.querySelector('i');
                        if (status === 'completed') {
                            icon.className = 'fas fa-check-circle';
                        } else if (status === 'in_progress') {
                            icon.className = 'fas fa-circle';
                        } else {
                            icon.className = 'far fa-circle';
                        }
                    }
                } else {
                    alert('Error updating objective status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating objective status');
            });
        }

        // Edit objective
        function editObjective(objectiveId, currentTitle, currentDescription) {
            const newTitle = prompt('Edit objective title:', currentTitle);
            if (newTitle && newTitle !== currentTitle) {
                fetch('api_objectives.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        objective_id: objectiveId,
                        objective_title: newTitle,
                        objective_description: currentDescription
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the title in the DOM
                        const titleElement = document.querySelector(`[data-objective-id="${objectiveId}"] .objective-title`);
                        if (titleElement) {
                            titleElement.textContent = newTitle;
                        }
                    } else {
                        alert('Error updating objective');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating objective');
                });
            }
        }

        // Delete objective
        function deleteObjective(objectiveId) {
            if (confirm('Are you sure you want to delete this objective?')) {
                fetch(`api_objectives.php?id=${objectiveId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove from DOM
                        const objectiveItem = document.querySelector(`[data-objective-id="${objectiveId}"]`);
                        if (objectiveItem) {
                            objectiveItem.remove();
                        }
                    } else {
                        alert('Error deleting objective');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting objective');
                });
            }
        }

        // Handle Add Project Form
        document.getElementById('addProjectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const projectData = {
                program_id: formData.get('program_id'),
                project_title: formData.get('project_title'),
                project_description: formData.get('project_description'),
                priority: formData.get('priority')
            };
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            // Send to API (only if new table structure exists)
            if (typeof window.useNewTable === 'undefined') {
                // Check if new table structure exists
                fetch('api_projects.php?check=1')
                    .then(response => response.json())
                    .then(data => {
                        window.useNewTable = data.exists;
                        submitProject();
                    })
                    .catch(() => {
                        window.useNewTable = false;
                        submitProject();
                    });
            } else {
                submitProject();
            }
            
            function submitProject() {
                if (window.useNewTable) {
                    fetch('api_projects.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(projectData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Project added successfully!');
                            closeProjectModal();
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.error || 'Failed to add project'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error adding project. Please try again.');
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
                } else {
                    // Fallback - show message that new project structure is needed
                    alert('Please set up the new project database structure first. Contact your administrator.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }
        });

        // Close modals when clicking outside
        document.querySelectorAll('.project-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

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
