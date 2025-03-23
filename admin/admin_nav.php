<?php
// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Function to determine if a menu item is active
function isActive($page_name) {
    global $current_page;
    return $current_page === $page_name ? 'bg-purple-800' : '';
}

// Get pending application counts for notification badges
try {
    require_once '../db_connection.php';
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM professional_applications WHERE status = 'pending'");
    $stmt->execute();
    $pendingApplications = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $pendingApplications = 0;
}
?>

<!-- Top Navigation Bar -->
<nav class="bg-purple-700 text-white shadow-lg">
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
                <!-- Quick Add Button -->
                <div class="ml-3 relative group">
                    <div>
                        <button type="button" class="bg-purple-800 p-1 rounded-full text-white hover:bg-purple-900 focus:outline-none focus:bg-purple-900">
                            <span class="sr-only">Quick Add Menu</span>
                            <i class="fas fa-plus text-xl p-1"></i>
                        </button>
                    </div>
                    <div class="hidden group-hover:block absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="quick-add-menu-button" tabindex="-1">
                        <a href="add_user.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                            <i class="fas fa-user-plus mr-2 text-purple-600"></i> Add User
                        </a>
                        <a href="add_resource.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                            <i class="fas fa-file-alt mr-2 text-purple-600"></i> Add Resource
                        </a>
                        <a href="create_announcement.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                            <i class="fas fa-bullhorn mr-2 text-purple-600"></i> Create Announcement
                        </a>
                    </div>
                </div>
                
                <!-- User Dropdown Menu -->
                <div class="ml-3 relative group">
                    <div>
                        <button type="button" class="flex text-sm rounded-full focus:outline-none" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">Open user menu</span>
                            <div class="h-8 w-8 rounded-full bg-purple-800 flex items-center justify-center text-white font-medium">
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
    <div class="w-64 bg-purple-900 text-white min-h-screen">
        <div class="pt-5 pb-4">
            <div class="px-4">
                <h2 class="text-lg font-medium">Admin Dashboard</h2>
                <p class="text-purple-200 text-sm mt-1">Manage Mental Space</p>
            </div>
            <nav class="mt-5 px-2">
                <a href="dashboard.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('dashboard.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-tachometer-alt mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Dashboard
                </a>
                <a href="users.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('users.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-users mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    User Management
                </a>
                <a href="applications.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('applications.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-user-md mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Professional Applications
                    <?php if ($pendingApplications > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $pendingApplications > 9 ? '9+' : $pendingApplications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="sessions.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('sessions.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-calendar-alt mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Sessions
                </a>
                <a href="journal_analytics.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('journal_analytics.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-chart-line mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Journal Analytics
                </a>
                <a href="resources.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('resources.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-book-reader mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Resources
                </a>
                <a href="announcements.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('announcements.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-bullhorn mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Announcements
                </a>
                <a href="reports.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('reports.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-file-alt mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Reports
                </a>
                <a href="analytics.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('analytics.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-chart-bar mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Platform Analytics
                </a>
                <a href="settings.php" class="group flex items-center px-3 py-2 text-base font-medium rounded-md <?php echo isActive('settings.php'); ?> hover:bg-purple-800 hover:text-white mb-1">
                    <i class="fas fa-cog mr-3 text-purple-300 group-hover:text-purple-200"></i>
                    Settings
                </a>
            </nav>
        </div>
        <div class="border-t border-purple-800 pt-4 pb-3 mt-5">
            <div class="px-4 flex items-center">
                <div class="flex-shrink-0">
                    <div class="h-10 w-10 rounded-full bg-purple-700 flex items-center justify-center text-white font-medium">
                        <?php echo substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1); ?>
                    </div>
                </div>
                <div class="ml-3">
                    <div class="text-base font-medium"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                    <div class="text-sm font-medium text-purple-300"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
            </div>
            <div class="mt-3 px-2">
                <a href="../logout.php" class="block rounded-md px-3 py-2 text-base font-medium text-purple-200 hover:text-white hover:bg-purple-800">
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