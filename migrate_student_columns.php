<?php
/**
 * Migration Script: Rename student columns
 * - Rename 'student_id' to 'id' (old legacy column)
 * - Rename 'student_number' to 'student_id' (new canonical column)
 */

require_once 'db.php';

try {
    echo "Starting migration...\n";
    
    // Get all foreign keys that reference student_id
    echo "Checking for foreign key constraints...\n";
    $fkQuery = $conn->prepare("
        SELECT CONSTRAINT_NAME, TABLE_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
        AND REFERENCED_TABLE_NAME = 'students' 
        AND REFERENCED_COLUMN_NAME = 'student_id'
    ");
    $fkQuery->execute();
    $foreignKeys = $fkQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Drop foreign key constraints
    foreach ($foreignKeys as $fk) {
        echo "  - Dropping FK: {$fk['CONSTRAINT_NAME']} from {$fk['TABLE_NAME']}\n";
        $conn->exec("ALTER TABLE `{$fk['TABLE_NAME']}` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
    }
    
    // Step 1: Check if 'id' column already exists
    $checkId = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'students' 
                                AND COLUMN_NAME = 'id'");
    $checkId->execute();
    $idExists = (int)$checkId->fetchColumn() > 0;
    
    if (!$idExists) {
        echo "\nStep 1: Renaming 'student_id' to 'id'...\n";
        
        // First, drop the unique constraint on student_id if it exists
        try {
            $conn->exec("ALTER TABLE students DROP INDEX uniq_student_id");
            echo "  - Dropped unique index on student_id\n";
        } catch (Exception $e) {
            echo "  - No unique index found on student_id (OK)\n";
        }
        
        // Rename student_id to id
        $conn->exec("ALTER TABLE students CHANGE COLUMN student_id id VARCHAR(32) NULL");
        echo "  - Renamed 'student_id' to 'id'\n";
    } else {
        echo "\nStep 1: Column 'id' already exists, skipping...\n";
    }
    
    // Step 2: Check if student_number still exists
    $checkStudentNumber = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS 
                                          WHERE TABLE_SCHEMA = DATABASE() 
                                          AND TABLE_NAME = 'students' 
                                          AND COLUMN_NAME = 'student_number'");
    $checkStudentNumber->execute();
    $studentNumberExists = (int)$checkStudentNumber->fetchColumn() > 0;
    
    if ($studentNumberExists) {
        echo "\nStep 2: Renaming 'student_number' to 'student_id'...\n";
        
        // Drop the unique constraint on student_number if it exists
        try {
            $conn->exec("ALTER TABLE students DROP INDEX uniq_student_number");
            echo "  - Dropped unique index on student_number\n";
        } catch (Exception $e) {
            echo "  - No unique index found on student_number (OK)\n";
        }
        
        // Rename student_number to student_id
        $conn->exec("ALTER TABLE students CHANGE COLUMN student_number student_id VARCHAR(32) NULL");
        echo "  - Renamed 'student_number' to 'student_id'\n";
        
        // Add unique constraint back on student_id
        $conn->exec("ALTER TABLE students ADD UNIQUE KEY uniq_student_id (student_id)");
        echo "  - Added unique index on student_id\n";
    } else {
        echo "\nStep 2: Column 'student_number' doesn't exist, skipping...\n";
    }
    
    // Recreate foreign key constraints (pointing to the new student_id column)
    echo "\nRecreating foreign key constraints...\n";
    foreach ($foreignKeys as $fk) {
        echo "  - Recreating FK: {$fk['CONSTRAINT_NAME']} on {$fk['TABLE_NAME']}\n";
        try {
            $conn->exec("ALTER TABLE `{$fk['TABLE_NAME']}` 
                        ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}` 
                        FOREIGN KEY (student_id) REFERENCES students(student_id) 
                        ON DELETE CASCADE ON UPDATE CASCADE");
        } catch (Exception $e) {
            echo "    Warning: Could not recreate FK - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "New structure:\n";
    echo "  - 'id' column (old student_id, can be NULL)\n";
    echo "  - 'student_id' column (was student_number, unique)\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
