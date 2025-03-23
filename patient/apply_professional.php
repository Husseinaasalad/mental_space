<?php
// Start the session
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if user is a patient (only patients can apply to be professionals)
if ($_SESSION['role'] !== 'patient') {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once '../db_connection.php';

// Initialize variables
$specialization = $qualification = $licenseNumber = $experience = "";
$hourlyRate = $availability = $additionalInfo = "";
$specializationErr = $qualificationErr = $licenseNumberErr = $experienceErr = "";
$hourlyRateErr = $uploadErr = $generalErr = $success = "";
$formValid = true;

// Check if user already has a pending application
try {
    $stmt = $conn->prepare("SELECT * FROM professional_applications WHERE user_id = :user_id AND status IN ('pending', 'approved')");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $application = $stmt->fetch();
        if ($application['status'] === 'pending') {
            $pendingApplication = true;
            $applicationDate = date("F j, Y", strtotime($application['application_date']));
        } else {
            // Application already approved, redirect to professional dashboard
            header("Location: ../professional/dashboard.php");
            exit();
        }
    } else {
        $pendingApplication = false;
    }
} catch(PDOException $e) {
    $generalErr = "Error checking application status: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($pendingApplication)) {
    // Validate specialization
    if (empty($_POST["specialization"])) {
        $specializationErr = "Specialization is required";
        $formValid = false;
    } else {
        $specialization = test_input($_POST["specialization"]);
    }
    
    // Validate qualification
    if (empty($_POST["qualification"])) {
        $qualificationErr = "Qualification is required";
        $formValid = false;
    } else {
        $qualification = test_input($_POST["qualification"]);
    }
    
    // Validate license number
    if (empty($_POST["licenseNumber"])) {
        $licenseNumberErr = "License number is required";
        $formValid = false;
    } else {
        $licenseNumber = test_input($_POST["licenseNumber"]);
    }
    
    // Validate years of experience
    if (empty($_POST["experience"])) {
        $experienceErr = "Years of experience is required";
        $formValid = false;
    } else {
        $experience = test_input($_POST["experience"]);
        if (!is_numeric($experience) || $experience < 0) {
            $experienceErr = "Please enter a valid number";
            $formValid = false;
        }
    }
    
    // Validate hourly rate (optional)
    if (!empty($_POST["hourlyRate"])) {
        $hourlyRate = test_input($_POST["hourlyRate"]);
        if (!is_numeric($hourlyRate) || $hourlyRate <= 0) {
            $hourlyRateErr = "Please enter a valid rate";
            $formValid = false;
        }
    } else {
        $hourlyRate = null;
    }
    
    // Get availability and additional info
    $availability = test_input($_POST["availability"] ?? '');
    $additionalInfo = test_input($_POST["additionalInfo"] ?? '');
    
    // Handle document upload
    $documentPath = null;
    if (isset($_FILES['credentials']) && $_FILES['credentials']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $fileType = $_FILES['credentials']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileName = time() . '_' . $_FILES['credentials']['name'];
            $uploadDir = '../uploads/credentials/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['credentials']['tmp_name'], $uploadPath)) {
                $documentPath = 'uploads/credentials/' . $fileName;
            } else {
                $uploadErr = "Failed to upload document";
                $formValid = false;
            }
        } else {
            $uploadErr = "Invalid file type. Please upload PDF, JPEG, or PNG";
            $formValid = false;
        }
    } else {
        if ($_FILES['credentials']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadErr = "Error uploading document. Please try again";
            $formValid = false;
        } else {
            $uploadErr = "Credentials document is required";
            $formValid = false;
        }
    }
    
    // If form is valid, insert application into database
    if ($formValid) {
        try {
            // Insert application
            $stmt = $conn->prepare("INSERT INTO professional_applications 
                                  (user_id, specialization, qualification, license_number, years_of_experience, 
                                   hourly_rate, availability, credentials_document, additional_info, application_date, status) 
                                  VALUES 
                                  (:user_id, :specialization, :qualification, :licenseNumber, :experience, 
                                   :hourlyRate, :availability, :documentPath, :additionalInfo, NOW(), 'pending')");
            
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':specialization', $specialization);
            $stmt->bindParam(':qualification', $qualification);
            $stmt->bindParam(':licenseNumber', $licenseNumber);
            $stmt->bindParam(':experience', $experience);
            $stmt->bindParam(':hourlyRate', $hourlyRate);
            $stmt->bindParam(':availability', $availability);
            $stmt->bindParam(':documentPath', $documentPath);
            $stmt->bindParam(':additionalInfo', $additionalInfo);
            
            $stmt->execute();
            
            // Set success message and reload page to show pending application status
            $success = "Your application has been submitted successfully! We will review it and get back to you soon.";
            
            // Redirect to same page to show pending status
            header("Location: apply_professional.php?success=1");
            exit();
        } catch(PDOException $e) {
            $generalErr = "Error submitting application: " . $e->getMessage();
        }
    }
}

