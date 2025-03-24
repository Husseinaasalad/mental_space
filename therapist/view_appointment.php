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
$patient = null;
$error = "";

// Get appointment details
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone
                           FROM sessions s 
                           JOIN users u ON s.patient_id = u.user_id 
                           WHERE s.session_id = :session_id 
                           AND s.therapist_id = :therapist_id");
    $stmt->bindParam(':session_id', $appointmentId);
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $appointment = $stmt->fetch();
        
        // Get additional patient details
        $stmt = $conn->prepare("SELECT pd.* FROM patient_details pd WHERE pd.user_id = :patient_id");
        $stmt->bindParam(':patient_id', $appointment['patient_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $patient = $stmt->fetch();
        }
    } else {
        header("Location: appointments.php?error=not_found");
        exit();
    }
} catch(PDOException $e) {
    $error = "Error retrieving appointment details: " . $e->getMessage();
}

// Get previous session notes with this patient
try {
    $stmt = $conn->prepare("SELECT session_id, session_date, session_notes 
                           FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND patient_id = :patient_id 
                           AND session_status = 'completed'
                           AND session_id != :current_session_id
                           ORDER BY session_date DESC 
                           LIMIT 3");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->bindParam(':patient_id', $appointment['patient_id']);
    $stmt->bindParam(':current_session_id', $appointmentId);
    $stmt->execute();
    $previousNotes = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving previous notes: " . $e->getMessage();
    $previousNotes = [];
}

// Format time for display
function formatAppointmentTime($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('g:i A');
}

// Format date for display
function formatAppointmentDate($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('l, F j, Y');
}

// Get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'bg-blue-100 text-blue-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'no_show':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-4xl mx-auto">
            <?php if($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
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
                        <span class="font-medium text-gray-700">Appointment Details</span>
                    </li>
                </ol>
            </nav>
            
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <!-- Appointment Header -->
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold text-gray-800">Appointment Details</h1>
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusBadgeClass($appointment['session_status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $appointment['session_status'])); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Appointment Details -->
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Date & Time Details -->
                        <div>
                            <h2 class="text-lg font-medium text-gray-800 mb-3">Date & Time</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-start mb-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="fas fa-calendar-day text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">Date</p>
                                        <p class="text-sm text-gray-600"><?php echo formatAppointmentDate($appointment['session_date']); ?></p>
                                    </div>
                                </div>
                                <div class="flex items-start mb-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="fas fa-clock text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">Time</p>
                                        <p class="text-sm text-gray-600"><?php echo formatAppointmentTime($appointment['session_date']); ?> (<?php echo $appointment['duration']; ?> minutes)</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="fas fa-tag text-green-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">Session Type</p>
                                        <p class="text-sm text-gray-600"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Session Notes -->
                            <?php if (!empty($appointment['session_notes'])): ?>
                                <div class="mt-6">
                                    <h2 class="text-lg font-medium text-gray-800 mb-3">Session Notes</h2>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-sm text-gray-600 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($appointment['session_notes'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Patient Details -->
                        <div>
                            <h2 class="text-lg font-medium text-gray-800 mb-3">Patient Information</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center mb-4">
                                    <div class="flex-shrink-0">
                                        <div class="h-12 w-12 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-500 text-lg font-medium">
                                            <?php echo substr($appointment['first_name'], 0, 1) . substr($appointment['last_name'], 0, 1); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h3>
                                        <div class="flex items-center mt-1">
                                            <a href="patient_profile.php?id=<?php echo $appointment['patient_id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-900">
                                                View Patient Profile
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 gap-3">
                                    <?php if (!empty($appointment['email'])): ?>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-1">
                                                <i class="fas fa-envelope text-gray-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">Email</p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['email']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($appointment['phone'])): ?>
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0 mt-1">
                                                <i class="fas fa-phone text-gray-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">Phone</p>
                                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['phone']); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($patient && !empty($patient['primary_concerns'])): ?>
                                        <div class="flex items-start mt-2">
                                            <div class="flex-shrink-0 mt-1">
                                                <i class="fas fa-clipboard-list text-gray-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-gray-900">Primary Concerns</p>
                                                <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($patient['primary_concerns'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Previous Session Notes -->
                            <?php if (!empty($previousNotes)): ?>
                                <div class="mt-6">
                                    <h2 class="text-lg font-medium text-gray-800 mb-3">Previous Session Notes</h2>
                                    <div class="space-y-3">
                                        <?php foreach ($previousNotes as $note): ?>
                                            <?php if (!empty($note['session_notes'])): ?>
                                                <div class="bg-gray-50 p-4 rounded-lg">
                                                    <p class="text-sm font-medium text-gray-900 mb-1"><?php echo date("M j, Y", strtotime($note['session_date'])); ?></p>
                                                    <p class="text-sm text-gray-600 whitespace-pre-line">
                                                        <?php 
                                                        $noteText = $note['session_notes'];
                                                        echo (strlen($noteText) > 150) 
                                                            ? nl2br(htmlspecialchars(substr($noteText, 0, 150))) . '... ' 
                                                            : nl2br(htmlspecialchars($noteText)); 
                                                        ?>
                                                        <?php if (strlen($noteText) > 150): ?>
                                                            <a href="session_notes.php?id=<?php echo $note['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">View full notes</a>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="bg-gray-50 px-6 py-4 flex flex-wrap justify-end space-x-3">
                    <a href="appointments.php" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Back to Appointments
                    </a>
                    
                    <?php if ($appointment['session_status'] === 'scheduled'): ?>
                        <?php 
                        $isPast = strtotime($appointment['session_date']) < time();
                        $isCurrent = (strtotime($appointment['session_date']) <= time() && 
                                     strtotime($appointment['session_date']) + ($appointment['duration'] * 60) >= time());
                        
                        if ($isCurrent): ?>
                            <a href="start_session.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-video mr-2"></i> Start Session
                            </a>
                        <?php elseif (!$isPast): ?>
                            <a href="reschedule_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-clock mr-2"></i> Reschedule
                            </a>
                            <a href="cancel_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <i class="fas fa-times-circle mr-2"></i> Cancel
                            </a>
                        <?php else: ?>
                            <a href="complete_session.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-check mr-2"></i> Mark as Completed
                            </a>
                            <a href="mark_no_show.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <i class="fas fa-user-slash mr-2"></i> Mark as No Show
                            </a>
                        <?php endif; ?>
                    <?php elseif ($appointment['session_status'] === 'completed' && (empty($appointment['session_notes']))): ?>
                        <a href="session_notes.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-clipboard-list mr-2"></i> Add Session Notes
                        </a>
                    <?php elseif ($appointment['session_status'] === 'completed'): ?>
                        <a href="session_notes.php?id=<?php echo $appointment['session_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-edit mr-2"></i> Edit Session Notes
                        </a>
                    <?php endif; ?>
                    
                    <a href="message_patient.php?id=<?php echo $appointment['patient_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-envelope mr-2"></i> Message Patient
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>