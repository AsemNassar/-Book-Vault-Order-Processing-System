<?php
// admin_book_update.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Get and Sanitize Input
    $isbn = sanitize_input($_POST['isbn']); // Primary Key (used for WHERE clause)
    $title = sanitize_input($_POST['title']);
    $year = intval($_POST['year']);
    $price = floatval($_POST['price']);
    $category = sanitize_input($_POST['category']);
    $stock = intval($_POST['stock']);
    $threshold = intval($_POST['threshold']);
    $publisher_id = intval($_POST['publisher_id']);

    // Helper for response
    function sendResponse($conn, $msg, $status = 'error')
    {
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => $status, 'message' => $msg]);
            if ($conn)
                $conn->close();
            exit();
        } else {
            if ($conn)
                $conn->close();
            header("Location: admin_books.php?msg=" . urlencode($msg));
            exit();
        }
    }

    // 2. Validation
    if (empty($isbn) || empty($title) || $price < 0) {
        sendResponse(null, "Error: Invalid input data");
    }

    // Part (c) Validation - Negative Stock Prevention
    if ($stock < 0) {
        sendResponse(null, "ALERT: Stock quantity cannot be negative!");
    }

    $conn = getDBConnection();

    // 2.5 Validation - Part (b) Restock Constraint
    // First, fetch current stock and sales history
    $check_sql = "SELECT b.current_stock, 
                  (SELECT COUNT(*) 
                   FROM SaleItem si 
                   JOIN Sale s ON si.sale_id = s.sale_id 
                   WHERE si.ISBN = b.ISBN AND s.status = 'Completed') as sales_count 
                  FROM Book b WHERE b.ISBN = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $isbn);
    $check_stmt->execute();
    $res = $check_stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $current_stock = intval($row['current_stock']);
        $sales_count = intval($row['sales_count']);

        // STRICT RULE: If Sales Count is 0, stock CANNOT be changed at all.
        if ($stock != $current_stock && $sales_count == 0) {
            $check_stmt->close();
            sendResponse($conn, "Error: Stock cannot be changed (increased or decreased) because this book has 0 sales.");
        }

        // 2.6 Validation - Decrease Stock Constraint (Only applies if sales > 0)
        // Rule: Do NOT allow decreasing stock if it results in a value below the threshold.
        // (i.e., you cannot manually set the stock to a "low" state).
        if ($sales_count > 0 && $stock < $current_stock) {
            if ($stock < $threshold) {
                $check_stmt->close();
                sendResponse($conn, "Error: Cannot decrease stock below the threshold level ($threshold).");
            }
        }
    } else {
        // Book not found (shouldn't happen given previous checks but safe to handle)
        $check_stmt->close();
        sendResponse($conn, "Error: Book not found.");
    }
    $check_stmt->close();

    // 3. Prepare Update Query
    // Note: We are allowing stock update. The Trigger `trg_book_before_update_stock` 
    // will automatically reject negative stock values with a SQLSTATE '45000'.

    $sql = "UPDATE Book SET 
            title = ?, 
            publication_year = ?, 
            selling_price = ?, 
            category = ?, 
            current_stock = ?, 
            threshold_quantity = ?, 
            publisher_id = ? 
            WHERE ISBN = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param(
            "sidsiiss",
            $title,
            $year,
            $price,
            $category,
            $stock,
            $threshold,
            $publisher_id,
            $isbn
        );

        // 4. Execute and Handle Errors
        try {
            if ($stmt->execute()) {
                // UPDATE AUTHORS LOGIC
                // 1. Delete existing authors for this book
                $del_sql = "DELETE FROM BookAuthors WHERE ISBN = ?";
                $del_stmt = $conn->prepare($del_sql);
                $del_stmt->bind_param("s", $isbn);
                $del_stmt->execute();
                $del_stmt->close();

                // 2. Insert new authors
                if (isset($_POST['author_ids']) && is_array($_POST['author_ids'])) {
                    $ins_sql = "INSERT INTO BookAuthors (ISBN, author_id) VALUES (?, ?)";
                    $ins_stmt = $conn->prepare($ins_sql);
                    foreach ($_POST['author_ids'] as $aid) {
                        $aid_int = intval($aid);
                        $ins_stmt->bind_param("si", $isbn, $aid_int);
                        $ins_stmt->execute();
                    }
                    $ins_stmt->close();
                }

                // Check if an auto-order was created by the database trigger
                // This happens when stock dropped below threshold
                $msg = "Book updated successfully.";

                // Query for most recent pending order for this book created within last 5 seconds
                $order_check_sql = "SELECT order_id, quantity, admin_notes 
                                   FROM PublisherOrder 
                                   WHERE ISBN = ? 
                                   AND status = 'Pending' 
                                   AND order_date >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
                                   ORDER BY order_date DESC 
                                   LIMIT 1";
                $order_check_stmt = $conn->prepare($order_check_sql);
                if ($order_check_stmt) {
                    $order_check_stmt->bind_param("s", $isbn);
                    $order_check_stmt->execute();
                    $order_result = $order_check_stmt->get_result();

                    if ($order_result->num_rows > 0) {
                        $order_row = $order_result->fetch_assoc();
                        $msg = "Book updated successfully. Auto-order #" . $order_row['order_id'] .
                            " created for " . $order_row['quantity'] . " units (stock fell below threshold).";
                    }
                    $order_check_stmt->close();
                }

                $status = 'success';
            } else {
                $msg = "Error updating book: " . $stmt->error;
                $status = 'error';
            }
        } catch (Exception $e) {
            // Catch custom SQL signal (e.g., negative stock)
            $msg = "Error: " . $e->getMessage();
            $status = 'error';
        }

        $stmt->close();
    } else {
        $msg = "Database error: " . $conn->error;
        $status = 'error';
    }

    sendResponse($conn, $msg, $status);
} else {
    // Direct access not allowed
    header("Location: admin_books.php");
    exit();
}
?>