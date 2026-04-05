<?php
// admin_login_process.php
define('DB_CONFIG', true);
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST['username']);
    $password = sanitize_input($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: admin_login.html?error=empty");
        exit();
    }

    $conn = getDBConnection();

    // Determine if we are checking against plain text or hashed password
    // For this implementation, we will check both to support legacy/test data

    $sql = "SELECT * FROM AdminUser WHERE username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();

            // Allow plain text comparison for the initial setup
            // In a real production app, ALWAYS use password_verify
            $auth_success = false;

            if ($password === $row['password']) {
                $auth_success = true;
            }
            // Future proofing: if we switch to hashed passwords later
            // elseif (password_verify($password, $row['password'])) {
            //     $auth_success = true;
            // }

            if ($auth_success) {
                // Set Admin Session
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $row['username'];

                header("Location: admin_dashboard.php");
                exit();
            }
        }
    }

    $conn->close();

    // If we got here, login failed
    header("Location: admin_login.html?error=invalid");
    exit();
} else {
    header("Location: admin_login.html");
    exit();
}
?>