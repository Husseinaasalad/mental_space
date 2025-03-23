<?php
// Include database connection
require_once 'db_connection.php';

// Initialize variables
$firstName = $lastName = $email = $password = "";
$firstNameErr = $lastNameErr = $emailErr = $passwordErr = $generalErr = "";
$formValid = true;
$registrationSuccess = false;

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    
    // Validate email
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
        $formValid = false;
    } else {
        $email = test_input($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid email format";
            $formValid = false;
        } else {
            // Check if email already exists in the database
            try {
                $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $emailErr = "This email is already registered";
                    $formValid = false;
                }
            } catch(PDOException $e) {
                $generalErr = "Registration error: " . $e->getMessage();
                $formValid = false;
            }
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
        $formValid = false;
    } else {
        $password = test_input($_POST["password"]);
        // Check if password meets requirements (at least 8 characters, 1 uppercase, 1 lowercase, 1 number)
        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/", $password)) {
            $passwordErr = "Password must be at least 8 characters and include uppercase, lowercase, and numbers";
            $formValid = false;
        }
    }
    
    // If form is valid, process the data and insert into database
    if ($formValid) {
        try {
            // Hash the password for security
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare SQL and bind parameters
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (:firstName, :lastName, :email, :password, 'patient')");
            $stmt->bindParam(':firstName', $firstName);
            $stmt->bindParam(':lastName', $lastName);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashedPassword);
            
            // Execute the query
            $stmt->execute();
            
            // Get the newly created user ID
            $userId = $conn->lastInsertId();
            
            // Create a record in the patient_details table
            $stmt = $conn->prepare("INSERT INTO patient_details (user_id) VALUES (:userId)");
            $stmt->bindParam(':userId', $userId);
            $stmt->execute();
            
            // Registration successful
            $registrationSuccess = true;
            
            // Redirect to login page after successful registration
            header("Location: login.php?registered=true");
            exit();
        } catch(PDOException $e) {
            $generalErr = "Registration failed: " . $e->getMessage();
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
    <title>Register | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Navigation -->
    <nav class="bg-indigo-600 shadow-lg">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-4">
                    <!-- Logo -->
                    <div>
                        <a href="index.php" class="flex items-center py-5 px-2 text-white">
                            <i class="fas fa-brain text-xl mr-1"></i>
                            <span class="font-bold text-xl">Mental Space</span>
                        </a>
                    </div>
                </div>
                <!-- Auth Buttons -->
                <div class="flex items-center space-x-1">
                    <a href="login.php" class="py-2 px-4 bg-white text-indigo-600 rounded hover:bg-gray-200 transition duration-300">Log in</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Registration Form -->
    <div class="container mx-auto px-4 py-16">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="py-4 px-6 bg-indigo-600 text-white text-center">
                <h2 class="text-2xl font-bold">Create Your Account</h2>
                <p class="text-indigo-200">Join the Mental Space community</p>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="py-4 px-6">
                <!-- First Name Field -->
                <div class="mb-4">
                    <label for="firstName" class="block text-gray-700 font-bold mb-2">First Name</label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo $firstName; ?>" 
                        class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $firstNameErr ? 'border-red-500' : ''; ?>" 
                        placeholder="Enter your first name">
                    <?php if ($firstNameErr): ?>
                        <p class="text-red-500 text-xs italic mt-1"><?php echo $firstNameErr; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Last Name Field -->
                <div class="mb-4">
                    <label for="lastName" class="block text-gray-700 font-bold mb-2">Last Name</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo $lastName; ?>" 
                        class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $lastNameErr ? 'border-red-500' : ''; ?>" 
                        placeholder="Enter your last name">
                    <?php if ($lastNameErr): ?>
                        <p class="text-red-500 text-xs italic mt-1"><?php echo $lastNameErr; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Email Field -->
                <div class="mb-4">
                    <label for="email" class="block text-gray-700 font-bold mb-2">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" 
                        class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $emailErr ? 'border-red-500' : ''; ?>" 
                        placeholder="Enter your email address">
                    <?php if ($emailErr): ?>
                        <p class="text-red-500 text-xs italic mt-1"><?php echo $emailErr; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Password Field -->
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                    <input type="password" id="password" name="password" 
                        class="appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo $passwordErr ? 'border-red-500' : ''; ?>" 
                        placeholder="Create a password">
                    <?php if ($passwordErr): ?>
                        <p class="text-red-500 text-xs italic mt-1"><?php echo $passwordErr; ?></p>
                    <?php else: ?>
                        <p class="text-gray-500 text-xs italic mt-1">Must be at least 8 characters with uppercase, lowercase, and numbers</p>
                    <?php endif; ?>
                </div>
                
                <!-- User Agreement -->
                <div class="mb-6">
                    <div class="flex items-center">
                        <input id="agree" name="agree" type="checkbox" required 
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="agree" class="ml-2 block text-sm text-gray-700">
                            I agree to the <a href="#" class="text-indigo-600 hover:text-indigo-500">Terms of Service</a> and <a href="#" class="text-indigo-600 hover:text-indigo-500">Privacy Policy</a>
                        </label>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                        Sign Up
                    </button>
                </div>
                
                <!-- Login Link -->
                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        Already have an account? <a href="login.php" class="text-indigo-600 hover:text-indigo-500">Log in</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="container mx-auto px-4 text-center">
            <a href="index.php" class="inline-flex items-center">
                <i class="fas fa-brain text-xl mr-1"></i>
                <span class="font-bold text-xl">Mental Space</span>
            </a>
            <p class="mt-2 text-sm text-gray-400">&copy; <?php echo date("Y"); ?> Mental Space. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>