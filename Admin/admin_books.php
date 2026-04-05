<?php
// admin_books.php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.html");
    exit();
}

define('DB_CONFIG', true);
require_once 'config.php';

$conn = getDBConnection();

// Fetch Publishers for Dropdown
$publishers = [];
$pub_sql = "SELECT publisher_id, name FROM Publisher ORDER BY name";
$pub_result = $conn->query($pub_sql);
if ($pub_result) {
    while ($row = $pub_result->fetch_assoc()) {
        $publishers[] = $row;
    }
}

// Fetch Authors for Dropdown
$authors = [];
$auth_sql = "SELECT author_id, full_name FROM Author ORDER BY full_name";
$auth_result = $conn->query($auth_sql);
if ($auth_result) {
    while ($row = $auth_result->fetch_assoc()) {
        $authors[] = $row;
    }
}

// Hardcoded Categories (matching DB ENUM)
$categories = ['Art', 'Geography', 'History', 'Religion', 'Science'];

// Fetch ISBNs (All)
$isbns = [];
$isbn_sql = "SELECT ISBN FROM Book ORDER BY ISBN";
$isbn_result = $conn->query($isbn_sql);
if ($isbn_result) {
    while ($row = $isbn_result->fetch_assoc()) {
        $isbns[] = $row['ISBN'];
    }
}

// Search & Filter Logic
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? sanitize_input($_GET['filter_type']) : '';
$filter_value = isset($_GET['filter_value']) ? sanitize_input($_GET['filter_value']) : '';
$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(b.title LIKE '%$search%' OR b.ISBN LIKE '%$search%' OR b.category LIKE '%$search%' OR p.name LIKE '%$search%' OR EXISTS (SELECT 1 FROM BookAuthors ba_search JOIN Author a_search ON ba_search.author_id = a_search.author_id WHERE ba_search.ISBN = b.ISBN AND a_search.full_name LIKE '%$search%'))";
}

if (!empty($filter_type) && !empty($filter_value)) {
    if ($filter_type === 'category') {
        $where_clauses[] = "b.category = '$filter_value'";
    } elseif ($filter_type === 'author') {
        // Filter by Author Name directly if value is name, or join if ID. 
        // Assuming value passed is name for simplicity in dropdown, or handle both.
        // Let's assume we pass the Author Name.
        $where_clauses[] = "EXISTS (SELECT 1 FROM BookAuthors ba_f JOIN Author a_f ON ba_f.author_id = a_f.author_id WHERE ba_f.ISBN = b.ISBN AND a_f.full_name = '$filter_value')";
    } elseif ($filter_type === 'publisher') {
        $where_clauses[] = "p.name = '$filter_value'";
    } elseif ($filter_type === 'isbn') {
        $where_clauses[] = "b.ISBN = '$filter_value'";
    }
}

