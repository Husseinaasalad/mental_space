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

// Get therapist details
try {
    $stmt = $conn->prepare("SELECT u.*, td.specialization, td.qualification, td.license_number, 
                           td.years_of_experience, td.hourly_rate, td.availability
                           FROM users u 
                           JOIN therapist_details td ON u.user_id = td.user_id 
                           WHERE u.user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $therapist = $stmt->fetch();
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Get today's appointments
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email
                           FROM sessions s 
                           JOIN users u ON s.patient_id = u.user_id 
                           WHERE s.therapist_id = :therapist_id 
                           AND DATE(s.session_date) = CURDATE() 
                           AND s.session_status IN ('scheduled', 'completed')
                           ORDER BY s.session_date ASC");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $todayAppointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving appointments: " . $e->getMessage();
    $todayAppointments = [];
}

// Get upcoming appointments (excluding today)
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name
                           FROM sessions s 
                           JOIN users u ON s.patient_id = u.user_id 
                           WHERE s.therapist_id = :therapist_id 
                           AND DATE(s.session_date) > CURDATE() 
                           AND s.session_status = 'scheduled'
                           ORDER BY s.session_date ASC
                           LIMIT 5");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $upcomingAppointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving appointments: " . $e->getMessage();
    $upcomingAppointments = [];
}

// Get recent patients
try {
    $stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name, MAX(s.session_date) as last_session
                           FROM users u 
                           JOIN sessions s ON u.user_id = s.patient_id 
                           WHERE s.therapist_id = :therapist_id
                           GROUP BY u.user_id, u.first_name, u.last_name
                           ORDER BY last_session DESC
                           LIMIT 5");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $recentPatients = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving patients: " . $e->getMessage();
    $recentPatients = [];
}

// Get appointment statistics
try {
    // Total appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE therapist_id = :therapist_id");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $totalAppointments = $stmt->fetch()['total'];
    
    // Upcoming appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_date > NOW() 
                           AND session_status = 'scheduled'");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $upcomingAppointmentsCount = $stmt->fetch()['total'];
    
    // Completed appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_status = 'completed'");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $completedAppointments = $stmt->fetch()['total'];
    
    // Unique patients
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM sessions 
                           WHERE therapist_id = :therapist_id");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $uniquePatients = $stmt->fetch()['total'];
    
} catch(PDOException $e) {
    $error = "Error retrieving statistics: " . $e->getMessage();
}

// Get unread messages count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $unreadMessages = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $unreadMessages = 0;
}

// Get monthly appointment data for chart
try {
    $stmt = $conn->prepare("SELECT DATE_FORMAT(session_date, '%Y-%m') as month, COUNT(*) as count
                           FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(session_date, '%Y-%m')
                           ORDER BY month ASC");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $monthlyData = $stmt->fetchAll();
    
    // Format for chart
    $chartLabels = [];
    $chartValues = [];
    
    foreach ($monthlyData as $data) {
        $date = DateTime::createFromFormat('Y-m', $data['month']);
        $chartLabels[] = $date->format('M Y');
        $chartValues[] = $data['count'];
    }
} catch(PDOException $e) {
    $error = "Error retrieving chart data: " . $e->getMessage();
    $chartLabels = [];
    $chartValues = [];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapist Dashboard | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Welcome, Dr. <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <div>
                <a href="schedule.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-calendar-alt mr-2"></i> Manage Schedule
                </a>
            </div>
        </div>
        
        <!-- Dashboard Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Appointments -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Total Appointments</p>
                        <p class="text-2xl font-semibold"><?php echo $totalAppointments; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="appointments.php" class="text-green-600 hover:text-green-800 text-sm">View history</a>
                </div>
            </div>
            
            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Upcoming Sessions</p>
                        <p class="text-2xl font-semibold"><?php echo $upcomingAppointmentsCount; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="upcoming_appointments.php" class="text-blue-600 hover:text-blue-800 text-sm">View schedule</a>
                </div>
            </div>
            
            <!-- Patients -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Active Patients</p>
                        <p class="text-2xl font-semibold"><?php echo $uniquePatients; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="patients.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all patients</a>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Unread Messages</p>
                        <p class="text-2xl font-semibold"><?php echo $unreadMessages; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="messages.php" class="text-yellow-600 hover:text-yellow-800 text-sm">View messages</a>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Today's Schedule -->
            <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Today's Schedule</h2>
                
                <?php if (count($todayAppointments) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($todayAppointments as $appointment): ?>
                            <?php 
                            $isPast = strtotime($appointment['session_date']) < time();
                            $isCurrent = (strtotime($appointment['session_date']) <= time() && 
                                          strtotime($appointment['session_date']) + ($appointment['duration'] * 60) >= time());
                            ?>
                            <div class="border-l-4 <?php echo $isPast ? 'border-gray-300' : ($isCurrent ? 'border-green-500' : 'border-blue-500'); ?> pl-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            <?php echo formatAppointmentTime($appointment['session_date']); ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Current</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-gray-600"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                                        <p class="text-gray-500 text-sm"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?> (<?php echo $appointment['duration']; ?> min)</p>
                                    </div>
                                    <div>
                                        <a href="patient_profile.php?id=<?php echo $appointment['patient_id']; ?>" class="text-indigo-600 hover:text-indigo-800 mr-3 text-sm">Profile</a>
                                        <?php if (!$isPast): ?>
                                            <a href="start_session.php?id=<?php echo $appointment['session_id']; ?>" class="text-green-600 hover:text-green-800 text-sm">
                                                <?php echo $isCurrent ? 'Join Now' : 'Prepare'; ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="session_notes.php?id=<?php echo $appointment['session_id']; ?>" class="text-gray-600 hover:text-gray-800 text-sm">Notes</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2"><i class="fas fa-calendar-day text-4xl"></i></div>
                        <p class="text-gray-500">You don't have any appointments scheduled for today.</p>
                        <a href="schedule.php" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800">Manage your schedule</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Patients -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Patients</h2>
                
                <?php if (count($recentPatients) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentPatients as $patient): ?>
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-medium">
                                        <?php echo substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1); ?>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                                    <p class="text-xs text-gray-500">Last session: <?php echo date("M j, Y", strtotime($patient['last_session'])); ?></p>
                                </div>
                                <div class="ml-auto">
                                    <a href="patient_profile.php?id=<?php echo $patient['user_id']; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="patients.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all patients</a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">You haven't seen any patients yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Monthly Sessions Chart -->
            <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Sessions Overview</h2>
                <div style="height: 300px;">
                    <canvas id="sessionsChart"></canvas>
                </div>
            </div>
            
            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Upcoming Appointments</h2>
                
                <?php if (count($upcomingAppointments) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="border-l-4 border-blue-500 pl-4">
                                <p class="font-medium text-gray-900"><?php echo date("M j", strtotime($appointment['session_date'])); ?> at <?php echo formatAppointmentTime($appointment['session_date']); ?></p>
                                <p class="text-gray-600"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                                <p class="text-gray-500 text-sm"><?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="upcoming_appointments.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all appointments</a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">You don't have any upcoming appointments.</p>
                        <a href="schedule.php" class="mt-3 inline-block text-indigo-600 hover:text-indigo-800">Manage your schedule</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize the sessions chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('sessionsChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Sessions',
                        data: <?php echo json_encode($chartValues); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>