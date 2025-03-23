<?php
// Include database connection
require_once 'db_connection.php';

// Function to update user password
function updateUserPassword($conn, $email, $newPassword) {
    // Hash the password properly using PHP's built-in function
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    try {
        // Update user password
        $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
        
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        echo "Error updating password: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Start updating passwords
try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Update passwords for existing users
    $users = [
        ['email' => 'admin@mentalspace.com', 'password' => 'Admin123'],
        ['email' => 'manager@mentalspace.com', 'password' => 'AdminUser456'],
        ['email' => 'sarah.johnson@mentalspace.com', 'password' => 'Therapist123'],
        ['email' => 'michael.thompson@mentalspace.com', 'password' => 'TherapistUser456'],
        ['email' => 'james.wilson@example.com', 'password' => 'Patient123']
    ];
    
    $updatedCount = 0;
    
    foreach ($users as $user) {
        echo "Updating password for {$user['email']}...<br>";
        if (updateUserPassword($conn, $user['email'], $user['password'])) {
            $updatedCount++;
            echo "Password updated successfully for {$user['email']}.<br>";
        } else {
            echo "Could not update password for {$user['email']} (user may not exist).<br>";
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo "<h2>Password Reset Summary</h2>";
    echo "<p>Updated passwords for {$updatedCount} users.</p>";
    
    echo "<h3>Login Credentials (Updated):</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>{$user['email']} / {$user['password']}</li>";
    }
    echo "</ul>";
    
} catch(Exception $e) {
    // Roll back the transaction in case of error
    $conn->rollBack();
    echo "Error: " . $e->getMessage();
}
?>