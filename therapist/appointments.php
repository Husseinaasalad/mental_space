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

// Get current view type (default: upcoming)
$view = isset($_GET['view']) ? $_GET['view'] : 'upcoming';

// Get date filters (if any)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+30 days'));

// Get patient filter (if any)
$patientFilter = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;

// Get session type filter (if any)
$sessionTypeFilter = isset($_GET['session_type']) ? $_GET['session_type'] : null;

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get appointments based on view type
try {
    // Base query parts
    $baseSelect = "SELECT s.*, u.first_name, u.last_name, u.email, u.phone ";
    $baseFrom = "FROM sessions s JOIN users u ON s.patient_id = u.user_id ";
    $baseWhere = "WHERE s.therapist_id = :therapist_id ";
    $params = [':therapist_id' => $_SESSION['user_id']];
    
    // Apply view filters
    switch ($view) {
        case 'past':
            $baseWhere .= "AND s.session_date < NOW() ";
            break;
        case 'today':
            $baseWhere .= "AND DATE(s.session_date) = CURDATE() ";
            break;
        case 'completed':
            $baseWhere .= "AND s.session_status = 'completed' ";
            break;
        case 'cancelled':
            $baseWhere .= "AND s.session_status = 'cancelled' ";
            break;
        case 'all':
            // No additional filter
            break;
        case 'upcoming':
        default:
            $baseWhere .= "AND s.session_date > NOW() AND s.session_status = 'scheduled' ";
            break;
    }
    
    // Apply date filters
    if ($view !== 'upcoming' && $view !== 'past') {
        $baseWhere .= "AND DATE(s.session_date) BETWEEN :start_date AND :end_date ";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }
    
    // Apply patient filter
    if ($patientFilter) {
        $baseWhere .= "AND s.patient_id = :patient_id ";
        $params[':patient_id'] = $patientFilter;
    }
    
    // Apply session type filter
    if ($sessionTypeFilter) {
        $baseWhere .= "AND s.session_type = :session_type ";
        $params[':session_type'] = $sessionTypeFilter;
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total " . $baseFrom . $baseWhere;
    $stmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalAppointments = $stmt->fetch()['total'];
    $totalPages = ceil($totalAppointments / $perPage);
    
    // Sort order based on view
    $orderBy = ($view === 'past' || $view === 'completed') 
        ? "ORDER BY s.session_date DESC " 
        : "ORDER BY s.session_date ASC ";
    
    // Get paginated results
    $query = $baseSelect . $baseFrom . $baseWhere . $orderBy . "LIMIT :offset, :per_page";
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll();
    
    // Get patients for filter dropdown
    $stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name 
                           FROM users u 
                           JOIN sessions s ON u.user_id = s.patient_id 
                           WHERE s.therapist_id = :therapist_id 
                           ORDER BY u.last_name, u.first_name");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $patients = $stmt->fetchAll();
    
    // Get session types for filter dropdown
    $stmt = $conn->prepare("SELECT DISTINCT session_type FROM sessions WHERE therapist_id = :therapist_id");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $sessionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error = "Error retrieving appointments: " . $e->getMessage();
    $appointments = [];
    $totalPages = 0;
    $patients = [];
    $sessionTypes = [];
}

// Get appointment summary statistics
try {
    // Today's appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND DATE(session_date) = CURDATE() 
                           AND session_status = 'scheduled'");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $todayCount = $stmt->fetch()['count'];
    
    // This week's appointments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND DATE(session_date) BETWEEN DATE(NOW()) AND DATE(DATE_ADD(NOW(), INTERVAL 7 DAY)) 
                           AND session_status = 'scheduled'");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $weekCount = $stmt->fetch()['count'];
    
    // Appointments requiring notes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_status = 'completed' 
                           AND (session_notes IS NULL OR session_notes = '')");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $pendingNotesCount = $stmt->fetch()['count'];
    
    // Cancellation rate (last 30 days)
    $stmt = $conn->prepare("SELECT 
                              COUNT(CASE WHEN session_status = 'cancelled' THEN 1 END) as cancelled,
                              COUNT(*) as total
                           FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->fetch();
    $cancellationRate = $result['total'] > 0 ? round(($result['cancelled'] / $result['total']) * 100, 1) : 0;
    
} catch(PDOException $e) {
    $todayCount = 0;
    $weekCount = 0;
    $pendingNotesCount = 0;
    $cancellationRate = 0;
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
    <title>Appointments | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Appointments</h1>
                <div>
                    <a href="schedule_session.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="fas fa-plus mr-2"></i> New Appointment
                    </a>
                </div>
            </div>
            
            <!-- Appointment Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <!-- Today's Appointments -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Today</p>
                            <p class="text-2xl font-semibold"><?php echo $todayCount; ?></p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo buildFilterUrl(['view' => 'today']); ?>" class="text-blue-600 hover:text-blue-800 text-sm">View today's appointments</a>
                    </div>
                </div>
                
                <!-- This Week's Appointments -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 uppercase">This Week</p>
                            <p class="text-2xl font-semibold"><?php echo $weekCount; ?></p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo buildFilterUrl(['view' => 'upcoming']); ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">View upcoming appointments</a>
                    </div>
                </div>
                
                <!-- Pending Notes -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Pending Notes</p>
                            <p class="text-2xl font-semibold"><?php echo $pendingNotesCount; ?></p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="session_notes.php?filter=pending" class="text-yellow-600 hover:text-yellow-800 text-sm">Complete session notes</a>
                    </div>
                </div>
                
                <!-- Cancellation Rate -->
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 uppercase">Cancellation Rate</p>
                            <p class="text-2xl font-semibold"><?php echo $cancellationRate; ?>%</p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <a href="<?php echo buildFilterUrl(['view' => 'cancelled']); ?>" class="text-red-600 hover:text-red-800 text-sm">View cancelled appointments</a>
                    </div>
                </div>
            </div>
            
            <!-- View Tabs -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="flex border-b">
                    <a href="<?php echo buildFilterUrl(['view' => 'upcoming']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'upcoming' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        Upcoming
                    </a>
                    <a href="<?php echo buildFilterUrl(['view' => 'today']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'today' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        Today
                    </a>
                    <a href="<?php echo buildFilterUrl(['view' => 'past']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'past' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        Past
                    </a>
                    <a href="<?php echo buildFilterUrl(['view' => 'completed']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'completed' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        Completed
                    </a>
                    <a href="<?php echo buildFilterUrl(['view' => 'cancelled']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'cancelled' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        Cancelled
                    </a>
                    <a href="<?php echo buildFilterUrl(['view' => 'all']); ?>" class="px-6 py-3 text-center w-full md:w-auto <?php echo $view === 'all' ? 'border-b-2 border-green-500 text-green-600 font-medium' : 'text-gray-600 hover:text-green-600'; ?>">
                        All
                    </a>
                </div>
            </div>
            
            <!-- Filter Controls -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                    
                    <!-- Patient Filter -->
                    <div>
                        <label for="patient_id" class="block text-sm font-medium text-gray-700 mb-1">Patient</label>
                        <select id="patient_id" name="patient_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                            <option value="">All Patients</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['user_id']; ?>" <?php echo $patientFilter == $patient['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Session Type Filter -->
                    <div>
                        <label for="session_type" class="block text-sm font-medium text-gray-700 mb-1">Session Type</label>
                        <select id="session_type" name="session_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                            <option value="">All Types</option>
                            <?php foreach ($sessionTypes as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $sessionTypeFilter == $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Appointments Table -->
            <div class="bg-white shadow overflow-hidden rounded-lg">
                <?php if (!empty($appointments)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date & Time
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Patient
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Session Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Duration
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($appointments as $appointment): ?>
                                    <?php 
                                    $isPast = strtotime($appointment['session_date']) < time();
                                    $isCurrent = (strtotime($appointment['session_date']) <= time() && 
                                                 strtotime($appointment['session_date']) + ($appointment['duration'] * 60) >= time());
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap"><td class="px-6 py-4 whitespace-nowrap">
                                                                                        <div class="text-sm font-medium text-gray-900">
                                                                                            <?php echo date("M j, Y", strtotime($appointment['session_date'])); ?>
                                                                                        </div>
                                                                                        <div class="text-sm text-gray-500">
                                                                                            <?php echo formatAppointmentTime($appointment['session_date']); ?>
                                                                                            <?php if ($isCurrent): ?>
                                                                                                <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Current</span>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-500 text-lg font-medium">
                                                    <?php echo substr($appointment['first_name'], 0, 1) . substr($appointment['last_name'], 0, 1); ?>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($appointment['email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo ucfirst(str_replace('_', ' ', $appointment['session_type'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $appointment['duration']; ?> min
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadgeClass($appointment['session_status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $appointment['session_status'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <?php if ($appointment['session_status'] === 'scheduled'): ?>
                                                    <?php if (!$isPast): ?>
                                                        <?php if ($isCurrent): ?>
                                                            <a href="start_session.php?id=<?php echo $appointment['session_id']; ?>" class="text-green-600 hover:text-green-900">
                                                                <i class="fas fa-video mr-1"></i> Join
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="view_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="reschedule_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-clock mr-1"></i> Reschedule
                                                        </a>
                                                        <a href="cancel_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-times-circle mr-1"></i> Cancel
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="complete_session.php?id=<?php echo $appointment['session_id']; ?>" class="text-green-600 hover:text-green-900">
                                                            <i class="fas fa-check mr-1"></i> Complete
                                                        </a>
                                                        <a href="mark_no_show.php?id=<?php echo $appointment['session_id']; ?>" class="text-yellow-600 hover:text-yellow-900">
                                                            <i class="fas fa-user-slash mr-1"></i> No Show
                                                        </a>
                                                    <?php endif; ?>
                                                <?php elseif ($appointment['session_status'] === 'completed'): ?>
                                                    <a href="session_notes.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                        <i class="fas fa-clipboard-list mr-1"></i> Notes
                                                    </a>
                                                <?php else: ?>
                                                    <a href="view_appointment.php?id=<?php echo $appointment['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="patient_profile.php?id=<?php echo $appointment['patient_id']; ?>" class="text-gray-600 hover:text-gray-900">
                                                    <i class="fas fa-user mr-1"></i> Profile
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    </table>
                    </div>

                    <!-- Pagination - Add this here -->
                    <?php if ($totalPages > 1): ?>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to <span class="font-medium"><?php echo min($offset + $perPage, $totalAppointments); ?></span> of <span class="font-medium"><?php echo $totalAppointments; ?></span> appointments
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <!-- All the pagination code goes here -->
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>