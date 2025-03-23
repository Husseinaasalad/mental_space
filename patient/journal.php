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
$mood = $activities = $thoughts = $gratitude = $goals = "";
$moodErr = $activitiesErr = $thoughtsErr = "";
$success = $error = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formValid = true;
    
    // Validate mood
    if (empty($_POST["mood"])) {
        $moodErr = "Please select your mood";
        $formValid = false;
    } else {
        $mood = $_POST["mood"];
    }
    
    // Validate activities
    if (empty($_POST["activities"])) {
        $activitiesErr = "Please share at least one activity";
        $formValid = false;
    } else {
        $activities = $_POST["activities"];
    }
    
    // Validate thoughts
    if (empty($_POST["thoughts"])) {
        $thoughtsErr = "Please share your thoughts";
        $formValid = false;
    } else {
        $thoughts = $_POST["thoughts"];
    }
    
    // These fields are optional
    $gratitude = $_POST["gratitude"] ?? "";
    $goals = $_POST["goals"] ?? "";
    
    if ($formValid) {
        try {
            // Insert journal entry into database
            $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, mood_rating, activities, thoughts, gratitude, goals, entry_date) 
                                    VALUES (:user_id, :mood, :activities, :thoughts, :gratitude, :goals, NOW())");
            
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':mood', $mood);
            $stmt->bindParam(':activities', $activities);
            $stmt->bindParam(':thoughts', $thoughts);
            $stmt->bindParam(':gratitude', $gratitude);
            $stmt->bindParam(':goals', $goals);
            
            $stmt->execute();
            
            $success = "Journal entry saved successfully!";
            
            // Clear form data after successful submission
            $mood = $activities = $thoughts = $gratitude = $goals = "";
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get recent journal entries
try {
    $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE user_id = :user_id ORDER BY entry_date DESC LIMIT 5");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $recentEntries = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving entries: " . $e->getMessage();
    $recentEntries = [];
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
    <title>My Journal | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">My Journal</h1>
        
        <div class="grid md:grid-cols-3 gap-6">
            <!-- Journal Entry Form -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">New Journal Entry</h2>
                    
                    <?php if($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                            <p><?php echo $success; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($error): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p><?php echo $error; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <!-- Mood Rating -->
                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">How are you feeling today?</label>
                            <div class="flex space-x-4">
                                <?php for($i=1; $i<=5; $i++): ?>
                                <label class="flex items-center">
                                    <input type="radio" name="mood" value="<?php echo $i; ?>" <?php if($i == 3) echo "checked"; ?> class="form-radio h-5 w-5 text-indigo-600">
                                    <span class="ml-2 text-gray-700">
                                        <?php echo getMoodText($i); ?>
                                    </span>
                                </label>
                                <?php endfor; ?>
                            </div>
                            <?php if($moodErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $moodErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activities -->
                        <div class="mb-4">
                            <label for="activities" class="block text-gray-700 font-bold mb-2">What activities did you do today?</label>
                            <textarea id="activities" name="activities" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $activitiesErr ? 'border-red-500' : ''; ?>"><?php echo $activities; ?></textarea>
                            <?php if($activitiesErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $activitiesErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thoughts -->
                        <div class="mb-4">
                            <label for="thoughts" class="block text-gray-700 font-bold mb-2">Share your thoughts and feelings</label>
                            <textarea id="thoughts" name="thoughts" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $thoughtsErr ? 'border-red-500' : ''; ?>"><?php echo $thoughts; ?></textarea>
                            <?php if($thoughtsErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $thoughtsErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Gratitude -->
                        <div class="mb-4">
                            <label for="gratitude" class="block text-gray-700 font-bold mb-2">Three things you're grateful for today (optional)</label>
                            <textarea id="gratitude" name="gratitude" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo $gratitude; ?></textarea>
                        </div>
                        
                        <!-- Goals -->
                        <div class="mb-6">
                            <label for="goals" class="block text-gray-700 font-bold mb-2">Goals for tomorrow (optional)</label>
                            <textarea id="goals" name="goals" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo $goals; ?></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Save Journal Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Entries Sidebar -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Entries</h2>
                    
                    <?php if(count($recentEntries) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach($recentEntries as $entry): ?>
                                <div class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-gray-600">
                                            <?php echo date("M j, Y", strtotime($entry['entry_date'])); ?>
                                        </span>
                                        <span class="px-2 py-1 rounded text-xs font-medium <?php echo getMoodColorClass($entry['mood_rating']); ?>">
                                            <?php echo getMoodText($entry['mood_rating']); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-700 text-sm mb-2 line-clamp-2">
                                        <?php echo htmlspecialchars(substr($entry['thoughts'], 0, 100)); ?>
                                        <?php if(strlen($entry['thoughts']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    <a href="view_journal.php?id=<?php echo $entry['entry_id']; ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">Read more</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 text-center">
                            <a href="journal_history.php" class="text-indigo-600 hover:text-indigo-800 text-sm">View all entries</a>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">No journal entries yet. Start by creating your first entry!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript to enhance the mood selector UI
        document.addEventListener('DOMContentLoaded', function() {
            const moodInputs = document.querySelectorAll('input[name="mood"]');
            moodInputs.forEach(input => {
                input.addEventListener('change', function() {
                    moodInputs.forEach(inp => {
                        const label = inp.parentElement;
                        if (inp.checked) {
                            label.classList.add('font-bold');
                        } else {
                            label.classList.remove('font-bold');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>