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
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Validate month and year
if (!is_numeric($month) || $month < 1 || $month > 12) {
    $month = date('m');
}

if (!is_numeric($year) || $year < 2000 || $year > date('Y')) {
    $year = date('Y');
}

// Build query based on filters
$query = "SELECT * FROM journal_entries WHERE user_id = :user_id";
$params = [':user_id' => $_SESSION['user_id']];

// Add month/year filter if not searching
if (empty($searchTerm)) {
    $query .= " AND MONTH(entry_date) = :month AND YEAR(entry_date) = :year";
    $params[':month'] = $month;
    $params[':year'] = $year;
} else {
    $query .= " AND (thoughts LIKE :search OR activities LIKE :search OR gratitude LIKE :search OR goals LIKE :search)";
    $params[':search'] = "%$searchTerm%";
}

$query .= " ORDER BY entry_date DESC";

// Get journal entries
try {
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $entries = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving journal entries: " . $e->getMessage();
    $entries = [];
}

// Get available months/years for filter
try {
    $stmt = $conn->prepare("SELECT DISTINCT YEAR(entry_date) as year, MONTH(entry_date) as month 
                           FROM journal_entries 
                           WHERE user_id = :user_id 
                           ORDER BY year DESC, month DESC");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $availableDates = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving dates: " . $e->getMessage();
    $availableDates = [];
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

// Get month name
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal History | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Journal History</h1>
            <div>
                <a href="journal.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-plus mr-2"></i> New Journal Entry
                </a>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="md:flex justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">Filter Entries</h2>
                    
                    <!-- Month/Year Filter -->
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex space-x-2">
                        <select name="month" class="shadow border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="1" <?php if($month == 1) echo 'selected'; ?>>January</option>
                            <option value="2" <?php if($month == 2) echo 'selected'; ?>>February</option>
                            <option value="3" <?php if($month == 3) echo 'selected'; ?>>March</option>
                            <option value="4" <?php if($month == 4) echo 'selected'; ?>>April</option>
                            <option value="5" <?php if($month == 5) echo 'selected'; ?>>May</option>
                            <option value="6" <?php if($month == 6) echo 'selected'; ?>>June</option>
                            <option value="7" <?php if($month == 7) echo 'selected'; ?>>July</option>
                            <option value="8" <?php if($month == 8) echo 'selected'; ?>>August</option>
                            <option value="9" <?php if($month == 9) echo 'selected'; ?>>September</option>
                            <option value="10" <?php if($month == 10) echo 'selected'; ?>>October</option>
                            <option value="11" <?php if($month == 11) echo 'selected'; ?>>November</option>
                            <option value="12" <?php if($month == 12) echo 'selected'; ?>>December</option>
                        </select>
                        
                        <select name="year" class="shadow border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <?php 
                            $currentYear = date('Y');
                            for($y = $currentYear; $y >= 2020; $y--) {
                                echo "<option value=\"$y\"" . ($year == $y ? ' selected' : '') . ">$y</option>";
                            }
                            ?>
                        </select>
                        
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-r focus:outline-none focus:shadow-outline">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Journal Entries List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if(count($entries) > 0): ?>
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <?php 
                        if (!empty($searchTerm)) {
                            echo 'Search Results for "' . htmlspecialchars($searchTerm) . '"';
                        } else {
                            echo 'Entries for ' . $monthName . ' ' . $year;
                        }
                        ?>
                        <span class="ml-2 text-sm font-normal text-gray-600">(<?php echo count($entries); ?> entries)</span>
                    </h2>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach($entries as $entry): ?>
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="flex items-center mb-2">
                                        <span class="text-gray-600 font-medium">
                                            <?php echo date("l, F j, Y", strtotime($entry['entry_date'])); ?>
                                        </span>
                                        <span class="ml-3 px-2 py-1 rounded text-xs font-medium <?php echo getMoodColorClass($entry['mood_rating']); ?>">
                                            <?php echo getMoodText($entry['mood_rating']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mt-2 mb-3">
                                        <h3 class="text-sm font-medium text-gray-700 mb-1">Thoughts:</h3>
                                        <p class="text-gray-600 line-clamp-2">
                                            <?php echo htmlspecialchars(substr($entry['thoughts'], 0, 200)); ?>
                                            <?php if(strlen($entry['thoughts']) > 200): ?>...<?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <?php if (!empty($entry['activities'])): ?>
                                        <div class="mb-3">
                                            <h3 class="text-sm font-medium text-gray-700 mb-1">Activities:</h3>
                                            <p class="text-gray-600 line-clamp-1">
                                                <?php echo htmlspecialchars(substr($entry['activities'], 0, 150)); ?>
                                                <?php if(strlen($entry['activities']) > 150): ?>...<?php endif; ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <a href="view_journal.php?id=<?php echo $entry['entry_id']; ?>" class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:border-indigo-300 focus:shadow-outline-indigo active:bg-indigo-200 transition ease-in-out duration-150">
                                        <i class="fas fa-book-open mr-1"></i> View Entry
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center">
                    <?php if (!empty($searchTerm)): ?>
                        <p class="text-gray-600 mb-4">No journal entries found matching "<?php echo htmlspecialchars($searchTerm); ?>".</p>
                        <a href="journal_history.php" class="text-indigo-600 hover:text-indigo-800">Clear search</a>
                    <?php else: ?>
                        <p class="text-gray-600 mb-4">No journal entries found for <?php echo $monthName . ' ' . $year; ?>.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>-4 rounded focus:outline-none focus:shadow-outline">
                            Apply
                        </button>
                    </form>
                </div>
                
                <!-- Search Box -->
                <div class="mt-4 md:mt-0">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" 
                            class="shadow appearance-none border rounded-l w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            placeholder="Search journal entries...">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px