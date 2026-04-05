<?php
// admin_order_process.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    $action = $_POST['action'];
    $conn = getDBConnection();

    if ($action == 'confirm') {
        // Update status to 'Confirmed'. Trigger trg_publisherorder_after_update will handle stock update.
        $sql = "UPDATE PublisherOrder SET status = 'Confirmed' WHERE order_id = ?";
        $msg_success = "Order #$order_id confirmed. Stock has been updated.";
    } elseif ($action == 'decline') {
        // Update status to 'Cancelled'. No stock update needed.
        $sql = "UPDATE PublisherOrder SET status = 'Cancelled' WHERE order_id = ?";
        $msg_success = "Order #$order_id cancelled.";
    } else {
        $conn->close();
        header("Location: admin_orders.php");
        exit();
    }

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $order_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $msg = $msg_success;
            } else {
                $msg = "Order #$order_id could not be updated or was already processed.";
            }
            header("Location: admin_orders.php?msg=" . urlencode($msg));
        } else {
            header("Location: admin_orders.php?msg=" . urlencode("Error: " . $conn->error));
        }
        $stmt->close();
    } else {
        header("Location: admin_orders.php?msg=Database error");
    }

    $conn->close();
} else {
    header("Location: admin_orders.php");
    exit();
}
?>