// Function to sanitize input data
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Professional | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Apply as a Mental Health Professional</h1>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>Your application has been submitted successfully! We will review it and get back to you soon.</p>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($generalErr): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $generalErr; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if(isset($pendingApplication) && $pendingApplication): ?>
            <!-- Pending Application Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <span class="h-12 w-12 rounded-full bg-yellow-100 flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </span>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-xl font-semibold text-gray-800">Application Under Review</h2>
                        <p class="text-gray-600">Your application submitted on <?php echo $applicationDate; ?> is currently being reviewed by our team.</p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 rounded-lg p-4 mb-4">
                    <p class="text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        The review process typically takes 2-3 business days. You will be notified by email once a decision has been made.
                    </p>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h3 class="text-lg font-medium text-gray-800 mb-2">What happens next?</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2"><i class="fas fa-check-circle"></i></span>
                            <span>Our admin team reviews your qualifications and credentials</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2"><i class="fas fa-check-circle"></i></span>
                            <span>You'll receive an email notification about the decision</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2"><i class="fas fa-check-circle"></i></span>
                            <span>If approved, your account will be upgraded to a professional account</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-green-500 mr-2"><i class="fas fa-check-circle"></i></span>
                            <span>You'll need to log out and log back in to access your new professional dashboard</span>
                        </li>
                    </ul>
                </div>
                
                <div class="mt-6">
                    <a href="dashboard.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Return to Dashboard
                    </a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Application Form -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h2 class="text-xl font-semibold text-gray-800">Professional Information</h2>
                        <p class="text-gray-600 text-sm">Please provide your professional qualifications and credentials</p>
                    </div>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                        <!-- Specialization Field -->
                        <div class="mb-4">
                            <label for="specialization" class="block text-gray-700 font-medium mb-2">Specialization</label>
                            <input type="text" id="specialization" name="specialization" value="<?php echo $specialization; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $specializationErr ? 'border-red-500' : ''; ?>"
                                placeholder="e.g., Cognitive Behavioral Therapy, Youth Counseling, etc.">
                            <?php if($specializationErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $specializationErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Qualification Field -->
                        <div class="mb-4">
                            <label for="qualification" class="block text-gray-700 font-medium mb-2">Highest Qualification</label>
                            <input type="text" id="qualification" name="qualification" value="<?php echo $qualification; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $qualificationErr ? 'border-red-500' : ''; ?>"
                                placeholder="e.g., Ph.D. in Clinical Psychology, Master's in Counseling, etc.">
                            <?php if($qualificationErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $qualificationErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- License Number Field -->
                        <div class="mb-4">
                            <label for="licenseNumber" class="block text-gray-700 font-medium mb-2">License Number</label>
                            <input type="text" id="licenseNumber" name="licenseNumber" value="<?php echo $licenseNumber; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $licenseNumberErr ? 'border-red-500' : ''; ?>"
                                placeholder="Your professional license number">
                            <?php if($licenseNumberErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $licenseNumberErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Years of Experience Field -->
                        <div class="mb-4">
                            <label for="experience" class="block text-gray-700 font-medium mb-2">Years of Experience</label>
                            <input type="number" id="experience" name="experience" value="<?php echo $experience; ?>" min="0" step="1" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $experienceErr ? 'border-red-500' : ''; ?>">
                            <?php if($experienceErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $experienceErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Hourly Rate Field -->
                        <div class="mb-4">
                            <label for="hourlyRate" class="block text-gray-700 font-medium mb-2">Hourly Rate (USD)</label>
                            <input type="number" id="hourlyRate" name="hourlyRate" value="<?php echo $hourlyRate; ?>" min="0" step="0.01" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $hourlyRateErr ? 'border-red-500' : ''; ?>"
                                placeholder="Leave blank if you want to discuss later">
                            <?php if($hourlyRateErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $hourlyRateErr; ?></p>
                            <?php else: ?>
                                <p class="text-gray-500 text-xs italic mt-1">Optional - you can set this later</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Availability Field -->
                        <div class="mb-4">
                            <label for="availability" class="block text-gray-700 font-medium mb-2">Availability</label>
                            <textarea id="availability" name="availability" rows="3" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="e.g., Weekdays 9am-5pm, Weekends by appointment only, etc."><?php echo $availability; ?></textarea>
                        </div>
                        
                        <!-- Credentials Document Upload -->
                        <div class="mb-4">
                            <label for="credentials" class="block text-gray-700 font-medium mb-2">
                                Upload Credentials Document
                                <span class="text-red-500">*</span>
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H8m36-12h-4m4 0H20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="credentials" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none">
                                            <span>Upload a file</span>
                                            <input id="credentials" name="credentials" type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        PDF, JPG, or PNG up to 10MB
                                    </p>
                                </div>
                            </div>
                            <?php if($uploadErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $uploadErr; ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-xs mt-1">Please upload a copy of your license, degree certificate, or professional credentials</p>
                        </div>
                        
                        <!-- Additional Information Field -->
                        <div class="mb-6">
                            <label for="additionalInfo" class="block text-gray-700 font-medium mb-2">Additional Information</label>
                            <textarea id="additionalInfo" name="additionalInfo" rows="4" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Additional information about your experience, approach, or anything else you'd like us to know"><?php echo $additionalInfo; ?></textarea>
                        </div>
                        
                        <!-- Terms and Agreement -->
                        <div class="mb-6">
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <input id="terms" name="terms" type="checkbox" required
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="terms" class="font-medium text-gray-700">I understand and agree that:</label>
                                        <p class="text-gray-500 mt-1">
                                            - All information provided is accurate and complete<br>
                                            - Mental Space will verify my credentials and may contact me for additional information<br>
                                            - If approved, I will adhere to Mental Space's professional guidelines and code of ethics<br>
                                            - I have the necessary qualifications and licenses to provide mental health services
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Submit Application
                            </button>
                            <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Information Section -->
            <div class="mt-8 bg-indigo-50 rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Becoming a Professional on Mental Space</h2>
                
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Application Review</h3>
                        <p class="text-gray-600 text-sm">Our team reviews all professional applications to ensure quality care for our users. The review process typically takes 2-3 business days.</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Professional Benefits</h3>
                        <p class="text-gray-600 text-sm">As a Mental Space professional, you'll have access to patient journals, appointment scheduling, and a platform to offer your expertise to those in need.</p>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <div class="text-indigo-600 text-2xl mb-2">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Privacy & Ethics</h3>
                        <p class="text-gray-600 text-sm">We uphold the highest standards of privacy and ethical conduct. All professionals must adhere to our code of ethics and privacy guidelines.</p>
                    </div>
                </div>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600 text-sm">
                        Have questions about becoming a professional? 
                        <a href="contact.php" class="text-indigo-600 hover:text-indigo-800">Contact our support team</a>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>