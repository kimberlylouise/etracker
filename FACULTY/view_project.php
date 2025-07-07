<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: Projects.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$program_id = intval($_GET['id']);

// Get program details with all project information
$program_sql = "
    SELECT 
        p.*,
        f.department,
        u.firstname,
        u.lastname
    FROM programs p
    LEFT JOIN faculty f ON p.faculty_id = f.id
    LEFT JOIN users u ON f.user_id = u.id
    WHERE p.id = ? AND p.user_id = ?
";
$program_stmt = $conn->prepare($program_sql);
$program_stmt->bind_param("ii", $program_id, $user_id);
$program_stmt->execute();
$program_result = $program_stmt->get_result();
$program = $program_result->fetch_assoc();
$program_stmt->close();

if (!$program) {
    header('Location: Projects.php');
    exit();
}

// Parse JSON data
$project_titles = [];
if (!empty($program['project_titles'])) {
    $titles = json_decode($program['project_titles'], true);
    if (is_array($titles)) {
        $project_titles = array_filter($titles);
    }
}

$sdg_goals = [];
if (!empty($program['sdg_goals'])) {
    $sdgs = json_decode($program['sdg_goals'], true);
    if (is_array($sdgs)) {
        $sdg_goals = $sdgs;
    }
}

$sessions = [];
if (!empty($program['sessions_data'])) {
    $sessions_data = json_decode($program['sessions_data'], true);
    if (is_array($sessions_data)) {
        $sessions = $sessions_data;
    }
}

