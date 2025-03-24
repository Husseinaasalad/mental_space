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
    if (isset($_POST['cancel'])) {
        $cancellationReason = $_POST['cancellation_reason'];
        $notifyPatient = isset($_POST['notify_patient']) ? 1 : 0;
        $rescheduleOption = $_POST['reschedule_option'];
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update appointment status
            $stmt = $conn->prepare("UPDATE sessions 
                                   SET session_status = 'cancelled',
                                       cancellation_reason = :cancellation_reason,
                                       cancellation_date = NOW(),
                                       updated_at = NOW()
                                   WHERE session_id = :session_id");
            $stmt->bindParam(':cancellation_reason', $cancellationReason);
            $stmt->bindParam(':session_id', $appointmentId);
            $stmt->execute();
            
            // Record the cancellation in history
            $stmt = $conn->prepare("INSERT INTO session_changes 
                                   (session_id, change_type, changed_by, notes) 
                                   VALUES (:session_id, 'cancellation', :user_id, :notes)");
            $stmt->bindParam(':session_id', $appointmentId);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':notes', $cancellationReason);
            $stmt->execute();
            
            // If notification requested, send email to patient
            if ($notifyPatient) {
                // In a real system, you would send an email here
                // For this example, we'll just record that a notification was sent
                $stmt = $conn->prepare("INSERT INTO notifications 
                                      (user_id, notification_type, content, related_id) 
                                      VALUES (:user_id, 'appointment_cancelled', :content, :session_id)");
                $stmt->bindParam(':user_id', $appointment['patient_id']);
                $content = "Your appointment with Dr. " . $_SESSION['last_name'] . " scheduled for " . 
                           date('l, F j, Y \a\t g:i A', strtotime($appointment['session_date'])) . 
                           " has been cancelled.";
                           
                if ($rescheduleOption === 'auto') {
                    $content .= " A new appointment will be scheduled automatically.";
                } elseif ($rescheduleOption === 'request') {
                    $content .= " Please contact us to reschedule your appointment.";
                }
                
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':session_id', $appointmentId);
                $stmt->execute();
            }
            
            // Handle reschedule option
            if ($rescheduleOption === 'auto') {
                // In a real system, you might automatically find a new slot and schedule it
                // For this example, we'll just redirect to the reschedule page
                $conn->commit();
                header("Location: reschedule_appointment.php?id=$appointmentId&auto=1");
                exit();
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Appointment successfully cancelled. Redirecting...";
            
            if ($rescheduleOption === 'request') {
                header("Refresh: 2; URL=schedule_session.php?patient_id=" . $appointment['patient_id']);
            } else {
                header("Refresh: 2; URL=appointments.php");
            }
            
        } catch(PDOException $e) {
            // Roll back transaction on error
            $conn->rollBack();
            $error = "Error cancelling appointment: " . $e->getMessage();
        }
    }
}

// Format date and time for display
function formatAppointmentDateTime($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('l, F j, Y \a\t g:i A');
}

// Calculate how far in advance the appointment is being cancelled
$appointmentTime = new DateTime($appointment['session_date']);
$now = new DateTime();
$interval = $now->diff($appointmentTime);
$daysUntilAppointment = $interval->days;
$hoursUntilAppointment = $interval->h + ($interval->days * 24);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment | Mental Space</title>
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
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <a href="view_appointment.php?id=<?php echo $appointmentId; ?>" class="hover:text-gray-700">Appointment Details</a>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium text-gray-700">Cancel Appointment</span>
                    </li>
                </ol>
            </nav>
            
            <?php if ($hoursUntilAppointment < 24): ?>
                <!-- Late Cancellation Warning -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Late Cancellation:</strong> This appointment is scheduled in less than 24 hours. 
                                Late cancellations may impact patient care and clinic policies.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <!-- Header -->
                <div class="bg-white px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold text-gray-800">Cancel Appointment</h1>
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            Currently Scheduled
                        </span>
                    </div>
                    <p class="mt-1 text-gray-600">
                        Appointment with <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                        on <?php echo formatAppointmentDateTime($appointment['session_date']); ?>
                    </p>
                </div>
                
                <!-- Appointment Details Summary -->
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Patient</p>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Session Type</p>
                            <p class="mt-1 text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Duration</p>
                            <p class="mt-1 text-sm text-gray-900"><?php echo $appointment['duration']; ?> minutes</p>
                        </div>
                    </div>
                </div>
                
                <!-- Cancellation Form -->
                <form method="POST" action="cancel_appointment.php?id=<?php echo $appointmentId; ?>" class="px-6 py-4">
                    <div class="space-y-6">
                        <!-- Cancellation Reason -->
                        <div>
                            <label for="cancellation_reason" class="block text-sm font-medium text-gray-700">Reason for Cancellation</label>
                            <div class="mt-1">
                                <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">
                                Please provide a brief explanation for the cancellation. This will be recorded in your records.
                            </p>
                        </div>
                        
                        <!-- Reschedule Options -->
                        <div>
                            <label for="reschedule_option" class="block text-sm font-medium text-gray-700">Reschedule Options</label>
                            <div class="mt-1">
                                <select id="reschedule_option" name="reschedule_option" class="shadow-sm focus:ring-red-500 focus:border-red-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <option value="none">No reschedule needed</option>
                                    <option value="request">Request patient to reschedule</option>
                                    <option value="auto">Reschedule immediately</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Notify Patient -->
                        <div>
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="notify_patient" name="notify_patient" type="checkbox" checked class="focus:ring-red-500 h-4 w-4 text-red-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="notify_patient" class="font-medium text-gray-700">Notify Patient</label>
                                    <p class="text-gray-500">Send an email notification to the patient about this cancellation</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="pt-5 pb-2 border-t border-gray-200 mt-5">
                        <div class="flex justify-end">
                            <a href="view_appointment.php?id=<?php echo $appointmentId; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Return to Appointment
                            </a>
                            <button type="submit" name="cancel" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Cancel Appointment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Cancellation Guidelines -->
            <div class="bg-white rounded-lg shadow mt-6 p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-3">Cancellation Guidelines</h2>
                <div class="text-sm text-gray-600 space-y-2">
                    <p><i class="fas fa-info-circle text-blue-500 mr-2"></i> Appointments should be cancelled at least 24 hours in advance when possible.</p>
                    <p><i class="fas fa-info-circle text-blue-500 mr-2"></i> Multiple cancellations for the same patient may indicate a need for intervention or a different approach.</p>
                    <p><i class="fas fa-info-circle text-blue-500 mr-2"></i> Consider offering telehealth as an alternative before cancelling completely.</p>
                    <p><i class="fas fa-info-circle text-blue-500 mr-2"></i> Ensure patients have a clear path to reschedule when necessary.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Client-side validation and functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const reason = document.getElementById('cancellation_reason').value.trim();
                
                if (reason.length < 5) {
                    e.preventDefault();
                    alert('Please provide a more detailed cancellation reason.');
                    return false;
                }
                
                // Confirm cancellation with warning
                if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            });
            
            // Show/hide patient notification options based on checkbox
            const notifyCheckbox = document.getElementById('notify_patient');
            notifyCheckbox.addEventListener('change', function() {
                // You could add additional logic here if needed
            });
        });
    </script>
</body>
</html>