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

// Error and success messages
$error = "";
$success = "";

// Get the current conversation if a patient_id is provided
$currentPatientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;
$currentPatient = null;

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $patientId = $_POST['recipient_id'];
    $messageContent = trim($_POST['message_content']);
    
    // Validate message
    if (empty($messageContent)) {
        $error = "Message cannot be empty.";
    } else {
        try {
            // Insert message
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, message_content, sent_at) 
                                   VALUES (:sender_id, :recipient_id, :message_content, NOW())");
            $stmt->bindParam(':sender_id', $_SESSION['user_id']);
            $stmt->bindParam(':recipient_id', $patientId);
            $stmt->bindParam(':message_content', $messageContent);
            $stmt->execute();
            
            $success = "Message sent successfully!";
            
            // Set current patient ID to stay on the same conversation
            $currentPatientId = $patientId;
            
        } catch(PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

// Mark messages as read if viewing a conversation
if ($currentPatientId) {
    try {
        // Mark messages from this patient as read
        $stmt = $conn->prepare("UPDATE messages 
                               SET is_read = 1
                               WHERE sender_id = :patient_id
                               AND recipient_id = :therapist_id
                               AND is_read = 0");
        $stmt->bindParam(':patient_id', $currentPatientId);
        $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
        $stmt->execute();
        
        // Get patient details
        $stmt = $conn->prepare("SELECT u.*, pd.primary_concerns 
                               FROM users u 
                               LEFT JOIN patient_details pd ON u.user_id = pd.user_id 
                               WHERE u.user_id = :patient_id AND u.role = 'patient'");
        $stmt->bindParam(':patient_id', $currentPatientId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $currentPatient = $stmt->fetch();
        } else {
            $error = "Patient not found.";
            $currentPatientId = null;
        }
    } catch(PDOException $e) {
        $error = "Error updating messages: " . $e->getMessage();
    }
}

// Get conversation list (patients with message history)
try {
    // Get patients with whom the therapist has exchanged messages
    $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, 
                           MAX(m.sent_at) as last_message_time,
                           (SELECT COUNT(*) FROM messages 
                            WHERE sender_id = u.user_id 
                            AND recipient_id = :therapist_id 
                            AND is_read = 0) as unread_count
                           FROM users u
                           JOIN messages m ON (u.user_id = m.sender_id OR u.user_id = m.recipient_id)
                           WHERE (m.sender_id = :therapist_id OR m.recipient_id = :therapist_id)
                           AND u.user_id != :therapist_id
                           AND u.role = 'patient'
                           GROUP BY u.user_id
                           ORDER BY last_message_time DESC");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $conversations = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error retrieving conversations: " . $e->getMessage();
    $conversations = [];
}

// Get all patients for new message dropdown
try {
    $stmt = $conn->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name 
                           FROM users u 
                           JOIN sessions s ON u.user_id = s.patient_id 
                           WHERE s.therapist_id = :therapist_id 
                           AND u.role = 'patient'
                           ORDER BY u.last_name, u.first_name");
    $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
    $stmt->execute();
    $allPatients = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving patients: " . $e->getMessage();
    $allPatients = [];
}

// Get message history for current conversation
$messages = [];
if ($currentPatientId) {
    try {
        $stmt = $conn->prepare("SELECT m.*, 
                               u_sender.first_name as sender_first_name, 
                               u_sender.last_name as sender_last_name,
                               u_recipient.first_name as recipient_first_name, 
                               u_recipient.last_name as recipient_last_name
                               FROM messages m
                               JOIN users u_sender ON m.sender_id = u_sender.user_id
                               JOIN users u_recipient ON m.recipient_id = u_recipient.user_id
                               WHERE (m.sender_id = :therapist_id AND m.recipient_id = :patient_id)
                               OR (m.sender_id = :patient_id AND m.recipient_id = :therapist_id)
                               ORDER BY m.sent_at ASC");
        $stmt->bindParam(':therapist_id', $_SESSION['user_id']);
        $stmt->bindParam(':patient_id', $currentPatientId);
        $stmt->execute();
        $messages = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Error retrieving messages: " . $e->getMessage();
    }
}

// Format time for display
function formatMessageTime($dateTime) {
    $timestamp = strtotime($dateTime);
    $now = time();
    $diffSeconds = $now - $timestamp;
    
    if ($diffSeconds < 60) {
        return "Just now";
    } elseif ($diffSeconds < 3600) {
        $mins = floor($diffSeconds / 60);
        return $mins . " " . ($mins == 1 ? "min" : "mins") . " ago";
    } elseif ($diffSeconds < 86400) {
        $hours = floor($diffSeconds / 3600);
        return $hours . " " . ($hours == 1 ? "hour" : "hours") . " ago";
    } elseif ($diffSeconds < 604800) {
        $days = floor($diffSeconds / 86400);
        return $days . " " . ($days == 1 ? "day" : "days") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

// Format date for message groups
function formatMessageDate($dateTime) {
    $timestamp = strtotime($dateTime);
    $now = time();
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');
    
    if ($timestamp >= $today) {
        return "Today";
    } elseif ($timestamp >= $yesterday) {
        return "Yesterday";
    } else {
        return date("l, F j, Y", $timestamp);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Mental Space</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom scrollbar */
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }
        .messages-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .messages-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        .messages-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Message bubbles */
        .message-bubble-sent {
            border-radius: 1rem 0 1rem 1rem;
        }
        .message-bubble-received {
            border-radius: 0 1rem 1rem 1rem;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include therapist sidebar and navigation -->
    <?php include 'therapist_nav.php'; ?>
    
    <div class="flex-1 flex">
        <!-- Conversations Sidebar -->
        <div class="w-72 bg-white border-r border-gray-200 flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800">Messages</h2>
                <div class="mt-4">
                    <button id="newMessageBtn" class="w-full flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-plus mr-2"></i> New Message
                    </button>
                </div>
            </div>
            
            <!-- Conversation List -->
            <div class="flex-1 overflow-y-auto">
                <?php if (!empty($conversations)): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($conversations as $convo): ?>
                            <a href="messages.php?patient_id=<?php echo $convo['user_id']; ?>" class="block p-4 hover:bg-gray-50 <?php echo ($currentPatientId == $convo['user_id']) ? 'bg-green-50' : ''; ?>">
                                <div class="flex justify-between">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-500">
                                                <?php echo substr($convo['first_name'], 0, 1) . substr($convo['last_name'], 0, 1); ?>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($convo['first_name'] . ' ' . $convo['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo formatMessageTime($convo['last_message_time']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($convo['unread_count'] > 0): ?>
                                        <div class="ml-2">
                                            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-green-600 rounded-full">
                                                <?php echo $convo['unread_count']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500">No message history found.</p>
                        <p class="text-gray-500 text-sm mt-1">Start a new conversation!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Message Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Error Messages -->
            <?php if($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 m-4" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 m-4" role="alert">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($currentPatientId && $currentPatient): ?>
                <!-- Conversation Header -->
                <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-white">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-500">
                                <?php echo substr($currentPatient['first_name'], 0, 1) . substr($currentPatient['last_name'], 0, 1); ?>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h2 class="text-lg font-medium text-gray-900">
                                <?php echo htmlspecialchars($currentPatient['first_name'] . ' ' . $currentPatient['last_name']); ?>
                            </h2>
                        </div>
                    </div>
                    <div>
                        <a href="patient_profile.php?id=<?php echo $currentPatientId; ?>" class="text-green-600 hover:text-green-800">
                            <i class="fas fa-user-circle mr-1"></i> View Profile
                        </a>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div class="flex-1 p-4 overflow-y-auto messages-container">
                    <?php if (!empty($messages)): ?>
                        <?php 
                        $currentDate = null;
                        foreach ($messages as $message):
                            $messageDate = formatMessageDate($message['sent_at']);
                            
                            // Show date separator if it's a new date
                            if ($messageDate !== $currentDate):
                                $currentDate = $messageDate;
                        ?>
                            <div class="flex justify-center my-4">
                                <div class="px-3 py-1 rounded-full bg-gray-200 text-sm text-gray-600">
                                    <?php echo $messageDate; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Message Bubble -->
                        <div class="flex <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'justify-end' : 'justify-start'; ?> mb-4">
                            <div class="max-w-xs md:max-w-md lg:max-w-lg <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'bg-green-100 message-bubble-sent' : 'bg-white border border-gray-200 message-bubble-received'; ?> px-4 py-2 shadow-sm">
                                <div class="text-sm <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'text-green-800' : 'text-gray-800'; ?>">
                                    <?php echo nl2br(htmlspecialchars($message['message_content'])); ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1 text-right">
                                    <?php echo date('g:i A', strtotime($message['sent_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <div class="text-gray-400 mb-3">
                                    <i class="fas fa-comment-dots text-5xl"></i>
                                </div>
                                <p class="text-gray-500">No messages yet.</p>
                                <p class="text-gray-500 text-sm mt-1">Send a message to start the conversation!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Message Input -->
                <div class="p-4 border-t border-gray-200 bg-white">
                    <form action="messages.php" method="POST" class="flex items-end">
                        <input type="hidden" name="recipient_id" value="<?php echo $currentPatientId; ?>">
                        <div class="flex-1 mr-4">
                            <label for="message_content" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea id="message_content" name="message_content" rows="3" required class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Type your message here..."></textarea>
                        </div>
                        <button type="submit" name="send_message" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-paper-plane mr-2"></i> Send
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- No Conversation Selected -->
                <div class="flex-1 flex items-center justify-center bg-gray-50">
                    <div class="text-center">
                        <div class="text-gray-400 mb-3">
                            <i class="fas fa-comments text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No conversation selected</h3>
                        <p class="text-gray-500 max-w-md mx-auto">
                            Select a conversation from the sidebar or start a new message to begin.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- New Message Modal -->
    <div id="newMessageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-xl font-medium text-gray-900">New Message</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form action="messages.php" method="POST" class="space-y-6">
                <!-- Patient Selection -->
                <div>
                    <label for="recipient_id" class="block text-sm font-medium text-gray-700">Select Patient</label>
                    <select id="recipient_id" name="recipient_id" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                        <option value="">Select a patient</option>
                        <?php foreach ($allPatients as $patient): ?>
                            <option value="<?php echo $patient['user_id']; ?>"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Message Content -->
                <div>
                    <label for="new_message_content" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea id="new_message_content" name="message_content" rows="4" required class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="pt-4 flex justify-end">
                    <button type="button" id="cancelNewMessage" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Cancel
                    </button>
                    <button type="submit" name="send_message" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const newMessageBtn = document.getElementById('newMessageBtn');
            const newMessageModal = document.getElementById('newMessageModal');
            const closeModal = document.getElementById('closeModal');
            const cancelNewMessage = document.getElementById('cancelNewMessage');
            
            // Open modal
            newMessageBtn.addEventListener('click', function() {
                newMessageModal.classList.remove('hidden');
            });
            
            // Close modal
            closeModal.addEventListener('click', function() {
                newMessageModal.classList.add('hidden');
            });
            
            cancelNewMessage.addEventListener('click', function() {
                newMessageModal.classList.add('hidden');
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === newMessageModal) {
                    newMessageModal.classList.add('hidden');
                }
            });
            
            // Auto-scroll to bottom of messages
            const messagesContainer = document.querySelector('.messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>