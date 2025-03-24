<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Function to determine if a menu item is active
function isActive($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'bg-green-700' : '';
}

// Get unread messages count
if (!isset($unreadMessages)) {
    try {
        require_once '../db_connection.php';
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = :user_id AND is_read = 0");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $unreadMessages = $stmt->fetch()['total'];
    } catch(PDOException $e) {
        $unreadMessages = 0;
    }
}

// Get today's appointments count
try {
    require_once '../db_connection.php';
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND DATE(session_date) = CURDATE() 
                           AND session_status = 'scheduled'");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $todayAppointments = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $todayAppointments = 0;
}

// Get pending session notes count
try {
    require_once '../db_connection.php';
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions 
                           WHERE therapist_id = :therapist_id 
                           AND session_status = 'completed' 
                           AND (session_notes IS NULL OR session_notes = '')");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $pendingNotes = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $pendingNotes = 0;
}
?>

<!-- Top Header Bar -->
<div class="bg-green-600 text-white w-full h-16 flex items-center">
    <div class="container mx-auto px-4 flex justify-between items-center">
        <div class="flex items-center">
            <a href="../index.php" class="flex items-center">
                <img src="../images/logo.png" alt="Mental Space" class="h-8 mr-2" onerror="this.onerror=null; this.src='../images/logo-placeholder.png';">
                <span class="font-bold text-xl">Mental Space</span>
            </a>
        </div>
        
        <div class="flex items-center">
            <!-- Notifications -->
            <div class="relative mx-2">
                <a href="notifications.php" class="p-2 rounded-full hover:bg-green-700">
                    <i class="fas fa-bell text-xl"></i>
                </a>
            </div>
            
            <!-- User Dropdown -->
            <div class="relative group">
                <button class="flex items-center focus:outline-none">
                    <div class="h-8 w-8 rounded-full bg-white flex items-center justify-center text-green-600 font-medium mr-2">
                        <?php echo substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1); ?>
                    </div>
                    <span class="hidden md:inline-block"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                    <i class="fas fa-chevron-down ml-1"></i>
                </button>
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10 hidden group-hover:block">
                    <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                    <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                    <div class="border-t border-gray-100"></div>
                    <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign out</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="flex flex-grow min-h-screen">
    <!-- Sidebar Navigation -->
    <div class="w-64 bg-green-900 text-white flex-shrink-0">
        <div class="p-4 border-b border-green-800">
            <h2 class="text-lg font-medium">Therapist Dashboard</h2>
            <p class="text-green-200 text-sm mt-1">Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
        </div>
        
        <nav class="mt-5 px-2">
            <a href="dashboard.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('dashboard.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-home mr-3 text-green-300 group-hover:text-green-200"></i>
                Dashboard
            </a>
            
            <a href="schedule.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('schedule.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-calendar-alt mr-3 text-green-300 group-hover:text-green-200"></i>
                My Schedule
            </a>
            
            <a href="patients.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('patients.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-users mr-3 text-green-300 group-hover:text-green-200"></i>
                My Patients
            </a>
            
            <a href="appointments.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('appointments.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-calendar-check mr-3 text-green-300 group-hover:text-green-200"></i>
                Appointments
                <?php if ($todayAppointments > 0): ?>
                    <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo $todayAppointments > 9 ? '9+' : $todayAppointments; ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="session_notes.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('session_notes.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-clipboard-list mr-3 text-green-300 group-hover:text-green-200"></i>
                Session Notes
                <?php if ($pendingNotes > 0): ?>
                    <span class="ml-auto bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo $pendingNotes > 9 ? '9+' : $pendingNotes; ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="messages.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('messages.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-envelope mr-3 text-green-300 group-hover:text-green-200"></i>
                Messages
                <?php if ($unreadMessages > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo $unreadMessages > 9 ? '9+' : $unreadMessages; ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="resources.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('resources.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-book-reader mr-3 text-green-300 group-hover:text-green-200"></i>
                Resources
            </a>
            
            <a href="analytics.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('analytics.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                <i class="fas fa-chart-bar mr-3 text-green-300 group-hover:text-green-200"></i>
                Analytics
            </a>
        </nav>
                </div>
            </div>
        </div>