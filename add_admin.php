<?php
include 'db.php';

$fullname = 'Santa Rita College';
$email = 'src@edu.ph';
$password = password_hash('Researchproject2025', PASSWORD_DEFAULT);

// Generate employee_id as numeric, zero-padded (e.g., 001, 002, ...)
$employee_id = '001';
try {
    $mx = $conn->query("SELECT MAX(CAST(employee_id AS UNSIGNED)) AS max_id FROM employees WHERE employee_id REGEXP '^[0-9]+$'");
    $row = $mx->fetch(PDO::FETCH_ASSOC);
    $next = (int)($row['max_id'] ?? 0) + 1;
    $employee_id = str_pad((string)$next, 3, '0', STR_PAD_LEFT);
} catch (Throwable $_) { /* keep default '001' if query fails */ }

// Split fullname into first, middle, last (simple heuristic)
$parts = preg_split('/\s+/', trim($fullname));
$first = $parts[0] ?? '';
$last = '';
$middle = null;
if (count($parts) === 1) {
    $last = '';
} elseif (count($parts) === 2) {
    $last = $parts[1];
} else {
    $last = array_pop($parts);
    $first = array_shift($parts);
    $middle = implode(' ', $parts);
}

// Detect availability of employee_type and role columns (prefer employee_type)
$hasEmpType = false; $hasRole = false;
try {
    $qEmpType = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'employee_type'");
    $qEmpType->execute();
    $hasEmpType = ((int)$qEmpType->fetchColumn() > 0);
} catch (Throwable $_) { $hasEmpType = false; }
try {
    $qRole = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND COLUMN_NAME = 'role'");
    $qRole->execute();
    $hasRole = ((int)$qRole->fetchColumn() > 0);
} catch (Throwable $_) { $hasRole = false; }
$roleCol = $hasEmpType ? 'employee_type' : ($hasRole ? 'role' : null);

// Detect available name columns in employees
$nameCols = ['firstname' => false, 'first_name' => false, 'middlename' => false, 'middle_name' => false, 'lastname' => false, 'last_name' => false];
try {
    $qCols = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees'");
    $qCols->execute();
    $cols = $qCols->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach (array_keys($nameCols) as $c) { $nameCols[$c] = in_array($c, $cols, true); }
} catch (Throwable $_) { /* keep defaults */ }

// Choose physical columns to insert into
$firstCol  = $nameCols['first_name']  ? 'first_name'  : ($nameCols['firstname']  ? 'firstname'  : null);
$middleCol = $nameCols['middle_name'] ? 'middle_name' : ($nameCols['middlename'] ? 'middlename' : null);
$lastCol   = $nameCols['last_name']   ? 'last_name'   : ($nameCols['lastname']   ? 'lastname'   : null);

$insertCols = ['employee_id'];
$insertVals = [$employee_id];
if ($firstCol) { $insertCols[] = $firstCol; $insertVals[] = $first; }
if ($middleCol) { $insertCols[] = $middleCol; $insertVals[] = $middle; }
if ($lastCol) { $insertCols[] = $lastCol; $insertVals[] = $last; }
$insertCols[] = 'email';       $insertVals[] = $email;
$insertCols[] = 'password';    $insertVals[] = $password;
if ($roleCol !== null) {
    // Use normalized ADMIN if employee_type exists; otherwise map to legacy role 'Dean'
    $roleVal = ($roleCol === 'employee_type') ? 'ADMIN' : 'Dean';
    $insertCols[] = $roleCol;      $insertVals[] = $roleVal;
}

$placeholders = rtrim(str_repeat('?,', count($insertCols)), ',');
$sql = "INSERT INTO employees (" . implode(',', $insertCols) . ") VALUES ($placeholders)";
$stmt = $conn->prepare($sql);

$attempts = 0;
while (true) {
    try {
        $stmt->execute($insertVals);
        break;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'employee_id') !== false && $attempts < 5) {
            $num = (int)preg_replace('/\D/', '', $employee_id);
            $num = max(0, $num) + 1;
            $employee_id = str_pad((string)$num, 3, '0', STR_PAD_LEFT);
            $insertVals[0] = $employee_id; // update PK value
            $attempts++;
            continue;
        }
        throw $e;
    }
}

echo "Admin user created successfully! Employee ID: {$employee_id}";
?>
