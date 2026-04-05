<?php
// admin_reports.php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

$conn = getDBConnection();

// Initialize variables
$total_sales_prev_month = 0;
$total_sales_day = 0;
$sales_date = isset($_GET['sales_date']) ? $_GET['sales_date'] : date('Y-m-d');
$top_customers = [];
$top_books = [];

// 1. Total Sales (Previous Month)
$prev_month_start = date('Y-m-01', strtotime('last month'));
$prev_month_end = date('Y-m-t', strtotime('last month'));

$sql1 = "SELECT SUM(total_amount) as total FROM Sale 
         WHERE sale_date BETWEEN '$prev_month_start 00:00:00' AND '$prev_month_end 23:59:59'";
$res1 = $conn->query($sql1);
if ($res1 && $row = $res1->fetch_assoc()) {
    $total_sales_prev_month = $row['total'] ?? 0;
}

// 2. Total Sales (Specific Day)
$sql2 = "SELECT SUM(total_amount) as total FROM Sale 
         WHERE DATE(sale_date) = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $sales_date);
$stmt2->execute();
$res2 = $stmt2->get_result();
if ($res2 && $row = $res2->fetch_assoc()) {
    $total_sales_day = $row['total'] ?? 0;
}

// 3. Top 5 Customers (Last 3 Months)
// "Purchase amount" usually means total money spent
$three_months_ago = date('Y-m-d', strtotime('-3 months'));
$sql3 = "SELECT c.customer_id, c.first_name, c.last_name, c.email, SUM(s.total_amount) as total_spent
         FROM Customer c
         JOIN Sale s ON c.customer_id = s.customer_id
         WHERE s.sale_date >= '$three_months_ago'
         GROUP BY c.customer_id
         ORDER BY total_spent DESC
         LIMIT 5";
$res3 = $conn->query($sql3);
if ($res3) {
    while ($row = $res3->fetch_assoc()) {
        $top_customers[] = $row;
    }
}

// 4. Top 10 Selling Books (Last 3 Months)
$sql4 = "SELECT b.ISBN, b.title, b.publication_year, SUM(si.quantity) as total_sold
         FROM Book b
         JOIN SaleItem si ON b.ISBN = si.ISBN
         JOIN Sale s ON si.sale_id = s.sale_id
         WHERE s.sale_date >= '$three_months_ago'
         GROUP BY b.ISBN, b.title, b.publication_year
         ORDER BY total_sold DESC
         LIMIT 10";
