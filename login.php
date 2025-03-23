<?php
// Start the session
session_start();

// Include database connection
require_once 'db_connection.php';

// Initialize variables
$email = $password = "";
$emailErr = $passwordErr = $loginErr = "";
$formValid = true;
$justRegistered = isset($_GET['registered']) && $_GET['registered'] == 'true';

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
        $formValid = false;
    } else {
        $password = test_input($_POST["password"]);
    }
    
    // If form is valid, check credentials
    if ($formValid) {
        try {
            // Prepare SQL statement to get user data
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = :email AND account_status = 'active'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            // Check if user exists
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Verify the password
                if (password_verify($password, $user['password'])) {
                    // Password is correct - set up the session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // Update last login time
                    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                    $updateStmt->bindParam(':user_id', $user['user_id']);
                    $updateStmt->execute();
                    
                    // Redirect based on user role
                    switch ($user['role']) {
                        case 'admin':
                            header("Location: admin/dashboard.php");
                            break;
                        case 'therapist':
                            header("Location: therapist/dashboard.php");
                            break;
                        case 'patient':
                        default:
                            header("Location: patient/dashboard.php");
                            break;
                    }
                    exit();
                } else {
                    // Password is incorrect
                    $loginErr = "Invalid email or password";
                }
            } else {
                // User doesn't exist or is inactive
                $loginErr = "Invalid email or password";
            }
        } catch(PDOException $e) {
            $loginErr = "Login error: " . $e->getMessage();
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
    <title>Log In | Mental Space</title>
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
                    <a href="register.php" class="py-2 px-4 bg-indigo-800 text-white rounded hover:bg-indigo-700 transition duration-300">Sign up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container mx-auto px-4 py-16">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="py-4 px-6 bg-indigo-600 text-white text-center">
                <h2 class="text-2xl font-bold">Welcome Back</h2>
                <p class="text-indigo-200">Log in to your Mental Space account</p>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="py-4 px-6">
                <?php if ($justRegistered): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <p>Registration successful! You can now log in with your email and password.</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($loginErr): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <p><?php echo $loginErr; ?></p>
                    </div>
                <?php endif; ?>
                
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
                        placeholder="Enter your password">
                    <?php if ($passwordErr): ?>
                        <p class="text-red-500 text-xs italic mt-1"><?php echo $passwordErr; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Remember Me and Forgot Password -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" 
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-gray-700">
                            Remember me
                        </label>
                    </div>
                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-500">Forgot your password?</a>
                </div>
                
                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                        Log In
                    </button>
                </div>
                
                <!-- Register Link -->
                <div class="text-center mt-4">
                    <p class="text-sm text-gray-600">
                        Don't have an account? <a href="register.php" class="text-indigo-600 hover:text-indigo-500">Sign up</a>
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