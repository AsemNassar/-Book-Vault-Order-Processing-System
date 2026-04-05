<?php
// checkout_process.php
define('DB_CONFIG', true);
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isLoggedIn()) {
    $card = isset($_POST['card']) ? sanitize_input($_POST['card']) : '';
    $expiry = isset($_POST['expiry']) ? sanitize_input($_POST['expiry']) : '';

    // Validate Credit Card (Simple 16-digit check)
    if (!preg_match('/^\d{16}$/', str_replace(' ', '', $card))) {
        $response['message'] = 'Invalid credit card number. Must be 16 digits.';
        echo json_encode($response);
        exit;
    }

    // Validate Expiry Date
    if (empty($expiry) || new DateTime($expiry) <= new DateTime()) {
        $response['message'] = 'Card has expired or date is invalid.';
        echo json_encode($response);
        exit;
    }

    try {
        $conn = getDBConnection();
        $customerId = getCustomerID();

        // Call stored procedure
        $stmt = $conn->prepare("CALL sp_checkout_cart(?, ?, ?)");
        $stmt->bind_param("iss", $customerId, $card, $expiry);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $response['success'] = true;
                $response['order_id'] = $row['sale_id'];
                $response['message'] = $row['message'];
            }
        } else {
            $response['message'] = "Checkout failed: " . $conn->error;
        }

        $conn->close();
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
?>