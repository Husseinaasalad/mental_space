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

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: applications.php");
    exit();
}

$applicationId = $_GET['id'];
$application = null;
$generalErr = "";

// Get application details
try {
    $stmt = $conn->prepare("SELECT pa.*, u.first_name, u.last_name, u.email, u.registration_date 
                           FROM professional_applications pa 
                           JOIN users u ON pa.user_id = u.user_id 
                           WHERE pa.application_id = :application_id");
    $stmt->bindParam(':application_id', $applicationId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $application = $stmt->fetch();
    } else {
        header("Location: applications.php");
        exit();
    }
} catch(PDOException $e) {
    $generalErr = "Error: " . $e->getMessage();
}

// Process form submission (application decision)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formValid = true;
    $decision = $_POST['decision'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    
    // Validate decision
    if ($decision !== 'approve' && $decision !== 'reject') {
        $generalErr = "Invalid decision.";
        $formValid = false;
    }
    
    // If form is valid, update application status
    if ($formValid) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update application status
            $status = ($decision === 'approve') ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE professional_applications 
                                  SET status = :status, admin_feedback = :feedback, decision_date = NOW() 
                                  WHERE application_id = :application_id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':feedback', $feedback);
            $stmt->bindParam(':application_id', $applicationId);
            $stmt->execute();
            
            // If approved, also update user role and create therapist details
            if ($decision === 'approve') {
                // Update user role
                $stmt = $conn->prepare("UPDATE users SET role = 'therapist' WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $application['user_id']);
                $stmt->execute();
                
                // Check if therapist details already exist
                $stmt = $conn->prepare("SELECT therapist_id FROM therapist_details WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $application['user_id']);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    // Insert therapist details
                    $stmt = $conn->prepare("INSERT INTO therapist_details 
                                          (user_id, specialization, qualification, license_number, years_of_experience, hourly_rate, availability) 
                                          VALUES 
                                          (:user_id, :specialization, :qualification, :license_number, :years_of_experience, :hourly_rate, :availability)");
                    $stmt->bindParam(':user_id', $application['user_id']);
                    $stmt->bindParam(':specialization', $application['specialization']);
                    $stmt->bindParam(':qualification', $application['qualification']);
                    $stmt->bindParam(':license_number', $application['license_number']);
                    $stmt->bindParam(':years_of_experience', $application['years_of_experience']);
                    $stmt->bindParam(':hourly_rate', $application['hourly_rate']);
                    $stmt->bindParam(':availability', $application['availability']);
                    $stmt->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Send notification email to user (in a real application)
            // Code for sending email would go here
            
            // Redirect back to applications list
            header("Location: applications.php?status=updated");
            exit();
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $generalErr = "Error processing application: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Professional Application | Mental Space Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include admin sidebar and navigation -->
    <?php include 'admin_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Professional Application Review</h1>
                <p class="text-gray-600">
                    Application ID: <?php echo $applicationId; ?> | 
                    Submitted: <?php echo date("F j, Y", strtotime($application['application_date'])); ?>
                </p>
            </div>
            <a href="applications.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Applications
            </a>
        </div>
        
        <?php if($generalErr): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $generalErr; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Application Status Header -->
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="rounded-full h-10 w-10 flex items-center justify-center mr-3
                        <?php 
                        switch ($application['status']) {
                            case 'approved': echo 'bg-green-100 text-green-600'; break;
                            case 'rejected': echo 'bg-red-100 text-red-600'; break;
                            default: echo 'bg-yellow-100 text-yellow-600'; break;
                        }
                        ?>">
                        <?php 
                        switch ($application['status']) {
                            case 'approved': echo '<i class="fas fa-check"></i>'; break;
                            case 'rejected': echo '<i class="fas fa-times"></i>'; break;
                            default: echo '<i class="fas fa-clock"></i>'; break;
                        }
                        ?>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            <?php 
                            switch ($application['status']) {
                                case 'approved': echo 'Approved Application'; break;
                                case 'rejected': echo 'Rejected Application'; break;
                                default: echo 'Pending Application'; break;
                            }
                            ?>
                        </h2>
                        <p class="text-gray-600">
                            <?php if ($application['status'] === 'pending'): ?>
                                Waiting for your review
                            <?php else: ?>
                                Decision made on <?php echo date("F j, Y", strtotime($application['decision_date'])); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Applicant Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Applicant Information</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Full Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Email Address</p>
                            <p class="font-medium"><?php echo htmlspecialchars($application['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">User Since</p>
                            <p class="font-medium"><?php echo date("F j, Y", strtotime($application['registration_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">User ID</p>
                            <p class="font-medium"><?php echo $application['user_id']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Professional Qualifications -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Professional Qualifications</h3>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Specialization</p>
                            <p class="font-medium"><?php echo htmlspecialchars($application['specialization']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Qualification</p>
                            <p class="font-medium"><?php echo htmlspecialchars($application['qualification']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">License Number</p>
                            <p class="font-medium"><?php echo htmlspecialchars($application['license_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Years of Experience</p>
                            <p class="font-medium"><?php echo $application['years_of_experience']; ?> years</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Hourly Rate</p>
                            <p class="font-medium">
                                <?php echo $application['hourly_rate'] ? '$' . number_format($application['hourly_rate'], 2) : 'Not specified'; ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Availability</p>
                            <p class="font-medium">
                                <?php echo !empty($application['availability']) ? htmlspecialchars($application['availability']) : 'Not specified'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Additional Information</h3>
                    <div>
                        <p class="text-sm text-gray-500 mb-2">Notes from Applicant</p>
                        <div class="bg-gray-50 p-4 rounded">
                            <?php if (!empty($application['additional_info'])): ?>
                                <p><?php echo nl2br(htmlspecialchars($application['additional_info'])); ?></p>
                            <?php else: ?>
                                <p class="text-gray-500 italic">No additional information provided</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Credentials Document -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Credentials Document</h3>
                    <?php if (!empty($application['credentials_document'])): ?>
                        <div class="flex items-center">
                            <i class="fas fa-file-alt text-2xl text-indigo-600 mr-3"></i>
                            <div>
                                <p class="font-medium">Credentials Document</p>
                                <a href="../<?php echo htmlspecialchars($application['credentials_document']); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-sm">
                                    View Document <i class="fas fa-external-link-alt ml-1"></i>
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 italic">No document uploaded</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($application['status'] === 'pending'): ?>
                    <!-- Application Decision Form -->
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $applicationId); ?>">
                        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Make a Decision</h3>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 font-medium mb-2">Application Decision</label>
                                <div class="flex space-x-4">
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="decision" value="approve" class="form-radio h-5 w-5 text-green-600" checked>
                                        <span class="ml-2 text-gray-700">Approve</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="radio" name="decision" value="reject" class="form-radio h-5 w-5 text-red-600">
                                        <span class="ml-2 text-gray-700">Reject</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="feedback" class="block text-gray-700 font-medium mb-2">Feedback (Optional)</label>
                                <textarea id="feedback" name="feedback" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                                <p class="text-gray-500 text-xs mt-1">This feedback will be shared with the applicant</p>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    Submit Decision
                                </button>
                                <a href="applications.php" class="text-gray-600 hover:text-gray-800 text-sm">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Decision Information -->
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Decision Information</h3>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-500 mb-1">Status</p>
                            <p class="font-medium">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo $application['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-500 mb-1">Decision Date</p>
                            <p class="font-medium"><?php echo date("F j, Y", strtotime($application['decision_date'])); ?></p>
                        </div>
                        
                        <?php if (!empty($application['admin_feedback'])): ?>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Feedback Provided</p>
                                <div class="bg-white p-4 rounded border border-gray-200">
                                    <p><?php echo nl2br(htmlspecialchars($application['admin_feedback'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>