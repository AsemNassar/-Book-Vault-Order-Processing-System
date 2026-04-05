<?php
session_start();
// Unset all session variables
$_SESSION = array();

// Destroy session
session_destroy();

// Redirect to login
header("Location: login.html");
exit();
?>