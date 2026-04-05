<?php
// orders.php - Order History
define('DB_CONFIG', true);
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.html");
    exit();
}

$customerInfo = getCustomerInfo();
$firstName = $customerInfo['first_name'];
$lastName = $customerInfo['last_name'];
$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

// Fetch orders
$conn = getDBConnection();
$customerId = getCustomerID();
$orders = [];

try {
    $sql = "SELECT s.sale_id, s.sale_date, s.total_amount, s.status, 
           (SELECT COUNT(*) FROM SaleItem WHERE sale_id = s.sale_id) as item_count
           FROM Sale s 
           WHERE s.customer_id = ? 
           ORDER BY s.sale_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderId = $row['sale_id'];
        // Get items for this order with FULL DETAILS
        $itemSql = "SELECT b.title, b.publication_year, b.category, 
                   p.name as publisher_name,
                   GROUP_CONCAT(a.full_name SEPARATOR ', ') as authors,
                   si.ISBN, si.quantity, si.unit_price, si.subtotal 
                   FROM SaleItem si 
                   JOIN Book b ON si.ISBN = b.ISBN 
                   LEFT JOIN Publisher p ON b.publisher_id = p.publisher_id
                   LEFT JOIN BookAuthors ba ON b.ISBN = ba.ISBN
                   LEFT JOIN Author a ON ba.author_id = a.author_id
                   WHERE si.sale_id = ?
                   GROUP BY si.ISBN";

        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param("i", $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();

        $items = [];
        while ($itemRow = $itemResult->fetch_assoc()) {
            $items[] = $itemRow;
        }
        $row['items'] = $items;
        $orders[] = $row;
        $itemStmt->close();
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Error loading orders";
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BookCorner</title>
    <link rel="stylesheet" href="user-styles.css">
    <style>
        .orders-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .order-meta {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-processing {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-item {
            display: flex;
            gap: 1.5rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f9fafb;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 70px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            background: #f3f4f6;
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .item-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 1rem;
        }

        .item-meta {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .item-price-qty {
            text-align: right;
            min-width: 100px;
        }

        .order-total {
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f3f4f6;
            font-weight: 700;
            font-size: 1.1rem;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header>
        <nav>
            <a href="user-home.php" class="logo">
                <img src="images/book-icon.png" alt="Book Icon">
                BookCorner
            </a>

            <ul class="nav-links">
                <li><a href="user-home.php">Home</a></li>
                <li><a href="browse.php">Browse Books</a></li>
                <li><a href="orders.php" class="active">My Orders</a></li>
            </ul>

            <div class="user-actions">
                <a href="cart.php" class="cart-button">
                    🛒
                    <span class="cart-badge" id="cartCount">0</span>
                </a>

                <div class="user-menu">
                    <button class="user-menu-button">
                        <div class="user-avatar"><?php echo $initials; ?></div>
                        <span class="user-name"><?php echo htmlspecialchars($firstName . ' ' . $lastName); ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </button>
                    <div class="user-dropdown">
                        <a href="profile.php" class="dropdown-item">👤 My Profile</a>
                        <a href="orders.php" class="dropdown-item">📦 My Orders</a>
                        <a href="cart.php" class="dropdown-item">🛒 Shopping Cart</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout">🚪 Logout</a>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div class="orders-container">
        <h1>My Orders</h1>

        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 12px;">
                <p style="color: #6b7280; margin-bottom: 1rem;">You haven't placed any orders yet.</p>
                <a href="browse.php" style="color: #2563eb; text-decoration: none; font-weight: 500;">Start Shopping →</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div style="font-weight: 600; font-size: 1.1rem;">Order #<?php echo $order['sale_id']; ?></div>
                            <div class="order-meta"><?php echo date('M d, Y, h:i A', strtotime($order['sale_date'])); ?></div>
                        </div>
                        <div class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <?php echo $order['status']; ?>
                        </div>
                    </div>

                    <div class="order-items">
                        <?php foreach ($order['items'] as $item): ?>
                            <div class="order-item">
                                <!-- Placeholder Image -->
                                <img src="https://images.unsplash.com/photo-1495446815901-a7297e633e8d?w=200&h=300&fit=crop"
                                    alt="Book Cover" class="item-image">

                                <div class="item-details">
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <span style="font-weight: normal; font-size: 0.9rem; color: #6b7280;">
                                            (<?php echo $item['publication_year']; ?>)
                                        </span>
                                    </div>
                                    <div class="item-meta">
                                        <div><strong>Author:</strong> <?php echo htmlspecialchars($item['authors'] ?? 'Unknown'); ?>
                                        </div>
                                        <div><strong>Publisher:</strong>
                                            <?php echo htmlspecialchars($item['publisher_name'] ?? 'Unknown'); ?></div>
                                        <div><strong>Category:</strong> <?php echo htmlspecialchars($item['category']); ?></div>
                                        <div style="font-size: 0.8rem; margin-top: 4px;">ISBN:
                                            <?php echo htmlspecialchars($item['ISBN']); ?></div>
                                    </div>
                                </div>

                                <div class="item-price-qty">
                                    <div style="font-weight: 600;">$<?php echo number_format($item['unit_price'], 2); ?></div>
                                    <div style="color: #64748b; font-size: 0.9rem;">Qty: <?php echo $item['quantity']; ?></div>
                                    <div style="font-weight: 600; color: #000; margin-top: 5px;">
                                        $<?php echo number_format($item['subtotal'], 2); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-total">
                        Total Paid: $<?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <a href="user-home.php" class="logo">
                    <img src="images/book-icon.png" alt="Book Icon">
                    BookCorner
                </a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 BookCorner. All rights reserved.</p>
        </div>
    </footer>

    <script src="user-home-dynamic.js"></script>
</body>

</html>