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
$firstName = $lastName = $email = "";
$dob = $emergencyName = $emergencyPhone = $concerns = "";
$firstNameErr = $lastNameErr = $emailErr = "";
$dobErr = $emergencyNameErr = $emergencyPhoneErr = "";
$generalErr = $success = "";

// Get user profile data from database
try {
    $stmt = $conn->prepare("SELECT u.*, pd.date_of_birth, pd.emergency_contact_name, pd.emergency_contact_phone, pd.primary_concerns
                           FROM users u
                           LEFT JOIN patient_details pd ON u.user_id = pd.user_id
                           WHERE u.user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        
        // Set form values from database
        $firstName = $user['first_name'];
        $lastName = $user['last_name'];
        $email = $user['email'];
        $dob = $user['date_of_birth'] ?? '';
        $emergencyName = $user['emergency_contact_name'] ?? '';
        $emergencyPhone = $user['emergency_contact_phone'] ?? '';
        $concerns = $user['primary_concerns'] ?? '';
    }
} catch(PDOException $e) {
    $generalErr = "Error: " . $e->getMessage();
}

// Process form data when the update form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $formValid = true;
    
    // Validate first name
    if (empty($_POST["firstName"])) {
        $firstNameErr = "First name is required";
        $formValid = false;
    } else {
        $firstName = test_input($_POST["firstName"]);
        // Check if name only contains letters and whitespace
        if (!preg_match("/^[a-zA-Z ]*$/", $firstName)) {
            $firstNameErr = "Only letters and white space allowed";
            $formValid = false;
        }
    }
    
    // Validate last name
    if (empty($_POST["lastName"])) {
        $lastNameErr = "Last name is required";
        $formValid = false;
    } else {
        $lastName = test_input($_POST["lastName"]);
        // Check if name only contains letters and whitespace
        if (!preg_match("/^[a-zA-Z ]*$/", $lastName)) {
            $lastNameErr = "Only letters and white space allowed";
            $formValid = false;
        }
    }
    
    // Email cannot be changed, so no validation needed
    
    // Validate date of birth (optional)
    if (!empty($_POST["dob"])) {
        $dob = test_input($_POST["dob"]);
        
        // Check if date is valid
        $dobDate = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dobDate || $dobDate->format('Y-m-d') !== $dob) {
            $dobErr = "Invalid date format";
            $formValid = false;
        } else {
            // Check if user is at least 13 years old
            $today = new DateTime();
            $age = $dobDate->diff($today)->y;
            if ($age < 13) {
                $dobErr = "You must be at least 13 years old";
                $formValid = false;
            }
        }
    } else {
        $dob = null;
    }
    
    // Validate emergency contact name (optional)
    if (!empty($_POST["emergencyName"])) {
        $emergencyName = test_input($_POST["emergencyName"]);
        if (!preg_match("/^[a-zA-Z ]*$/", $emergencyName)) {
            $emergencyNameErr = "Only letters and white space allowed";
            $formValid = false;
        }
    } else {
        $emergencyName = null;
    }
    
    // Validate emergency contact phone (optional)
    if (!empty($_POST["emergencyPhone"])) {
        $emergencyPhone = test_input($_POST["emergencyPhone"]);
        // Basic phone validation
        if (!preg_match("/^[0-9()+\- ]*$/", $emergencyPhone)) {
            $emergencyPhoneErr = "Invalid phone number format";
            $formValid = false;
        }
    } else {
        $emergencyPhone = null;
    }
    
    // Get primary concerns
    $concerns = test_input($_POST["concerns"] ?? '');
    
    // If form is valid, update user information in database
    if ($formValid) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET first_name = :firstName, last_name = :lastName WHERE user_id = :user_id");
            $stmt->bindParam(':firstName', $firstName);
            $stmt->bindParam(':lastName', $lastName);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Update patient_details table
            $stmt = $conn->prepare("UPDATE patient_details 
                                  SET date_of_birth = :dob, 
                                      emergency_contact_name = :emergencyName, 
                                      emergency_contact_phone = :emergencyPhone, 
                                      primary_concerns = :concerns 
                                  WHERE user_id = :user_id");
            $stmt->bindParam(':dob', $dob);
            $stmt->bindParam(':emergencyName', $emergencyName);
            $stmt->bindParam(':emergencyPhone', $emergencyPhone);
            $stmt->bindParam(':concerns', $concerns);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Update session data
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            
            $success = "Profile updated successfully!";
        } catch(PDOException $e) {
            // Rollback transaction on error
            $conn->rollBack();
            $generalErr = "Error updating profile: " . $e->getMessage();
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
    <title>My Profile | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">My Profile</h1>
        
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
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="md:flex">
                <!-- Profile Sidebar -->
                <div class="md:w-1/3 bg-indigo-50 p-6">
                    <div class="text-center">
                        <div class="h-32 w-32 rounded-full bg-indigo-600 mx-auto flex items-center justify-center text-white text-3xl font-bold">
                            <?php echo substr($firstName, 0, 1) . substr($lastName, 0, 1); ?>
                        </div>
                        
                        <!-- Account Type -->
                        <div class="mt-2">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                                Patient
                            </span>
                        </div>
                        
                        <!-- Account Info -->
                        <div class="mt-6 text-left">
                            <p class="text-sm text-gray-700 mb-1">
                                <span class="font-medium">Member since:</span> <?php echo date("M j, Y", strtotime($user['registration_date'])); ?>
                            </p>
                            <p class="text-sm text-gray-700 mb-1">
                                <span class="font-medium">Last login:</span> <?php echo $user['last_login'] ? date("M j, Y g:i A", strtotime($user['last_login'])) : 'Never'; ?>
                            </p>
                        </div>
                        
                        <!-- Apply as Professional Button -->
                        <div class="mt-8">
                            <a href="apply_professional.php" class="block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                                Apply as Professional
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Form -->
                <div class="md:w-2/3 p-6">
                    <div class="border-b border-gray-200 pb-4 mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Personal Information</h2>
                        <p class="text-gray-600 text-sm">Update your personal information and emergency contacts</p>
                    </div>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <!-- First Name Field -->
                        <div class="mb-4">
                            <label for="firstName" class="block text-gray-700 font-medium mb-2">First Name</label>
                            <input type="text" id="firstName" name="firstName" value="<?php echo $firstName; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $firstNameErr ? 'border-red-500' : ''; ?>">
                            <?php if($firstNameErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $firstNameErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Last Name Field -->
                        <div class="mb-4">
                            <label for="lastName" class="block text-gray-700 font-medium mb-2">Last Name</label>
                            <input type="text" id="lastName" name="lastName" value="<?php echo $lastName; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $lastNameErr ? 'border-red-500' : ''; ?>">
                            <?php if($lastNameErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $lastNameErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Email Field (read-only) -->
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo $email; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-500 leading-tight bg-gray-100" readonly>
                            <p class="text-gray-500 text-xs italic mt-1">Email address cannot be changed</p>
                        </div>
                        
                        <!-- Date of Birth Field -->
                        <div class="mb-4">
                            <label for="dob" class="block text-gray-700 font-medium mb-2">Date of Birth</label>
                            <input type="date" id="dob" name="dob" value="<?php echo $dob; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $dobErr ? 'border-red-500' : ''; ?>">
                            <?php if($dobErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $dobErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4 mt-4 mb-4">
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Emergency Contact Information</h3>
                        </div>
                        
                        <!-- Emergency Contact Name Field -->
                        <div class="mb-4">
                            <label for="emergencyName" class="block text-gray-700 font-medium mb-2">Emergency Contact Name</label>
                            <input type="text" id="emergencyName" name="emergencyName" value="<?php echo $emergencyName; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $emergencyNameErr ? 'border-red-500' : ''; ?>">
                            <?php if($emergencyNameErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $emergencyNameErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Emergency Contact Phone Field -->
                        <div class="mb-4">
                            <label for="emergencyPhone" class="block text-gray-700 font-medium mb-2">Emergency Contact Phone</label>
                            <input type="text" id="emergencyPhone" name="emergencyPhone" value="<?php echo $emergencyPhone; ?>" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $emergencyPhoneErr ? 'border-red-500' : ''; ?>">
                            <?php if($emergencyPhoneErr): ?>
                                <p class="text-red-500 text-xs italic mt-1"><?php echo $emergencyPhoneErr; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4 mt-4 mb-4">
                            <h3 class="text-lg font-medium text-gray-800 mb-2">Health Information</h3>
                        </div>
                        
                        <!-- Primary Concerns Field -->
                        <div class="mb-6">
                            <label for="concerns" class="block text-gray-700 font-medium mb-2">Primary Concerns (Optional)</label>
                            <textarea id="concerns" name="concerns" rows="4" 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo $concerns; ?></textarea>
                            <p class="text-gray-500 text-xs italic mt-1">This information helps us match you with appropriate resources and professionals</p>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex items-center justify-between">
                            <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Update Profile
                            </button>
                            <a href="change_password.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                Change Password
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
                        <h2 class="mt-4 text-xl font-semibold"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></h2>
                        <p class="text-gray-600"><?php echo htmlspecialchars($email); ?></p>
                        
                        <!-- Account Status -->
                        <div class="mt-6">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <span class="h-2 w-2 rounded-full bg-green-500 mr-1"></span>
                                Active Account
                            </span>
                        </div>