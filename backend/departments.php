<?php
require_once 'db.php';

// Set content type for JSON response
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // Return hardcoded departments since we don't have a departments table
                $departments = [
                    ['id' => 1, 'name' => 'Department of Hospitality Management', 'code' => 'DHM', 'status' => 'active'],
                    ['id' => 2, 'name' => 'Department of Language and Mass Communication', 'code' => 'DLMC', 'status' => 'active'],
                    ['id' => 3, 'name' => 'Department of Physical Education', 'code' => 'DPE', 'status' => 'active'],
                    ['id' => 4, 'name' => 'Department of Social Sciences and Humanities', 'code' => 'DSSH', 'status' => 'active'],
                    ['id' => 5, 'name' => 'Teacher Education Department', 'code' => 'TED', 'status' => 'active'],
                    ['id' => 6, 'name' => 'Department of Administration - ENTREP', 'code' => 'DA-E', 'status' => 'active'],
                    ['id' => 7, 'name' => 'Department of Administration - BSOA', 'code' => 'DA-BSOA', 'status' => 'active'],
                    ['id' => 8, 'name' => 'Department of Administration - BM', 'code' => 'DA-BM', 'status' => 'active'],
                    ['id' => 9, 'name' => 'Department of Computer Studies', 'code' => 'DCS', 'status' => 'active']
                ];
                echo json_encode($departments);
                break;
                
            case 'options':
                // Get departments for dropdown options
                $options = [
                    ['value' => 'Department of Hospitality Management', 'text' => 'Department of Hospitality Management', 'code' => 'DHM'],
                    ['value' => 'Department of Language and Mass Communication', 'text' => 'Department of Language and Mass Communication', 'code' => 'DLMC'],
                    ['value' => 'Department of Physical Education', 'text' => 'Department of Physical Education', 'code' => 'DPE'],
                    ['value' => 'Department of Social Sciences and Humanities', 'text' => 'Department of Social Sciences and Humanities', 'code' => 'DSSH'],
                    ['value' => 'Teacher Education Department', 'text' => 'Teacher Education Department', 'code' => 'TED'],
                    ['value' => 'Department of Administration - ENTREP', 'text' => 'Department of Administration - ENTREP', 'code' => 'DA-E'],
                    ['value' => 'Department of Administration - BSOA', 'text' => 'Department of Administration - BSOA', 'code' => 'DA-BSOA'],
                    ['value' => 'Department of Administration - BM', 'text' => 'Department of Administration - BM', 'code' => 'DA-BM'],
                    ['value' => 'Department of Computer Studies', 'text' => 'Department of Computer Studies', 'code' => 'DCS']
                ];
                echo json_encode($options);
                break;
                
            case 'stats':
                if (isset($_GET['dept_id'])) {
                    $dept_id = intval($_GET['dept_id']);
                    $sql = "SELECT 
                                d.name as department_name,
                                COUNT(p.id) as total_programs,
                                COUNT(CASE WHEN p.status = 'ongoing' THEN 1 END) as active_programs,
                                COUNT(CASE WHEN p.status = 'ended' THEN 1 END) as completed_programs,
                                COUNT(CASE WHEN p.status = 'planning' THEN 1 END) as planning_programs,
                                SUM(p.budget) as total_budget,
                                AVG(p.max_students) as avg_capacity
                            FROM departments d
                            LEFT JOIN programs p ON d.id = p.department_id
                            WHERE d.id = ?
                            GROUP BY d.id, d.name";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $dept_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    echo json_encode($result->fetch_assoc());
                    $stmt->close();
                } else {
                    echo json_encode(['error' => 'Department ID required']);
                }
                break;
                
            case 'programs':
                if (isset($_GET['dept_id'])) {
                    $dept_id = intval($_GET['dept_id']);
                    $status = $_GET['status'] ?? null;
                    
                    if ($status) {
                        $sql = "SELECT p.*, d.name as department_name FROM programs p 
                               LEFT JOIN departments d ON p.department_id = d.id 
                               WHERE p.department_id = ? AND p.status = ? 
                               ORDER BY p.created_at DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("is", $dept_id, $status);
                    } else {
                        $sql = "SELECT p.*, d.name as department_name FROM programs p 
                               LEFT JOIN departments d ON p.department_id = d.id 
                               WHERE p.department_id = ? 
                               ORDER BY p.created_at DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $dept_id);
                    }
                    
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $programs = [];
                    
                    while ($row = $result->fetch_assoc()) {
                        $programs[] = $row;
                    }
                    
                    echo json_encode($programs);
                    $stmt->close();
                } else {
                    echo json_encode(['error' => 'Department ID required']);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'create':
                    $sql = "INSERT INTO departments (code, name, status) VALUES (?, ?, 'active')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ss", $data['code'], $data['name']);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Department created successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create department']);
                    }
                    $stmt->close();
                    break;
                    
                case 'update':
                    if (isset($data['id'])) {
                        $sql = "UPDATE departments SET name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $data['name'], $data['id']);
                        
                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update department']);
                        }
                        $stmt->close();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Department ID required']);
                    }
                    break;
                    
                case 'deactivate':
                    if (isset($data['id'])) {
                        $sql = "UPDATE departments SET status = 'inactive', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $data['id']);
                        
                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => 'Department deactivated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to deactivate department']);
                        }
                        $stmt->close();
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Department ID required']);
                    }
                    break;
                    
                default:
                    echo json_encode(['error' => 'Unknown action']);
            }
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
        return $result->fetch_assoc();
    }
    
    /**
     * Create new department
     */
    public function createDepartment($data) {
        $sql = "INSERT INTO departments (
            department_code, 
            department_name, 
            department_description, 
            contact_email, 
            contact_phone, 
            building_location,
            budget_allocation
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssssd", 
            $data['department_code'],
            $data['department_name'],
            $data['department_description'],
            $data['contact_email'],
            $data['contact_phone'],
            $data['building_location'],
            $data['budget_allocation']
        );
        
        return $stmt->execute();
    }
    
    /**
     * Update department
     */
    public function updateDepartment($id, $data) {
        $sql = "UPDATE departments SET 
            department_name = ?, 
            department_description = ?, 
            contact_email = ?, 
            contact_phone = ?, 
            building_location = ?,
            budget_allocation = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssdi", 
            $data['department_name'],
            $data['department_description'],
            $data['contact_email'],
            $data['contact_phone'],
            $data['building_location'],
            $data['budget_allocation'],
            $id
        );
        
        return $stmt->execute();
    }
    
    /**
     * Get department statistics
     */
    public function getDepartmentStats($dept_id) {
        $stmt = $this->conn->prepare("CALL GetDepartmentStats(?)");
        $stmt->bind_param("i", $dept_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get programs by department
     */
    public function getProgramsByDepartment($dept_id, $status = null) {
        if ($status) {
            $sql = "SELECT * FROM programs_with_departments WHERE department_id = ? AND status = ? ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("is", $dept_id, $status);
        } else {
            $sql = "SELECT * FROM programs_with_departments WHERE department_id = ? ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $dept_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $programs = [];
        
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
        
        return $programs;
    }
    
    /**
     * Deactivate department (soft delete)
     */
    public function deactivateDepartment($id) {
        $stmt = $this->conn->prepare("UPDATE departments SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    /**
     * Get departments for dropdown/select options
     */
    public function getDepartmentOptions() {
        $departments = $this->getAllDepartments();
        $options = [];
        
        foreach ($departments as $dept) {
            $options[] = [
                'value' => $dept['id'],
                'text' => $dept['department_name'],
                'code' => $dept['department_code']
            ];
        }
        
        return $options;
    }
}

// Usage example for API endpoints
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $deptManager = new DepartmentManager($conn);
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'list':
                echo json_encode($deptManager->getAllDepartments());
                break;
                
            case 'options':
                echo json_encode($deptManager->getDepartmentOptions());
                break;
                
            case 'stats':
                if (isset($_GET['dept_id'])) {
                    echo json_encode($deptManager->getDepartmentStats($_GET['dept_id']));
                } else {
                    echo json_encode(['error' => 'Department ID required']);
                }
                break;
                
            case 'programs':
                if (isset($_GET['dept_id'])) {
                    $status = $_GET['status'] ?? null;
                    echo json_encode($deptManager->getProgramsByDepartment($_GET['dept_id'], $status));
                } else {
                    echo json_encode(['error' => 'Department ID required']);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    } else {
        echo json_encode($deptManager->getAllDepartments());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deptManager = new DepartmentManager($conn);
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'create':
                if ($deptManager->createDepartment($data)) {
                    echo json_encode(['success' => true, 'message' => 'Department created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create department']);
                }
                break;
                
            case 'update':
                if (isset($data['id']) && $deptManager->updateDepartment($data['id'], $data)) {
                    echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update department']);
                }
                break;
                
            case 'deactivate':
                if (isset($data['id']) && $deptManager->deactivateDepartment($data['id'])) {
                    echo json_encode(['success' => true, 'message' => 'Department deactivated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to deactivate department']);
                }
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    }
}

$conn->close();
?>
