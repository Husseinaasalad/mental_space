<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: applications.php?error=invalid_id");
    exit();
}

$applicationId = $_GET['id'];

// Process application rejection
try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get application details first
    $stmt = $conn->prepare("SELECT * FROM professional_applications WHERE application_id = :application_id AND status = 'pending'");
    $stmt->bindParam(':application_id', $applicationId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Application not found or not pending
        $conn->rollBack();
        header("Location: applications.php?error=not_found");
        exit();
    }
    
    // Update application status
    $stmt = $conn->prepare("UPDATE professional_applications 
                          SET status = 'rejected', decision_date = NOW() 
                          WHERE application_id = :application_id");
    $stmt->bindParam(':application_id', $applicationId);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect back to applications page with success message
    header("Location: applications.php?success=rejected");
    exit();
} catch(PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    header("Location: applications.php?error=db_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>