<?php
include 'db.php';

try {
    // Create strands table
    $conn->exec("CREATE TABLE IF NOT EXISTS strands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strand VARCHAR(50) NOT NULL UNIQUE
    )");

    // Insert default strands
    $strands = ['HUMSS', 'STEM', 'TVL', 'GAS'];
    $stmt = $conn->prepare("INSERT IGNORE INTO strands (strand) VALUES (?)");
    
    foreach ($strands as $strand) {
        $stmt->execute([$strand]);
    }

    // Add strand_id column to announcements table
    $conn->exec("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS strand_id INT");
    
    // Add foreign key if it doesn't exist
    $conn->exec("ALTER TABLE announcements 
                ADD CONSTRAINT fk_announcement_strand 
                FOREIGN KEY (strand_id) 
                REFERENCES strands(id)");

    // Add strand column to subadmins table
    $conn->exec("ALTER TABLE subadmins ADD COLUMN IF NOT EXISTS strand VARCHAR(50)");

    echo "Database tables and columns created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
