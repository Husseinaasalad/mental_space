<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is a therapist
if ($_SESSION['role'] !== 'therapist') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: appointments.php?error=invalid_id");
    exit();
}

$appointmentId = $_GET['id'];
$appointment = null;
$error = "";
$success = "";

// Get appointment details
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email 
                           FROM sessions s 
                           JOIN users u ON s.patient_id = u.user_id 
                           WHERE s.session_id = :session_id 
                           AND s.therapist_id = :therapist_id
                           AND s.session_status = 'scheduled'");
    $stmt->bindParam(':session_id', $appointmentId);
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $appointment = $stmt->fetch();
    } else {
        header("Location: appointments.php?error=not_found_or_not_active");
        exit();
    }
} catch(PDOException $e) {
    $error = "Error retrieving appointment details: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reschedule'])) {
        $newDate = $_POST['new_date'];
        $newTime = $_POST['new_time'];
        $newDuration = $_POST['new_duration'];
        $notifyPatient = isset($_POST['notify_patient']) ? 1 : 0;
        $rescheduleNotes = $_POST['reschedule_notes'];
        
        // Validate inputs
        if (empty($newDate) || empty($newTime) || empty($newDuration)) {
            $error = "All fields are required.";
        } else {
            // Format new date and time
            $newDateTime = date('Y-m-d H:i:s', strtotime("$newDate $newTime"));
            
            try {
                // Begin transaction
                $conn->beginTransaction();
                
                // Update appointment
                $stmt = $conn->prepare("UPDATE sessions 
                                       SET session_date = :new_date_time,
                                           duration = :new_duration,
                                           updated_at = NOW()
                                       WHERE session_id = :session_id");
                $stmt->bindParam(':new_date_time', $newDateTime);
                $stmt->bindParam(':new_duration', $newDuration);
                $stmt->bindParam(':session_id', $appointmentId);
                $stmt->execute();
                
                // Record the reschedule in history
                $stmt = $conn->prepare("INSERT INTO session_changes 
                                       (session_id, change_type, changed_by, notes, previous_date, new_date) 
                                       VALUES (:session_id, 'reschedule', :user_id, :notes, :previous_date, :new_date)");
                $stmt->bindParam(':session_id', $appointmentId);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':notes', $rescheduleNotes);
                $stmt->bindParam(':previous_date', $appointment['session_date']);
                $stmt->bindParam(':new_date', $newDateTime);
                $stmt->execute();
                
                // If notification requested, send email to patient
                if ($notifyPatient) {
                    // In a real system, you would send an email here
                    // For this example, we'll just record that a notification was sent
                    $stmt = $conn->prepare("INSERT INTO notifications 
                                          (user_id, notification_type, content, related_id) 
                                          VALUES (:user_id, 'appointment_rescheduled', :content, :session_id)");
                    $stmt->bindParam(':user_id', $appointment['patient_id']);
                    $content = "Your appointment with Dr. " . $_SESSION['last_name'] . " has been rescheduled to " . 
                               date('l, F j, Y \a\t g:i A', strtotime($newDateTime));
                    $stmt->bindParam(':content', $content);
                    $stmt->bindParam(':session_id', $appointmentId);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = "Appointment successfully rescheduled. Redirecting...";
                header("Refresh: 2; URL=view_appointment.php?id=$appointmentId");
                
            } catch(PDOException $e) {
                // Roll back transaction on error
                $conn->rollBack();
                $error = "Error rescheduling appointment: " . $e->getMessage();
            }
        }
    }
}

// Get therapist's availability for the date picker
try {
    // In a real system, you might fetch actual availability slots
    // For this demo, we'll assume the therapist is available from 9 AM to 5 PM
    $workingHours = [
        'start' => '09:00',
        'end' => '17:00'
    ];
} catch(PDOException $e) {
    $error = "Error retrieving availability: " . $e->getMessage();
}

// Format date and time for display
function formatAppointmentDateTime($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('Y-m-d\TH:i');
}

// Extract date and time parts for the form
$currentDate = date('Y-m-d', strtotime($appointment['session_date']));
$currentTime = date('H:i', strtotime($appointment['session_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-3xl mx-auto">
            <?php if($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li>
                        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="appointments.php" class="hover:text-gray-700">Appointments</a>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">