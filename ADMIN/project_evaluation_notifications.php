<?php
require_once 'db.php';

// Function to create project evaluation notifications
function createProjectEvaluationNotification($project_id, $type, $message, $recipient_user_id = null) {
    global $conn;
    
    try {
        // If no recipient specified, get faculty from project
        if (!$recipient_user_id) {
            $faculty_sql = "SELECT f.user_id 
                           FROM projects pr 
                           JOIN programs p ON pr.program_id = p.id 
                           JOIN faculty f ON p.faculty_id = f.id 
                           WHERE pr.id = ?";
            $faculty_stmt = $conn->prepare($faculty_sql);
            $faculty_stmt->bind_param("i", $project_id);
            $faculty_stmt->execute();
            $faculty_result = $faculty_stmt->get_result();
            
            if ($faculty_row = $faculty_result->fetch_assoc()) {
                $recipient_user_id = $faculty_row['user_id'];
            } else {
                return false;
            }
        }
        
        // Create notification
        $notification_sql = "INSERT INTO notifications 
                            (user_id, type, title, message, related_id, created_at, expires_at) 
                            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))";
        
        $title = '';
        switch ($type) {
            case 'project_evaluation_needed':
                $title = 'Project Evaluation Required';
                break;
            case 'project_evaluation_completed':
                $title = 'Project Evaluation Completed';
                break;
            case 'project_evaluation_needs_improvement':
                $title = 'Project Needs Improvement';
                break;
            case 'project_evaluation_approved':
                $title = 'Project Approved';
                break;
            default:
                $title = 'Project Update';
        }
        
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("isssi", $recipient_user_id, $type, $title, $message, $project_id);
        
        return $notification_stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

// Function to send email notification (optional)
function sendProjectEvaluationEmail($project_id, $type, $recipient_email = null) {
    global $conn;
    
    try {
        // Get project and faculty information
        $project_sql = "SELECT 
                           pr.project_title,
                           p.program_name,
                           CONCAT(u.firstname, ' ', u.lastname) as faculty_name,
                           u.email as faculty_email,
                           pe.evaluation_status,
                           pe.overall_rating
                       FROM projects pr
                       JOIN programs p ON pr.program_id = p.id
                       JOIN faculty f ON p.faculty_id = f.id
                       JOIN users u ON f.user_id = u.id
                       LEFT JOIN project_evaluations pe ON pr.id = pe.project_id
                       WHERE pr.id = ?";
        
        $project_stmt = $conn->prepare($project_sql);
        $project_stmt->bind_param("i", $project_id);
        $project_stmt->execute();
        $project_result = $project_stmt->get_result();
        
        if ($project_row = $project_result->fetch_assoc()) {
            $email_to = $recipient_email ?: $project_row['faculty_email'];
            $faculty_name = $project_row['faculty_name'];
            $project_title = $project_row['project_title'];
            $program_name = $project_row['program_name'];
            
            $subject = '';
            $message = '';
            
            switch ($type) {
                case 'project_evaluation_needed':
                    $subject = "Project Evaluation Required - $project_title";
                    $message = "Dear $faculty_name,\n\n";
                    $message .= "Your project '$project_title' under the program '$program_name' has been completed and is now pending admin evaluation.\n\n";
                    $message .= "The admin team will review your project and provide feedback shortly.\n\n";
                    $message .= "Best regards,\nExtension Services Team";
                    break;
                    
                case 'project_evaluation_completed':
                    $status = $project_row['evaluation_status'];
                    $rating = $project_row['overall_rating'];
                    
                    $subject = "Project Evaluation Completed - $project_title";
                    $message = "Dear $faculty_name,\n\n";
                    $message .= "Your project '$project_title' has been evaluated by the admin team.\n\n";
                    $message .= "Evaluation Result: " . ucfirst(str_replace('_', ' ', $status)) . "\n";
                    if ($rating) {
                        $message .= "Overall Rating: $rating/5.0\n\n";
                    }
                    $message .= "Please log in to the system to view detailed feedback and recommendations.\n\n";
                    $message .= "Best regards,\nExtension Services Team";
                    break;
            }
            
            // Here you would integrate with your email system
            // For now, we'll just log the email content
            error_log("Email notification: To: $email_to, Subject: $subject, Message: $message");
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error sending email notification: " . $e->getMessage());
        return false;
    }
}

// Function to get project evaluation notifications for a user
function getProjectEvaluationNotifications($user_id, $limit = 10) {
    global $conn;
    
    try {
        $sql = "SELECT 
                   n.id,
                   n.type,
                   n.title,
                   n.message,
                   n.related_id as project_id,
                   n.is_read,
                   n.created_at,
                   pr.project_title,
                   p.program_name
               FROM notifications n
               LEFT JOIN projects pr ON n.related_id = pr.id
               LEFT JOIN programs p ON pr.program_id = p.id
               WHERE n.user_id = ? 
               AND n.type LIKE 'project_evaluation_%'
               AND (n.expires_at IS NULL OR n.expires_at >= NOW())
               ORDER BY n.created_at DESC
               LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
        
    } catch (Exception $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// Function to mark notification as read
function markNotificationAsRead($notification_id, $user_id) {
    global $conn;
    
    try {
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    $user_id = $_GET['user_id'] ?? null;
    
    switch ($action) {
        case 'get_notifications':
            if ($user_id) {
                $notifications = getProjectEvaluationNotifications($user_id);
                echo json_encode(['success' => true, 'data' => $notifications]);
            } else {
                echo json_encode(['success' => false, 'error' => 'User ID required']);
            }
            break;
            
        case 'mark_read':
            $notification_id = $_GET['notification_id'] ?? null;
            if ($notification_id && $user_id) {
                $success = markNotificationAsRead($notification_id, $user_id);
                echo json_encode(['success' => $success]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Notification ID and User ID required']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Function to check and create notifications for overdue evaluations
function checkOverdueEvaluations() {
    global $conn;
    
    try {
        // Find projects that are overdue for evaluation
        $sql = "SELECT 
                   pr.id as project_id,
                   pr.project_title,
                   p.program_name,
                   f.user_id as faculty_user_id
               FROM projects pr
               JOIN programs p ON pr.program_id = p.id
               JOIN faculty f ON p.faculty_id = f.id
               WHERE pr.status = 'completed'
               AND pr.evaluation_required = 1
               AND pr.evaluation_deadline < CURDATE()
               AND NOT EXISTS (
                   SELECT 1 FROM project_evaluations pe WHERE pe.project_id = pr.id
               )
               AND NOT EXISTS (
                   SELECT 1 FROM notifications n 
                   WHERE n.related_id = pr.id 
                   AND n.type = 'project_evaluation_overdue'
                   AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               )";
        
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $message = "Your project '{$row['project_title']}' evaluation is overdue. Please ensure all project documentation is complete for admin review.";
            createProjectEvaluationNotification(
                $row['project_id'],
                'project_evaluation_overdue',
                $message,
                $row['faculty_user_id']
            );
        }
        
    } catch (Exception $e) {
        error_log("Error checking overdue evaluations: " . $e->getMessage());
    }
}

// Auto-run overdue check if called directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    checkOverdueEvaluations();
    echo "Overdue evaluation check completed.\n";
}
?>
