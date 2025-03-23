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

// Process application approval
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
    
    $application = $stmt->fetch();
    $userId = $application['user_id'];
    
    // Update application status
    $stmt = $conn->prepare("UPDATE professional_applications 
                          SET status = 'approved', decision_date = NOW() 
                          WHERE application_id = :application_id");
    $stmt->bindParam(':application_id', $applicationId);
    $stmt->execute();
    
    // Update user role
    $stmt = $conn->prepare("UPDATE users SET role = 'therapist' WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    // Check if therapist details already exist
    $stmt = $conn->prepare("SELECT therapist_id FROM therapist_details WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Insert therapist details
        $stmt = $conn->prepare("INSERT INTO therapist_details 
                               (user_id, specialization, qualification, license_number, years_of_experience, hourly_rate, availability) 
                               VALUES 
                               (:user_id, :specialization, :qualification, :license_number, :years_of_experience, :hourly_rate, :availability)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':specialization', $application['specialization']);
        $stmt->bindParam(':qualification', $application['qualification']);
        $stmt->bindParam(':license_number', $application['license_number']);
        $stmt->bindParam(':years_of_experience', $application['years_of_experience']);
        $stmt->bindParam(':hourly_rate', $application['hourly_rate']);
        $stmt->bindParam(':availability', $application['availability']);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect back to applications page with success message
    header("Location: applications.php?success=approved");
    exit();
} catch(PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    header("Location: applications.php?error=db_error&message=" . urlencode($e->getMessage()));
    exit();
}
?>