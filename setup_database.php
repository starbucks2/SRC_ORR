<?php
require_once 'db.php';

try {
    // Create strands table
    $sql = "CREATE TABLE IF NOT EXISTS strands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strand VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB;";
    
    $conn->exec($sql);
    
    // Check if strands table is empty
    $check = $conn->query("SELECT COUNT(*) FROM strands")->fetchColumn();
    
    if ($check == 0) {
        // Insert default strands
        $insertStrands = $conn->prepare("INSERT INTO strands (strand) VALUES (?)");
        $defaultStrands = ['HUMSS', 'STEM', 'TVL', 'GAS'];
        
        foreach ($defaultStrands as $strand) {
            $insertStrands->execute([$strand]);
        }
    }
    
    // Check if strand_id column exists in announcements table
    $result = $conn->query("SHOW COLUMNS FROM announcements LIKE 'strand_id'");
    if ($result->rowCount() == 0) {
        // Add strand_id column if it doesn't exist
        $conn->exec("ALTER TABLE announcements ADD COLUMN strand_id INT");
        $conn->exec("ALTER TABLE announcements ADD CONSTRAINT fk_announcement_strand 
                    FOREIGN KEY (strand_id) REFERENCES strands(id)");
    }
    
    // Check if strand column exists in subadmins table
    $result = $conn->query("SHOW COLUMNS FROM subadmins LIKE 'strand'");
    if ($result->rowCount() == 0) {
        // Add strand column if it doesn't exist
        $conn->exec("ALTER TABLE subadmins ADD COLUMN strand VARCHAR(50)");
    }

    // Create groups table
    $conn->exec("CREATE TABLE IF NOT EXISTS groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_number INT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed groups 1..10 if missing
    $checkGroups = $conn->query("SELECT COUNT(*) FROM groups")->fetchColumn();
    if ((int)$checkGroups === 0) {
        $ins = $conn->prepare("INSERT INTO groups (group_number) VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10)");
        $ins->execute();
    }

    // Add group_number column to students if missing
    $result = $conn->query("SHOW COLUMNS FROM students LIKE 'group_number'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE students ADD COLUMN group_number INT NULL AFTER strand");
    }

    // Create mapping table student_groups
    $conn->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        group_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student (student_id),
        INDEX idx_group (group_id),
        CONSTRAINT fk_sg_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        CONSTRAINT fk_sg_group FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "Database setup completed successfully!<br>";
    echo "<a href='subadmin_announcements.php'>Return to Announcements</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "SQL State: " . $e->getCode() . "<br>";
}
?>
