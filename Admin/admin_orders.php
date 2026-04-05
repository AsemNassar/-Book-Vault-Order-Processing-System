<?php
// admin_orders.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

$conn = getDBConnection();

// Fetch Pending Orders
$orders = [];
$sql = "SELECT po.*, b.title, b.publication_year, b.category, b.current_stock, b.selling_price, 
        p.name as publisher_name,
        GROUP_CONCAT(a.full_name SEPARATOR ', ') as authors
        FROM PublisherOrder po
        JOIN Book b ON po.ISBN = b.ISBN
        JOIN Publisher p ON b.publisher_id = p.publisher_id
        LEFT JOIN BookAuthors ba ON b.ISBN = ba.ISBN
        LEFT JOIN Author a ON ba.author_id = a.author_id
        WHERE po.status = 'Pending'
        GROUP BY po.order_id
        ORDER BY po.order_date ASC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - BookCorner Admin</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="admin-layout">

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-open fa-lg"></i>
            <h3>BookCorner Admin</h3>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="admin_books.php"><i class="fas fa-book"></i> Book Management</a></li>
            <li><a href="admin_orders.php" class="active"><i class="fas fa-shopping-cart"></i> Order Management</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Pending Publisher Orders</h1>
        </div>


        <?php if (isset($_GET['msg'])): ?>
            <?php
            $msg = $_GET['msg'];
            // Detect if message is an error
            $isError = (stripos($msg, 'error') === 0 || stripos($msg, 'alert') === 0 || stripos($msg, 'failed') !== false || stripos($msg, 'cannot') !== false || stripos($msg, 'could not') !== false);
            $bgColor = $isError ? '#fee2e2' : '#d1fae5';
            $textColor = $isError ? '#991b1b' : '#065f46';
            ?>
            <div
                style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>


        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; color: #64748b;">
                <i class="fas fa-check-circle fa-3x" style="color: #10b981; margin-bottom: 1rem;"></i>
                <h3>No Pending Orders</h3>
                <p>All stock requests have been processed.</p>
            </div>
        <?php else: ?>
            <div class="stat-card" style="box-shadow: none; padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th style="width: 30%;">Book Details</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Publisher</th>
                            <th>Qty</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $order['order_id']; ?></strong>
                                    <?php if (!empty($order['admin_notes'])): ?>
                                        <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 5px;">
                                            <i class="fas fa-sticky-note"></i>
                                            <?php echo htmlspecialchars($order['admin_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="font-medium">
                                        <?php echo htmlspecialchars($order['title']); ?>
                                        <span class="text-muted">(<?php echo $order['publication_year']; ?>)</span>
                                    </div>
                                    <div class="text-muted" style="margin-top: 2px;">
                                        by <?php echo htmlspecialchars($order['authors']); ?>
                                    </div>
                                    <div class="text-sm" style="color: #94a3b8; margin-top: 2px;">
                                        ISBN: <?php echo htmlspecialchars($order['ISBN']); ?> • Price:
                                        $<?php echo $order['selling_price']; ?>
                                    </div>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($order['category']); ?></td>
                                <td>
                                    <?php
                                    $stock = $order['current_stock'];
                                    $stockClass = $stock < 5 ? 'badge-stock-low' : 'badge-stock-ok';
                                    // $stock indicator logic
                                    ?>
                                    <span class="badge <?php echo $stockClass; ?>"><?php echo $stock; ?></span>
                                </td>
                                <td class="text-muted"><?php echo htmlspecialchars($order['publisher_name']); ?></td>
                                <td>
                                    <span class="badge badge-qty"><?php echo $order['quantity']; ?></span>
                                </td>
                                <td class="text-muted">
                                    <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <form action="admin_order_process.php" method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="btn-confirm">Confirm</button>
                                        </form>
                                        <form action="admin_order_process.php" method="POST"
                                            onsubmit="return confirm('Are you sure you want to decline this order?');">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="action" value="decline">
                                            <button type="submit" class="btn-decline">Decline</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>

</html>