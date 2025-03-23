<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Function to determine if a menu item is active
function isActive($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'bg-green-800' : '';
}

// Get unread messages count (if needed on all pages)
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

<!-- Top Navigation Bar -->
<nav class="bg-green-600 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="../index.php" class="flex items-center">
                        <i class="fas fa-brain text-xl mr-1"></i>
                        <span class="font-bold text-xl">Mental Space</span>
                    </a>
                </div>
            </div>
            
            <!-- Right Navigation Elements -->
            <div class="flex items-center">
                <!-- Today's Appointments -->
                <?php if ($todayAppointments > 0): ?>
                <a href="todays_appointments.php" class="relative p-1 rounded-full text-white hover:bg-green-700 focus:outline-none focus:bg-green-700 mr-3">
                    <span class="sr-only">Today's appointments</span>
                    <i class="fas fa-calendar-day text-xl"></i>
                    <span class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-yellow-500 text-xs text-center">
                        <?php echo $todayAppointments; ?>
                    </span>
                </a>
                <?php endif; ?>
                
                <!-- Messages Notification -->
                <a href="messages.php" class="relative p-1 rounded-full text-white hover:bg-green-700 focus:outline-none focus:bg-green-700 mr-3">
                    <span class="sr-only">View messages</span>
                    <i class="fas fa-envelope text-xl"></i>
                    <?php if ($unreadMessages > 0): ?>
                        <span class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-red-500 text-xs text-center">
                            <?php echo $unreadMessages > 9 ? '9+' : $unreadMessages; ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Pending Notes Notification -->
                <?php if ($pendingNotes > 0): ?>
                <a href="pending_notes.php" class="relative p-1 rounded-full text-white hover:bg-green-700 focus:outline-none focus:bg-green-700 mr-3">
                    <span class="sr-only">Pending notes</span>
                    <i class="fas fa-clipboard-list text-xl"></i>
                    <span class="absolute top-0 right-0 block h-4 w-4 rounded-full bg-blue-500 text-xs text-center">
                        <?php echo $pendingNotes > 9 ? '9+' : $pendingNotes; ?>
                    </span>
                </a>
                <?php endif; ?>
                
                <!-- User Dropdown Menu -->
                <div class="ml-3 relative group">
                    <div>
                        <button type="button" class="flex text-sm rounded-full focus:outline-none" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <div class="h-8 w-8 rounded-full bg-green-800 flex items-center justify-center text-white font-medium">
                                <?php echo substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1); ?>
                            </div>
                        </button>
                    </div>
                    <div class="hidden group-hover:block absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Your Profile</a>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Settings</a>
                        <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar Navigation -->
<div class="flex">
    <div class="w-64 bg-green-900 text-white min-h-screen">
        <div class="pt-5 pb-4">
            <div class="px-4">
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
                    <i class="fas fa-chart-line mr-3 text-green-300 group-hover:text-green-200"></i>
                    Analytics
                </a>
                <a href="profile.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('profile.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                    <i class="fas fa-user-circle mr-3 text-green-300 group-hover:text-green-200"></i>
                    Profile
                </a>
                <a href="settings.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('settings.php'); ?> hover:bg-green-800 hover:text-white mb-1">
                    <i class="fas fa-cog mr-3 text-green-300 group-hover:text-green-200"></i>
                    Settings
                </a>
            </nav>
        </div>
        <div class="border-t border-green-800 pt-4 pb-3 mt-5">
            <div class="px-4 flex items-center">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-green-700 flex items-center justify-center text-white font-medium">
                        <?php echo substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1); ?>
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium">Dr. <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="text-sm font-medium text-green-300"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
            </div>
            <div class="mt-3 px-2">
                <a href="../logout.php" class="block rounded-md px-3 py-2 text-base font-medium text-green-200 hover:text-white hover:bg-green-800">
                    <i class="fas fa-sign-out-alt mr-3"></i> Sign out
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area (the rest of the page will go here) -->
    <div class="flex-1">
        <!-- Content from individual pages will be placed here -->
    </div>
</div>