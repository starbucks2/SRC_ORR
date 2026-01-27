<?php
// One-time migration to set all course/strand values to BSIS across key tables.
// Usage: open this file in your browser once (e.g., http://localhost/FinalBecuran/migrate_set_course_bsis.php)
// It is idempotent and safe to re-run; it only updates rows where strand is not already 'BSIS'.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Starting BSIS course backfill...\n\n";

$tables = [
    // table => column
    'students' => 'strand',
    'research_submission' => 'strand',
    'announcements' => 'strand',
    'sub_admins' => 'strand',
];

$totalUpdated = 0;

try {
    // Ensure connection available
    if (!isset($conn)) {
        throw new Exception('Database connection not available');
    }

    foreach ($tables as $table => $col) {
        // Check column exists to avoid errors
        $check = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $check->execute([$col]);
        if ($check->rowCount() === 0) {
            echo "- Skipping $table: column `$col` not found\n";
            continue;
        }

        // Update all non-BSIS, non-null, non-empty values to BSIS
        $sql = "UPDATE `$table` SET `$col` = 'BSIS' WHERE `$col` IS NOT NULL AND TRIM(`$col`) <> '' AND UPPER(`$col`) <> 'BSIS'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $updated = $stmt->rowCount();
        $totalUpdated += $updated;
        echo "- $table: updated $updated row(s) to BSIS\n";
    }

    echo "\nBackfill complete. Total rows updated: $totalUpdated\n";
    echo "You may delete this file after confirming the data looks correct.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
