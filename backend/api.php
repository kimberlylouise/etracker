<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? '';

// Get all programs
if ($action === 'programs') {

    $result = $conn->query("SELECT * FROM programs WHERE faculty_certificate_issued = 0");
    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }
    echo json_encode($programs);
    exit;
}

// Get all students or faculty
if ($action === 'users') {
    $role = $_GET['role'] ?? '';
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $res = $stmt->get_result();
    $users = [];
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode($users);
    exit;
}

// Get eligible participants for certificate
if ($action === 'eligible_for_certificate') {
    $program_id = $_GET['program_id'];

    // Get pending student certificates
    $sql_students = "
        SELECT 
            p.id AS cert_id,
            p.student_name AS name,
            pr.program_name,
            p.certificate_issued,
            p.issued_on,
            'student' AS type,
            pr.id AS program_id,
            'Student' AS role,
            p.evaluated
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE p.status = 'accepted'
          AND pr.id = ?
          AND pr.status = 'ended'
          AND (p.certificate_issued = 0 OR p.certificate_issued IS NULL)
    ";

    // Get pending faculty certificates
    $sql_faculty = "
        SELECT 
            NULL AS cert_id,
            CONCAT(u.firstname, ' ', u.lastname) AS name,
            pr.program_name,
            pr.faculty_certificate_issued AS certificate_issued,
            pr.faculty_certificate_issued_on AS issued_on,
            'faculty' AS type,
            pr.id AS program_id,
            'Faculty' AS role,
            NULL AS evaluated
        FROM programs pr
        JOIN faculty f ON pr.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE pr.faculty_id IS NOT NULL
          AND pr.id = ?
          AND pr.status = 'ended'
          AND (pr.faculty_certificate_issued = 0 OR pr.faculty_certificate_issued IS NULL)
    ";

    // Execute student query
    $stmt = $conn->prepare($sql_students);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed (students)', 'sql' => $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $eligible_students = [];
    while ($row = $res->fetch_assoc()) {
        $eligible_students[] = $row;
    }
    $stmt->close();

    // Debug: Log the count of students found
    error_log("Found " . count($eligible_students) . " eligible students");

    // Execute faculty query
    $stmt = $conn->prepare($sql_faculty);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed (faculty)', 'sql' => $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $eligible_faculty = [];
    while ($row = $res->fetch_assoc()) {
        $eligible_faculty[] = $row;
    }
    $stmt->close();

    // Return both sets of results
    echo json_encode([
        'students' => $eligible_students,
        'faculty' => $eligible_faculty
    ]);
    exit;
}

