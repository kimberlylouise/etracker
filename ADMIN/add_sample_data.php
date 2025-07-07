<?php
require_once 'db.php';

try {
    // First, let's check if we have any existing data
    $programs_count = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
    $projects_count = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
    
    echo "Current data:\n";
    echo "- Programs: $programs_count\n";
    echo "- Projects: $projects_count\n\n";
    
    // Add some sample users if they don't exist
    $user_check = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'faculty'")->fetch_assoc()['count'];
    if ($user_check == 0) {
        echo "Adding sample faculty users...\n";
        $conn->query("INSERT INTO users (firstname, lastname, email, role) VALUES 
            ('John', 'Doe', 'john.doe@cvsu.edu.ph', 'faculty'),
            ('Jane', 'Smith', 'jane.smith@cvsu.edu.ph', 'faculty')");
        
        $conn->query("INSERT INTO faculty (user_id, department, position) VALUES 
            (LAST_INSERT_ID()-1, 'Computer Science', 'Professor'),
            (LAST_INSERT_ID(), 'Engineering', 'Associate Professor')");
    }
    
    // Add sample programs if they don't exist
    if ($programs_count == 0) {
        echo "Adding sample programs...\n";
        $conn->query("INSERT INTO programs (program_name, department, start_date, end_date, status, faculty_id, project_titles, description) VALUES 
            ('Community Health Program', 'Health Sciences', '2024-01-15', '2024-12-15', 'ended', 1, 
             '[\"Health Education Workshop\", \"Medical Mission\", \"Nutrition Awareness Campaign\"]',
             'A comprehensive health program for the community'),
            ('Digital Literacy Training', 'Computer Science', '2024-03-01', '2024-11-30', 'ended', 2,
             '[\"Basic Computer Skills\", \"Internet Safety Workshop\", \"Digital Tools for Seniors\"]',
             'Training program to improve digital literacy in the community')");
    }
    
    // Add sample projects if they don't exist
    if ($projects_count == 0) {
        echo "Adding sample projects...\n";
        
        // Get program IDs
        $program_result = $conn->query("SELECT id FROM programs LIMIT 2");
        $programs = [];
        while ($row = $program_result->fetch_assoc()) {
            $programs[] = $row['id'];
        }
        
        if (count($programs) >= 2) {
            $conn->query("INSERT INTO projects (program_id, project_title, project_description, status, priority, progress_percentage, budget_allocated, start_date, end_date) VALUES 
                ({$programs[0]}, 'Health Education Workshop', 'Educational workshop on health and wellness for community members', 'completed', 'high', 100, 50000, '2024-01-15', '2024-06-15'),
                ({$programs[0]}, 'Medical Mission', 'Free medical consultation and basic treatment for underserved communities', 'completed', 'high', 100, 75000, '2024-04-01', '2024-08-31'),
                ({$programs[1]}, 'Basic Computer Skills', 'Introduction to computer basics for community members', 'completed', 'medium', 100, 30000, '2024-03-01', '2024-07-31'),
                ({$programs[1]}, 'Internet Safety Workshop', 'Workshop on safe internet practices and digital security', 'completed', 'medium', 100, 25000, '2024-05-01', '2024-09-30')");
        }
    }
    
    // Add some sample participants
    $participants_count = $conn->query("SELECT COUNT(*) as count FROM participants")->fetch_assoc()['count'];
    if ($participants_count == 0 && count($programs) >= 2) {
        echo "Adding sample participants...\n";
        $conn->query("INSERT INTO participants (program_id, student_name, student_email, status) VALUES 
            ({$programs[0]}, 'Maria Santos', 'maria.santos@email.com', 'accepted'),
            ({$programs[0]}, 'Juan Dela Cruz', 'juan.delacruz@email.com', 'accepted'),
            ({$programs[1]}, 'Ana Garcia', 'ana.garcia@email.com', 'accepted'),
            ({$programs[1]}, 'Pedro Martinez', 'pedro.martinez@email.com', 'accepted')");
    }
    
    // Final count
    $programs_count = $conn->query("SELECT COUNT(*) as count FROM programs")->fetch_assoc()['count'];
    $projects_count = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
    $participants_count = $conn->query("SELECT COUNT(*) as count FROM participants")->fetch_assoc()['count'];
    
    echo "\nFinal data counts:\n";
    echo "- Programs: $programs_count\n";
    echo "- Projects: $projects_count\n";
    echo "- Participants: $participants_count\n";
    echo "\nSample data setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
