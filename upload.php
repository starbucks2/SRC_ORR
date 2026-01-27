<?php
session_start();

// Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Include your database connection file

$student_id = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a file was uploaded
    if (isset($_FILES['research_file']) && $_FILES['research_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['research_file'];

        // Validate file type and size
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            // Create an uploads directory if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads');
            }

            // Generate a unique file name
            $file_name = uniqid() . '_' . basename($file['name']);
            $file_path = 'uploads/' . $file_name;

            // Move the file to the uploads directory
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Save the file path in the database
                $stmt = $conn->prepare("UPDATE students SET research_file = ? WHERE id = ?");
                $stmt->execute([$file_path, $student_id]);

                echo "<script>alert('File uploaded successfully!');</script>";
            } else {
                echo "<script>alert('Failed to move the uploaded file.');</script>";
            }
        } else {
            echo "<script>alert('Invalid file type or size. Only PDF and DOC/DOCX files up to 5MB are allowed.');</script>";
        }
    } else {
        echo "<script>alert('No file uploaded or an error occurred.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Research Title</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-image: url('bnhsbackground.jpg'); /* Replace with your image path */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .overlay {
            background-color: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="overlay p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="flex justify-center mb-4">
            <img src="Bnhslogo.jpg" alt="Logo" class="w-16 h-16">
        </div>
        <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Welcome to Online Research Repository For Senior High School at Becuran National High School</h2>
  
        <form action="upload.php" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="research_file" class="block text-sm font-medium text-gray-700">Research File (PDF/DOC/DOCX)</label>
                <input type="file" name="research_file" id="research_file" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" accept=".pdf,.doc,.docx" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Upload</button>
        </form>
        <p class="mt-4 text-center text-sm text-gray-600">
            <a href="index.php" class="text-blue-500 hover:text-blue-700">Back to Home</a>
        </p>
    </div>
</body>
</html>
