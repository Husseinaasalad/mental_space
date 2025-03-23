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

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patients.php");
    exit();
}

$patientId = $_GET['id'];
$patient = null;
$generalErr = "";

// Check if the patient exists and has had sessions with this therapist
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND patient_id = :patient_id");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    
    if ($stmt->fetch()['count'] == 0) {
        // This patient hasn't had sessions with this therapist
        header("Location: patients.php?error=unauthorized");
        exit();
    }
} catch(PDOException $e) {
    $generalErr = "Error: " . $e->getMessage();
    header("Location: patients.php?error=database");
    exit();
}

// Get patient details
try {
    $stmt = $conn->prepare("SELECT u.*, pd.date_of_birth, pd.emergency_contact_name, pd.emergency_contact_phone, pd.primary_concerns 
                           FROM users u 
                           LEFT JOIN patient_details pd ON u.user_id = pd.user_id 
                           WHERE u.user_id = :patient_id AND u.role = 'patient'");
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $patient = $stmt->fetch();
    } else {
        header("Location: patients.php?error=not_found");
        exit();
    }
} catch(PDOException $e) {
    $generalErr = "Error retrieving patient details: " . $e->getMessage();
}

// Calculate age if date_of_birth is available
$age = null;
if (!empty($patient['date_of_birth'])) {
    $birthDate = new DateTime($patient['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// Get patient's journal entries (last 5)
try {
    $stmt = $conn->prepare("SELECT * FROM journal_entries 
                           WHERE user_id = :patient_id 
                           ORDER BY entry_date DESC 
                           LIMIT 5");
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    $recentJournals = $stmt->fetchAll();
} catch(PDOException $e) {
    $generalErr = "Error retrieving journal entries: " . $e->getMessage();
    $recentJournals = [];
}

// Get session history
try {
    $stmt = $conn->prepare("SELECT * FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND patient_id = :patient_id 
                           ORDER BY session_date DESC 
                           LIMIT 10");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    $sessionHistory = $stmt->fetchAll();
} catch(PDOException $e) {
    $generalErr = "Error retrieving session history: " . $e->getMessage();
    $sessionHistory = [];
}

// Get mood data for chart (last 30 days)
try {
    $stmt = $conn->prepare("SELECT DATE(entry_date) as date, AVG(mood_rating) as avg_mood 
                           FROM journal_entries 
                           WHERE user_id = :patient_id 
                           AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                           GROUP BY DATE(entry_date) 
                           ORDER BY date ASC");
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    $moodData = $stmt->fetchAll();
    
    // Format the data for the chart
    $chartLabels = [];
    $chartValues = [];
    
    foreach ($moodData as $data) {
        $date = new DateTime($data['date']);
        $chartLabels[] = $date->format('M d');
        $chartValues[] = floatval($data['avg_mood']);
    }
} catch(PDOException $e) {
    $generalErr = "Error retrieving mood data: " . $e->getMessage();
    $chartLabels = [];
    $chartValues = [];
}

// Check for upcoming appointments with this patient
try {
    $stmt = $conn->prepare("SELECT * FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND patient_id = :patient_id 
                           AND session_date > NOW() 
                           AND session_status = 'scheduled'
                           ORDER BY session_date ASC 
                           LIMIT 1");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->bindParam(':patient_id', $patientId);
    $stmt->execute();
    $upcomingAppointment = $stmt->fetch();
} catch(PDOException $e) {
    $generalErr = "Error retrieving appointment data: " . $e->getMessage();
    $upcomingAppointment = null;
}

// Function to convert mood rating to text
function getMoodText($rating) {
    switch($rating) {
        case 1: return "Very Low";
        case 2: return "Low";
        case 3: return "Neutral";
        case 4: return "Good";
        case 5: return "Excellent";
        default: return "Unknown";
    }
}

// Function to get mood color class
function getMoodColorClass($rating) {
    switch($rating) {
        case 1: return "bg-red-100 text-red-800";
        case 2: return "bg-orange-100 text-orange-800";
        case 3: return "bg-yellow-100 text-yellow-800";
        case 4: return "bg-green-100 text-green-800";
        case 5: return "bg-indigo-100 text-indigo-800";
        default: return "bg-gray-100 text-gray-800";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Profile | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <?php if($generalErr): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $generalErr; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h1>
                <p class="text-gray-600">
                    <?php 
                    $details = [];
                    if ($age) $details[] = $age . ' years old';
                    if ($patient['primary_concerns']) $details[] = 'Primary concerns: ' . htmlspecialchars($patient['primary_concerns']);
                    echo implode(' • ', $details);
                    ?>
                </p>
            </div>
            <div class="flex">
                <a href="message_patient.php?id=<?php echo $patientId; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">
                    <i class="fas fa-envelope mr-2"></i> Message
                </a>
                <a href="schedule_session.php?patient_id=<?php echo $patientId; ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-calendar-plus mr-2"></i> Schedule Session
                </a>
            </div>
        </div>
        
        <!-- Patient Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Personal Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Personal Information</h2>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="font-medium"><?php echo htmlspecialchars($patient['email']); ?></p>
                    </div>
                    
                    <?php if (!empty($patient['date_of_birth'])): ?>
                    <div>
                        <p class="text-sm text-gray-500">Date of Birth</p>
                        <p class="font-medium"><?php echo date("F j, Y", strtotime($patient['date_of_birth'])); ?> (<?php echo $age; ?> years)</p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <p class="text-sm text-gray-500">Member Since</p>
                        <p class="font-medium"><?php echo date("F j, Y", strtotime($patient['registration_date'])); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($patient['emergency_contact_name']) || !empty($patient['emergency_contact_phone'])): ?>
                <div class="mt-6">
                    <h3 class="text-md font-medium text-gray-800 mb-2">Emergency Contact</h3>
                    <div class="space-y-2">
                        <?php if (!empty($patient['emergency_contact_name'])): ?>
                        <div>
                            <p class="text-sm text-gray-500">Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($patient['emergency_contact_phone'])): ?>
                        <div>
                            <p class="text-sm text-gray-500">Phone</p>
                            <p class="font-medium"><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Upcoming Appointment -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Next Appointment</h2>
                
                <?php if ($upcomingAppointment): ?>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="font-medium text-green-800 mb-2">
                            <?php echo date("l, F j, Y", strtotime($upcomingAppointment['session_date'])); ?>
                        </div>
                        <div class="flex justify-between items-center text-green-700">
                            <div>
                                <i class="fas fa-clock mr-1"></i> <?php echo date("g:i A", strtotime($upcomingAppointment['session_date'])); ?>
                            </div>
                            <div>
                                <?php echo $upcomingAppointment['duration']; ?> minutes
                            </div>
                        </div>
                        <div class="mt-1 text-sm text-green-600">
                            <i class="fas fa-tag mr-1"></i> <?php echo ucfirst(str_replace('_', ' ', $upcomingAppointment['session_type'])); ?>
                        </div>
                        
                        <?php if (!empty($upcomingAppointment['session_notes'])): ?>
                        <div class="mt-3 pt-3 border-t border-green-200">
                            <p class="text-sm text-green-700"><?php echo nl2br(htmlspecialchars($upcomingAppointment['session_notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 flex space-x-2">
                            <a href="view_appointment.php?id=<?php echo $upcomingAppointment['session_id']; ?>" class="text-green-700 hover:text-green-900 text-sm font-medium">
                                View Details
                            </a>
                            <span class="text-green-300">|</span>
                            <a href="reschedule_appointment.php?id=<?php echo $upcomingAppointment['session_id']; ?>" class="text-green-700 hover:text-green-900 text-sm font-medium">
                                Reschedule
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2"><i class="fas fa-calendar text-4xl"></i></div>
                        <p class="text-gray-500 mb-4">No upcoming appointments scheduled with this patient.</p>
                        <a href="schedule_session.php?patient_id=<?php echo $patientId; ?>" class="bg-green-600 hover:bg-green-700 text-white text-sm font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Schedule Session
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Primary Concerns -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Primary Concerns</h2>
                
                <?php if (!empty($patient['primary_concerns'])): ?>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($patient['primary_concerns'])); ?></p>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">No primary concerns have been recorded for this patient.</p>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="add_notes.php?patient_id=<?php echo $patientId; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-plus-circle mr-1"></i> Add therapist notes
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Mood Tracking Chart -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Mood Tracking (Last 30 Days)</h2>
            
            <?php if (count($chartLabels) > 0): ?>
                <div style="height: 300px;">
                    <canvas id="moodChart"></canvas>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <p class="text-gray-500">No mood data available for this patient in the last 30 days.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Journal Entries -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Journal Entries</h2>
                    <a href="all_journals.php?patient_id=<?php echo $patientId; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        View all entries
                    </a>
                </div>
                
                <?php if (count($recentJournals) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentJournals as $journal): ?>
                            <div class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-gray-600">
                                        <?php echo date("M j, Y", strtotime($journal['entry_date'])); ?>
                                    </span>
                                    <span class="px-2 py-1 rounded text-xs font-medium <?php echo getMoodColorClass($journal['mood_rating']); ?>">
                                        <?php echo getMoodText($journal['mood_rating']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($journal['thoughts'])): ?>
                                <div class="mb-2">
                                    <p class="text-sm font-medium text-gray-700">Thoughts:</p>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars(substr($journal['thoughts'], 0, 150)); ?>
                                        <?php if (strlen($journal['thoughts']) > 150): ?>...<a href="view_journal.php?id=<?php echo $journal['entry_id']; ?>" class="text-indigo-600 hover:text-indigo-800">Read more</a><?php endif; ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($journal['gratitude'])): ?>
                                <div class="mb-2">
                                    <p class="text-sm font-medium text-gray-700">Gratitude:</p>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars(substr($journal['gratitude'], 0, 100)); ?>
                                        <?php if (strlen($journal['gratitude']) > 100): ?>...<?php endif; ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <a href="view_journal.php?id=<?php echo $journal['entry_id']; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                    View full entry
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">This patient hasn't created any journal entries yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Session History -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Session History</h2>
                    <a href="all_sessions.php?patient_id=<?php echo $patientId; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                        View all sessions
                    </a>
                </div>
                
                <?php if (count($sessionHistory) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($sessionHistory as $session): ?>
                            <div class="border-l-4 <?php echo $session['session_status'] === 'completed' ? 'border-green-500' : 'border-blue-500'; ?> pl-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            <?php echo date("M j, Y", strtotime($session['session_date'])); ?> at <?php echo date("g:i A", strtotime($session['session_date'])); ?>
                                        </p>
                                        <p class="text-gray-500 text-sm">
                                            <?php echo ucfirst(str_replace('_', ' ', $session['session_type'])); ?> (<?php echo $session['duration']; ?> min)
                                        </p>
                                        <span class="inline-block mt-1 px-2 py-1 text-xs rounded-full 
                                            <?php 
                                            switch ($session['session_status']) {
                                                case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'scheduled': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                case 'no_show': echo 'bg-yellow-100 text-yellow-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $session['session_status'])); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <a href="session_details.php?id=<?php echo $session['session_id']; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                            <?php echo $session['session_status'] === 'completed' ? 'View Notes' : 'Details'; ?>
                                        </a>
                                    </div>
                                </div>
                                
                                <?php if (!empty($session['session_notes']) && $session['session_status'] === 'completed'): ?>
                                <div class="mt-2 pl-2 border-l-2 border-gray-200">
                                    <p class="text-sm text-gray-600 italic">
                                        <?php echo htmlspecialchars(substr($session['session_notes'], 0, 100)); ?>
                                        <?php if (strlen($session['session_notes']) > 100): ?>...<?php endif; ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">No session history available for this patient.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (count($chartLabels) > 0): ?>
    <script>
        // Initialize the mood chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('moodChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Mood Rating',
                        data: <?php echo json_encode($chartValues); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        pointRadius: 4,
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 1,
                            max: 5,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    switch (value) {
                                        case 1: return 'Very Low';
                                        case 2: return 'Low';
                                        case 3: return 'Neutral';
                                        case 4: return 'Good';
                                        case 5: return 'Excellent';
                                        default: return '';
                                    }
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var value = context.raw;
                                    var moodText = '';
                                    switch (Math.round(value)) {
                                        case 1: moodText = 'Very Low'; break;
                                        case 2: moodText = 'Low'; break;
                                        case 3: moodText = 'Neutral'; break;
                                        case 4: moodText = 'Good'; break;
                                        case 5: moodText = 'Excellent'; break;
                                    }
                                    return 'Mood: ' + moodText + ' (' + value.toFixed(1) + ')';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>