<?php
// admin_book_process.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $isbn = sanitize_input($_POST['isbn']);
    $title = sanitize_input($_POST['title']);
    $year = intval($_POST['year']);
    $price = floatval($_POST['price']);
    $category = sanitize_input($_POST['category']);
    $stock = intval($_POST['stock']);
    $threshold = intval($_POST['threshold']);
    $publisher_id = intval($_POST['publisher_id']);

    // Handle multiple authors
    $author_ids_array = isset($_POST['author_ids']) ? $_POST['author_ids'] : [];
    $author_ids_string = implode(',', $author_ids_array);

    // --- INTEGRITY VALIDATION ---

    // 1. Validate ISBN Format
    if (strlen($isbn) !== 13 || !ctype_digit($isbn)) {
        header("Location: admin_books.php?msg=Error: ISBN must be exactly 13 digits.");
        exit();
    }

    // 2. Validate Year
    $current_year = date("Y");
    if ($year < 1900 || $year > $current_year + 2) {
        header("Location: admin_books.php?msg=Error: Invalid publication year.");
        exit();
    }

    // 3. Validate Price & Stock
    if ($price < 0) {
        header("Location: admin_books.php?msg=Error: Price cannot be negative.");
        exit();
    }
    if ($stock < 0) {
        header("Location: admin_books.php?msg=ALERT: Stock quantity cannot be negative!");
        exit();
    }

    $conn = getDBConnection();

    // 4. Check for Duplicate ISBN
    $check_stmt = $conn->prepare("SELECT 1 FROM Book WHERE ISBN = ?");
    $check_stmt->bind_param("s", $isbn);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        $conn->close();
        header("Location: admin_books.php?msg=Error: Book with this ISBN already exists.");
        exit();
    }
    $check_stmt->close();

    // Call stored procedure
    $stmt = $conn->prepare("CALL sp_add_new_book(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param(
            "ssidsiiis",
            $isbn,
            $title,
            $year,
            $price,
            $category,
            $threshold,
            $publisher_id,
            $stock,
            $author_ids_string
        );

        if ($stmt->execute()) {
            // Clear stored procedure results to prevent "Commands out of sync" error
            $stmt->close();
            while ($conn->more_results()) {
                $conn->next_result();
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            }

            // Check if stock is below threshold - create auto publisher order
            if ($stock < $threshold) {
                $order_qty = $threshold - $stock + 10; // Order enough to get above threshold plus buffer
                $auto_order_sql = "INSERT INTO PublisherOrder (ISBN, quantity, status, order_date) VALUES (?, ?, 'Pending', NOW())";
                $auto_stmt = $conn->prepare($auto_order_sql);
                if ($auto_stmt) {
                    $auto_stmt->bind_param("si", $isbn, $order_qty);
                    $auto_stmt->execute();
                    $auto_stmt->close();
                    // Success with auto-order
                    header("Location: admin_books.php?msg=Book added successfully. Auto-order created for " . $order_qty . " units (stock below threshold).");
                } else {
                    header("Location: admin_books.php?msg=Book added, but failed to create auto-order: " . $conn->error);
                }
            } else {
                // Success without auto-order
                header("Location: admin_books.php?msg=Book added successfully");
            }
        } else {
            // Error
            header("Location: admin_books.php?msg=Error adding book: " . $conn->error);
        }
    } else {
        header("Location: admin_books.php?msg=Database error");
    }

    $conn->close();
}
?>