// SDG Names for display
$sdg_names = [
    1 => "No Poverty", 2 => "Zero Hunger", 3 => "Good Health and Well-being",
    4 => "Quality Education", 5 => "Gender Equality", 6 => "Clean Water and Sanitation",
    7 => "Affordable and Clean Energy", 8 => "Decent Work and Economic Growth",
    9 => "Industry, Innovation and Infrastructure", 10 => "Reduced Inequalities",
    11 => "Sustainable Cities and Communities", 12 => "Responsible Consumption and Production",
    13 => "Climate Action", 14 => "Life Below Water", 15 => "Life on Land",
    16 => "Peace, Justice and Strong Institutions", 17 => "Partnerships for the Goals"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTracker - Project Details: <?php echo htmlspecialchars($program['program_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="Create.css">
    <style>
        .project-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .project-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .project-meta {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #28a745;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .objectives-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .objective-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
            position: relative;
        }

        .objective-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .objective-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }

        .objective-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            background: #e3f2fd;
            color: #1976d2;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .sdg-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .sdg-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .sdg-number {
            background: #28a745;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .sdg-info h4 {
            margin: 0 0 5px 0;
            color: #495057;
            font-size: 1rem;
        }

        .sdg-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .sessions-timeline {
            position: relative;
            padding-left: 30px;
        }

        .sessions-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #28a745;
        }

        .session-item {
            position: relative;
            margin-bottom: 25px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .session-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #28a745;
        }

        .session-date {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 5px;
        }

        .session-time {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .session-title {
            color: #495057;
            font-weight: 500;
        }

        .sidebar-info {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .info-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .info-value {
            color: #495057;
            font-weight: 500;
            text-align: right;
        }

        .action-buttons {
            display: grid;
            gap: 10px;
        }

        .action-btn {
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }

        .btn-primary:hover { background: #218838; color: white; }
        .btn-secondary:hover { background: #545b62; color: white; }
        .btn-info:hover { background: #138496; color: white; }
        .btn-warning:hover { background: #e0a800; color: #212529; }

        .back-btn {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #e9ecef;
            color: #495057;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .project-meta {
                flex-direction: column;
                gap: 10px;
            }
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
                <a href="Projects.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Projects
                </a>

                <!-- Project Header -->
                <div class="project-header">
                    <div class="project-title"><?php echo htmlspecialchars($program['program_name']); ?></div>
                    <p><?php echo htmlspecialchars($program['description']); ?></p>
                    
                    <div class="project-meta">
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($program['location']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M j, Y', strtotime($program['start_date'])); ?> - <?php echo date('M j, Y', strtotime($program['end_date'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $program['max_students']; ?> participants</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-university"></i>
                            <span><?php echo htmlspecialchars($program['department']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="content-grid">
                    <!-- Main Content -->
                    <div class="main-content">
                        <!-- Project Objectives -->
                        <?php if (!empty($project_titles)): ?>
                            <div class="section-card">
                                <h3 class="section-title">
                                    <i class="fas fa-bullseye"></i>
                                    Project Objectives
                                </h3>
                                <div class="objectives-list">
                                    <?php foreach ($project_titles as $index => $title): ?>
                                        <div class="objective-item">
                                            <div class="objective-header">
                                                <div>
                                                    <div class="objective-title">Objective <?php echo $index + 1; ?></div>
                                                    <div><?php echo htmlspecialchars($title); ?></div>
                                                </div>
                                                <div class="objective-status">Planning</div>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo rand(20, 80); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- SDG Goals -->
                        <?php if (!empty($sdg_goals)): ?>
                            <div class="section-card">
                                <h3 class="section-title">
                                    <i class="fas fa-globe"></i>
                                    Sustainable Development Goals
                                </h3>
                                <div class="sdg-grid">
                                    <?php foreach ($sdg_goals as $sdg): ?>
                                        <div class="sdg-item">
                                            <div class="sdg-number"><?php echo $sdg; ?></div>
                                            <div class="sdg-info">
                                                <h4>SDG <?php echo $sdg; ?></h4>
                                                <p><?php echo $sdg_names[$sdg] ?? 'Unknown Goal'; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Sessions Timeline -->
                        <?php if (!empty($sessions)): ?>
                            <div class="section-card">
                                <h3 class="section-title">
                                    <i class="fas fa-clock"></i>
                                    Program Sessions
                                </h3>
                                <div class="sessions-timeline">
                                    <?php foreach ($sessions as $session): ?>
                                        <div class="session-item">
                                            <div class="session-date">
                                                <?php echo date('F j, Y', strtotime($session['date'])); ?>
                                            </div>
                                            <div class="session-time">
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                            </div>
                                            <div class="session-title">
                                                <?php echo htmlspecialchars($session['title'] ?: 'Program Session'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="sidebar-info">
                        <!-- Program Details -->
                        <div class="info-card">
                            <h4 class="info-title">
                                <i class="fas fa-info-circle"></i>
                                Program Details
                            </h4>
                            <div class="info-item">
                                <span class="info-label">Program ID</span>
                                <span class="info-value"><?php echo $program['id']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value"><?php echo ucfirst($program['status']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Level</span>
                                <span class="info-value"><?php echo ucfirst($program['program_level']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Category</span>
                                <span class="info-value"><?php echo ucfirst($program['program_category']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Created</span>
                                <span class="info-value"><?php echo date('M j, Y', strtotime($program['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Participants Info -->
                        <div class="info-card">
                            <h4 class="info-title">
                                <i class="fas fa-users"></i>
                                Participants
                            </h4>
                            <div class="info-item">
                                <span class="info-label">Maximum</span>
                                <span class="info-value"><?php echo $program['max_students']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Male</span>
                                <span class="info-value"><?php echo $program['male_count']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Female</span>
                                <span class="info-value"><?php echo $program['female_count']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Planned</span>
                                <span class="info-value"><?php echo $program['male_count'] + $program['female_count']; ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="info-card">
                            <h4 class="info-title">
                                <i class="fas fa-tools"></i>
                                Actions
                            </h4>
                            <div class="action-buttons">
                                <a href="edit_program.php?id=<?php echo $program['id']; ?>" class="action-btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Program
                                </a>
                                <a href="Attendance.php?program_id=<?php echo $program['id']; ?>" class="action-btn btn-info">
                                    <i class="fas fa-calendar-check"></i> Attendance
                                </a>
                                <a href="reports.php?program_id=<?php echo $program['id']; ?>" class="action-btn btn-secondary">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </a>
                                <a href="certificates.php?program_id=<?php echo $program['id']; ?>" class="action-btn btn-warning">
                                    <i class="fas fa-certificate"></i> Certificates
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
