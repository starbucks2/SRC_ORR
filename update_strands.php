<?php
require_once 'db.php';

try {
    // First ensure the strands table exists and has data
    $conn->exec("CREATE TABLE IF NOT EXISTS strands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strand VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB");

    // Insert default strands if they don't exist
    $defaultStrands = ['HUMSS', 'STEM', 'TVL', 'GAS'];
    $insertStrand = $conn->prepare("INSERT IGNORE INTO strands (strand) VALUES (?)");
    foreach ($defaultStrands as $strand) {
        $insertStrand->execute([$strand]);
    }

    // Add strand column to students table if it doesn't exist
    $conn->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS strand VARCHAR(50)");
    
    // Add strand column to subadmins table if it doesn't exist
    $conn->exec("ALTER TABLE subadmins ADD COLUMN IF NOT EXISTS strand VARCHAR(50)");

    // Get list of strands for validation
    $strands = $conn->query("SELECT strand FROM strands")->fetchAll(PDO::FETCH_COLUMN);
    $validStrands = array_merge($strands, ['']);  // Allow empty strand as valid

    // Update students with random strands if strand is NULL
    $students = $conn->query("SELECT id FROM students WHERE strand IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($students)) {
        $updateStudent = $conn->prepare("UPDATE students SET strand = ? WHERE id = ?");
        foreach ($students as $studentId) {
            // Randomly assign a strand
            $randomStrand = $strands[array_rand($strands)];
            $updateStudent->execute([$randomStrand, $studentId]);
        }
    }

    // Update subadmins with random strands if strand is NULL
    $subadmins = $conn->query("SELECT id FROM subadmins WHERE strand IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($subadmins)) {
        $updateSubadmin = $conn->prepare("UPDATE subadmins SET strand = ? WHERE id = ?");
        foreach ($subadmins as $subadminId) {
            // Randomly assign a strand
            $randomStrand = $strands[array_rand($strands)];
            $updateSubadmin->execute([$randomStrand, $subadminId]);
        }
    }

    echo "✅ Database updated successfully!<br>";
    echo "Added strand columns to students and subadmins tables<br>";
    echo "Assigned random strands to students and subadmins<br>";
    echo "<br>Available strands:<br>";
    foreach ($strands as $strand) {
        echo "- " . htmlspecialchars($strand) . "<br>";
    }
    echo "<br><a href='subadmin_announcements.php' class='text-blue-600 hover:text-blue-800'>Return to Announcements</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "SQL State: " . $e->getCode() . "<br>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Strands</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            padding: 2rem;
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold mb-4">Update Student and Subadmin Strands</h2>
        <div class="space-y-4">
            <h3 class="text-lg font-semibold mt-4">Manage Strands:</h3>
            <form action="manage_strands.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Update Student Strand</label>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="student_id" placeholder="Student ID" class="rounded border p-2" required>
                        <select name="student_strand" class="rounded border p-2" required>
                            <?php foreach ($strands as $strand): ?>
                            <option value="<?= htmlspecialchars($strand) ?>"><?= htmlspecialchars($strand) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_student" class="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Update Subadmin Strand</label>
                    <div class="mt-1 flex gap-2">
                        <input type="number" name="subadmin_id" placeholder="Subadmin ID" class="rounded border p-2" required>
                        <select name="subadmin_strand" class="rounded border p-2" required>
                            <?php foreach ($strands as $strand): ?>
                            <option value="<?= htmlspecialchars($strand) ?>"><?= htmlspecialchars($strand) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_subadmin" class="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
