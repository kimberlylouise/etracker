-- Basic tables needed for the project evaluation system
-- This creates the minimum required structure based on Faculty Projects.php

-- Create programs table if it doesn't exist
CREATE TABLE IF NOT EXISTS programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_name VARCHAR(255) NOT NULL,
    project_titles JSON,
    department VARCHAR(100),
    location VARCHAR(255),
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'ongoing', 'ended', 'completed', 'cancelled') DEFAULT 'planning',
    max_students INT DEFAULT 0,
    description TEXT,
    sdg_goals TEXT,
    faculty_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create faculty table if it doesn't exist
CREATE TABLE IF NOT EXISTS faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    department VARCHAR(100),
    position VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create users table if it doesn't exist
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(100),
    lastname VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin', 'faculty', 'student') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create participants table if it doesn't exist
CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT,
    user_id INT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
);

-- Insert sample data if tables are empty

-- Sample users
INSERT IGNORE INTO users (id, firstname, lastname, email, role) VALUES
(1, 'Admin', 'User', 'admin@cvsu.edu.ph', 'admin'),
(2, 'John', 'Doe', 'john.doe@cvsu.edu.ph', 'faculty'),
(3, 'Jane', 'Smith', 'jane.smith@cvsu.edu.ph', 'faculty');

-- Sample faculty
INSERT IGNORE INTO faculty (id, user_id, department, position) VALUES
(1, 2, 'Computer Science', 'Professor'),
(2, 3, 'Engineering', 'Associate Professor');

-- Sample programs with project titles
INSERT IGNORE INTO programs (id, program_name, project_titles, department, location, start_date, end_date, status, faculty_id, description) VALUES
(1, 'Community Health Program', 
 '["Health Education Workshop", "Medical Mission", "Nutrition Awareness Campaign"]',
 'Health Sciences', 'Barangay San Jose', '2024-01-15', '2024-12-15', 'ended', 1,
 'A comprehensive health program for the community'),
(2, 'Digital Literacy Training', 
 '["Basic Computer Skills", "Internet Safety Workshop", "Digital Tools for Seniors"]',
 'Computer Science', 'Community Center', '2024-03-01', '2024-11-30', 'ended', 2,
 'Training program to improve digital literacy in the community'),
(3, 'Environmental Conservation Project', 
 '["Tree Planting Activity", "Waste Management Workshop", "Clean River Campaign"]',
 'Environmental Science', 'Various Locations', '2024-02-01', '2024-10-31', 'ongoing', 1,
 'Environmental awareness and conservation activities');

-- Sample participants
INSERT IGNORE INTO participants (program_id, user_id, status) VALUES
(1, 3, 'accepted'),
(2, 3, 'accepted'),
(3, 2, 'accepted');
