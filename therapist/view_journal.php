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
function getMoodText($rating) {
    switch($rating) {
        case 1: return "bg-red-100 text-red-800";
        case 2: return "bg-orange-100 text-orange-800";
        case 3: return "bg-yellow-100 text-yellow-800";
        case 4: return "bg-green-100 text-green-800";
        case 5: return "bg-indigo-100 text-indigo-800";
        default: return "bg-gray-100 text-gray-800";
    }
} 1: return "Very Low";
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
        case