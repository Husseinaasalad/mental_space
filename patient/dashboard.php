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

// Get user details
try {
    $stmt = $conn->prepare("SELECT u.*, pd.date_of_birth, pd.emergency_contact_name, pd.emergency_contact_phone, pd.primary_concerns 
                           FROM users u 
                           LEFT JOIN patient_details pd ON u.user_id = pd.user_id 
                           WHERE u.user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch();
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Calculate age if date_of_birth is set
$age = null;
if (!empty($user['date_of_birth'])) {
    $birthDate = new DateTime($user['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
}

// Get mood data for chart (last 7 days)
try {
    $stmt = $conn->prepare("SELECT DATE(entry_date) as date, AVG(mood_rating) as avg_mood 
                           FROM journal_entries 
                           WHERE user_id = :user_id 
                           AND entry_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                           GROUP BY DATE(entry_date) 
                           ORDER BY date ASC");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
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
    
    // Fill in missing days with null values
    $today = new DateTime('today');
    $sixDaysAgo = new DateTime('today -6 days');
    $dateRange = new DatePeriod($sixDaysAgo, new DateInterval('P1D'), $today->modify('+1 day'));
    
    $completeChartLabels = [];
    $completeChartValues = [];
    
    foreach ($dateRange as $date) {
        $dateStr = $date->format('M d');
        $completeChartLabels[] = $dateStr;
        
        $found = false;
        foreach ($chartLabels as $index => $label) {
            if ($label === $dateStr) {
                $completeChartValues[] = $chartValues[$index];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $completeChartValues[] = null;
        }
    }
    
    $chartLabels = $completeChartLabels;
    $chartValues = $completeChartValues;
    
} catch(PDOException $e) {
    $error = "Error retrieving mood data: " . $e->getMessage();
    $chartLabels = [];
    $chartValues = [];
}

// Get upcoming appointments
try {
    $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name 
                           FROM sessions s 
                           JOIN users u ON s.therapist_id = u.user_id 
                           WHERE s.patient_id = :user_id 
                           AND s.session_date >= NOW() 
                           AND s.session_status != 'cancelled'
                           ORDER BY s.session_date ASC 
                           LIMIT 3");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $upcomingAppointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving appointments: " . $e->getMessage();
    $upcomingAppointments = [];
}

// Get latest journal entry
try {
    $stmt = $conn->prepare("SELECT * FROM journal_entries 
                           WHERE user_id = :user_id 
                           ORDER BY entry_date DESC 
                           LIMIT 1");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $latestJournal = $stmt->fetch();
} catch(PDOException $e) {
    $error = "Error retrieving journal entry: " . $e->getMessage();
    $latestJournal = null;
}

// Get total journal entries count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM journal_entries WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $journalCount = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $journalCount = 0;
}

// Get unread messages count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $unreadMessages = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $unreadMessages = 0;
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
    <title>Dashboard | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
            <div>
                <a href="journal.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-plus mr-2"></i> New Journal Entry
                </a>
            </div>
        </div>
        
        <!-- Dashboard Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Journal Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Journal Entries</p>
                        <p class="text-2xl font-semibold"><?php echo $journalCount; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="journal_history.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all entries</a>
                </div>
            </div>
            
            <!-- Mood Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <i class="fas fa-smile"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Current Mood</p>
                        <?php if ($latestJournal): ?>
                            <p class="text-2xl font-semibold"><?php echo getMoodText($latestJournal['mood_rating']); ?></p>
                        <?php else: ?>
                            <p class="text-2xl font-semibold">-</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="mood_tracker.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View mood tracker</a>
                </div>
            </div>
            
            <!-- Appointment Stats -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Next Appointment</p>
                        <?php if (is_array($upcomingAppointments) && count($upcomingAppointments) > 0): ?>
                            <p class="text-2xl font-semibold"><?php echo date("M j", strtotime($upcomingAppointments[0]['session_date'])); ?></p>
                        <?php else: ?>
                            <p class="text-2xl font-semibold">None</p>
                        <?php endif; ?>