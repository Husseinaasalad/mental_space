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

// Default view (all/pending)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get appointment ID if provided for single view
$sessionId = isset($_GET['id']) ? $_GET['id'] : null;
$singleSession = false;

// For single session view
$session = null;
$patient = null;
$previousSessions = [];

// Error and success messages
$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_notes'])) {
        $sessionId = $_POST['session_id'];
        $sessionNotes = $_POST['session_notes'];
        $treatmentPlan = isset($_POST['treatment_plan']) ? $_POST['treatment_plan'] : null;
        $followUpNeeded = isset($_POST['follow_up_needed']) ? 1 : 0;
        $followUpType = isset($_POST['follow_up_type']) ? $_POST['follow_up_type'] : null;
        $sessionRating = isset($_POST['session_rating']) ? $_POST['session_rating'] : null;
        $moodRating = isset($_POST['mood_rating']) ? $_POST['mood_rating'] : null;
        $problemAreas = isset($_POST['problem_areas']) ? implode(',', $_POST['problem_areas']) : null;
        $noteStatus = isset($_POST['mark_final']) ? 'final' : 'draft';
        
        try {
            // Begin transaction
            $conn->beginTransaction();
            
            // Update session notes
            $stmt = $conn->prepare("UPDATE sessions 
                                   SET session_notes = :notes,
                                       treatment_plan = :treatment_plan,
                                       follow_up_needed = :follow_up,
                                       follow_up_type = :follow_up_type,
                                       session_rating = :session_rating,
                                       mood_rating = :mood_rating,
                                       problem_areas = :problem_areas,
                                       note_status = :note_status,
                                       updated_at = NOW()
                                   WHERE session_id = :session_id
                                   AND therapist_id = :therapist_id");
            
            $stmt->bindParam(':notes', $sessionNotes);
            $stmt->bindParam(':treatment_plan', $treatmentPlan);
            $stmt->bindParam(':follow_up', $followUpNeeded);
            $stmt->bindParam(':follow_up_type', $followUpType);
            $stmt->bindParam(':session_rating', $sessionRating);
            $stmt->bindParam(':mood_rating', $moodRating);
            $stmt->bindParam(':problem_areas', $problemAreas);
            $stmt->bindParam(':note_status', $noteStatus);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // If session status is not completed, mark it as completed
            $stmt = $conn->prepare("UPDATE sessions 
                                   SET session_status = 'completed'
                                   WHERE session_id = :session_id
                                   AND session_status = 'scheduled'
                                   AND therapist_id = :therapist_id");
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Check if follow-up is needed and none is scheduled yet
            if ($followUpNeeded && $followUpType) {
                // Get patient ID from the session
                $stmt = $conn->prepare("SELECT patient_id, session_date, session_type FROM sessions WHERE session_id = :session_id");
                $stmt->bindParam(':session_id', $sessionId);
                $stmt->execute();
                $currentSession = $stmt->fetch();
                
                // Check if follow-up is already scheduled
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions 
                                       WHERE therapist_id = :therapist_id 
                                       AND patient_id = :patient_id
                                       AND session_date > :current_session_date
                                       AND session_status = 'scheduled'");
                $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
                $stmt->bindParam(':patient_id', $currentSession['patient_id']);
                $stmt->bindParam(':current_session_date', $currentSession['session_date']);
                $stmt->execute();
                $followUpExists = $stmt->fetch()['count'] > 0;
                
                if (!$followUpExists && isset($_POST['schedule_follow_up'])) {
                    // Redirect to schedule follow-up
                    $conn->commit();
                    header("Location: schedule_session.php?patient_id=" . $currentSession['patient_id'] . "&follow_up=1&type=" . $followUpType);
                    exit();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Session notes saved successfully!";
            
        } catch(PDOException $e) {
            // Roll back transaction on error
            $conn->rollBack();
            $error = "Error saving notes: " . $e->getMessage();
        }
    }
}

// Handle single session view
if ($sessionId) {
    $singleSession = true;
    
    try {
        // Get session details
        $stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name, u.email, u.phone 
                               FROM sessions s 
                               JOIN users u ON s.patient_id = u.user_id 
                               WHERE s.session_id = :session_id 
                               AND s.therapist_id = :therapist_id");
        $stmt->bindParam(':session_id', $sessionId);
        $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $session = $stmt->fetch();
            
            // Get patient details
            $stmt = $conn->prepare("SELECT pd.* FROM patient_details pd WHERE pd.user_id = :patient_id");
            $stmt->bindParam(':patient_id', $session['patient_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $patient = $stmt->fetch();
            }
            
            // Get previous sessions with this patient
            $stmt = $conn->prepare("SELECT session_id, session_date, session_type, session_notes, note_status 
                                   FROM sessions 
                                   WHERE therapist_id = :therapist_id 
                                   AND patient_id = :patient_id 
                                   AND session_id != :current_session_id
                                   AND session_status = 'completed'
                                   ORDER BY session_date DESC 
                                   LIMIT 3");
            $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
            $stmt->bindParam(':patient_id', $session['patient_id']);
            $stmt->bindParam(':current_session_id', $sessionId);
            $stmt->execute();
            $previousSessions = $stmt->fetchAll();
        } else {
            header("Location: session_notes.php?error=not_found");
            exit();
        }
    } catch(PDOException $e) {
        $error = "Error retrieving session details: " . $e->getMessage();
    }
} else {
    // Get all sessions based on filter
    try {
        // Base query parts
        $baseSelect = "SELECT s.*, u.first_name, u.last_name ";
        $baseFrom = "FROM sessions s JOIN users u ON s.patient_id = u.user_id ";
        $baseWhere = "WHERE s.therapist_id = :therapist_id ";
        
        // Apply filters
        switch ($filter) {
            case 'pending':
                $baseWhere .= "AND s.session_status = 'completed' AND (s.session_notes IS NULL OR s.session_notes = '') ";
                break;
            case 'draft':
                $baseWhere .= "AND s.session_status = 'completed' AND s.note_status = 'draft' ";
                break;
            case 'recent':
                $baseWhere .= "AND s.session_status = 'completed' AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";
                break;
            case 'all':
            default:
                $baseWhere .= "AND s.session_status = 'completed' ";
                break;
        }
        
        // Get sessions
        $query = $baseSelect . $baseFrom . $baseWhere . "ORDER BY s.session_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
        $stmt->execute();
        $sessions = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Error retrieving sessions: " . $e->getMessage();
        $sessions = [];
    }
}

// Format time for display
function formatAppointmentTime($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('g:i A');
}

// Format date for display
function formatAppointmentDate($dateTime) {
    $date = new DateTime($dateTime);
    return $date->format('l, F j, Y');
}

// Get treatment categories for dropdown
$treatmentCategories = [
    'cognitive_behavioral' => 'Cognitive Behavioral Therapy',
    'psychodynamic' => 'Psychodynamic Therapy',
    'humanistic' => 'Humanistic Therapy',
    'mindfulness' => 'Mindfulness-Based Therapy',
    'interpersonal' => 'Interpersonal Therapy',
    'supportive' => 'Supportive Therapy',
    'solution_focused' => 'Solution-Focused Brief Therapy',
    'trauma_focused' => 'Trauma-Focused Therapy',
    'other' => 'Other'
];

// Common problem areas for checklist
$problemAreasList = [
    'anxiety' => 'Anxiety',
    'depression' => 'Depression',
    'stress' => 'Stress',
    'trauma' => 'Trauma/PTSD',
    'relationships' => 'Relationship Issues',
    'family' => 'Family Concerns',
    'grief' => 'Grief/Loss',
    'self_esteem' => 'Self-Esteem',
    'anger' => 'Anger Management',
    'substance' => 'Substance Use',
    'eating' => 'Eating Disorders',
    'sleep' => 'Sleep Issues',
    'work' => 'Work/Career Issues',
    'identity' => 'Identity Issues',
    'life_transitions' => 'Life Transitions'
];

// Get note status badge class
function getNoteStatusBadgeClass($status) {
    switch ($status) {
        case 'draft':
            return 'bg-yellow-100 text-yellow-800';
        case 'final':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $singleSession ? 'Session Notes' : 'All Session Notes'; ?> | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
            <?php if($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Breadcrumb -->
            <nav class="mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li>
                        <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                    </li>
                    <?php if ($singleSession): ?>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <a href="session_notes.php" class="hover:text-gray-700">Session Notes</a>
                        </li>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-700">Edit Notes</span>
                        </li>
                    <?php else: ?>
                        <li class="flex items-center">
                            <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium text-gray-700">Session Notes</span>
                        </li>
                    <?php endif; ?>
                </ol>
            </nav>
            
            <?php if ($singleSession): ?>
                <!-- Single Session Notes View -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <!-- Header -->
                    <div class="bg-white px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h1 class="text-2xl font-bold text-gray-800">Session Notes</h1>
                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getNoteStatusBadgeClass($session['note_status'] ?? 'none'); ?>">
                                <?php echo isset($session['note_status']) ? ucfirst($session['note_status']) : 'No Notes'; ?>
                            </span>
                        </div>
                        <p class="mt-1 text-gray-600">
                            Session with <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?>
                            on <?php echo formatAppointmentDate($session['session_date']); ?> at <?php echo formatAppointmentTime($session['session_date']); ?>
                        </p>
                    </div>
                    
                    <!-- Session Notes Form -->
                    <form method="POST" action="session_notes.php?id=<?php echo $sessionId; ?>" class="px-6 py-4">
                        <input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Main Notes Column -->
                            <div class="md:col-span-2 space-y-6">
                                <!-- Session Notes -->
                                <div>
                                    <label for="session_notes" class="block text-sm font-medium text-gray-700">Session Notes</label>
                                    <div class="mt-1">
                                        <textarea id="session_notes" name="session_notes" rows="10" class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($session['session_notes'] ?? ''); ?></textarea>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">
                                        Include observations, interventions, patient's responses, and progress toward goals.
                                    </p>
                                </div>
                                
                                <!-- Treatment Plan -->
                                <div>
                                    <label for="treatment_plan" class="block text-sm font-medium text-gray-700">Treatment Plan</label>
                                    <div class="mt-1">
                                        <textarea id="treatment_plan" name="treatment_plan" rows="4" class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md"><?php echo htmlspecialchars($session['treatment_plan'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Problem Areas -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Problem Areas Addressed</label>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                        <?php 
                                        $currentProblemAreas = isset($session['problem_areas']) ? explode(',', $session['problem_areas']) : [];
                                        foreach ($problemAreasList as $key => $area): 
                                        ?>
                                            <div class="flex items-start">
                                                <div class="flex items-center h-5">
                                                    <input id="problem_<?php echo $key; ?>" name="problem_areas[]" value="<?php echo $key; ?>" type="checkbox" <?php echo in_array($key, $currentProblemAreas) ? 'checked' : ''; ?> class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                                </div>
                                                <div class="ml-3 text-sm">
                                                    <label for="problem_<?php echo $key; ?>" class="font-medium text-gray-700"><?php echo $area; ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sidebar Column -->
                            <div class="space-y-6">
                                <!-- Patient Quick Info -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex items-center mb-4">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-500 text-lg font-medium">
                                                <?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?></h3>
                                            <a href="patient_profile.php?id=<?php echo $session['patient_id']; ?>" class="text-sm text-indigo-600 hover:text-indigo-900">View Profile</a>
                                        </div>
                                    </div>
                                    
                                    <?php if ($patient && !empty($patient['primary_concerns'])): ?>
                                        <div class="mt-2">
                                            <h4 class="text-sm font-medium text-gray-700">Primary Concerns</h4>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($patient['primary_concerns'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Session Rating -->
                                <div>
                                    <label for="session_rating" class="block text-sm font-medium text-gray-700">Session Progress Rating</label>
                                    <select id="session_rating" name="session_rating" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">Select a rating</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo (isset($session['session_rating']) && $session['session_rating'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> - <?php echo $i == 1 ? 'Poor' : ($i == 2 ? 'Fair' : ($i == 3 ? 'Average' : ($i == 4 ? 'Good' : 'Excellent'))); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <!-- Mood Assessment -->
                                <div>
                                    <label for="mood_rating" class="block text-sm font-medium text-gray-700">Patient Mood Assessment</label>
                                    <select id="mood_rating" name="mood_rating" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                        <option value="">Select a rating</option>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo (isset($session['mood_rating']) && $session['mood_rating'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> - <?php echo $i == 1 ? 'Very Low' : ($i == 2 ? 'Low' : ($i == 3 ? 'Neutral' : ($i == 4 ? 'Good' : 'Excellent'))); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <!-- Follow-up Options -->
                                <div class="border-t border-gray-200 pt-4">
                                    <div class="flex items-start mb-3">
                                        <div class="flex items-center h-5">
                                            <input id="follow_up_needed" name="follow_up_needed" type="checkbox" <?php echo (isset($session['follow_up_needed']) && $session['follow_up_needed']) ? 'checked' : ''; ?> class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="follow_up_needed" class="font-medium text-gray-700">Follow-up Required</label>
                                        </div>
                                    </div>
                                    
                                    <div id="follow_up_options" class="<?php echo (isset($session['follow_up_needed']) && $session['follow_up_needed']) ? '' : 'hidden'; ?> ml-7 space-y-3">
                                        <div>
                                            <label for="follow_up_type" class="block text-sm font-medium text-gray-700">Follow-up Type</label>
                                            <select id="follow_up_type" name="follow_up_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                                <option value="">Select type</option>
                                                <option value="standard" <?php echo (isset($session['follow_up_type']) && $session['follow_up_type'] == 'standard') ? 'selected' : ''; ?>>Standard Session</option>
                                                <option value="brief" <?php echo (isset($session['follow_up_type']) && $session['follow_up_type'] == 'brief') ? 'selected' : ''; ?>>Brief Check-in</option>
                                                <option value="urgent" <?php echo (isset($session['follow_up_type']) && $session['follow_up_type'] == 'urgent') ? 'selected' : ''; ?>>Urgent Session</option>
                                            </select>
                                        </div>
                                        
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input id="schedule_follow_up" name="schedule_follow_up" type="checkbox" class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="schedule_follow_up" class="font-medium text-gray-700">Schedule now</label>
                                                <p class="text-gray-500">You'll be redirected to scheduling after saving</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Note Status -->
                                <div class="border-t border-gray-200 pt-4">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="mark_final" name="mark_final" type="checkbox" <?php echo (isset($session['note_status']) && $session['note_status'] == 'final') ? 'checked' : ''; ?> class="focus:ring-green-500 h-4 w-4 text-green-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="mark_final" class="font-medium text-gray-700">Mark as Final</label>
                                            <p class="text-gray-500">Draft notes can be edited later. Final notes are locked for editing.</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Previous Sessions Quick View -->
                                <?php if (!empty($previousSessions)): ?>
                                    <div class="border-t border-gray-200 pt-4">
                                        <h3 class="text-sm font-medium text-gray-700 mb-2">Previous Sessions</h3>
                                        <div class="space-y-2">
                                            <?php foreach ($previousSessions as $prevSession): ?>
                                                <div class="bg-gray-50 p-3 rounded-md text-sm">
                                                    <div class="flex justify-between items-center mb-1">
                                                        <span class="font-medium"><?php echo date("M j, Y", strtotime($prevSession['session_date'])); ?></span>
                                                        <span class="text-xs px-2 py-1 rounded-full <?php echo getNoteStatusBadgeClass($prevSession['note_status']); ?>">
                                                            <?php echo ucfirst($prevSession['note_status']); ?>
                                                        </span>
                                                    </div>
                                                    <p class="text-gray-600 text-xs line-clamp-2">
                                                        <?php 
                                                        if (!empty($prevSession['session_notes'])) {
                                                            echo htmlspecialchars(substr($prevSession['session_notes'], 0, 100)) . (strlen($prevSession['session_notes']) > 100 ? '...' : '');
                                                        } else {
                                                            echo "No notes recorded";
                                                        }
                                                        ?>
                                                    </p>
                                                    <a href="session_notes.php?id=<?php echo $prevSession['session_id']; ?>" class="text-indigo-600 hover:text-indigo-900 text-xs">View full notes</a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="pt-5 pb-2 border-t border-gray-200 mt-6 flex justify-end space-x-3">
                            <a href="<?php echo $sessionId ? "view_appointment.php?id=$sessionId" : "session_notes.php"; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Cancel
                            </a>
                            <button type="submit" name="save_notes" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Save Notes
                            </button>