-- Update faculty table to link with departments

-- Add department_id column to faculty table if it doesn't exist
ALTER TABLE faculty 
ADD COLUMN department_id INT,
ADD INDEX idx_faculty_department_id (department_id),
ADD CONSTRAINT fk_faculty_department 
FOREIGN KEY (department_id) REFERENCES departments(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Update existing faculty records to link with departments
-- This assumes you have existing faculty with department names in the 'department' column

UPDATE faculty f 
JOIN departments d ON f.department = d.name 
SET f.department_id = d.id 
WHERE f.department IS NOT NULL AND f.department_id IS NULL;

-- Alternative: Update based on partial matches if exact names don't match
UPDATE faculty f 
JOIN departments d ON f.department LIKE CONCAT('%', d.code, '%') 
SET f.department_id = d.id 
WHERE f.department_id IS NULL;

-- Update based on common department name patterns
UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'HM' LIMIT 1)
WHERE department LIKE '%Hospitality%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'LMC' LIMIT 1)
WHERE department LIKE '%Language%' OR department LIKE '%Communication%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'PE' LIMIT 1)
WHERE department LIKE '%Physical%' OR department LIKE '%Education%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'SSH' LIMIT 1)
WHERE department LIKE '%Social%' OR department LIKE '%Humanities%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'TED' LIMIT 1)
WHERE department LIKE '%Teacher%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'ENTREP' LIMIT 1)
WHERE department LIKE '%ENTREP%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'BSOA' LIMIT 1)
WHERE department LIKE '%BSOA%' AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'BM' LIMIT 1)
WHERE department LIKE '%BM%' OR (department LIKE '%Business%' AND department LIKE '%Management%') AND department_id IS NULL;

UPDATE faculty SET department_id = (SELECT id FROM departments WHERE code = 'CS' LIMIT 1)
WHERE department LIKE '%Computer%' AND department_id IS NULL;

-- Create a view for easy faculty-department queries
CREATE OR REPLACE VIEW faculty_with_departments AS
SELECT 
    f.*,
    d.code as department_code,
    d.name as department_name,
    u.firstname,
    u.lastname,
    u.email,
    CONCAT(u.firstname, ' ', u.lastname) as full_name
FROM faculty f
LEFT JOIN departments d ON f.department_id = d.id
LEFT JOIN users u ON f.user_id = u.id
WHERE d.status = 'active' OR d.status IS NULL;
