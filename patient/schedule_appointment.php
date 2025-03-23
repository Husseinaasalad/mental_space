</div>
        
        <!-- Information Section -->
        <div class="mt-8 bg-indigo-50 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Appointment Information</h2>
            
            <div class="grid md:grid-cols-3 gap-6">
                <?php
                // Get appointment info from database
                try {
                    $stmt = $conn->prepare("SELECT * FROM platform_settings WHERE setting_key LIKE 'appointment_%' LIMIT 3");
                    $stmt->execute();
                    $appointmentInfo = $stmt->fetchAll();
                    
                    if (count($appointmentInfo) > 0) {
                        foreach ($appointmentInfo as $info) {
                            $icon = 'fas fa-info-circle';
                            $title = 'Information';
                            
                            // Determine icon based on setting key
                            if (strpos($info['setting_key'], 'duration') !== false) {
                                $icon = 'fas fa-clock';
                                $title = 'Session Duration';
                            } elseif (strpos($info['setting_key'], 'cancel') !== false) {
                                $icon = 'fas fa-calendar-times';
                                $title = 'Cancellation Policy';
                            } elseif (strpos($info['setting_key'], 'format') !== false) {
                                $icon = 'fas fa-video';
                                $title = 'Session Format';
                            }
                            ?>
                            <div class="bg-white rounded-lg p-4 shadow-sm">
                                <div class="text-indigo-600 text-2xl mb-2">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($title); ?></h3>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($info['setting_value']); ?></p>
                            </div>
                            <?php
                        }
                    } else {
                        // Fallback to default information if database settings not found
                        ?>
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-indigo-600 text-2xl mb-2">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Session Duration</h3>
                            <p class="text-gray-600 text-sm">All therapy sessions are 60 minutes long. Please arrive 5-10 minutes early to ensure a timely start.</p>
                        </div>
                        
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-indigo-600 text-2xl mb-2">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Cancellation Policy</h3>
                            <p class="text-gray-600 text-sm">Appointments can be cancelled up to 24 hours in advance without charge. Late cancellations may incur a fee.</p>
                        </div>
                        
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-indigo-600 text-2xl mb-2">
                                <i class="fas fa-video"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Session Format</h3>
                            <p class="text-gray-600 text-sm">All sessions are conducted through our secure video platform. You'll receive a link to join 15 minutes before your appointment.</p>
                        </div>
                        <?php
                    }
                } catch (PDOException $e) {
                    // Display fallback info if there's a database error
                    ?>
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Session Duration</h3>
                        <p class="text-gray-600 text-sm">All therapy sessions are 60 minutes long. Please arrive 5-10 minutes early to ensure a timely start.</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-calendar-times"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Cancellation Policy</h3>
                        <p class="text-gray-600 text-sm">Appointments can be cancelled up to 24 hours in advance without charge. Late cancellations may incur a fee.</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Session Format</h3>
                        <p class="text-gray-600 text-sm">All sessions are conducted through our secure video platform. You'll receive a link to join 15 minutes before your appointment.</p>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</body><?php
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
$therapistId = isset($_GET['therapist_id']) ? $_GET['therapist_id'] : '';
$selectedDate = isset($_POST['appointment_date']) ? $_POST['appointment_date'] : '';
$selectedTime = isset($_POST['appointment_time']) ? $_POST['appointment_time'] : '';
$sessionType = isset($_POST['session_type']) ? $_POST['session_type'] : '';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

$therapistIdErr = $appointmentDateErr = $appointmentTimeErr = $sessionTypeErr = "";
$success = $generalErr = "";

// Get all therapists for dropdown
try {
    $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, td.specialization 
                           FROM users u 
                           JOIN therapist_details td ON u.user_id = td.user_id 
                           WHERE u.role = 'therapist' AND u.account_status = 'active'
                           ORDER BY u.last_name, u.first_name");
    $stmt->execute();
    $therapists = $stmt->fetchAll();
} catch(PDOException $e) {
    $generalErr = "Error retrieving therapists: " . $e->getMessage();
    $therapists = [];
}

// If a therapist is selected, get their details and available time slots
$selectedTherapist = null;
$availableTimeSlots = [];

if (!empty($therapistId) && is_numeric($therapistId)) {
    try {
        // Get therapist details
        $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, td.specialization, 
                                td.qualification, td.years_of_experience, td.hourly_rate, td.availability 
                               FROM users u 
                               JOIN therapist_details td ON u.user_id = td.user_id 
                               WHERE u.user_id = :therapist_id AND u.role = 'therapist' AND u.account_status = 'active'");
        $stmt->bindParam(':therapist_id', $therapistId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $selectedTherapist = $stmt->fetch();
            
            // If a date is selected, get available time slots
            if (!empty($selectedDate)) {
                // Get booked time slots for the therapist on selected date
                $stmt = $conn->prepare("SELECT TIME_FORMAT(session_date, '%H:%i') as session_time 
                                      FROM sessions 
                                      WHERE therapist_id = :therapist_id 
                                      AND DATE(session_date) = :selected_date 
                                      AND session_status != 'cancelled'");
                $stmt->bindParam(':therapist_id', $therapistId);
                $stmt->bindParam(':selected_date', $selectedDate);
                $stmt->execute();
                $bookedTimeSlots = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                
                // Generate available time slots (9 AM to 5 PM, hourly)
                $availableTimeSlots = [];
                for ($hour = 9; $hour < 17; $hour++) {
                    $timeSlot = sprintf("%02d:00", $hour);
                    if (!in_array($timeSlot, $bookedTimeSlots)) {
                        $availableTimeSlots[] = $timeSlot;
                    }
                }
            }
        }
    } catch(PDOException $e) {
        $generalErr = "Error retrieving therapist details: " . $e->getMessage();
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['schedule'])) {
    $formValid = true;
    
    // Validate therapist
    if (empty($_POST['therapist_id'])) {
        $therapistIdErr = "Please select a therapist";
        $formValid = false;
    } else {
        $therapistId = $_POST['therapist_id'];
    }
    
    // Validate appointment date
    if (empty($_POST['appointment_date'])) {
        $appointmentDateErr = "Please select a date";
        $formValid = false;
    } else {
        $selectedDate = $_POST['appointment_date'];
        $appointmentDate = new DateTime($selectedDate);
        $today = new DateTime('today');
        
        if ($appointmentDate < $today) {
            $appointmentDateErr = "Please select a future date";
            $formValid = false;
        }
    }
    
    // Validate appointment time
    if (empty($_POST['appointment_time'])) {
        $appointmentTimeErr = "Please select a time";
        $formValid = false;
    } else {
        $selectedTime = $_POST['appointment_time'];
    }
    
    // Validate session type
    if (empty($_POST['session_type'])) {
        $sessionTypeErr = "Please select a session type";
        $formValid = false;
    } else {
        $sessionType = $_POST['session_type'];
    }
    
    // Additional notes (optional)
    $notes = $_POST['notes'] ?? '';
    
    // If form is valid, create the appointment
    if ($formValid) {
        try {
            // Format the appointment date and time
            $appointmentDateTime = $selectedDate . ' ' . $selectedTime;
            
            // Default session duration is 60 minutes
            $duration = 60;
            
            // Insert session into database
            $stmt = $conn->prepare("INSERT INTO sessions 
                                    (therapist_id, patient_id, session_date, duration, session_type, session_status, session_notes, created_at) 
                                    VALUES 
                                    (:therapist_id, :patient_id, :session_date, :duration, :session_type, 'scheduled', :session_notes, NOW())");
            
            $stmt->bindParam(':therapist_id', $therapistId);
            $stmt->bindParam(':patient_id', $_SESSION['user_id']);
            $stmt->bindParam(':session_date', $appointmentDateTime);
            $stmt->bindParam(':duration', $duration);
            $stmt->bindParam(':session_type', $sessionType);
            $stmt->bindParam(':session_notes', $notes);
            
            $stmt->execute();
            
            // Set success message
            $success = "Your appointment has been scheduled successfully!";
            
            // Clear form data
            $selectedDate = $selectedTime = $sessionType = $notes = "";
        } catch(PDOException $e) {
            $generalErr = "Error scheduling appointment: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Schedule an Appointment</h1>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
                <div class="mt-4">
                    <a href="appointments.php" class="text-green-700 font-medium underline">View your appointments</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if($generalErr): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $generalErr; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Step Indicator -->
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="relative flex-grow">
                        <div class="flex items-center relative">
                            <div class="rounded-full h-8 w-8 flex items-center justify-center bg-indigo-600 text-white font-medium">1</div>
                            <div class="ml-3 text-sm font-medium text-gray-900">Select Therapist</div>
                        </div>
                        <div class="absolute left-4 top-4 h-full border-l border-gray-300"></div>
                    </div>
                    <div class="relative flex-grow">
                        <div class="flex items-center relative">
                            <div class="rounded-full h-8 w-8 flex items-center justify-center <?php echo !empty($therapistId) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?> font-medium">2</div>
                            <div class="ml-3 text-sm font-medium text-gray-900">Choose Date & Time</div>
                        </div>
                        <div class="absolute left-4 top-4 h-full border-l border-gray-300"></div>
                    </div>
                    <div class="relative flex-grow">
                        <div class="flex items-center relative">
                            <div class="rounded-full h-8 w-8 flex items-center justify-center <?php echo (!empty($therapistId) && !empty($selectedDate) && !empty($selectedTime)) ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'; ?> font-medium">3</div>
                            <div class="ml-3 text-sm font-medium text-gray-900">Confirm Details</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Step 1: Select Therapist -->
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">1. Select a Therapist</h2>
                        
                        <div class="mb-4">
                            <label for="therapist_id" class="block text-gray-700 font-medium mb-2">Choose a therapist</label>
                            <select id="therapist_id" name="therapist_id" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $therapistIdErr ? 'border-red-500' : ''; ?>"
                                onchange="this.form.submit()">
                                <option value="">-- Select a Therapist --</option>
                                <?php foreach($therapists as $therapist): ?>
                                    <option value="<?php echo $therapist['user_id']; ?>" <?php if($therapistId == $therapist['user_id']) echo 'selected'; ?>>
                                        Dr. <?php echo htmlspecialchars($therapist['first_name'] . ' ' . $therapist['last_name']); ?> 
                                        (<?php echo htmlspecialchars($therapist['specialization']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if($therapistIdErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $therapistIdErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($selectedTherapist): ?>
                            <div class="bg-indigo-50 p-4 rounded-lg mb-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <div class="h-16 w-16 rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold">
                                            <?php echo substr($selectedTherapist['first_name'], 0, 1) . substr($selectedTherapist['last_name'], 0, 1); ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <h3 class="text-lg font-medium text-gray-900">Dr. <?php echo htmlspecialchars($selectedTherapist['first_name'] . ' ' . $selectedTherapist['last_name']); ?></h3>
                                        <p class="text-indigo-600"><?php echo htmlspecialchars($selectedTherapist['specialization']); ?></p>
                                        <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($selectedTherapist['qualification']); ?> • <?php echo $selectedTherapist['years_of_experience']; ?> years of experience</p>
                                        
                                        <?php if($selectedTherapist['hourly_rate']): ?>
                                            <p class="text-gray-700 mt-2">
                                                <span class="font-medium">Rate:</span> $<?php echo number_format($selectedTherapist['hourly_rate'], 2); ?> per hour
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if($selectedTherapist['availability']): ?>
                                            <p class="text-gray-700 mt-1">
                                                <span class="font-medium">Availability:</span> <?php echo htmlspecialchars($selectedTherapist['availability']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($selectedTherapist): ?>
                        <!-- Step 2: Choose Date & Time -->
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">2. Choose Date & Time</h2>
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label for="appointment_date" class="block text-gray-700 font-medium mb-2">Select a date</label>
                                    <input type="date" id="appointment_date" name="appointment_date" value="<?php echo $selectedDate; ?>" 
                                        min="<?php echo date('Y-m-d'); ?>"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $appointmentDateErr ? 'border-red-500' : ''; ?>"
                                        onchange="this.form.submit()">
                                    <?php if($appointmentDateErr): ?>
                                        <p class="text-red-500 text-xs italic mt-1"><?php echo $appointmentDateErr; ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if(!empty($selectedDate)): ?>
                                    <div>
                                        <label for="appointment_time" class="block text-gray-700 font-medium mb-2">Select a time</label>
                                        <select id="appointment_time" name="appointment_time" 
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $appointmentTimeErr ? 'border-red-500' : ''; ?>">
                                            <option value="">-- Select a Time --</option>
                                            <?php if(count($availableTimeSlots) > 0): ?>
                                                <?php foreach($availableTimeSlots as $timeSlot): ?>
                                                    <option value="<?php echo $timeSlot; ?>" <?php if($selectedTime == $timeSlot) echo 'selected'; ?>>
                                                        <?php 
                                                        $hour = (int)substr($timeSlot, 0, 2);
                                                        $ampm = $hour >= 12 ? 'PM' : 'AM';
                                                        $hour12 = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
                                                        echo $hour12 . ':00 ' . $ampm; 
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <option value="" disabled>No available time slots for this date</option>
                                            <?php endif; ?>
                                        </select>
                                        <?php if($appointmentTimeErr): ?>
                                            <p class="text-red-500 text-xs italic mt-1"><?php echo $appointmentTimeErr; ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Step 3: Confirm Details -->
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">3. Confirm Details</h2>
                            
                            <div class="mb-4">
                                <label for="session_type" class="block text-gray-700 font-medium mb-2">Session Type</label>
                                <select id="session_type" name="session_type" 
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $sessionTypeErr ? 'border-red-500' : ''; ?>">
                                    <option value="">-- Select Session Type --</option>
                                    <option value="individual" <?php if($sessionType == 'individual') echo 'selected'; ?>>Individual Therapy</option>
                                    <option value="initial_assessment" <?php if($sessionType == 'initial_assessment') echo 'selected'; ?>>Initial Assessment</option>
                                    <option value="follow_up" <?php if($sessionType == 'follow_up') echo 'selected'; ?>>Follow-up Session</option>
                                    <option value="crisis_intervention" <?php if($sessionType == 'crisis_intervention') echo 'selected'; ?>>Crisis Intervention</option>
                                </select>
                                <?php if($sessionTypeErr): ?>
                                    <p class="text-red-500 text-xs italic mt-1"><?php echo $sessionTypeErr; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label for="notes" class="block text-gray-700 font-medium mb-2">Additional Notes (Optional)</label>
                                <textarea id="notes" name="notes" rows="3" 
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Share any specific concerns or topics you'd like to discuss"><?php echo $notes; ?></textarea>
                                <p class="text-gray-500 text-xs mt-1">This information will be shared with your therapist before the session.</p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                                <h3 class="text-base font-medium text-gray-800 mb-2">Appointment Summary</h3>
                                <p class="text-gray-600 mb-1">
                                    <span class="font-medium">Therapist:</span> 
                                    <?php echo $selectedTherapist ? 'Dr. ' . htmlspecialchars($selectedTherapist['first_name'] . ' ' . $selectedTherapist['last_name']) : '-'; ?>
                                </p>
                                <p class="text-gray-600 mb-1">
                                    <span class="font-medium">Date:</span> 
                                    <?php 
                                    if (!empty($selectedDate)) {
                                        $date = new DateTime($selectedDate);
                                        echo $date->format('l, F j, Y');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </p>
                                <p class="text-gray-600 mb-1">
                                    <span class="font-medium">Time:</span> 
                                    <?php 
                                    if (!empty($selectedTime)) {
                                        $hour = (int)substr($selectedTime, 0, 2);
                                        $ampm = $hour >= 12 ? 'PM' : 'AM';
                                        $hour12 = $hour > 12 ? $hour - 12 : ($hour === 0 ? 12 : $hour);
                                        echo $hour12 . ':00 ' . $ampm;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </p>
                                <p class="text-gray-600">
                                    <span class="font-medium">Duration:</span> 60 minutes
                                </p>
                                
                                <?php if($selectedTherapist && $selectedTherapist['hourly_rate']): ?>
                                    <p class="text-gray-600 mt-2">
                                        <span class="font-medium">Estimated Cost:</span> $<?php echo number_format($selectedTherapist['hourly_rate'], 2); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-between">
                            <button type="submit" name="schedule" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Schedule Appointment
                            </button>
                            <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm">
                                Cancel
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Information Section -->
        <div class="mt-8 bg-indigo-50 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Appointment Information</h2>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-indigo-600 text-2xl mb-2">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">Session Duration</h3>
                    <p class="text-gray-600 text-sm">All therapy sessions are 60 minutes long. Please arrive 5-10 minutes early to ensure a timely start.</p>
                </div>
                
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-indigo-600 text-2xl mb-2">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">Cancellation Policy</h3>
                    <p class="text-gray-600 text-sm">Appointments can be cancelled up to 24 hours in advance without charge. Late cancellations may incur a fee.</p>
                </div>
                
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <div class="text-indigo-600 text-2xl mb-2">
                        <i class="fas fa-video"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-800 mb-2">Session Format</h3>
                    <p class="text-gray-600 text-sm">All sessions are conducted through our secure video platform. You'll receive a link to join 15 minutes before your appointment.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>