// Issue certificate (set certificate_issued = 1)
if ($action === 'issue_certificate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $participant_id = $_POST['participant_id'];

    // Get participant and program info
    $stmt = $conn->prepare("
        SELECT p.student_name, pr.program_name
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $stmt->bind_result($student_name, $program_name);
    $stmt->fetch();
    $stmt->close();

    // Generate PDF
    require_once __DIR__ . '/fpdf186/fpdf.php';

    // Create portrait A4 PDF (single page)
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false); // Disable automatic page breaks

    // Elegant outer border with shadow effect
    $pdf->SetLineWidth(2.0);
    $pdf->SetDrawColor(27, 71, 43); // Deep green
    $pdf->Rect(12, 12, 186, 273, 'D');
    
    // Inner decorative border
    $pdf->SetLineWidth(0.8);
    $pdf->SetDrawColor(184, 134, 11); // Gold
    $pdf->Rect(18, 18, 174, 261, 'D');
    
    // Subtle inner frame
    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(100, 100, 100); // Gray
    $pdf->Rect(22, 22, 166, 253, 'D');

    // Header background rectangle
    $pdf->SetFillColor(27, 71, 43); // Green background
    $pdf->Rect(22, 22, 166, 35, 'F');

    // Add logo if exists
    if (file_exists(__DIR__ . '/logo.png')) {
        $pdf->Image(__DIR__ . '/logo.png', 27, 27, 25); // left, top, width
    }

    // University header in white on green background
    $pdf->SetY(30);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->Cell(0, 8, 'CAVITE STATE UNIVERSITY - IMUS CAMPUS', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, 'Extension Services Office', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0); // Reset to black
    $pdf->Ln(15);

    // Elegant title with gold background
    $pdf->SetFillColor(248, 243, 227); // Light gold background
    $pdf->Rect(50, $pdf->GetY() - 3, 110, 20, 'F');
    
    $pdf->SetFont('Arial', 'B', 26);
    $pdf->SetTextColor(184, 134, 11); // Gold text
    $pdf->Cell(0, 12, 'CERTIFICATION', 0, 1, 'C');
    
    // Decorative ornamental line
    $pdf->SetLineWidth(1.5);
    $pdf->SetDrawColor(184, 134, 11);
    $pdf->Line(70, $pdf->GetY() + 2, 140, $pdf->GetY() + 2);
    // Small decorative diamonds
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetXY(66, $pdf->GetY() - 1);
    $pdf->Cell(4, 4, '♦', 0, 0, 'C');
    $pdf->SetXY(140, $pdf->GetY());
    $pdf->Cell(4, 4, '♦', 0, 0, 'C');
    
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->SetTextColor(80, 80, 80); // Dark gray
    $pdf->Cell(0, 6, 'Summary of Extension Workload Hours Accomplished', 0, 1, 'C');
    $pdf->Ln(12);

    // Ornamental flourish before main text
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 8, '⬥ ⬥ ⬥', 0, 1, 'C');
    $pdf->Ln(5);

    // Main body text with better typography
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, "This is to certify that", 0, 1, 'C');
    $pdf->Ln(6);
    
    // Student name with elegant styling
    $pdf->SetFillColor(240, 248, 255); // Very light blue
    $pdf->Rect(30, $pdf->GetY() - 2, 150, 12, 'F');
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(27, 71, 43); // Green
    $pdf->Cell(0, 10, strtoupper($student_name), 0, 1, 'C');
    $pdf->Ln(4);
    
    // Body text continuation
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(60, 60, 60); // Dark gray
    $pdf->Cell(0, 5, "from the Department of Computer Studies, Cavite State University – Imus Campus,", 0, 1, 'C');
    $pdf->Cell(0, 5, "has actively participated in and accomplished the required Extension workload hours", 0, 1, 'C');
    $pdf->Cell(0, 5, "in the officially recognized Extension activities/programs/projects as detailed below:", 0, 1, 'C');
    $pdf->Ln(10);

    // Program information in elegant box
    $pdf->SetFillColor(250, 250, 250); // Very light gray
    $pdf->SetDrawColor(200, 200, 200); // Light gray border
    $pdf->Rect(35, $pdf->GetY(), 140, 25, 'DF');
    
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(27, 71, 43);
    $pdf->Cell(0, 8, "Program Title:", 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, $program_name, 0, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, "Period: From _______________  To _______________", 0, 1, 'C');
    $pdf->Ln(12);

    // Elegant table design
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(27, 71, 43); // Green header
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetDrawColor(27, 71, 43);
    $pdf->SetLineWidth(0.5);
    
    // Table header with proper spacing
    $pdf->Cell(55, 10, 'Student Name', 1, 0, 'C', true);
    $pdf->Cell(55, 10, 'Program Title', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Role', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Hours', 1, 1, 'C', true);

    // Table content with alternating colors
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(248, 250, 252); // Very light blue
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(55, 10, $student_name, 1, 0, 'C', true);
    $pdf->Cell(55, 10, $program_name, 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Participant', 1, 0, 'C', true);
    $pdf->Cell(30, 10, '40', 1, 1, 'C', true);

    $pdf->Ln(10);

    // Total hours with decorative styling
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(27, 71, 43);
    $pdf->Cell(0, 8, 'Total Department Extension Hours Rendered: ________________', 0, 1, 'C');
    $pdf->Ln(12);

    // Certification statement in justified format
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(0, 5, "This certification is issued for record purposes and submission to the Office of the", 0, 1, 'C');
    $pdf->Cell(0, 5, "Extension Services and the Campus Extension Coordinator.", 0, 1, 'C');
    $pdf->Ln(8);
    
    // Current date with proper formatting
    $current_date = date('jS \d\a\y \o\f F, Y');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, "Issued this {$current_date} at Cavite State University – Imus Campus, Imus City, Cavite.", 0, 1, 'C');

    // Decorative bottom flourish
    $pdf->SetY(-80);
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, '⬥ ⬥ ⬥', 0, 1, 'C');
    $pdf->Ln(8);

    // Signature section with elegant formatting
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Certified by:', 0, 1, 'C');
    $pdf->Ln(18);
    
    // Elegant signature line
    $pdf->SetLineWidth(0.8);
    $pdf->SetDrawColor(27, 71, 43);
    $pdf->Line(75, $pdf->GetY(), 135, $pdf->GetY());
    $pdf->Ln(4);
    
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(27, 71, 43);
    $pdf->Cell(0, 6, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Chair, Department of Computer Studies', 0, 1, 'C');

    // Save PDF
    $certDir = __DIR__ . '/../certificates/';
    if (!is_dir($certDir)) mkdir($certDir, 0777, true);
    $file_path = "certificates/certificate_{$participant_id}.pdf";
    $pdf->Output('F', __DIR__ . '/../' . $file_path);

    // Update DB with file path
    $stmt = $conn->prepare("UPDATE participants SET certificate_issued = 1, issued_on = NOW(), certificate_file = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed', 'sql' => $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $file_path, $participant_id);
    $stmt->execute();

    // Update program's faculty certificate status
    $stmt = $conn->prepare("UPDATE programs SET faculty_certificate_issued = 1, faculty_certificate_file = ?, faculty_certificate_issued_on = NOW() WHERE id = ?");
    $stmt->bind_param("si", $file_path, $program_id);
    $stmt->execute();

    echo json_encode(['message' => 'Certificate issued!', 'file' => $file_path]);
    exit;
}


// Issue faculty certificate (set faculty_certificate_issued = 1)
if ($action === 'issue_faculty_certificate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = $_POST['program_id'];

    // Get faculty and program info
    $stmt = $conn->prepare("
        SELECT u.firstname, u.lastname, p.program_name
        FROM programs p
        JOIN faculty f ON p.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname, $program_name);
    $stmt->fetch();
    $stmt->close();

    $faculty_name = $firstname . ' ' . $lastname;

    // Generate PDF
    require_once __DIR__ . '/fpdf186/fpdf.php';
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false); // Disable automatic page breaks

    // Elegant outer border with shadow effect
    $pdf->SetLineWidth(2.0);
    $pdf->SetDrawColor(27, 71, 43); // Deep green
    $pdf->Rect(12, 12, 186, 273, 'D');
    
    // Inner decorative border
    $pdf->SetLineWidth(0.8);
    $pdf->SetDrawColor(184, 134, 11); // Gold
    $pdf->Rect(18, 18, 174, 261, 'D');
    
    // Subtle inner frame
    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(100, 100, 100); // Gray
    $pdf->Rect(22, 22, 166, 253, 'D');

    // Header background rectangle
    $pdf->SetFillColor(27, 71, 43); // Green background
    $pdf->Rect(22, 22, 166, 35, 'F');

    // Add logo if exists
    if (file_exists(__DIR__ . '/logo.png')) {
        $pdf->Image(__DIR__ . '/logo.png', 27, 27, 25); // left, top, width
    }

    // University header in white on green background
    $pdf->SetY(30);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->Cell(0, 8, 'CAVITE STATE UNIVERSITY - IMUS CAMPUS', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 6, 'Extension Services Office', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0); // Reset to black
    $pdf->Ln(15);

    // Elegant title with gold background
    $pdf->SetFillColor(248, 243, 227); // Light gold background
    $pdf->Rect(50, $pdf->GetY() - 3, 110, 20, 'F');
    
    $pdf->SetFont('Arial', 'B', 26);
    $pdf->SetTextColor(184, 134, 11); // Gold text
    $pdf->Cell(0, 12, 'CERTIFICATION', 0, 1, 'C');
    
    // Decorative ornamental line
    $pdf->SetLineWidth(1.5);
    $pdf->SetDrawColor(184, 134, 11);
    $pdf->Line(70, $pdf->GetY() + 2, 140, $pdf->GetY() + 2);
    // Small decorative diamonds
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetXY(66, $pdf->GetY() - 1);
    $pdf->Cell(4, 4, '♦', 0, 0, 'C');
    $pdf->SetXY(140, $pdf->GetY());
    $pdf->Cell(4, 4, '♦', 0, 0, 'C');
    
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->SetTextColor(80, 80, 80); // Dark gray
    $pdf->Cell(0, 6, 'Summary of Extension Workload Hours Accomplished', 0, 1, 'C');
    $pdf->Ln(10);

    // Ornamental flourish before main text
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 8, '⬥ ⬥ ⬥', 0, 1, 'C');
    $pdf->Ln(5);

    // Body text with better typography
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(60, 60, 60); // Dark gray
    $pdf->Cell(0, 5, "This is to certify that the faculty members listed below from the Department of Computer Studies,", 0, 1, 'C');
    $pdf->Cell(0, 5, "Cavite State University – Imus Campus, have participated in and accomplished the corresponding", 0, 1, 'C');
    $pdf->Cell(0, 5, "number of Extension workload hours rendered in the officially recognized Extension", 0, 1, 'C');
    $pdf->Cell(0, 5, "activities/programs/projects for the period:", 0, 1, 'C');
    $pdf->Ln(8);

    // Faculty name with elegant styling
    $pdf->SetFillColor(240, 248, 255); // Very light blue
    $pdf->Rect(30, $pdf->GetY() - 2, 150, 12, 'DF');
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(27, 71, 43); // Green
    $pdf->Cell(0, 10, strtoupper($faculty_name), 0, 1, 'C');
    $pdf->Ln(8);

    // Period info in elegant box
    $pdf->SetFillColor(250, 250, 250); // Very light gray
    $pdf->SetDrawColor(200, 200, 200); // Light gray border
    $pdf->Rect(50, $pdf->GetY(), 110, 15, 'DF');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, "From: _____________  To: _______________", 0, 1, 'C');
    $pdf->Ln(10);

    // Elegant table header
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(27, 71, 43); // Green header
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetDrawColor(27, 71, 43);
    $pdf->SetLineWidth(0.5);
    
    $pdf->Cell(50, 10, 'Faculty Member', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Extension Activity/Project', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Role/Designation', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Hours Rendered', 1, 1, 'C', true);

    // Table content with alternating colors
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetFillColor(248, 250, 252); // Very light blue
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(50, 10, $faculty_name, 1, 0, 'C', true);
    $pdf->Cell(50, 10, $program_name, 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Coordinator', 1, 0, 'C', true);
    $pdf->Cell(35, 10, '40 Hours', 1, 1, 'C', true);

    $pdf->Ln(10);

    // Total hours with decorative styling
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(27, 71, 43);
    $pdf->Cell(0, 8, 'Total Department Extension Hours Rendered: ________________', 0, 1, 'C');
    $pdf->Ln(10);

    // Certification statement
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(0, 5, "This certification is issued for record purposes and submission to the Office of the Extension", 0, 1, 'C');
    $pdf->Cell(0, 5, "Services and the Campus Extension Coordinator.", 0, 1, 'C');
    $pdf->Ln(8);
    
    // Current date with proper formatting
    $current_date = date('jS \d\a\y \o\f F, Y');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, "Issued this {$current_date} at Cavite State University - Imus Campus, Imus City, Cavite.", 0, 1, 'C');

    // Decorative bottom flourish
    $pdf->SetY(-80);
    $pdf->SetTextColor(184, 134, 11);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, '⬥ ⬥ ⬥', 0, 1, 'C');
    $pdf->Ln(8);

    // Signature section with elegant formatting
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Certified by:', 0, 1, 'C');
    $pdf->Ln(18);
    
    // Elegant signature line
    $pdf->SetLineWidth(0.8);
    $pdf->SetDrawColor(27, 71, 43);
    $pdf->Line(75, $pdf->GetY(), 135, $pdf->GetY());
    $pdf->Ln(4);
    
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->SetTextColor(27, 71, 43);
    $pdf->Cell(0, 6, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Chair, Department of Computer Studies', 0, 1, 'C');
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 6, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Chair, Department of Computer Studies', 0, 1, 'C');

    // Save PDF with unique name per program
    $certDir = __DIR__ . '/../certificates/';
    if (!is_dir($certDir)) mkdir($certDir, 0777, true);
    $file_path = "certificates/faculty_certificate_{$program_id}.pdf";
    $pdf->Output('F', __DIR__ . '/../' . $file_path);

    // Update DB with file path and new issued_on date
    $stmt = $conn->prepare("UPDATE programs SET faculty_certificate_issued = 1, faculty_certificate_file = ?, faculty_certificate_issued_on = NOW() WHERE id = ?");
    $stmt->bind_param("si", $file_path, $program_id);
    $stmt->execute();

    echo json_encode(['message' => 'Faculty certificate issued!', 'file' => $file_path]);
    exit;
}

