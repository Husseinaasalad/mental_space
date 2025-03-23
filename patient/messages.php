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
$selectedConversation = null;
$messages = [];
$recipient_id = isset($_GET['user']) ? $_GET['user'] : null;

// Process sending a new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message_content']);
    $to_user_id = $_POST['recipient_id'];
    
    if (empty($message_text)) {
        $error = "Message cannot be empty";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, message_content, sent_at) 
                                   VALUES (:sender_id, :recipient_id, :message_content, NOW())");
            $stmt->bindParam(':sender_id', $_SESSION['user_id']);
            $stmt->bindParam(':recipient_id', $to_user_id);
            $stmt->bindParam(':message_content', $message_text);
            $stmt->execute();
            
            $success = "Message sent successfully";
            
            // Update recipient_id to keep the conversation open
            $recipient_id = $to_user_id;
        } catch(PDOException $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

// Get conversations (distinct users the patient has exchanged messages with)
try {
    $stmt = $conn->prepare("SELECT DISTINCT 
                            CASE 
                                WHEN m.sender_id = :user_id THEN m.recipient_id 
                                WHEN m.recipient_id = :user_id THEN m.sender_id 
                            END as contact_id,
                            u.first_name, 
                            u.last_name,
                            u.role,
                            (SELECT COUNT(*) FROM messages 
                             WHERE recipient_id = :user_id 
                             AND sender_id = contact_id
                             AND is_read = 0) as unread_count,
                            (SELECT MAX(sent_at) FROM messages 
                             WHERE (sender_id = :user_id AND recipient_id = contact_id)
                             OR (sender_id = contact_id AND recipient_id = :user_id)) as last_message_time
                           FROM messages m
                           JOIN users u ON u.user_id = 
                                CASE 
                                    WHEN m.sender_id = :user_id THEN m.recipient_id 
                                    WHEN m.recipient_id = :user_id THEN m.sender_id 
                                END
                           WHERE m.sender_id = :user_id OR m.recipient_id = :user_id
                           ORDER BY last_message_time DESC");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $conversations = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving conversations: " . $e->getMessage();
    $conversations = [];
}

// If no specific recipient is selected, pick the first conversation
if ($recipient_id === null && !empty($conversations)) {
    $recipient_id = $conversations[0]['contact_id'];
}

// If a recipient is selected, get the conversation
if ($recipient_id !== null) {
    try {
        // Mark messages as read
        $stmt = $conn->prepare("UPDATE messages 
                               SET is_read = 1 
                               WHERE recipient_id = :user_id AND sender_id = :sender_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':sender_id', $recipient_id);
        $stmt->execute();
        
        // Get recipient details
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, role FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $recipient_id);
        $stmt->execute();
        $selectedConversation = $stmt->fetch();
        
        // Get messages
        $stmt = $conn->prepare("SELECT m.*, 
                               u_sender.first_name as sender_first_name, 
                               u_sender.last_name as sender_last_name
                               FROM messages m
                               JOIN users u_sender ON m.sender_id = u_sender.user_id
                               WHERE (m.sender_id = :user_id AND m.recipient_id = :recipient_id)
                               OR (m.sender_id = :recipient_id AND m.recipient_id = :user_id)
                               ORDER BY m.sent_at ASC");
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':recipient_id', $recipient_id);
        $stmt->execute();
        $messages = $stmt->fetchAll();
    } catch(PDOException $e) {
        $error = "Error retrieving messages: " . $e->getMessage();
        $messages = [];
    }
}

// Get therapists list for new message
try {
    $stmt = $conn->prepare("SELECT u.user_id, u.first_name, u.last_name, td.specialization
                           FROM users u
                           JOIN therapist_details td ON u.user_id = td.user_id
                           WHERE u.role = 'therapist'
                           AND u.account_status = 'active'
                           ORDER BY u.last_name, u.first_name");
    $stmt->execute();
    $therapists = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error retrieving therapists: " . $e->getMessage();
    $therapists = [];
}

// Update unread messages count in sidebar
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE recipient_id = :user_id AND is_read = 0");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $unreadMessages = $stmt->fetch()['total'];
} catch(PDOException $e) {
    $unreadMessages = 0;
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
        .messages-container {
            height: calc(100vh - 350px);
            min-height: 400px;
        }
        
        .message-box {
            max-width: 75%;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Include patient sidebar and navigation -->
    <?php include 'patient_nav.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Messages</h1>
        
        <?php if($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p><?php echo $success; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="md:flex">
                <!-- Conversations Sidebar -->
                <div class="md:w-1/3 border-r border-gray-200">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Conversations</h2>
                        <button onclick="document.getElementById('newMessageModal').classList.remove('hidden')" class="mt-2 w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            <i class="fas fa-plus mr-2"></i> New Message
                        </button>
                    </div>
                    
                    <div class="overflow-y-auto" style="max-height: 500px;">
                        <?php if(count($conversations) > 0): ?>
                            <ul class="divide-y divide-gray-200">
                                <?php foreach($conversations as $conversation): ?>
                                    <li>
                                        <a href="?user=<?php echo $conversation['contact_id']; ?>" class="block px-4 py-3 hover:bg-gray-50 transition duration-150 ease-in-out <?php echo $recipient_id == $conversation['contact_id'] ? 'bg-indigo-50' : ''; ?>">
                                            <div class="flex justify-between">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium">
                                                            <?php echo substr($conversation['first_name'], 0, 1) . substr($conversation['last_name'], 0, 1); ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-sm font-medium text-gray-900">
                                                            <?php 
                                                            echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']);
                                                            if ($conversation['role'] === 'therapist') {
                                                                echo ' <span class="text-xs text-gray-500">(Therapist)</span>';
                                                            } elseif ($conversation['role'] === 'admin') {
                                                                echo ' <span class="text-xs text-gray-500">(Admin)</span>';
                                                            }
                                                            ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500">
                                                            <?php 
                                                            $messageTime = new DateTime($conversation['last_message_time']);
                                                            $now = new DateTime();
                                                            $diff = $messageTime->diff($now);
                                                            
                                                            if ($diff->days == 0) {
                                                                echo 'Today at ' . $messageTime->format('g:i A');
                                                            } elseif ($diff->days == 1) {
                                                                echo 'Yesterday at ' . $messageTime->format('g:i A');
                                                            } else {
                                                                echo $messageTime->format('M j, Y');
                                                            }
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if($conversation['unread_count'] > 0): ?>
                                                    <div class="flex-shrink-0">
                                                        <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-600 text-white text-xs font-medium">
                                                            <?php echo $conversation['unread_count']; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="p-4 text-center text-gray-500">
                                <p>No conversations yet.</p>
                                <p class="mt-2 text-sm">Start a new message by clicking the button above.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Messages Display -->
                <div class="md:w-2/3">
                    <?php if($selectedConversation): ?>
                        <!-- Conversation Header -->
                        <div class="p-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-medium">
                                    <?php echo substr($selectedConversation['first_name'], 0, 1) . substr($selectedConversation['last_name'], 0, 1); ?>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?php 
                                        echo htmlspecialchars($selectedConversation['first_name'] . ' ' . $selectedConversation['last_name']);
                                        ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <?php 
                                        if ($selectedConversation['role'] === 'therapist') {
                                            echo 'Therapist';
                                        } elseif ($selectedConversation['role'] === 'admin') {
                                            echo 'Administrator';
                                        } else {
                                            echo 'Patient';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Container -->
                        <div class="p-4 overflow-y-auto messages-container" id="messages-container">
                            <?php if(count($messages) > 0): ?>
                                <div class="space-y-4">
                                    <?php foreach($messages as $message): ?>
                                        <?php $isSender = $message['sender_id'] == $_SESSION['user_id']; ?>
                                        <div class="flex <?php echo $isSender ? 'justify-end' : 'justify-start'; ?>">
                                            <div class="message-box p-3 rounded-lg <?php echo $isSender ? 'bg-indigo-100 text-indigo-900' : 'bg-gray-100 text-gray-900'; ?>">
                                                <p class="text-sm"><?php echo nl2br(htmlspecialchars($message['message_content'])); ?></p>
                                                <p class="text-xs text-gray-500 mt-1 text-right">
                                                    <?php 
                                                    $messageTime = new DateTime($message['sent_at']);
                                                    echo $messageTime->format('M j, g:i A'); 
                                                    ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-gray-500 my-10">
                                    <p>No messages yet.</p>
                                    <p class="mt-2 text-sm">Start the conversation by sending a message below.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="border-t border-gray-200 p-4">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?user=' . $recipient_id; ?>">
                                <input type="hidden" name="recipient_id" value="<?php echo $recipient_id; ?>">
                                <div class="flex">
                                    <textarea name="message_content" rows="3" class="shadow appearance-none border rounded-l w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Type your message..."></textarea>
                                    <button type="submit" name="send_message" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-r focus:outline-none focus:shadow-outline">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center p-6">
                                <div class="text-indigo-500 text-5xl mb-4">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h3 class="text-xl font-medium text-gray-900 mb-2">Your Messages</h3>
                                <p class="text-gray-500 mb-4">Select a conversation from the sidebar or start a new one.</p>
                                <button onclick="document.getElementById('newMessageModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    <i class="fas fa-plus mr-2"></i> New Message
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Message Modal -->
    <div id="newMessageModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">New Message</h3>
                    <button onclick="document.getElementById('newMessageModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label for="recipient_id" class="block text-gray-700 font-medium mb-2">Recipient</label>
                        <select id="recipient_id" name="recipient_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <option value="">-- Select Recipient --</option>
                            <?php foreach($therapists as $therapist): ?>
                                <option value="<?php echo $therapist['user_id']; ?>">
                                    Dr. <?php echo htmlspecialchars($therapist['first_name'] . ' ' . $therapist['last_name']); ?> 
                                    (<?php echo htmlspecialchars($therapist['specialization']); ?>)
                                </option>
                            <?php endforeach; ?>
                            <!-- Add admins or other users here if needed -->
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="message_content" class="block text-gray-700 font-medium mb-2">Message</label>
                        <textarea id="message_content" name="message_content" rows="5" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Type your message..." required></textarea>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end">
                    <button type="button" onclick="document.getElementById('newMessageModal').classList.add('hidden')" class="mr-3 px-4 py-2 text-gray-700 font-medium rounded hover:bg-gray-100 focus:outline-none">
                        Cancel
                    </button>
                    <button type="submit" name="send_message" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded focus:outline-none focus:shadow-outline">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Scroll to bottom of messages container
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>