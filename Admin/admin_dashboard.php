<?php
// admin_dashboard.php
session_start();

// Admin Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

$conn = getDBConnection();
$stats = [
    'books' => 0,
    'customers' => 0,
    'orders_today' => 0,
    'low_stock' => 0
];

// Fetch Stats
$res = $conn->query("SELECT COUNT(*) as c FROM Book");
if ($res)
    $stats['books'] = $res->fetch_assoc()['c'];

$res = $conn->query("SELECT COUNT(*) as c FROM Customer");
if ($res)
    $stats['customers'] = $res->fetch_assoc()['c'];

$today = date('Y-m-d');
$res = $conn->query("SELECT COUNT(*) as c FROM Sale WHERE DATE(sale_date) = '$today'");
if ($res)
    $stats['orders_today'] = $res->fetch_assoc()['c'];

$res = $conn->query("SELECT COUNT(*) as c FROM Book WHERE current_stock < 5"); // Using 5 as low stock indicator per visual
if ($res)
    $stats['low_stock'] = $res->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookCorner</title>
    <link rel="stylesheet" href="admin-styles.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="admin-layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-open fa-lg"></i>
            <h3>BookCorner Admin</h3>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php" class="active">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="admin_books.php">
                    <i class="fas fa-book"></i> Book Management
                </a>
            </li>
            <li>
                <a href="admin_orders.php">
                    <i class="fas fa-shopping-cart"></i> Order Management
                </a>
            </li>
            <li>
                <a href="admin_reports.php">
                    <i class="fas fa-file-alt"></i> Reports
                </a>
            </li>
            <li style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <a href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></span>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="stats-grid">
            <a href="admin_books.php" class="stat-card-link">
                <div class="stat-card">
                    <i class="fas fa-book fa-2x" style="color: #3b82f6; margin-bottom: 1rem;"></i>
                    <div class="stat-value"><?php echo $stats['books']; ?></div>
                    <div class="stat-label">Total Books</div>
                </div>
            </a>

            <a href="admin_reports.php#customers" class="stat-card-link">
                <div class="stat-card">
                    <i class="fas fa-user-friends fa-2x" style="color: #10b981; margin-bottom: 1rem;"></i>
                    <div class="stat-value"><?php echo $stats['customers']; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
            </a>

            <a href="admin_reports.php#orders_today" class="stat-card-link">
                <div class="stat-card">
                    <i class="fas fa-shopping-bag fa-2x" style="color: #8b5cf6; margin-bottom: 1rem;"></i>
                    <div class="stat-value"><?php echo $stats['orders_today']; ?></div>
                    <div class="stat-label">Orders Today</div>
                </div>
            </a>

            <a href="admin_reports.php#low_stock" class="stat-card-link">
                <div class="stat-card">
                    <i class="fas fa-exclamation-triangle fa-2x" style="color: #f59e0b; margin-bottom: 1rem;"></i>
                    <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
            </a>
        </div>

        <div class="dashboard-section">
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Quick Actions</h2>
            <div style="display: flex; gap: 1rem;">
                <a href="admin_books.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Book
                </a>
                <a href="admin_orders.php" class="btn btn-outline">
                    <i class="fas fa-box-open"></i> View Pending Orders
                </a>
            </div>
        </div>
    </main>

</body>

</html>