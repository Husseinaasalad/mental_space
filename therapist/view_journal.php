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

// Check if journal ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: patients.php");
    exit();
}

$journalId = $_GET['id'];
$journal = null;
$patient = null;
$error = "";

// Get journal entry details
try {
    $stmt = $conn->prepare("SELECT j.*, u.first_name, u.last_name, u.user_id, u.email 
                           FROM journal_entries j 
                           JOIN users u ON j.user_id = u.user_id 
                           WHERE j.entry_id = :entry_id");
    $stmt->bindParam(':entry_id', $journalId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $journal = $stmt->fetch();
        
        // Verify therapist has access to this patient's data
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                               WHERE therapist_id = :therapist_id 
                               AND patient_id = :patient_id");
        $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
        $stmt->bindParam(':patient_id', $journal['user_id']);
        $stmt->execute();
        
        if ($stmt->fetch()['count'] == 0) {
            // This patient hasn't had sessions with this therapist
            header("Location: patients.php?error=unauthorized");
            exit();
        }
        
        // Get patient details
        $stmt = $conn->prepare("SELECT pd.* FROM patient_details pd WHERE pd.user_id = :patient_id");
        $stmt->bindParam(':patient_id', $journal['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $patient = $stmt->fetch();
        }
    } else {
        header("Location: patients.php?error=not_found");
        exit();
    }
} catch(PDOException $e) {
    $error = "Error retrieving journal entry: " . $e->getMessage();
}

// Get other journal entries from this patient (for navigation)
try {
    $stmt = $conn->prepare("SELECT entry_id, entry_date, mood_rating 
                           FROM journal_entries 
                           WHERE user_id = :user_id 
                           ORDER BY entry_date DESC 
                           LIMIT 10");
    $stmt->bindParam(':user_id', $journal['user_id']);
    $stmt->execute();
    $otherJournals = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving other journal entries: " . $e->getMessage();
    $otherJournals = [];
}

// Function to convert mood rating to text
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
    <title>Journal Entry | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    <div class="flex-1 p-8">
            <div class="max-w-4xl mx-auto">
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
                            <a href="patient_profile.php?id=<?php echo $journal['user_id']; ?>" class="hover:text-gray-700">Patient Profile</a>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <a href="all_journals.php?patient_id=<?php echo $journal['user_id']; ?>" class="hover:text-gray-700">Journal Entries</a>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-700">Journal Entry</span>
                        </li>
                    </ol>
                </nav>

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Journal Entry</h1>
                        <p class="text-gray-600">
                            <?php echo htmlspecialchars($journal['first_name'] . ' ' . $journal['last_name']); ?> •
                            <?php echo date("F j, Y", strtotime($journal['entry_date'])); ?>
                        </p>
                    </div>
                    <div>
                        <a href="message_patient.php?id=<?php echo $journal['user_id']; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mr-2">
                            <i class="fas fa-envelope mr-2"></i> Message Patient
                        </a>
                    </div>
                </div>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                            <!-- Journal Header -->
                            <div class="border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-800">
                                        Entry Details
                                    </h2>
                                    <p class="text-sm text-gray-500">
                                        <?php echo date("l, F j, Y \a\t g:i A", strtotime($journal['entry_date'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getMoodColorClass($journal['mood_rating']); ?>">
                                        Mood: <?php echo getMoodText($journal['mood_rating']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Journal Content -->
                            <div class="p-6">
                                <div class="space-y-6">
                                    <?php if (!empty($journal['thoughts'])): ?>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-800 mb-2">Thoughts & Feelings</h3>
                                            <div class="bg-gray-50 p-4 rounded-md">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($journal['thoughts'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($journal['gratitude'])): ?>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-800 mb-2">Gratitude</h3>
                                            <div class="bg-gray-50 p-4 rounded-md">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($journal['gratitude'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($journal['goals'])): ?>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-800 mb-2">Goals & Intentions</h3>
                                            <div class="bg-gray-50 p-4 rounded-md">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($journal['goals'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($journal['challenges'])): ?>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-800 mb-2">Challenges</h3>
                                            <div class="bg-gray-50 p-4 rounded-md">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($journal['challenges'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($journal['coping_strategies'])): ?>
                                        <div>
                                            <h3 class="text-lg font-medium text-gray-800 mb-2">Coping Strategies</h3>
                                            <div class="bg-gray-50 p-4 rounded-md">
                                                <p class="text-gray-700 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($journal['coping_strategies'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <!-- Journal Navigation -->
                                        <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                            <h3 class="text-sm font-medium text-gray-700 mb-3">Other Journal Entries</h3>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach ($otherJournals as $entry): ?>
                                                    <a href="view_journal.php?id=<?php echo $entry['entry_id']; ?>" class="inline-flex items-center px-3 py-1 border <?php echo ($entry['entry_id'] == $journalId) ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'; ?> text-sm font-medium rounded-md">
                                                        <?php echo date("M j", strtotime($entry['entry_date'])); ?>
                                                        <span class="ml-1 h-2 w-2 rounded-full <?php echo $entry['mood_rating'] >= 4 ? 'bg-green-500' : ($entry['mood_rating'] <= 2 ? 'bg-red-500' : 'bg-yellow-500'); ?>"></span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-6 flex justify-between">
                                        <a href="all_journals.php?patient_id=<?php echo $journal['user_id']; ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <i class="fas fa-arrow-left mr-2"></i> Back to Journal List
                                        </a>

                                        <a href="patient_profile.php?id=<?php echo $journal['user_id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <i class="fas fa-user mr-2"></i> View Patient Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </body>
                        </html>