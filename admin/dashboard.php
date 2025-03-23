<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Get statistics for the dashboard
try {
    // Total users count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['total'];
    
    // User counts by role
    $stmt = $conn->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $stmt->execute();
    $usersByRole = $stmt->fetchAll();
    
    // Format user roles data for the chart
    $roleLabels = [];
    $roleCounts = [];
    $roleColors = [
        'patient' => '#4338CA', // indigo-700
        'therapist' => '#059669', // emerald-600
        'admin' => '#DC2626' // red-600
    ];
    $roleColorValues = [];
    
    foreach ($usersByRole as $role) {
        $roleLabels[] = ucfirst($role['role']) . 's';
        $roleCounts[] = $role['count'];
        $roleColorValues[] = $roleColors[$role['role']] ?? '#94A3B8'; // slate-400 default
    }
    
    // Active sessions count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM sessions WHERE session_date >= CURDATE() AND session_status IN ('scheduled', 'completed')");
    $stmt->execute();
    $activeSessions = $stmt->fetch()['total'];
    
    // Journal entries count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM journal_entries");
    $stmt->execute();
    $journalEntries = $stmt->fetch()['total'];
    
    // Resource count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM resources");
    $stmt->execute();
    $resourceCount = $stmt->fetch()['total'];
    
    // Pending professional applications
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM professional_applications WHERE status = 'pending'");
    $stmt->execute();
    $pendingApplications = $stmt->fetch()['total'];
    
    // Recent users (last 5)
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, role, registration_date FROM users ORDER BY registration_date DESC LIMIT 5");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll();
    
    // User registration trend (last 30 days)
    $stmt = $conn->prepare("SELECT DATE(registration_date) as date, COUNT(*) as count 
                           FROM users 
                           WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                           GROUP BY DATE(registration_date) 
                           ORDER BY date ASC");
    $stmt->execute();
    $registrationData = $stmt->fetchAll();
    
    // Format registration data for chart
    $registrationDates = [];
    $registrationCounts = [];
    
    foreach ($registrationData as $data) {
        $date = new DateTime($data['date']);
        $registrationDates[] = $date->format('M d');
        $registrationCounts[] = $data['count'];
    }
    
    // Get pending professional applications
    $stmt = $conn->prepare("SELECT pa.*, u.first_name, u.last_name, u.email 
                           FROM professional_applications pa 
                           JOIN users u ON pa.user_id = u.user_id 
                           WHERE pa.status = 'pending' 
                           ORDER BY pa.application_date ASC");
    $stmt->execute();
    $pendingApplicationsList = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include admin sidebar and navigation -->
    <?php include 'admin_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
            <div>
                <a href="reports.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-chart-line mr-2"></i> View Reports
                </a>
            </div>
        </div>
        
        <!-- Dashboard Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Total Users -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Total Users</p>
                        <p class="text-2xl font-semibold"><?php echo $totalUsers; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="users.php" class="text-indigo-600 hover:text-indigo-800 text-sm">Manage users</a>
                </div>
            </div>
            
            <!-- Active Sessions -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Active Sessions</p>
                        <p class="text-2xl font-semibold"><?php echo $activeSessions; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="sessions.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all sessions</a>
                </div>
            </div>
            
            <!-- Journal Entries -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Journal Entries</p>
                        <p class="text-2xl font-semibold"><?php echo $journalEntries; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="journal_analytics.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View analytics</a>
                </div>
            </div>
            
            <!-- Pending Applications -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 uppercase">Pending Applications</p>
                        <p class="text-2xl font-semibold"><?php echo $pendingApplications; ?></p>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="#applications" class="text-indigo-600 hover:text-indigo-800 text-sm">Review applications</a>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- User Distribution Chart -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">User Distribution</h2>
                <div style="height: 250px;">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
            
            <!-- Registration Trend Chart -->
            <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">User Registration Trend (Last 30 Days)</h2>
                <div style="height: 250px;">
                    <canvas id="registrationTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent User Registrations</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Joined</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    switch ($user['role']) {
                                        case 'admin': echo 'bg-red-100 text-red-800'; break;
                                        case 'therapist': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-indigo-100 text-indigo-800'; break;
                                    }
                                    ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date("M j, Y", strtotime($user['registration_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view_user.php?id=<?php echo $user['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 text-right">
                <a href="users.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all users</a>
            </div>
        </div>
        
        <!-- Pending Professional Applications -->
        <div id="applications">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Pending Professional Applications</h2>
            
            <?php if (count($pendingApplicationsList) > 0): ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Experience</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Applied</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($pendingApplicationsList as $application): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($application['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($application['specialization']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($application['qualification']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $application['years_of_experience']; ?> years
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date("M j, Y", strtotime($application['application_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view_application.php?id=<?php echo $application['application_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                    <a href="approve_application.php?id=<?php echo $application['application_id']; ?>" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('Are you sure you want to approve this application?');">Approve</a>
                                    <a href="reject_application.php?id=<?php echo $application['application_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to reject this application?');">Reject</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow p-6 text-center">
                    <p class="text-gray-600 mb-2">There are no pending professional applications at this time.</p>
                </div>
            <?php endif; ?>
            
            <div class="mt-4 text-right">
                <a href="applications.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all applications</a>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize the user distribution chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('userDistributionChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($roleLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($roleCounts); ?>,
                        backgroundColor: <?php echo json_encode($roleColorValues); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Initialize the registration trend chart
            var trendCtx = document.getElementById('registrationTrendChart').getContext('2d');
            var trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($registrationDates); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($registrationCounts); ?>,
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                        pointRadius: 4,
                        tension: 0.2
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