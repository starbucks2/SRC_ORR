<?php
function hasStrandPermission($subadmin, $action, $strand) {
    if (!isset($subadmin['permissions'])) {
        return false;
    }

    $permissions = json_decode($subadmin['permissions'], true);
    $required_permission = $action . '_' . strtolower($strand);
    
    return in_array($required_permission, $permissions);
}

function canAccessStudent($subadmin, $student) {
    return $subadmin['strand'] === $student['strand'];
}

function canAccessResearch($subadmin, $research) {
    return $subadmin['strand'] === $research['strand'];
}

// Example usage in your other files:
/*
if (hasStrandPermission($_SESSION['subadmin'], 'verify_students', 'TVL')) {
    // Allow access to verify TVL students
}

if (canAccessStudent($_SESSION['subadmin'], $student)) {
    // Allow access to student's information
}

if (canAccessResearch($_SESSION['subadmin'], $research)) {
    // Allow access to research paper
}
*/
?>
