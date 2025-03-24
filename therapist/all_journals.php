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
if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    header("Location: patients.php");
    exit();
}

$patientId = $_GET['patient_id'];
$patient = null;
$error = "";

// Verify therapist has access to this patient's data
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
    $error = "Error: " . $e->getMessage();
}

// Get patient details
try {
    $stmt = $conn->prepare("SELECT u.*, pd.date_of_birth, pd.primary_concerns 
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
    $error = "Error retrieving patient details: " . $e->getMessage();
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get filter data
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-90 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get total number of journal entries
try {
    // Base query parts
    $baseSelect = "SELECT COUNT(*) as total ";
    $baseFrom = "FROM journal_entries ";
    $baseWhere = "WHERE user_id = :patient_id ";
    $params = [':patient_id' => $patientId];
    
    // Apply date filter
    $baseWhere .= "AND DATE(entry_date) BETWEEN :start_date AND :end_date ";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
    
    // Apply mood filter if needed
    if ($filter !== 'all' && is_numeric($filter)) {
        $baseWhere .= "AND mood_rating = :mood_rating ";
        $params[':mood_rating'] = $filter;
    }
    
    // Get total count for pagination
    $countQuery = $baseSelect . $baseFrom . $baseWhere;
    $stmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalEntries = $stmt->fetch()['total'];
    $totalPages = ceil($totalEntries / $perPage);
    
} catch(PDOException $e) {
    $error = "Error retrieving journal entries count: " . $e->getMessage();
    $totalEntries = 0;
    $totalPages = 0;
}

// Get journal entries with pagination
try {
    // Base query for data
    $baseSelect = "SELECT * ";
    
    // Add pagination
    $query = $baseSelect . $baseFrom . $baseWhere . "ORDER BY entry_date DESC LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $journals = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error retrieving journal entries: " . $e->getMessage();
    $journals = [];
}

// Calculate age if date_of_birth is available
$age = null;
if (!empty($patient['date_of_birth'])) {
    $birthDate = new DateTime($patient['date_of_birth']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
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

// Build pagination URL
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Build filter URL
function buildFilterUrl($params = []) {
    $currentParams = $_GET;
    unset($currentParams['page']); // Reset pagination when filtering
    $mergedParams = array_merge($currentParams, $params);
    return '?' . http_build_query($mergedParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
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
                        <a href="patient_profile.php?id=<?php echo $patientId; ?>" class="hover:text-gray-700">Patient Profile</a>
                    </li>
                    <li class="flex items-center">
                        <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium text-gray-700">Journal Entries</span>
                    </li>
                </ol>
            </nav>
            
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>'s Journal Entries</h1>
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
            
            <!-- Filter Panel -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <input type="hidden" name="patient_id" value="<?php echo $patientId; ?>">
                    
                    <!-- Date Range -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <!-- Mood Filter -->
                    <div>
                        <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Filter by Mood</label>
                        <select id="filter" name="filter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Moods</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $filter == $i ? 'selected' : ''; ?>>
                                    <?php echo getMoodText($i); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>