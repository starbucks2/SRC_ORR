<?php
include 'db.php';

try {
    $stmt = $conn->prepare("UPDATE students SET reset_token = NULL, reset_token_expiry = NULL WHERE reset_token_expiry < NOW()");
    $stmt->execute();
    echo "Expired tokens cleaned up successfully.";
} catch (PDOException $e) {
    echo "Error during cleanup: " . $e->getMessage();
}