// List all certificate records
if ($action === 'list_certificates') {
    $sql = "
        SELECT 
            p.id AS cert_id,
            p.student_name AS name,
            pr.program_name,
            p.certificate_issued,
            p.issued_on,
            'student' AS type,
            pr.id AS program_id
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE p.status = 'accepted'
        AND pr.status = 'ended'

        UNION ALL

        SELECT 
            NULL AS cert_id,
            CONCAT(u.firstname, ' ', u.lastname) AS name,
            pr.program_name,
            pr.faculty_certificate_issued AS certificate_issued,
            pr.faculty_certificate_issued_on AS issued_on,
            'faculty' AS type,
            pr.id AS program_id
        FROM programs pr
        JOIN faculty f ON pr.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE pr.faculty_id IS NOT NULL
        AND pr.status = 'ended'
    ";
    $result = $conn->query($sql);
    $certs = [];
    while ($row = $result->fetch_assoc()) {
        $certs[] = $row;
    }
    echo json_encode($certs);
    exit;
}

if ($action === 'programs_with_faculty') {
    $sql = "
        SELECT p.id AS program_id, p.program_name, f.id AS faculty_id, u.firstname, u.lastname
        FROM programs p
        JOIN faculty f ON p.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE p.faculty_id IS NOT NULL
          AND p.status = 'ended'
    ";
    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
    exit;
}