$res4 = $conn->query($sql4);
if ($res4) {
    while ($row = $res4->fetch_assoc()) {
        $top_books[] = $row;
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - BookCorner Admin</title>
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
            <li><a href="admin_orders.php"><i class="fas fa-shopping-cart"></i> Order Management</a></li>
            <li><a href="admin_reports.php" class="active"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">System Reports</h1>
            <div style="color: #64748b;">Overview of sales and performance</div>
        </div>

        <!-- Sales Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar-alt fa-2x" style="color: #3b82f6; margin-bottom: 1rem;"></i>
                <div class="stat-value">$<?php echo number_format($total_sales_prev_month, 2); ?></div>
                <div class="stat-label">Total Sales (Previous Month)</div>
                <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.5rem;">
                    <?php echo date('M Y', strtotime('last month')); ?>
                </div>
            </div>

            <div class="stat-card">
                <i class="fas fa-day fa-2x" style="color: #10b981; margin-bottom: 1rem;"></i>
                <div class="stat-value">$<?php echo number_format($total_sales_day, 2); ?></div>
                <div class="stat-label">Daily Sales</div>
                <form action="" method="GET" style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <input type="date" name="sales_date" value="<?php echo $sales_date; ?>"
                        style="border: 1px solid #cbd5e1; padding: 0.25rem; border-radius: 4px;"
                        onchange="this.form.submit()">
                </form>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">

            <!-- Top Customers -->
            <div class="stat-card">
                <h3 style="margin-bottom: 1.5rem; color: #1e293b;">🏆 Top 5 Customers (Last 3 Months)</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem 0;">Customer</th>
                            <th style="padding: 0.75rem 0; text-align: right;">Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_customers as $cust): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 0;">
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #64748b;">
                                        <?php echo htmlspecialchars($cust['email']); ?></div>
                                </td>
                                <td style="padding: 0.75rem 0; text-align: right; font-weight: 600; color: #10b981;">
                                    $<?php echo number_format($cust['total_spent'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_customers)): ?>
                            <tr>
                                <td colspan="2" style="padding: 1rem; text-align: center; color: #94a3b8;">No data available
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Books -->
            <div class="stat-card">
                <h3 style="margin-bottom: 1.5rem; color: #1e293b;">📚 Top 10 Best Sellers (Last 3 Months)</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem 0;">Book Title</th>
                            <th style="padding: 0.75rem 0; text-align: right;">Copies Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_books as $book): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem 0;">
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                        <span
                                            style="font-weight: normal; color: #64748b; font-size: 0.9em;">(<?php echo $book['publication_year']; ?>)</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #64748b;">ISBN:
                                        <?php echo htmlspecialchars($book['ISBN']); ?></div>
                                </td>
                                <td style="padding: 0.75rem 0; text-align: right; font-weight: 600; color: #3b82f6;">
                                    <?php echo $book['total_sold']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_books)): ?>
                            <tr>
                                <td colspan="2" style="padding: 1rem; text-align: center; color: #94a3b8;">No data available
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>


        <!-- 5. Publishers Directory -->
        <?php
        $pub_dir_sql = "SELECT publisher_id, name, address, phone, email FROM Publisher ORDER BY publisher_id ASC";
        $pub_dir_res = $conn->query($pub_dir_sql);
        ?>
        <div id="publishers_directory" class="stat-card" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-building" style="color: #2258c3;"></i> Publishers Directory</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem;">ID</th>
                            <th style="padding: 0.75rem;">Publisher Name</th>
                            <th style="padding: 0.75rem;">Address</th>
                            <th style="padding: 0.75rem;">Phone</th>
                            <th style="padding: 0.75rem;">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pub_dir_res && $pub_dir_res->num_rows > 0): ?>
                            <?php while($pub = $pub_dir_res->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem; color: #64748b;">#<?php echo $pub['publisher_id']; ?></td>
                                    <td style="padding: 0.75rem; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($pub['name']); ?></td>
                                    <td style="padding: 0.75rem; color: #475569; font-size: 0.9em; max-width: 300px;"><?php echo htmlspecialchars($pub['address']); ?></td>
                                    <td style="padding: 0.75rem; color: #2258c3; font-weight: 500;"><?php echo htmlspecialchars($pub['phone']); ?></td>
                                    <td style="padding: 0.75rem; color: #64748b; font-size: 0.9em;"><?php echo htmlspecialchars($pub['email']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding: 1rem; text-align: center; color: #94a3b8;">No publishers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5.5. Publisher Orders History (Confirmed/Declined) -->
        <?php
        $hist_sql = "SELECT po.order_id, b.title, p.name as publisher, po.quantity, po.status, po.admin_notes, po.order_date
                     FROM PublisherOrder po
                     JOIN Book b ON po.ISBN = b.ISBN
                     JOIN Publisher p ON b.publisher_id = p.publisher_id
                     WHERE po.status IN ('Confirmed', 'Cancelled')
                     ORDER BY po.order_date DESC";
        $hist_res = $conn->query($hist_sql);
        ?>
        <div id="publisher_history" class="stat-card" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-history" style="color: #2258c3;"></i> Publisher Orders History</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem;">Order ID</th>
                            <th style="padding: 0.75rem;">Date</th>
                            <th style="padding: 0.75rem;">Book Title</th>
                            <th style="padding: 0.75rem;">Publisher</th>
                            <th style="padding: 0.75rem; text-align: center;">Qty</th>
                            <th style="padding: 0.75rem;">Status</th>
                            <th style="padding: 0.75rem;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($hist_res && $hist_res->num_rows > 0): ?>
                            <?php while($h = $hist_res->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem; color: #64748b;">#<?php echo $h['order_id']; ?></td>
                                    <td style="padding: 0.75rem; font-size: 0.9em;"><?php echo date('M d, Y', strtotime($h['order_date'])); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($h['title']); ?></td>
                                    <td style="padding: 0.75rem; color: #64748b; font-size: 0.9em;"><?php echo htmlspecialchars($h['publisher']); ?></td>
                                    <td style="padding: 0.75rem; text-align: center; font-weight: 600;"><?php echo $h['quantity']; ?></td>
                                    <td style="padding: 0.75rem;">
                                        <?php if ($h['status'] == 'Confirmed'): ?>
                                            <span class="badge" style="background: #dcfce7; color: #166534;">Confirmed</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fee2e2; color: #991b1b;">Declined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem; font-size: 0.85rem; color: #64748b; max-width: 250px;">
                                        <?php echo htmlspecialchars($h['admin_notes']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="padding: 1rem; text-align: center; color: #94a3b8;">No history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 6. Total Customers Report -->
        <?php
        $cust_sql = "SELECT c.customer_id, c.first_name, c.last_name, c.email, c.shipping_address, COUNT(s.sale_id) as total_orders
                     FROM Customer c
                     LEFT JOIN Sale s ON c.customer_id = s.customer_id
                     GROUP BY c.customer_id
                     ORDER BY total_orders DESC, c.last_name";
        $cust_res = $conn->query($cust_sql);
        ?>
        <div id="customers" class="stat-card" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-users" style="color: #10b981;"></i> Customer Details</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem;">ID</th>
                            <th style="padding: 0.75rem;">Name</th>
                            <th style="padding: 0.75rem;">Email</th>
                            <th style="padding: 0.75rem;">Address</th>
                            <th style="padding: 0.75rem; text-align: center;">Orders Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cust_res && $cust_res->num_rows > 0): ?>
                            <?php while($c = $cust_res->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem; color: #64748b;">#<?php echo $c['customer_id']; ?></td>
                                    <td style="padding: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></td>
                                    <td style="padding: 0.75rem;"><?php echo htmlspecialchars($c['email']); ?></td>
                                    <td style="padding: 0.75rem; color: #64748b; font-size: 0.9em; max-width: 250px;"><?php echo htmlspecialchars($c['shipping_address']); ?></td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <?php if ($c['total_orders'] > 0): ?>
                                            <span class="badge" style="background: #dbeafe; color: #1e40af;"><?php echo $c['total_orders']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding: 1rem; text-align: center;">No customers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 7. Orders Today Report -->
        <?php
        $today_sql = "SELECT s.sale_id, s.total_amount, s.status, c.first_name, c.last_name 
                      FROM Sale s 
                      JOIN Customer c ON s.customer_id = c.customer_id 
                      WHERE DATE(s.sale_date) = CURDATE() 
                      ORDER BY s.sale_date DESC";
        $today_res = $conn->query($today_sql);
        ?>
        <div id="orders_today" class="stat-card" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-clock" style="color: #8b5cf6;"></i> Orders Today</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                        <th style="padding: 0.75rem;">Order ID</th>
                        <th style="padding: 0.75rem;">Customer</th>
                        <th style="padding: 0.75rem;">Status</th>
                        <th style="padding: 0.75rem; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($today_res && $today_res->num_rows > 0): ?>
                        <?php while($o = $today_res->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem; color: #64748b;">#<?php echo $o['sale_id']; ?></td>
                                <td style="padding: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($o['first_name'] . ' ' . $o['last_name']); ?></td>
                                <td style="padding: 0.75rem;">
                                    <span class="badge" style="background: <?php echo $o['status'] == 'Ordered' ? '#dbeafe' : '#f1f5f9'; ?>; color: <?php echo $o['status'] == 'Ordered' ? '#1e40af' : '#475569'; ?>;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; font-weight: 600;">$<?php echo number_format($o['total_amount'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="padding: 1rem; text-align: center;">No orders placed today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 8. Low Stock Report -->
        <?php
        // Using strict stock < threshold rule
        $low_sql = "SELECT title, ISBN, current_stock, threshold_quantity 
                    FROM Book 
                    WHERE current_stock < threshold_quantity 
                    ORDER BY current_stock ASC";
        $low_res = $conn->query($low_sql);
        ?>
        <div id="low_stock" class="stat-card" style="margin-top: 2rem; margin-bottom: 4rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-exclamation-circle" style="color: #f59e0b;"></i> Low Stock Items (Below Threshold)</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                        <th style="padding: 0.75rem;">ISBN</th>
                        <th style="padding: 0.75rem;">Book Title</th>
                        <th style="padding: 0.75rem;">Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($low_res && $low_res->num_rows > 0): ?>
                        <?php while($b = $low_res->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($b['ISBN']); ?></td>
                                <td style="padding: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($b['title']); ?></td>
                                <td style="padding: 0.75rem;">
                                    <span style="color: #ef4444; font-weight: 700; background: #fee2e2; padding: 2px 8px; border-radius: 4px;">
                                        <?php echo $b['current_stock']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="padding: 1rem; text-align: center; color: #10b981;">All stock levels appear healthy!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 9. Detailed Book Sales Analysis -->
        <?php
        $sales_analysis_sql = "SELECT b.ISBN, b.title, p.name as publisher, b.current_stock, b.selling_price, 
                               COALESCE(SUM(si.quantity), 0) as total_sold
                               FROM Book b
                               LEFT JOIN Publisher p ON b.publisher_id = p.publisher_id
                               LEFT JOIN SaleItem si ON b.ISBN = si.ISBN
                               GROUP BY b.ISBN
                               ORDER BY total_sold DESC, b.title ASC";
        $sales_analysis_res = $conn->query($sales_analysis_sql);
        ?>
        <div id="book_sales_analysis" class="stat-card" style="margin-top: 2rem; margin-bottom: 4rem;">
            <h3 style="margin-bottom: 1.5rem; color: #1e293b;"><i class="fas fa-chart-bar" style="color: #3b82f6;"></i> Detailed Book Sales Analysis</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left;">
                            <th style="padding: 0.75rem;">ISBN</th>
                            <th style="padding: 0.75rem;">Title</th>
                            <th style="padding: 0.75rem;">Publisher</th>
                            <th style="padding: 0.75rem;">Price</th>
                            <th style="padding: 0.75rem; text-align: center;">Stock</th>
                            <th style="padding: 0.75rem; text-align: center;">Total Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($sales_analysis_res && $sales_analysis_res->num_rows > 0): ?>
                            <?php while($row = $sales_analysis_res->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem; color: #64748b;"><?php echo htmlspecialchars($row['ISBN']); ?></td>
                                    <td style="padding: 0.75rem; font-weight: 500;"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td style="padding: 0.75rem; color: #475569;"><?php echo htmlspecialchars($row['publisher']); ?></td>
                                    <td style="padding: 0.75rem;">$<?php echo number_format($row['selling_price'], 2); ?></td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <span style="font-weight: 500; color: #64748b;"><?php echo $row['current_stock']; ?></span>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <?php if ($row['total_sold'] > 0): ?>
                                            <span class="badge" style="background: #dbeafe; color: #1e40af; font-size: 0.9rem;">
                                                <?php echo $row['total_sold']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding: 1rem; text-align: center;">No books found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</body>

</html>