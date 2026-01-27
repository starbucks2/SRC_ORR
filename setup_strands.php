<?php
try {
    require_once 'db.php';

    // Create strands table
    $sql = "CREATE TABLE IF NOT EXISTS strands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strand VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB;";
    
    $conn->exec($sql);

    // Get existing strands from subadmins table
    $existingStrands = $conn->query("SELECT DISTINCT strand FROM subadmins WHERE strand IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    
    // Combine with default strands to ensure all standard strands are included
    $defaultStrands = ['HUMSS', 'STEM', 'TVL', 'GAS'];
    $allStrands = array_unique(array_merge($existingStrands, $defaultStrands));
    
    // Insert strands
    $stmt = $conn->prepare("INSERT IGNORE INTO strands (strand) VALUES (?)");
    foreach ($allStrands as $strand) {
        if (!empty($strand)) {
            $stmt->execute([$strand]);
        }
    }

    // Add strand_id column to announcements table if it doesn't exist
    $result = $conn->query("SHOW COLUMNS FROM announcements LIKE 'strand_id'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE announcements ADD COLUMN strand_id INT");
        $conn->exec("ALTER TABLE announcements ADD CONSTRAINT fk_announcement_strand 
                    FOREIGN KEY (strand_id) REFERENCES strands(id)");
    }

    // Redirect back to the announcements page
    header("Location: subadmin_announcements.php");
    exit();

} catch (PDOException $e) {
    die("Setup Error: " . $e->getMessage());
}
?>
