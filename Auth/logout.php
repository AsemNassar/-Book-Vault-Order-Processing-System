<?php
// logout.php - Handle customer logout
define('DB_CONFIG', true);
require_once 'config.php';

// Check if user is logged in
if (isLoggedIn()) {
    $customer_id = getCustomerID();
    $conn = getDBConnection();
    
    // Call the stored procedure to clear cart (as per your database requirement)
    $stmt = $conn->prepare("CALL sp_logout_customer(?)");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    
    // Get the message from procedure
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $logout_message = $row['message'];
    }
    
    $stmt->close();
    $conn->close();
}

// Destroy session
session_unset();
session_destroy();

// Remove remember me cookie
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Redirect to home page with logout message
header("Location: home.html?logout=success");
exit();
?>