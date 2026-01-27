<?php
function ensureStrandsTableExists($conn) {
    try {
        // Check if strands table exists
        $result = $conn->query("SHOW TABLES LIKE 'strands'");
        if ($result->rowCount() == 0) {
            // Create strands table
            $conn->exec("CREATE TABLE IF NOT EXISTS strands (
                id INT AUTO_INCREMENT PRIMARY KEY,
                strand VARCHAR(50) NOT NULL UNIQUE
            ) ENGINE=InnoDB");

            // Get existing strands from subadmins table
            $existingStrands = $conn->query("SELECT DISTINCT strand FROM subadmins WHERE strand IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
            
            // Combine with default strands
            $defaultStrands = ['HUMSS', 'STEM', 'TVL', 'GAS'];
            $allStrands = array_unique(array_merge($existingStrands, $defaultStrands));
            
            // Insert strands
            $stmt = $conn->prepare("INSERT IGNORE INTO strands (strand) VALUES (?)");
            foreach ($allStrands as $strand) {
                if (!empty($strand)) {
                    $stmt->execute([$strand]);
                }
            }
        }

        // Make sure announcements table has strand_id column
        $result = $conn->query("SHOW COLUMNS FROM announcements LIKE 'strand_id'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE announcements ADD COLUMN strand_id INT");
            $conn->exec("ALTER TABLE announcements ADD CONSTRAINT fk_announcement_strand 
                        FOREIGN KEY (strand_id) REFERENCES strands(id)");
        }

        return true;
    } catch (PDOException $e) {
        error_log("Error setting up strands table: " . $e->getMessage());
        return false;
    }
}
?>
