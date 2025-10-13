<?php
include 'db.php';

$fullname = 'Becuran Highschool';
$email = 'Src@edu.ph';
$password = password_hash('Researchproject2025', PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admin (fullname, email, password) VALUES (?, ?, ?)");
$stmt->execute([$fullname, $email, $password]);

echo "Admin user created successfully!";
?>
