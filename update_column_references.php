<?php
/**
 * Script to update all PHP files to use new column names:
 * - Replace references to 'student_number' with 'student_id'
 * - Keep the old 'student_id' references as 'id' where appropriate
 */

$directory = __DIR__;
$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory),
    RecursiveIteratorIterator::SELF_FIRST
);

$replacements = [
    // Database column references in queries
    "student_number" => "student_id",
    "'student_number'" => "'student_id'",
    '"student_number"' => '"student_id"',
    "`student_number`" => "`student_id`",
    "COLUMN_NAME = 'student_number'" => "COLUMN_NAME = 'student_id'",
    
    // Session variable references
    "\$_SESSION['student_number']" => "\$_SESSION['student_id']",
    '$_SESSION["student_number"]' => '$_SESSION["student_id"]',
    
    // Variable names (be careful with these)
    '$student_number' => '$student_id',
    '$row[\'student_number\']' => '$row[\'student_id\']',
    '$row["student_number"]' => '$row["student_id"]',
    '$user[\'student_number\']' => '$user[\'student_id\']',
    '$user["student_number"]' => '$user["student_id"]',
    '$student[\'student_number\']' => '$student[\'student_id\']',
    '$student["student_number"]' => '$student["student_id"]',
    '$grow[\'student_number\']' => '$grow[\'student_id\']',
    '$grow["student_number"]' => '$grow["student_id"]',
];

$excludeFiles = [
    'migrate_student_columns.php',
    'update_column_references.php',
    'vendor'
];

$filesUpdated = 0;
$totalReplacements = 0;

foreach ($phpFiles as $file) {
    if ($file->isDir()) continue;
    if ($file->getExtension() !== 'php') continue;
    
    $filePath = $file->getPathname();
    $fileName = $file->getFilename();
    
    // Skip excluded files
    $skip = false;
    foreach ($excludeFiles as $exclude) {
        if (strpos($filePath, $exclude) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $fileReplacements = 0;
    
    foreach ($replacements as $search => $replace) {
        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        $fileReplacements += $count;
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        $filesUpdated++;
        $totalReplacements += $fileReplacements;
        echo "Updated: $fileName ($fileReplacements replacements)\n";
    }
}

echo "\n=== Summary ===\n";
echo "Files updated: $filesUpdated\n";
echo "Total replacements: $totalReplacements\n";
?>
