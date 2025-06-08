CREATE TABLE participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (program_id) REFERENCES programs(id)
);