$where_clause = "";
if (!empty($where_clauses)) {
    $where_clause = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch Books for List
$books = [];
$book_sql = "SELECT b.*, p.name as publisher_name, 
             GROUP_CONCAT(a.full_name SEPARATOR ', ') as authors,
             GROUP_CONCAT(a.author_id) as author_ids,
             (SELECT COALESCE(SUM(si.quantity), 0) FROM SaleItem si WHERE si.ISBN = b.ISBN) as total_sold
             FROM Book b
             LEFT JOIN Publisher p ON b.publisher_id = p.publisher_id
             LEFT JOIN BookAuthors ba ON b.ISBN = ba.ISBN
             LEFT JOIN Author a ON ba.author_id = a.author_id
             $where_clause
             GROUP BY b.ISBN
             ORDER BY b.title";
$book_result = $conn->query($book_sql);
if ($book_result) {
    while ($row = $book_result->fetch_assoc()) {
        $books[] = $row;
    }
}

// AJAX Table Response
if (isset($_GET['ajax_table'])) {
    if (empty($books)) {
        echo '<tr><td colspan="8" style="padding: 2rem; text-align: center; color: #64748b;">No books found matching your criteria.</td></tr>';
    } else {
        foreach ($books as $book) {
            $stock = $book['current_stock'];
            $thresh = $book['threshold_quantity'];
            $color = $stock < $thresh ? '#ef4444' : '#10b981';

            // Re-creating the row HTML for AJAX
            echo '<tr>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($book['ISBN']) . '</td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-weight: 500;">' . htmlspecialchars($book['title']) . ' 
                        <span style="font-weight: normal; color: #64748b; font-size: 0.9em;">(' . $book['publication_year'] . ')</span>
                    </div>
                    <div style="font-size: 0.85rem; color: #64748b;">' . htmlspecialchars($book['authors']) . '</div>
                  </td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <span class="badge" style="background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">' . htmlspecialchars($book['category']) . '</span>
                  </td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($book['publisher_name']) . '</td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <span style="color: ' . $color . '; font-weight: 500;">' . $stock . '</span>
                  </td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <span style="font-weight: 600; color: #3b82f6;">' . $book['total_sold'] . '</span>
                  </td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">$' . $book['selling_price'] . '</td>';
            echo '<td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                    <button class="btn btn-outline action-btn" onclick=\'openEditModal(' . json_encode($book) . ')\'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                  </td>';
            echo '</tr>';
        }
    }
    exit(); // Stop further execution for AJAX requests
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - BookCorner Admin</title>
    <link rel="stylesheet" href="admin-styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inline styles moved to admin-styles.css -->
</head>

<body class="admin-layout">

    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-open fa-lg"></i>
            <h3>BookCorner Admin</h3>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="admin_books.php" class="active"><i class="fas fa-book"></i> Book Management</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-shopping-cart"></i> Order Management</a></li>
            <li><a href="admin_reports.php"><i class="fas fa-file-alt"></i> Reports</a></li>
            <li style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Book Management</h1>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Book
            </button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php
            $msg = $_GET['msg'];
            // Detect if message is an error (starts with Error, ALERT, or Failed)
            $isError = (stripos($msg, 'error') === 0 || stripos($msg, 'alert') === 0 || stripos($msg, 'failed') !== false || stripos($msg, 'cannot') !== false);
            $bgColor = $isError ? '#fee2e2' : '#d1fae5';
            $textColor = $isError ? '#991b1b' : '#065f46';
            ?>
            <div id="global-msg"
                style="background: <?php echo $bgColor; ?>; color: <?php echo $textColor; ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; transition: opacity 1s ease-out;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar with Browse Dropdown -->
        <div class="search-container">
            <div class="search-wrapper">
                <div class="search-controls">

                    <div class="browse-dropdown-container">
                        <button id="browseByBtn" class="browse-btn" onclick="toggleBrowseMenu(event)">
                            <i class="fas fa-filter"></i> Browse By ▼
                        </button>
                        <div id="browseMenu" class="dropdown-menu">
                            <!-- Main Menu -->
                            <div id="menu-main">
                                <div class="dropdown-header">Select Filter Type</div>
                                <div class="dropdown-item" onclick="showSubMenu('authors')">Authors <i
                                        class="fas fa-chevron-right"
                                        style="float: right; font-size: 0.8em; margin-top: 3px;"></i></div>
                                <div class="dropdown-item" onclick="showSubMenu('categories')">Categories <i
                                        class="fas fa-chevron-right"
                                        style="float: right; font-size: 0.8em; margin-top: 3px;"></i></div>
                                <div class="dropdown-item" onclick="showSubMenu('publishers')">Publishers <i
                                        class="fas fa-chevron-right"
                                        style="float: right; font-size: 0.8em; margin-top: 3px;"></i></div>
                                <div class="dropdown-item" onclick="showSubMenu('isbns')">ISBN <i
                                        class="fas fa-chevron-right"
                                        style="float: right; font-size: 0.8em; margin-top: 3px;"></i></div>
                            </div>

                            <!-- Sub Menu: Authors -->
                            <div id="menu-authors" class="sub-menu">
                                <div class="dropdown-header" onclick="showMainMenu()" style="cursor: pointer;">
                                    <i class="fas fa-chevron-left"></i> Back to Types
                                </div>
                                <div class="scrollable-menu" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($authors as $auth): ?>
                                        <div class="dropdown-item"
                                            onclick="applyFilter('author', '<?php echo addslashes($auth['full_name']); ?>')">
                                            <?php echo htmlspecialchars($auth['full_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Sub Menu: Categories -->
                            <div id="menu-categories" class="sub-menu">
                                <div class="dropdown-header" onclick="showMainMenu()" style="cursor: pointer;">
                                    <i class="fas fa-chevron-left"></i> Back to Types
                                </div>
                                <div class="scrollable-menu" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($categories as $cat): ?>
                                        <div class="dropdown-item"
                                            onclick="applyFilter('category', '<?php echo addslashes($cat); ?>')">
                                            <?php echo htmlspecialchars($cat); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Sub Menu: Publishers -->
                            <div id="menu-publishers" class="sub-menu">
                                <div class="dropdown-header" onclick="showMainMenu()" style="cursor: pointer;">
                                    <i class="fas fa-chevron-left"></i> Back to Types
                                </div>
                                <div class="scrollable-menu" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($publishers as $pub): ?>
                                        <div class="dropdown-item"
                                            onclick="applyFilter('publisher', '<?php echo addslashes($pub['name']); ?>')">
                                            <?php echo htmlspecialchars($pub['name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Sub Menu: ISBNs -->
                            <div id="menu-isbns" class="sub-menu">
                                <div class="dropdown-header" onclick="showMainMenu()" style="cursor: pointer;">
                                    <i class="fas fa-chevron-left"></i> Back to Types
                                </div>
                                <div class="scrollable-menu" style="max-height: 250px; overflow-y: auto;">
                                    <?php foreach ($isbns as $isbn_val): ?>
                                        <div class="dropdown-item"
                                            onclick="applyFilter('isbn', '<?php echo $isbn_val; ?>')">
                                            <?php echo $isbn_val; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        </div>
                    </div>

                    <form action="" method="GET" class="search-form">
                        <input type="text" name="search" placeholder="Search by ISBN or Title..."
                            value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if (!empty($search) || !empty($filter_type)): ?>
                            <a href="admin_books.php" class="btn btn-outline">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="stat-card" style="box-shadow: none; padding: 0;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">ISBN</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Title</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Category</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Publisher</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Stock</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Sold</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Price</th>
                        <th style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: #64748b;">No books found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($book['ISBN']); ?>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <div style="font-weight: 500;">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                        <span
                                            style="font-weight: normal; color: #64748b; font-size: 0.9em;">(<?php echo $book['publication_year']; ?>)</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #64748b;">
                                        <?php echo htmlspecialchars($book['authors']); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <span class="badge"
                                        style="background: #e0e7ff; color: #3730a3; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($book['category']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <?php echo htmlspecialchars($book['publisher_name']); ?>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <?php
                                    $stock = $book['current_stock'];
                                    $thresh = $book['threshold_quantity'];
                                    $color = $stock < $thresh ? '#ef4444' : '#10b981';
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 500;"><?php echo $stock; ?></span>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <span style="font-weight: 600; color: #3b82f6;">
                                        <?php echo $book['total_sold']; ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    $<?php echo $book['selling_price']; ?></td>
                                <td style="padding: 1rem; border-bottom: 1px solid #e2e8f0;">
                                    <button class="btn btn-outline action-btn"
                                        onclick='openEditModal(<?php echo json_encode($book); ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Book</h3>
                <button class="close-modal" onclick="closeModal('addBookModal')">×</button>
            </div>
            <form id="addBookForm" action="admin_book_process.php" method="POST"
                onsubmit="return validateAddBookForm()">
                <div id="add-error-msg"
                    style="display: none; background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 500;">
                </div>
                <div class="form-group">
                    <label>ISBN (13 chars)</label>
                    <input type="text" name="isbn" required maxlength="13">
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Publication Year</label>
                        <input type="number" name="year" required min="1900" max="2099" value="2024">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="Science">Science</option>
                            <option value="Art">Art</option>
                            <option value="Religion">Religion</option>
                            <option value="History">History</option>
                            <option value="Geography">Geography</option>
                        </select>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="price" required step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Initial Stock</label>
                        <input type="number" name="stock" id="add_stock" required value="10">
                    </div>
                </div>

                <div class="form-group">
                    <label>Threshold (Auto-order level)</label>
                    <input type="number" name="threshold" required min="0" value="5">
                </div>
                <div class="form-group">
                    <label>Publisher</label>
                    <select name="publisher_id" required>
                        <option value="">Select Publisher</option>
                        <?php foreach ($publishers as $pub): ?>
                            <option value="<?php echo $pub['publisher_id']; ?>">
                                <?php echo htmlspecialchars($pub['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Authors (Select all that apply)</label>
                    <div class="checkbox-list-container">
                        <?php foreach ($authors as $auth): ?>
                            <div class="checkbox-item">
                                <label style="display: flex; align-items: center; width: 100%; cursor: pointer;">
                                    <input type="checkbox" name="author_ids[]" value="<?php echo $auth['author_id']; ?>">
                                    <?php echo htmlspecialchars($auth['full_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Book</button>
            </form>
        </div>
    </div>

    <!-- Edit Book Modal -->
    <div id="editBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Book</h3>
                <button class="close-modal" onclick="closeModal('editBookModal')">×</button>
            </div>
            <!-- Error Banner -->
            <div id="edit-error-msg"
                style="display: none; background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #fecaca; font-weight: 500;">
            </div>

            <form action="admin_book_update.php" method="POST">
                <input type="hidden" name="isbn" id="edit_isbn_hidden">

                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" id="edit_isbn_display" disabled style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Publication Year</label>
                        <input type="number" name="year" id="edit_year" required min="1900" max="2099">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="edit_category" required>
                            <option value="Science">Science</option>
                            <option value="Art">Art</option>
                            <option value="Religion">Religion</option>
                            <option value="History">History</option>
                            <option value="Geography">Geography</option>
                        </select>
                    </div>
                </div>

                <div class="modal-grid">
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" name="price" id="edit_price" required step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" id="edit_stock" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Threshold (Auto-order level)</label>
                    <input type="number" name="threshold" id="edit_threshold" required min="0">
                </div>

                <div class="form-group">
                    <label>Publisher</label>
                    <select name="publisher_id" id="edit_publisher_id" required>
                        <?php foreach ($publishers as $pub): ?>
                            <option value="<?php echo $pub['publisher_id']; ?>">
                                <?php echo htmlspecialchars($pub['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Authors (Select all that apply)</label>
                    <div class="checkbox-list-container">
                        <?php foreach ($authors as $auth): ?>
                            <div class="checkbox-item">
                                <label style="display: flex; align-items: center; width: 100%; cursor: pointer;">
                                    <input type="checkbox" name="author_ids[]" value="<?php echo $auth['author_id']; ?>">
                                    <?php echo htmlspecialchars($auth['full_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Book</button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addBookModal').classList.add('show');
        }

        function openEditModal(book) {
            document.getElementById('edit_isbn_hidden').value = book.ISBN;
            document.getElementById('edit_isbn_display').value = book.ISBN;
            document.getElementById('edit_title').value = book.title;
            document.getElementById('edit_year').value = book.publication_year;
            document.getElementById('edit_category').value = book.category;
            document.getElementById('edit_price').value = book.selling_price;
            document.getElementById('edit_stock').value = book.current_stock;
            document.getElementById('edit_threshold').value = book.threshold_quantity;
            document.getElementById('edit_publisher_id').value = book.publisher_id;

            // VISUAL LOCK: If sales are 0, disable stock editing
            const stockInput = document.getElementById('edit_stock');
            // Ensure integer comparison
            if (parseInt(book.total_sold) === 0) {
                stockInput.readOnly = true;
                stockInput.style.backgroundColor = '#e2e8f0';
                stockInput.style.color = '#64748b';
                stockInput.title = "Stock cannot be changed until at least one sale is made.";
                // Optional: add a small helper text or tooltip if needed, but Title works for hover.
            } else {
                stockInput.readOnly = false;
                stockInput.style.backgroundColor = '#f8fafc';
                stockInput.style.color = 'inherit';
                stockInput.title = "";
            }

            // Reset all checkboxes first
            document.querySelectorAll('#editBookModal input[type="checkbox"]').forEach(cb => cb.checked = false);

            // Select appropriate authors
            if (book.author_ids) {
                // author_ids could be a string "1,2,3" or already array/object depending on PHP json_encode
                const ids = typeof book.author_ids === 'string' ? book.author_ids.split(',') : book.author_ids;
                ids.forEach(id => {
                    const checkbox = document.querySelector(`#editBookModal input[value="${id}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }

            // Clear previous errors
            const errorDiv = document.getElementById('edit-error-msg');
            if (errorDiv) errorDiv.style.display = 'none';

            document.getElementById('editBookModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Validation for Add Book form - prevent zero or negative stock
        function validateAddBookForm() {
            const stockInput = document.getElementById('add_stock');
            const stockValue = parseInt(stockInput.value);
            const errorDiv = document.getElementById('add-error-msg');

            // Reset error display
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';

            if (stockValue < 0) {
                errorDiv.textContent = '❌ Error: Negative stock numbers are not allowed!';
                errorDiv.style.display = 'block';
                stockInput.focus();
                return false; // Prevent form submission
            }

            if (stockValue === 0) {
                errorDiv.textContent = '❌ Error: Initial stock cannot be zero! Please enter at least 1.';
                errorDiv.style.display = 'block';
                stockInput.focus();
                return false; // Prevent form submission
            }

            return true; // Allow form submission
        }

        // Close on outside click
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Fade-out global message
        const globalMsg = document.getElementById('global-msg');
        if (globalMsg) {
            setTimeout(() => {
                globalMsg.style.opacity = '0';
                setTimeout(() => globalMsg.remove(), 1000); // Remove from DOM after transition
            }, 5000);
        }

        // Multi-level Dropdown Logic
        function toggleBrowseMenu(e) {
            e.stopPropagation();
            const menu = document.getElementById('browseMenu');
            menu.classList.toggle('show');
            // Reset to main menu whenever opened
            if (menu.classList.contains('show')) {
                showMainMenu();
            }
        }

        function showMainMenu() {
            document.getElementById('menu-main').style.display = 'block';
            document.querySelectorAll('.sub-menu').forEach(el => el.style.display = 'none');
        }

        function showSubMenu(type) {
            document.getElementById('menu-main').style.display = 'none';
            document.getElementById('menu-' + type).style.display = 'block';
        }

        function applyFilter(type, value) {
            // Close menu
            const menu = document.getElementById('browseMenu');
            menu.classList.remove('show');

            // Show Loading State (Optional, but good for UX)
            const tbody = document.querySelector('table tbody');
            tbody.style.opacity = '0.5';

            // Construct URL for fetch
            const params = new URLSearchParams();
            params.append('ajax_table', '1');
            params.append('filter_type', type);
            params.append('filter_value', value);

            // Construct new Page URL
            const pageParams = new URLSearchParams();
            pageParams.append('filter_type', type);
            pageParams.append('filter_value', value);
            const newUrl = `${window.location.pathname}?${pageParams.toString()}`;

            // Fetch Data
            fetch(`admin_books.php?${params.toString()}`)
                .then(response => response.text())
                .then(html => {
                    tbody.innerHTML = html;
                    tbody.style.opacity = '1';

                    // Add animation class to new rows
                    Array.from(tbody.children).forEach((row, index) => {
                        row.classList.add('table-row-animate');
                        row.style.animationDelay = `${index * 0.05}s`; // Staggered animation
                    });

                    // Update URL without reload
                    window.history.pushState({ path: newUrl }, '', newUrl);

                    // Update header text to show filter
                    updateFilterStatus(type, value);
                })
                .catch(err => {
                    console.error('Error fetching data:', err);
                    tbody.style.opacity = '1';
                });
        }

        function updateFilterStatus(type, value) {
            // Simple helper to add/remove the filter status text dynamically if needed
            // For now, we rely on the server-side generated one on refresh, 
            // but we can inject a simple status div here.
            let statusDiv = document.getElementById('filter-status-msg');
            if (!statusDiv) {
                // If it doesn't exist, create it after search container
                const container = document.querySelector('.search-container');
                statusDiv = document.createElement('div');
                statusDiv.id = 'filter-status-msg';
                statusDiv.style.marginBottom = '1rem';
                statusDiv.style.color = '#64748b';
                statusDiv.style.fontWeight = '500';
                container.parentNode.insertBefore(statusDiv, container.nextSibling);
            }

            statusDiv.innerHTML = `Filtering by ${type.charAt(0).toUpperCase() + type.slice(1)}: <span style="color: #2258c3;">${value.replace(/</g, "&lt;")}</span> 
                                   <a href="admin_books.php" class="btn btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.8em; margin-left: 10px;">Clear</a>`;
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            const menu = document.getElementById('browseMenu');
            if (menu && menu.classList.contains('show')) {
                // Check if click is inside menu
                if (!menu.contains(e.target)) {
                    menu.classList.remove('show');
                }
            }
        });

        // AJAX Form Handling for Edit Modal
        const editForm = document.querySelector('#editBookModal form');
        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('ajax', '1');

                const errorDiv = document.getElementById('edit-error-msg');
                if (errorDiv) errorDiv.style.display = 'none';

                fetch('admin_book_update.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Reload with success message
                            window.location.href = 'admin_books.php?msg=Book updated successfully';
                        } else {
                            // Show error "drop down from up" inside modal
                            if (errorDiv) {
                                errorDiv.innerText = data.message;
                                errorDiv.style.display = 'block';
                                // Scroll to top of modal to ensure visibility
                                document.querySelector('#editBookModal .modal-content').scrollTop = 0;
                            } else {
                                alert(data.message); // Fallback
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred.');
                    });
            });
        }
    </script>
</body>

</html>