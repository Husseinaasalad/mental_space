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
$error = "";
$resourceType = isset($_GET['type']) ? $_GET['type'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "SELECT r.*, u.first_name, u.last_name 
          FROM resources r
          LEFT JOIN users u ON r.added_by = u.user_id
          WHERE r.is_public = 1";
$params = [];

// Add resource type filter
if (!empty($resourceType)) {
    $query .= " AND r.resource_type = :resource_type";
    $params[':resource_type'] = $resourceType;
}

// Add search filter
if (!empty($searchTerm)) {
    $query .= " AND (r.title LIKE :search OR r.description LIKE :search)";
    $params[':search'] = "%$searchTerm%";
}

$query .= " ORDER BY r.created_at DESC";

// Get resources
try {
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $resources = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving resources: " . $e->getMessage();
    $resources = [];
}

// Get resource types for filter
try {
    $stmt = $conn->prepare("SELECT DISTINCT resource_type FROM resources WHERE is_public = 1");
    $stmt->execute();
    $resourceTypes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch(PDOException $e) {
    $error = "Error retrieving resource types: " . $e->getMessage();
    $resourceTypes = [];
}

// Function to get appropriate icon for resource type
function getResourceIcon($type) {
    switch ($type) {
        case 'article':
            return 'fas fa-newspaper';
        case 'video':
            return 'fas fa-video';
        case 'audio':
            return 'fas fa-headphones';
        case 'worksheet':
            return 'fas fa-file-alt';
        case 'infographic':
            return 'fas fa-chart-pie';
        case 'ebook':
            return 'fas fa-book';
        default:
            return 'fas fa-file';
    }
}

// Function to get appropriate button text for resource type
function getResourceButtonText($type) {
    switch ($type) {
        case 'article':
            return 'Read Article';
        case 'video':
            return 'Watch Video';
        case 'audio':
            return 'Listen Now';
        case 'worksheet':
            return 'Open Worksheet';
        case 'infographic':
            return 'View Infographic';
        case 'ebook':
            return 'Read E-Book';
        default:
            return 'View Resource';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Mental Health Resources</h1>
            <div>
                <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                        class="shadow appearance-none border rounded-l w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        placeholder="Search resources...">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-r focus:outline-none focus:shadow-outline">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Resource Filters -->
        <div class="mb-6">
            <div class="flex flex-wrap gap-2">
                <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                   class="px-4 py-2 rounded-full <?php echo empty($resourceType) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                    All Resources
                </a>
                
                <?php foreach($resourceTypes as $type): ?>
                    <a href="?type=<?php echo urlencode($type); ?>" 
                       class="px-4 py-2 rounded-full <?php echo $resourceType === $type ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                        <?php echo ucfirst($type); ?>s
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Resources Display -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (count($resources) > 0): ?>
                <?php foreach ($resources as $resource): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                    <i class="<?php echo getResourceIcon($resource['resource_type']); ?>"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="uppercase tracking-wide text-sm text-indigo-500 font-semibold">
                                        <?php echo htmlspecialchars(ucfirst($resource['resource_type'])); ?>
                                    </div>
                                    <h3 class="mt-1 text-lg font-medium leading-tight">
                                        <?php echo htmlspecialchars($resource['title']); ?>
                                    </h3>
                                    <p class="mt-2 text-gray-600 text-sm line-clamp-3">
                                        <?php echo htmlspecialchars($resource['description']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-3 flex justify-between items-center">
                            <div class="text-xs text-gray-500">
                                <?php 
                                if ($resource['added_by'] && $resource['first_name'] && $resource['last_name']) {
                                    echo 'Added by Dr. ' . htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']);
                                } else {
                                    echo 'Added by Mental Space';
                                }
                                ?>
                            </div>
                            <a href="view_resource.php?id=<?php echo $resource['resource_id']; ?>" 
                               class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                <?php echo getResourceButtonText($resource['resource_type']); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-3">
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <?php if (!empty($searchTerm)): ?>
                            <p class="text-gray-600 mb-4">No resources found matching "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                            <a href="resources.php" class="text-indigo-600 hover:text-indigo-800">Clear search</a>
                        <?php elseif (!empty($resourceType)): ?>
                            <p class="text-gray-600 mb-4">No <?php echo htmlspecialchars($resourceType); ?> resources available.</p>
                            <a href="resources.php" class="text-indigo-600 hover:text-indigo-800">View all resources</a>
                        <?php else: ?>
                            <p class="text-gray-600">No resources available at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Resources Categories -->
        <div class="mt-12">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Resource Categories</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="text-indigo-600 text-3xl mb-4">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Mental Health Basics</h3>
                    <p class="text-gray-600 mb-4">
                        Learn about common mental health conditions, symptoms, and treatment options.
                    </p>
                    <a href="?type=article&search=basics" class="text-indigo-600 hover:text-indigo-800 font-medium">Explore resources</a>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="text-indigo-600 text-3xl mb-4">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Self-Care Strategies</h3>
                    <p class="text-gray-600 mb-4">
                        Discover techniques for managing stress, anxiety, and improving your overall well-being.
                    </p>
                    <a href="?type=article&search=self-care" class="text-indigo-600 hover:text-indigo-800 font-medium">Explore resources</a>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="text-indigo-600 text-3xl mb-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Relationships & Support</h3>
                    <p class="text-gray-600 mb-4">
                        Resources to help you build healthy relationships and create strong support networks.
                    </p>
                    <a href="?type=article&search=relationships" class="text-indigo-600 hover:text-indigo-800 font-medium">Explore resources</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>