if ($action === 'verify_certificate') {
    $cert_id = $_GET['cert_id'];
    $type = $_GET['type'];
    $program_id = $_GET['program_id'];
    
    $filepath = __DIR__ . '/../certificates/' . 
        ($type === 'faculty' ? "faculty_certificate_{$program_id}.pdf" : "certificate_{$cert_id}.pdf");
    
    if (file_exists($filepath)) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }
    exit;
}

// Example: Get all faculty for a program and their extension work
if ($action === 'faculty_for_program') {
    $program_id = $_GET['program_id']; // from POST or GET

    $sql = "
        SELECT 
            CONCAT(u.firstname, ' ', u.lastname) AS faculty_name,
            pr.program_name,
            pr.start_date,
            pr.end_date,
            pr.department,
            f.role AS designation,
            f.hours_rendered,
            pr.implementation_dates
        FROM programs pr
        JOIN faculty f ON pr.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE pr.id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $faculty_rows = [];
    $total_hours = 0;
    while ($row = $res->fetch_assoc()) {
        $faculty_rows[] = $row;
        $total_hours += $row['hours_rendered'];
    }
    $stmt->close();


    // Generate PDF for faculty extension work
    require_once __DIR__ . '/fpdf186/fpdf.php';
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Add border
    $pdf->SetLineWidth(1.5);
    $pdf->Rect(10, 10, 277, 190, 'D');

    // Header
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 12, 'CAVITE STATE UNIVERSITY IMUS CAMPUS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 8, 'Extension Services Office', 0, 1, 'C');
    $pdf->Ln(5);

    // Title
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->Cell(0, 15, 'CERTIFICATION', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, 'Summary of Extension Workload Hours Accomplished', 0, 1, 'C');
    $pdf->Ln(8);

    // Body
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, "This is to certify that the student member/s listed below from the Department of Computer Studies, Cavite State University – Imus Campus, have participated in and accomplished the corresponding number of Extension workload hours rendered in the officially recognized Extension activities/programs/projects for the period:\n\nFrom: _____________    To: _______________", 0, 'L');
    $pdf->Ln(4);

    // Table header for student
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(80, 8, 'Student Name', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'Program Title', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Role', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'No. of Hours', 1, 0, 'C', true);
    $pdf->Cell(37, 8, 'Dates of Participation', 1, 1, 'C', true);

    // Example row (replace with real data if available)
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(80, 8, $student_name, 1, 0, 'C');
    $pdf->Cell(80, 8, $program_name, 1, 0, 'C');
    $pdf->Cell(40, 8, 'Participant', 1, 0, 'C');
    $pdf->Cell(40, 8, '40', 1, 0, 'C');
    $pdf->Cell(37, 8, 'Jan 10 - Feb 20, 2025', 1, 1, 'C');

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Total Department Extension Hours Rendered: ________________', 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, "This certification is issued for record purposes and submission to the Office of the Extension Services and the Campus Extension Coordinator.", 0, 'C');
    $pdf->Ln(4);

    $pdf->Cell(0, 8, "Issued this _____ day of _______________, 2025 at Cavite State University - Imus Campus, Imus City, Cavite.", 0, 1, 'C');
    $pdf->Ln(16);

    // Signature
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Certified by:', 0, 1, 'C');
    $pdf->Ln(12);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Chair, Department of Computer Studies', 0, 1, 'C');

    // Save PDF
    $certDir = __DIR__ . '/../certificates/';
    if (!is_dir($certDir)) mkdir($certDir, 0777, true);
    $file_path = "certificates/extension_summary_certificate.pdf";
    $pdf->Output('F', __DIR__ . '/../' . $file_path);

    // Update DB with file path
    $stmt = $conn->prepare("UPDATE participants SET certificate_issued = 1, issued_on = NOW(), certificate_file = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed', 'sql' => $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $file_path, $participant_id);
    $stmt->execute();

    // Update program's faculty certificate status
    $stmt = $conn->prepare("UPDATE programs SET faculty_certificate_issued = 1, faculty_certificate_file = ?, faculty_certificate_issued_on = NOW() WHERE id = ?");
    $stmt->bind_param("si", $file_path, $program_id);
    $stmt->execute();

    echo json_encode(['message' => 'Certificate issued!', 'file' => $file_path]);
    exit;
}

if ($action === 'regenerate_certificate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $participant_id = $_POST['participant_id'];

    // Get participant and program info
    $stmt = $conn->prepare("
        SELECT p.student_name, pr.program_name
        FROM participants p
        JOIN programs pr ON p.program_id = pr.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $stmt->bind_result($student_name, $program_name);
    $stmt->fetch();
    $stmt->close();

    // Generate PDF
    require_once __DIR__ . '/fpdf186/fpdf.php';

    // Create landscape A4 PDF
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Add border
    $pdf->SetLineWidth(0.3);
    $pdf->Rect(10, 10, 277, 190, 'D');

    // Header
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 12, 'CAVITE STATE UNIVERSITY - IMUS CAMPUS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 8, 'Extension Services Office', 0, 1, 'C');
    $pdf->Ln(5);

    // Title
    $pdf->SetFont('Arial', 'B', 22);
    $pdf->Cell(0, 15, 'CERTIFICATION', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, 'Summary of Extension Workload Hours Accomplished', 0, 1, 'C');
    $pdf->Ln(8);

    // Body (centered, line by line)
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, "This is to certify that", 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, "{$student_name}", 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, "from the Department of Computer Studies, Cavite State University – Imus Campus,", 0, 1, 'C');
    $pdf->Cell(0, 8, "has actively participated in and accomplished the required Extension workload hours", 0, 1, 'C');
    $pdf->Cell(0, 8, "in the officially recognized Extension activities/programs/projects as detailed below:", 0, 1, 'C');
    $pdf->Ln(4);
    $pdf->Cell(0, 8, "Program Title: {$program_name}", 0, 1, 'C');
    $pdf->Cell(0, 8, "Period: From _______________    To _______________", 0, 1, 'C');
    $pdf->Ln(4);

    // Table header (centered)
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(80, 8, 'Student Name', 1, 0, 'C', true);
    $pdf->Cell(80, 8, 'Program Title', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Role', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'No. of Hours', 1, 0, 'C', true);
    $pdf->Cell(37, 8, 'Dates of Participation', 1, 1, 'C', true);

    // Table row (centered)
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(80, 8, $student_name, 1, 0, 'C');
    $pdf->Cell(80, 8, $program_name, 1, 0, 'C');
    $pdf->Cell(40, 8, 'Participant', 1, 0, 'C');
    $pdf->Cell(40, 8, '40', 1, 0, 'C');
    $pdf->Cell(37, 8, 'Jan 10 - Feb 20, 2025', 1, 1, 'C');

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Total Department Extension Hours Rendered: ________________', 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 8, "This certification is issued for record purposes and submission to the Office of the Extension Services and the Campus Extension Coordinator.", 0, 'C');
    $pdf->Ln(4);
    $pdf->Cell(0, 8, "Issued this _____ day of _______________, 2025 at Cavite State University - Imus Campus, Imus City, Cavite.", 0, 1, 'C');
    $pdf->Ln(16);

    // Signature (centered at bottom)
    $pdf->SetY(-45);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Certified by:', 0, 1, 'C');
    $pdf->Ln(12);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Chair, Department of Computer Studies', 0, 1, 'C');

    // Save PDF
    $certDir = __DIR__ . '/../certificates/';
    if (!is_dir($certDir)) mkdir($certDir, 0777, true);
    $file_path = "certificates/certificate_{$participant_id}.pdf";
    $pdf->Output('F', __DIR__ . '/../' . $file_path);

    // Update DB with file path and new issued_on date
    $stmt = $conn->prepare("UPDATE participants SET certificate_issued = 1, issued_on = NOW(), certificate_file = ? WHERE id = ?");
    $stmt->bind_param("si", $file_path, $participant_id);
    $stmt->execute();

    echo json_encode(['message' => 'Certificate regenerated!', 'file' => $file_path]);
    exit;
}

if ($action === 'regenerate_faculty_certificate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = $_POST['program_id'];

    // Get faculty and program info
    $stmt = $conn->prepare("
        SELECT u.firstname, u.lastname, p.program_name
        FROM programs p
        JOIN faculty f ON p.faculty_id = f.id
        JOIN users u ON f.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $program_id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname, $program_name);
    $stmt->fetch();
    $stmt->close();

    $faculty_name = $firstname . ' ' . $lastname;

    // Generate PDF (same as your issue_faculty_certificate logic)
    require_once __DIR__ . '/fpdf186/fpdf.php';
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Add border
    $pdf->SetLineWidth(0.3);
    $pdf->Rect(10, 10, 190, 277, 'D');

    // Add logo (adjust path and size as needed)
    $pdf->Image(__DIR__ . '/logo.png', 20, 15, 40); // left, top, width

    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 51, 153); // Blue
    $pdf->Cell(0, 10, 'CAVITE STATE UNIVERSITY - IMUS CAMPUS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0,0,0);
    $pdf->Cell(0, 7, 'Extension Services Office', 0, 1, 'C');
    $pdf->Ln(2);

    // Title
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'CERTIFICATION', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 7, 'Summary of Extension Workload Hours Accomplished', 0, 1, 'C');
    $pdf->Ln(5);

    // Body
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 6,
        "This is to certify that the faculty members listed below from the Department of Computer Studies, Cavite State University - Imus Campus, have participated in and accomplished the corresponding number of Extension workload hours as part of their involvement in officially recognized Extension activities/programs/projects for the period:",
        0, 'C'
    );
    $pdf->Ln(2);

    // Period lines (centered)
    $pdf->Cell(0, 7, 'From:  _________________________', 0, 1, 'C');
    $pdf->Cell(0, 7, 'To:      _________________________', 0, 1, 'C');
    $pdf->Ln(2);

    // Table header (centered, smaller font)
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->SetLineWidth(0.3);
    $pdf->Cell(38, 9, 'Name of Faculty Member', 1, 0, 'C', true);
    $pdf->Cell(38, 9, 'Extension Activity/Project Title', 1, 0, 'C', true);
    $pdf->Cell(32, 9, 'Role/Designation', 1, 0, 'C', true);
    $pdf->Cell(32, 9, 'No. of Hours Rendered', 1, 0, 'C', true);
    $pdf->Cell(38, 9, "Date/s of Implementation", 1, 1, 'C', true);

    // Table row (dynamic faculty/program)
    $pdf->SetFont('Arial', '', 9.5);
    $pdf->Cell(38, 9, $faculty_name, 1, 0, 'C');
    $pdf->Cell(38, 9, $program_name, 1, 0, 'C');
    $pdf->Cell(32, 9, 'Coordinator', 1, 0, 'C');
    $pdf->Cell(32, 9, '40', 1, 0, 'C');
    $pdf->Cell(38, 9, 'Jan 10 - Feb 20, 2025', 1, 1, 'C');

    // Add empty rows for manual fill-in (optional)
    for ($i = 0; $i < 2; $i++) {
        $pdf->Cell(38, 9, '', 1, 0, 'C');
        $pdf->Cell(38, 9, '', 1, 0, 'C');
        $pdf->Cell(32, 9, '', 1, 0, 'C');
        $pdf->Cell(32, 9, '', 1, 0, 'C');
        $pdf->Cell(38, 9, '', 1, 1, 'C');
    }

    $pdf->Ln(3);

    // Total Hours line (centered)
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Total Department Extension Hours Rendered: ________________________________________', 0, 1, 'C');
    $pdf->Ln(2);

    // Certification statement (centered)
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 6, "This certification is issued for record purposes and submission to the Office of the Extension Services and the Campus Extension Coordinator.", 0, 'C');
    $pdf->Ln(2);

    // Issued date line (centered)
    $pdf->MultiCell(0, 7, "Issued this _____ day of ____________, 2025 at Cavite State University - Imus Campus, Imus City, Cavite.", 0, 'C');
    $pdf->Ln(8);

    // Signature lines (centered)
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Certified by:', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'GRACE S. IBAÑEZ', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, 'Chair, Department of Computer Studies', 0, 1, 'C');

    // Save PDF with unique name per program
    $certDir = __DIR__ . '/../certificates/';
    if (!is_dir($certDir)) mkdir($certDir, 0777, true);
    $file_path = "certificates/faculty_certificate_{$program_id}.pdf";
    $pdf->Output('F', __DIR__ . '/../' . $file_path);

    // Update DB with file path and new issued_on date
    $stmt = $conn->prepare("UPDATE programs SET faculty_certificate_issued = 1, faculty_certificate_file = ?, faculty_certificate_issued_on = NOW() WHERE id = ?");
    $stmt->bind_param("si", $file_path, $program_id);
    $stmt->execute();

    echo json_encode(['message' => 'Faculty certificate regenerated!', 'file' => $file_path]);
    exit;
}

echo json_encode(['message' => 'Invalid action']);
exit;
?>
