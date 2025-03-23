<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Space | Urban Youth Mental Health Support</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<?php
// Start session to check if user is logged in
session_start();

// Check for logout success message
$logoutSuccess = isset($_GET['logout']) && $_GET['logout'] === 'success';
?>
<body class="bg-gray-100 font-sans">
    <?php if ($logoutSuccess): ?>
    <!-- Logout Success Message -->
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 text-center relative" role="alert">
        <span class="block sm:inline">You have been successfully logged out.</span>
    </div>
    <?php endif; ?>
    
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
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="py-2 px-4 bg-white text-indigo-600 rounded hover:bg-gray-200 transition duration-300 mr-2">Dashboard</a>
                        <?php elseif ($_SESSION['role'] === 'therapist'): ?>
                            <a href="therapist/dashboard.php" class="py-2 px-4 bg-white text-indigo-600 rounded hover:bg-gray-200 transition duration-300 mr-2">Dashboard</a>
                        <?php else: ?>
                            <a href="patient/dashboard.php" class="py-2 px-4 bg-white text-indigo-600 rounded hover:bg-gray-200 transition duration-300 mr-2">Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="py-2 px-4 bg-indigo-800 text-white rounded hover:bg-indigo-700 transition duration-300">Log out</a>
                    <?php else: ?>
                        <a href="login.php" class="py-2 px-4 bg-white text-indigo-600 rounded hover:bg-gray-200 transition duration-300">Log in</a>
                        <a href="register.php" class="py-2 px-4 bg-indigo-800 text-white rounded hover:bg-indigo-700 transition duration-300">Sign up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-indigo-700 text-white py-16">
        <div class="container mx-auto px-4 flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 mb-10 md:mb-0">
                <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-4">Your Mental Health Matters</h1>
                <p class="text-xl mb-8">A safe digital space for urban youth to connect, share, and receive mental health support.</p>
                <div class="flex flex-col sm:flex-row">
                    <a href="register.php" class="bg-white text-indigo-700 hover:bg-gray-100 font-bold py-3 px-6 rounded-lg mb-4 sm:mb-0 sm:mr-4 text-center">Get Started</a>
                    <a href="#about" class="bg-transparent border-2 border-white hover:bg-indigo-800 font-bold py-3 px-6 rounded-lg text-center">Learn More</a>
                </div>
            </div>
            <div class="md:w-1/2">
                <img src="https://images.squarespace-cdn.com/content/v1/5c27c93f1aef1d60b29781f9/1626956150227-NQE0UNYJ1DUQRRNDQM0E/Youth+3.jpg" alt="Urban youth supporting each other" class="rounded-lg shadow-xl">
            </div>
        </div>
    </div>

    <!-- About Section -->
    <section id="about" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">What is Mental Space?</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                    <div class="text-indigo-600 text-4xl mb-4">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Connect & Share</h3>
                    <p class="text-gray-600">A safe platform to connect with peers who understand your experiences and share your journey in a supportive environment.</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                    <div class="text-indigo-600 text-4xl mb-4">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Professional Support</h3>
                    <p class="text-gray-600">Access to mental health professionals who specialize in urban youth issues and challenges.</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg shadow-md">
                    <div class="text-indigo-600 text-4xl mb-4">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2 text-gray-800">Resources & Tools</h3>
                    <p class="text-gray-600">Educational resources, coping mechanisms, and interactive tools designed specifically for urban youth mental wellness.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-16 bg-gray-100">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">How Mental Space Works</h2>
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="md:w-1/2 mb-8 md:mb-0 md:pr-8">
                    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-indigo-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3">1</div>
                            <h3 class="text-xl font-bold text-gray-800">Create Your Account</h3>
                        </div>
                        <p class="text-gray-600 pl-11">Sign up to join our community and create your personal profile.</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <div class="flex items-center mb-4">
                            <div class="bg-indigo-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3">2</div>
                            <h3 class="text-xl font-bold text-gray-800">Connect With Others</h3>
                        </div>
                        <p class="text-gray-600 pl-11">Join group discussions or connect with peers who share similar experiences.</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <div class="flex items-center mb-4">
                            <div class="bg-indigo-600 text-white rounded-full w-8 h-8 flex items-center justify-center mr-3">3</div>
                            <h3 class="text-xl font-bold text-gray-800">Access Support</h3>
                        </div>
                        <p class="text-gray-600 pl-11">Schedule sessions with professionals or utilize self-help resources.</p>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <img src="https://domf5oio6qrcr.cloudfront.net/medialibrary/14528/3f85b1b1-9dc7-4a90-855c-dc204646e889.jpg" alt="Mental Space app interface" class="rounded-lg shadow-xl">
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="bg-indigo-600 text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-6">Ready to Take the First Step?</h2>
            <p class="text-xl mb-8 max-w-2xl mx-auto">Join Mental Space today and be part of a community that understands and supports your mental health journey.</p>
            <div class="flex flex-col sm:flex-row justify-center">
                <a href="register.php" class="bg-white text-indigo-700 hover:bg-gray-100 font-bold py-3 px-8 rounded-lg mb-4 sm:mb-0 sm:mr-4">Sign Up Now</a>
                <a href="login.php" class="bg-transparent border-2 border-white hover:bg-indigo-800 font-bold py-3 px-8 rounded-lg">Login</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="mb-6 md:mb-0">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-brain text-xl mr-1"></i>
                        <span class="font-bold text-xl">Mental Space</span>
                    </a>
                    <p class="mt-2 text-gray-400">Supporting urban youth mental health</p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Quick Links</h3>
                        <ul class="text-gray-400">
                            <li class="mb-2"><a href="#about" class="hover:text-white">About Us</a></li>
                            <li class="mb-2"><a href="#" class="hover:text-white">Resources</a></li>
                            <li class="mb-2"><a href="#" class="hover:text-white">Support</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Legal</h3>
                        <ul class="text-gray-400">
                            <li class="mb-2"><a href="#" class="hover:text-white">Privacy Policy</a></li>
                            <li class="mb-2"><a href="#" class="hover:text-white">Terms of Service</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Connect</h3>
                        <div class="flex space-x-4">
                            <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400">
                <p>&copy; <?php echo date("Y"); ?> Mental Space. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>