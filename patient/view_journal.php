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

// Check if entry ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: journal_history.php");
    exit();
}

$entry_id = $_GET['id'];

// Initialize variables
$error = $success = "";
$entry = null;
$previousEntryId = $nextEntryId = null;

// Get journal entry
try {
    // Verify the entry belongs to the logged-in user
    $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE entry_id = :entry_id AND user_id = :user_id");
    $stmt->bindParam(':entry_id', $entry_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $entry = $stmt->fetch();
        
        // Get previous entry ID
        $stmt = $conn->prepare("SELECT entry_id FROM journal_entries 
                               WHERE user_id = :user_id AND entry_date < :entry_date 
                               ORDER BY entry_date DESC LIMIT 1");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':entry_date', $entry['entry_date']);
        $stmt->execute();
        $prev = $stmt->fetch();
        if ($prev) {
            $previousEntryId = $prev['entry_id'];
        }
        
        // Get next entry ID
        $stmt = $conn->prepare("SELECT entry_id FROM journal_entries 
                               WHERE user_id = :user_id AND entry_date > :entry_date 
                               ORDER BY entry_date ASC LIMIT 1");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':entry_date', $entry['entry_date']);
        $stmt->execute();
        $next = $stmt->fetch();
        if ($next) {
            $nextEntryId = $next['entry_id'];
        }
    } else {
        header("Location: journal_history.php");
        exit();
    }
} catch(PDOException $e) {
    $error = "Error retrieving journal entry: " . $e->getMessage();
}

// Process delete request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM journal_entries WHERE entry_id = :entry_id AND user_id = :user_id");
        $stmt->bindParam(':entry_id', $entry_id);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        $success = "Journal entry deleted successfully.";
        header("Location: journal_history.php?deleted=1");
        exit();
    } catch(PDOException $e) {
        $error = "Error deleting journal entry: " . $e->getMessage();
    }
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
    <title>View Journal Entry | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Journal Entry</h1>
            <div class="flex space-x-2">
                <a href="journal_history.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-arrow-left mr-2"></i> Back to History
                </a>
                <a href="journal.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-plus mr-2"></i> New Entry
                </a>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($entry): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 flex justify-between items-center">
                    <div>
                        <div class="flex items-center">
                            <span class="text-gray-700 font-medium text-lg">
                                <?php echo date("l, F j, Y", strtotime($entry['entry_date'])); ?>
                            </span>
                            <span class="ml-3 px-3 py-1 rounded-full text-sm font-medium <?php echo getMoodColorClass($entry['mood_rating']); ?>">
                                <?php echo getMoodText($entry['mood_rating']); ?>
                            </span>
                        </div>
                        <p class="text-gray-500 text-sm">
                            <?php echo date("g:i A", strtotime($entry['entry_date'])); ?>
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="edit_journal.php?id=<?php echo $entry['entry_id']; ?>" class="text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button onclick="confirmDelete()" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-3">Thoughts</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($entry['thoughts'])); ?></p>
                            </div>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold text-gray-800 mb-3">Activities</h2>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($entry['activities'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if(!empty($entry['gratitude']) || !empty($entry['goals'])): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <?php if(!empty($entry['gratitude'])): ?>
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Gratitude</h2>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($entry['gratitude'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($entry['goals'])): ?>
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-800 mb-3">Goals</h2>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($entry['goals'])); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="border-t border-gray-200 bg-gray-50 px-6 py-4 flex justify-between">
                    <div>
                        <?php if($previousEntryId): ?>
                            <a href="view_journal.php?id=<?php echo $previousEntryId; ?>" class="text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-chevron-left mr-1"></i> Previous Entry
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">
                                <i class="fas fa-chevron-left mr-1"></i> Previous Entry
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if($nextEntryId): ?>
                            <a href="view_journal.php?id=<?php echo $nextEntryId; ?>" class="text-indigo-600 hover:text-indigo-800">
                                Next Entry <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">
                                Next Entry <i class="fas fa-chevron-right ml-1"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-gray-700">Are you sure you want to delete this journal entry? This action cannot be undone.</p>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                        <button onclick="closeDeleteModal()" class="mr-3 px-4 py-2 text-gray-700 font-medium rounded hover:bg-gray-100 focus:outline-none">
                            Cancel
                        </button>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $entry_id; ?>">
                            <button type="submit" name="delete" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded focus:outline-none focus:shadow-outline">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <p class="text-gray-600 mb-4">Journal entry not found or you don't have permission to view it.</p>
                <a href="journal_history.php" class="text-indigo-600 hover:text-indigo-800">Return to Journal History</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function confirmDelete() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>