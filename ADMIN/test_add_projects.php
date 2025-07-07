<?php
require_once 'db.php';

// Add some test projects to see in the evaluation dashboard
try {
    // First, let's check if there are any programs
    $programs_check = $conn->query("SELECT COUNT(*) as count FROM programs");
    $programs_row = $programs_check->fetch_assoc();
    
    if ($programs_row['count'] == 0) {
        echo "No programs found. Creating sample program first...\n";
        
        // Create a sample faculty user
        $faculty_check = $conn->query("SELECT COUNT(*) as count FROM faculty");
        $faculty_row = $faculty_check->fetch_assoc();
        
        if ($faculty_row['count'] == 0) {
            // Create sample user first
            $user_sql = "INSERT INTO users (firstname, lastname, email, password, role, department) 
                        VALUES ('John', 'Doe', 'john.doe@cvsu.edu.ph', 'password123', 'faculty', 'Computer Science')";
            $conn->query($user_sql);
            $user_id = $conn->insert_id;
            
            // Create sample faculty
            $faculty_sql = "INSERT INTO faculty (user_id, faculty_name, faculty_id, department, position) 
                           VALUES (?, 'John Doe', 'FAC001', 'Computer Science', 'Professor')";
            $faculty_stmt = $conn->prepare($faculty_sql);
            $faculty_stmt->bind_param("i", $user_id);
            $faculty_stmt->execute();
            $faculty_id = $conn->insert_id;
        } else {
            // Get existing faculty
            $faculty_result = $conn->query("SELECT id FROM faculty LIMIT 1");
            $faculty_row = $faculty_result->fetch_assoc();
            $faculty_id = $faculty_row['id'];
        }
        
        // Create sample program
        $program_sql = "INSERT INTO programs (program_name, department, start_date, end_date, location, max_students, description, program_level, program_category, faculty_id, status) 
                       VALUES ('Community Health Extension Program', 'Health Sciences', '2024-01-15', '2024-12-15', 'Barangay Hall', 50, 'Health awareness and medical assistance program for rural communities', 'intermediate', 'health', ?, 'ended')";
        $program_stmt = $conn->prepare($program_sql);
        $program_stmt->bind_param("i", $faculty_id);
        $program_stmt->execute();
        $program_id = $conn->insert_id;
        
    } else {
        // Get existing program
        $program_result = $conn->query("SELECT id FROM programs LIMIT 1");
        $program_row = $program_result->fetch_assoc();
        $program_id = $program_row['id'];
    }
    
    // Now check if there are any projects
    $projects_check = $conn->query("SELECT COUNT(*) as count FROM projects");
    $projects_row = $projects_check->fetch_assoc();
    
    if ($projects_row['count'] == 0) {
        echo "No projects found. Creating sample projects...\n";
        
        // Create sample projects
        $sample_projects = [
            [
                'title' => 'Health Screening and Medical Check-up Drive',
                'description' => 'Free medical check-ups and health screening for community members including blood pressure monitoring, blood sugar testing, and general wellness assessment.',
                'status' => 'completed',
                'priority' => 'high',
                'progress' => 100
            ],
            [
                'title' => 'Nutrition Education and Awareness Campaign',
                'description' => 'Educational sessions on proper nutrition, healthy eating habits, and food safety practices for families in the community.',
                'status' => 'completed', 
                'priority' => 'medium',
                'progress' => 100
            ],
            [
                'title' => 'Mental Health Support and Counseling Services',
                'description' => 'Providing mental health awareness, stress management workshops, and basic counseling services to community members.',
                'status' => 'completed',
                'priority' => 'high', 
                'progress' => 90
            ]
        ];
        
        foreach ($sample_projects as $index => $project) {
            $project_sql = "INSERT INTO projects (program_id, project_title, project_description, project_index, status, priority, start_date, end_date, budget_allocated, budget_spent, progress_percentage) 
                           VALUES (?, ?, ?, ?, ?, ?, '2024-01-15', '2024-11-30', 25000.00, 22000.00, ?)";
            
            $project_stmt = $conn->prepare($project_sql);
            $project_index = $index + 1;
            $project_stmt->bind_param("ississi", 
                $program_id, 
                $project['title'], 
                $project['description'], 
                $project_index,
                $project['status'], 
                $project['priority'], 
                $project['progress']
            );
            
            if ($project_stmt->execute()) {
                $project_id = $conn->insert_id;
                echo "Created project: " . $project['title'] . " (ID: $project_id)\n";
                
                // Add some sample objectives for each project
                $objectives = [
                    'Conduct initial community assessment',
                    'Implement main project activities', 
                    'Evaluate project outcomes and impact'
                ];
                
                foreach ($objectives as $obj_index => $objective) {
                    $obj_sql = "INSERT INTO project_objectives (project_id, objective_title, objective_description, status, priority, due_date) 
                               VALUES (?, ?, ?, 'completed', ?, '2024-11-30')";
                    $obj_stmt = $conn->prepare($obj_sql);
                    $obj_priority = $obj_index + 1;
                    $obj_stmt->bind_param("issi", $project_id, $objective, $objective, $obj_priority);
                    $obj_stmt->execute();
                }
            }
        }
        
        // Add some sample participants
        $participants_sql = "INSERT INTO participants (program_id, student_name, student_email, status) VALUES 
                            (?, 'Maria Santos', 'maria.santos@email.com', 'accepted'),
                            (?, 'Juan dela Cruz', 'juan.delacruz@email.com', 'accepted'),
                            (?, 'Ana Garcia', 'ana.garcia@email.com', 'accepted')";
        $participants_stmt = $conn->prepare($participants_sql);
        $participants_stmt->bind_param("iii", $program_id, $program_id, $program_id);
        $participants_stmt->execute();
        
        echo "Sample data created successfully!\n";
        echo "You can now test the Project Evaluation dashboard.\n";
        
    } else {
        echo "Projects already exist in the database.\n";
        echo "Current project count: " . $projects_row['count'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
