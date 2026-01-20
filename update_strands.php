<?php
require_once 'db.php';

try {
    // First ensure the strands table exists and has data
    $conn->exec("CREATE TABLE IF NOT EXISTS strands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        strand VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB");

    // Insert default strands if they don't exist
    $defaultStrands = ['ABM', 'HUMSS', 'STEM', 'TVL', 'GAS'];
    $insertStrand = $conn->prepare("INSERT IGNORE INTO strands (strand) VALUES (?)");
    foreach ($defaultStrands as $strand) {
        $insertStrand->execute([$strand]);
    }

    // Ensure students.course_strand column exists (canonical)
    $conn->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS course_strand VARCHAR(50)");

    // Get list of strands for validation
    $strands = $conn->query("SELECT strand FROM strands")->fetchAll(PDO::FETCH_COLUMN);
    $validStrands = array_merge($strands, ['']);  // Allow empty strand as valid

    // Optionally assign random strands to students missing course_strand
    $students = $conn->query("SELECT student_id FROM students WHERE course_strand IS NULL OR course_strand = ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($students) && !empty($strands)) {
        $updateStudent = $conn->prepare("UPDATE students SET course_strand = ? WHERE student_id = ?");
        foreach ($students as $studentId) {
            $randomStrand = $strands[array_rand($strands)];
            $updateStudent->execute([$randomStrand, $studentId]);
        }
    }

    echo "✅ Database updated successfully!<br>";
    echo "Ensured strands table and seeded defaults.<br>";
    echo "Ensured students.course_strand column and filled empties where possible.<br>";
    echo "<br>Available strands:<br>";
    foreach ($strands as $strand) {
        echo "- " . htmlspecialchars($strand) . "<br>";
    }
    echo "<br><a href='subadmin_dashboard.php' class='text-blue-600 hover:text-blue-800'>Return to Dashboard</a>";

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
