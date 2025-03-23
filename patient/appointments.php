<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is a patient
if ($_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Initialize variables
$success = $error = "";

// Process appointment cancellation if requested
if (isset($_GET['cancel']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $appointmentId = $_GET['id'];
    
    try {
        // Check if appointment belongs to the logged-in user
        $stmt = $conn->prepare("SELECT * FROM sessions WHERE session_id = :session_id AND patient_id = :patient_id");
        $stmt->bindParam(':session_id', $appointmentId);
        $stmt->bindParam(':patient_id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $appointment = $stmt->fetch();
            
            // Calculate if cancellation is within 24 hours of appointment
            $appointmentTime = new DateTime($appointment['session_date']);
            $now = new DateTime();
            $interval = $now->diff($appointmentTime);
            $hoursUntilAppointment = $interval->days * 24 + $interval->h;
            
            // Update appointment status to cancelled
            $stmt = $conn->prepare("UPDATE sessions SET session_status = 'cancelled', cancellation_reason = 'Patient cancelled', cancel_date = NOW() WHERE session_id = :session_id");
            $stmt->bindParam(':session_id', $appointmentId);
            $stmt->execute();
            
            if ($hoursUntilAppointment < 24) {
                $success = "Appointment cancelled successfully. Note: This cancellation was within 24 hours of the scheduled time.";
            } else {
                $success = "Appointment cancelled successfully.";
            }
        } else {
            $error = "Invalid appointment or you don't have permission to cancel it.";
        }
    } catch(PDOException $e) {
        $error = "Error cancelling appointment: " . $e->getMessage();
    }
}

// Fetch all appointments for the user
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name 
                           FROM sessions s 
                           JOIN users u ON s.therapist_id = u.user_id 
                           WHERE s.patient_id = :user_id 
                           ORDER BY s.session_date DESC");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $appointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving appointments: " . $e->getMessage();
    $appointments = [];
}

// Group appointments by status
$upcomingAppointments = [];
$pastAppointments = [];
$cancelledAppointments = [];

$now = new DateTime();

foreach ($appointments as $appointment) {
    $appointmentDate = new DateTime($appointment['session_date']);
    
    if ($appointment['session_status'] === 'cancelled') {
        $cancelledAppointments[] = $appointment;
    } elseif ($appointmentDate > $now) {
        $upcomingAppointments[] = $appointment;
    } else {
        $pastAppointments[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">My Appointments</h1>
            <div>
                <a href="schedule_appointment.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-plus mr-2"></i> Schedule New Appointment
                </a>
            </div>
        </div>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Appointment Tabs -->
        <div class="mb-6">
            <ul class="flex border-b border-gray-200">
                <li class="-mb-px mr-1">
                    <a class="bg-white inline-block border-l border-t border-r rounded-t py-2 px-4 text-indigo-700 font-semibold" href="#upcoming" onclick="showTab('upcoming')">
                        Upcoming (<?php echo count($upcomingAppointments); ?>)
                    </a>
                </li>
                <li class="mr-1">
                    <a class="bg-white inline-block py-2 px-4 text-gray-600 hover:text-gray-800 font-semibold" href="#past" onclick="showTab('past')">
                        Past (<?php echo count($pastAppointments); ?>)
                    </a>
                </li>
                <li class="mr-1">
                    <a class="bg-white inline-block py-2 px-4 text-gray-600 hover:text-gray-800 font-semibold" href="#cancelled" onclick="showTab('cancelled')">
                        Cancelled (<?php echo count($cancelledAppointments); ?>)
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Upcoming Appointments Tab -->
        <div id="upcoming-tab" class="tab-content">
            <?php if (count($upcomingAppointments) > 0): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Therapist</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo date("M j, Y", strtotime($appointment['session_date'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("g:i A", strtotime($appointment['session_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">Dr. <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <?php echo ucfirst($appointment['session_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                    <a href="appointments.php?cancel=1&id=<?php echo $appointment['session_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-600 mb-4">You don't have any upcoming appointments scheduled.</p>
                    <a href="schedule_appointment.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Schedule an Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Past Appointments Tab -->
        <div id="past-tab" class="tab-content hidden">
            <?php if (count($pastAppointments) > 0): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Therapist</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pastAppointments as $appointment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo date("M j, Y", strtotime($appointment['session_date'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("g:i A", strtotime($appointment['session_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">Dr. <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Completed
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-600">You don't have any past appointments.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cancelled Appointments Tab -->
        <div id="cancelled-tab" class="tab-content hidden">
            <?php if (count($cancelledAppointments) > 0): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Therapist</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Session Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cancellation Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($cancelledAppointments as $appointment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo date("M j, Y", strtotime($appointment['session_date'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date("g:i A", strtotime($appointment['session_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">Dr. <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        <?php echo $appointment['cancel_date'] ? date("M j, Y", strtotime($appointment['cancel_date'])) : 'N/A'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-600">You don't have any cancelled appointments.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Function to show/hide tabs
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Update tab styles
            document.querySelectorAll('ul.flex li a').forEach(link => {
                link.classList.remove('text-indigo-700', 'border-l', 'border-t', 'border-r', 'rounded-t', '-mb-px');
                link.classList.add('text-gray-600', 'hover:text-gray-800');
            });
            
            // Style the active tab
            const activeTab = document.querySelector(`a[href="#${tabName}"]`);
            activeTab.classList.remove('text-gray-600', 'hover:text-gray-800');
            activeTab.classList.add('text-indigo-700', 'border-l', 'border-t', 'border-r', 'rounded-t', '-mb-px');
        }
    </script>
</body>
</html>