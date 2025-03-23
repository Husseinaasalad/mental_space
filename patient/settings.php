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
$error = $success = "";
$emailNotifications = $smsNotifications = $appointmentReminders = $journalReminders = $newsletterSubscription = false;
$reminderTime = '';
$theme = 'light';
$language = 'english';

// Get user settings
try {
    $stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $settings = $stmt->fetch();
        
        $emailNotifications = (bool) $settings['email_notifications'];
        $smsNotifications = (bool) $settings['sms_notifications'];
        $appointmentReminders = (bool) $settings['appointment_reminders'];
        $journalReminders = (bool) $settings['journal_reminders'];
        $newsletterSubscription = (bool) $settings['newsletter_subscription'];
        $reminderTime = $settings['reminder_time'];
        $theme = $settings['theme'];
        $language = $settings['language'];
    }
} catch(PDOException $e) {
    $error = "Error retrieving settings: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $appointmentReminders = isset($_POST['appointment_reminders']) ? 1 : 0;
    $journalReminders = isset($_POST['journal_reminders']) ? 1 : 0;
    $newsletterSubscription = isset($_POST['newsletter_subscription']) ? 1 : 0;
    $reminderTime = $_POST['reminder_time'] ?? '';
    $theme = $_POST['theme'] ?? 'light';
    $language = $_POST['language'] ?? 'english';
    
    try {
        // Check if settings exist for user
        $stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update existing settings
            $stmt = $conn->prepare("UPDATE user_settings 
                                  SET email_notifications = :email_notifications,
                                      sms_notifications = :sms_notifications,
                                      appointment_reminders = :appointment_reminders,
                                      journal_reminders = :journal_reminders,
                                      newsletter_subscription = :newsletter_subscription,
                                      reminder_time = :reminder_time,
                                      theme = :theme,
                                      language = :language,
                                      updated_at = NOW()
                                  WHERE user_id = :user_id");
        } else {
            // Insert new settings
            $stmt = $conn->prepare("INSERT INTO user_settings 
                                  (user_id, email_notifications, sms_notifications, appointment_reminders, 
                                   journal_reminders, newsletter_subscription, reminder_time, theme, language, created_at)
                                  VALUES 
                                  (:user_id, :email_notifications, :sms_notifications, :appointment_reminders, 
                                   :journal_reminders, :newsletter_subscription, :reminder_time, :theme, :language, NOW())");
        }
        
        // Bind parameters
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':email_notifications', $emailNotifications);
        $stmt->bindParam(':sms_notifications', $smsNotifications);
        $stmt->bindParam(':appointment_reminders', $appointmentReminders);
        $stmt->bindParam(':journal_reminders', $journalReminders);
        $stmt->bindParam(':newsletter_subscription', $newsletterSubscription);
        $stmt->bindParam(':reminder_time', $reminderTime);
        $stmt->bindParam(':theme', $theme);
        $stmt->bindParam(':language', $language);
        
        $stmt->execute();
        
        $success = "Settings updated successfully!";
    } catch(PDOException $e) {
        $error = "Error updating settings: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Account Settings</h1>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <!-- Notifications Settings -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Notifications</h2>
                        
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="email_notifications" name="email_notifications" type="checkbox" <?php if($emailNotifications) echo "checked"; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="email_notifications" class="font-medium text-gray-700">Email Notifications</label>
                                    <p class="text-gray-500">Receive important notifications via email.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="sms_notifications" name="sms_notifications" type="checkbox" <?php if($smsNotifications) echo "checked"; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="sms_notifications" class="font-medium text-gray-700">SMS Notifications</label>
                                    <p class="text-gray-500">Receive important notifications via text message.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="appointment_reminders" name="appointment_reminders" type="checkbox" <?php if($appointmentReminders) echo "checked"; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="appointment_reminders" class="font-medium text-gray-700">Appointment Reminders</label>
                                    <p class="text-gray-500">Receive reminders for upcoming therapy appointments.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="journal_reminders" name="journal_reminders" type="checkbox" <?php if($journalReminders) echo "checked"; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="journal_reminders" class="font-medium text-gray-700">Journal Reminders</label>
                                    <p class="text-gray-500">Receive reminders to write in your journal.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="newsletter_subscription" name="newsletter_subscription" type="checkbox" <?php if($newsletterSubscription) echo "checked"; ?> class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="newsletter_subscription" class="font-medium text-gray-700">Newsletter Subscription</label>
                                    <p class="text-gray-500">Receive our monthly newsletter with mental health tips and resources.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="reminder_time" class="block text-sm font-medium text-gray-700">Preferred Reminder Time</label>
                            <select id="reminder_time" name="reminder_time" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="morning" <?php if($reminderTime === 'morning') echo "selected"; ?>>Morning (8:00 AM)</option>
                                <option value="afternoon" <?php if($reminderTime === 'afternoon') echo "selected"; ?>>Afternoon (1:00 PM)</option>
                                <option value="evening" <?php if($reminderTime === 'evening') echo "selected"; ?>>Evening (6:00 PM)</option>
                                <option value="night" <?php if($reminderTime === 'night') echo "selected"; ?>>Night (9:00 PM)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Appearance Settings -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Appearance</h2>
                        
                        <div class="mb-4">
                            <label for="theme" class="block text-sm font-medium text-gray-700">Theme</label>
                            <select id="theme" name="theme" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="light" <?php if($theme === 'light') echo "selected"; ?>>Light Mode</option>
                                <option value="dark" <?php if($theme === 'dark') echo "selected"; ?>>Dark Mode</option>
                                <option value="system" <?php if($theme === 'system') echo "selected"; ?>>Use System Preference</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="language" class="block text-sm font-medium text-gray-700">Language</label>
                            <select id="language" name="language" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="english" <?php if($language === 'english') echo "selected"; ?>>English</option>
                                <option value="spanish" <?php if($language === 'spanish') echo "selected"; ?>>Spanish</option>
                                <option value="french" <?php if($language === 'french') echo "selected"; ?>>French</option>
                                <option value="german" <?php if($language === 'german') echo "selected"; ?>>German</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Privacy Settings -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Privacy & Security</h2>
                        
                        <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shield-alt text-yellow-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">Privacy Reminder</h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>Your journal entries and profile information are private and only visible to you and the professionals you've authorized.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <a href="change_password.php" class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                    <i class="fas fa-key mr-2"></i>
                                    Change Password
                                </a>
                            </div>
                            
                            <div>
                                <a href="sessions.php" class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                    <i class="fas fa-history mr-2"></i>
                                    Active Sessions
                                </a>
                            </div>
                            
                            <div>
                                <a href="data_export.php" class="text-indigo-600 hover:text-indigo-900 flex items-center">
                                    <i class="fas fa-download mr-2"></i>
                                    Export Your Data
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Actions -->
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Account</h2>
                        
                        <div class="space-y-4">
                            <div>
                                <a href="deactivate_account.php" class="text-red-600 hover:text-red-900 flex items-center">
                                    <i class="fas fa-user-slash mr-2"></i>
                                    Deactivate Account
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="pt-5 border-t border-gray-200">
                        <div class="flex justify-end">
                            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Save Settings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>