<?php
// browse.php - Browse all books
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
$currentCategory = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'all';
$searchQuery = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : (isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - BookCorner</title>
    <link rel="stylesheet" href="user-styles.css">
    <style>
        .filters-section {
            padding: 2rem 5%;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
        }

        .filter-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }

        .active-filter {
            background: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
        }

        .results-header {
            padding: 2rem 5% 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        #debug-log {
            background: #1f2937;
            color: #10b981;
            padding: 1rem;
            margin: 1rem 5%;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            /* Hidden by default */
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <a href="user-home.php" class="logo"><img src="images/book-icon.png" alt="Book Icon">BookCorner</a>
            <ul class="nav-links">
                <li><a href="user-home.php">Home</a></li>
                <li><a href="browse.php" class="active">Browse Books</a></li>
                <li><a href="orders.php">My Orders</a></li>
            </ul>
            <div class="search-wrapper"
                style="display: flex; gap: 10px; align-items: center; flex: 1; max-width: 600px; margin: 0 auto;">
                <div class="browse-dropdown-container" style="position: relative;">
                    <button id="browseByBtn" class="browse-btn">Browse By ▼</button>
                    <!-- Type Selection Menu -->
                    <div id="browseByTypeMenu" class="dropdown-menu">
                        <div class="dropdown-item" onclick="showFilterOptions('category')">Category</div>
                        <div class="dropdown-item" onclick="showFilterOptions('author')">Author</div>
                        <div class="dropdown-item" onclick="showFilterOptions('publisher')">Publisher</div>
                        <div class="dropdown-item" onclick="showFilterOptions('isbn')">ISBN</div>
                    </div>
                    <!-- Specific Options Menu -->
                    <div id="browseOptionsMenu" class="dropdown-menu sub-menu">
                        <div class="menu-header">
                            <span onclick="backToTypes()" style="cursor: pointer;">← Back</span>
                            <span id="filterTitle" style="font-weight: bold; margin-left: 10px;">Select...</span>
                            <span onclick="closeBrowseMenu()" style="cursor: pointer; float: right;">×</span>
                        </div>
                        <div id="filterOptionsList" style="max-height: 300px; overflow-y: auto;">
                            <!-- Options will be loaded here -->
                        </div>
                    </div>
                </div>

                <div class="search-container" style="margin: 0; flex: 1;">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Search books..."
                        value="<?php echo $searchQuery; ?>">
                </div>
            </div>

            <style>
                .browse-btn {
                    padding: 0.5rem 1rem;
                    background: #f3f4f6;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    cursor: pointer;
                    font-weight: 500;
                    white-space: nowrap;
                    color: #374151;
                    height: 42px;
                    /* Match search input height usually */
                }

                .browse-btn:hover {
                    background: #e5e7eb;
                }

                .dropdown-menu {
                    display: none;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    background: white;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    min-width: 200px;
                    z-index: 1000;
                    margin-top: 5px;
                    text-align: left;
                }

                .dropdown-menu.show {
                    display: block;
                }

                .dropdown-item {
                    padding: 10px 15px;
                    cursor: pointer;
                    border-bottom: 1px solid #f3f4f6;
                    color: #374151;
                }

                .dropdown-item:last-child {
                    border-bottom: none;
                }

                .dropdown-item:hover {
                    background: #f9fafb;
                    color: #2563eb;
                }

                .menu-header {
                    padding: 10px;
                    background: #f3f4f6;
                    border-bottom: 1px solid #e5e7eb;
                    font-size: 0.9rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    color: #374151;
                }
            </style>

            <script>
                // Browse By Dropdown Logic
                const browseByBtn = document.getElementById('browseByBtn');
                const browseByTypeMenu = document.getElementById('browseByTypeMenu');
                const browseOptionsMenu = document.getElementById('browseOptionsMenu');
                const filterOptionsList = document.getElementById('filterOptionsList');
                const filterTitle = document.getElementById('filterTitle');

                browseByBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    browseByTypeMenu.classList.toggle('show');
                    browseOptionsMenu.classList.remove('show');
                });

                document.addEventListener('click', () => {
                    closeBrowseMenu();
                });

                browseByTypeMenu.addEventListener('click', e => e.stopPropagation());
                browseOptionsMenu.addEventListener('click', e => e.stopPropagation());

                function closeBrowseMenu() {
                    browseByTypeMenu.classList.remove('show');
                    browseOptionsMenu.classList.remove('show');
                }

                function backToTypes() {
                    browseOptionsMenu.classList.remove('show');
                    browseByTypeMenu.classList.add('show');
                }

                async function showFilterOptions(type) {
                    browseByTypeMenu.classList.remove('show');
                    browseOptionsMenu.classList.add('show');

                    filterTitle.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                    filterOptionsList.innerHTML = '<div style="padding: 10px; text-align: center;">Loading...</div>';

                    try {
                        const response = await fetch(`get_filters.php?type=${type}`);
                        const result = await response.json();

                        if (result.success && result.data.length > 0) {
                            filterOptionsList.innerHTML = result.data.map(item => `
                                <div class="dropdown-item" onclick="selectFilter('${type}', '${item.replace(/'/g, "\\'")}')">
                                    ${item}
                                </div>
                            `).join('');
                        } else {
                            filterOptionsList.innerHTML = '<div style="padding: 10px; text-align: center;">No options found</div>';
                        }
                    } catch (error) {
                        console.error('Error fetching filters:', error);
                        filterOptionsList.innerHTML = '<div style="padding: 10px; text-align: center; color: red;">Error loading options</div>';
                    }
                }

                function selectFilter(type, value) {
                    if (type === 'category') {
                        window.location.href = `browse.php?category=${encodeURIComponent(value)}`;
                    } else {
                        window.location.href = `browse.php?search=${encodeURIComponent(value)}`;
                    }
                }
            </script>
            <div class="user-actions">
                <a href="cart.php" class="cart-button">🛒<span class="cart-badge" id="cartCount">0</span></a>
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

    <div id="debug-log"></div>

    <section class="filters-section">
        <div class="filter-controls">
            <div class="category-pills">
                <a href="browse.php"
                    class="category-pill <?php echo $currentCategory === 'all' ? 'active-filter' : ''; ?>">All Books</a>
                <a href="browse.php?category=Science"
                    class="category-pill <?php echo $currentCategory === 'Science' ? 'active-filter' : ''; ?>">Science</a>
                <a href="browse.php?category=Art"
                    class="category-pill <?php echo $currentCategory === 'Art' ? 'active-filter' : ''; ?>">Art</a>
                <a href="browse.php?category=Religion"
                    class="category-pill <?php echo $currentCategory === 'Religion' ? 'active-filter' : ''; ?>">Religion</a>
                <a href="browse.php?category=History"
                    class="category-pill <?php echo $currentCategory === 'History' ? 'active-filter' : ''; ?>">History</a>
                <a href="browse.php?category=Geography"
                    class="category-pill <?php echo $currentCategory === 'Geography' ? 'active-filter' : ''; ?>">Geography</a>
            </div>
        </div>
    </section>

    <div class="results-header">
        <h2 id="resultsTitle">
            <?php
            if ($searchQuery)
                echo 'Search Results for "' . htmlspecialchars($searchQuery) . '"';
            elseif ($currentCategory !== 'all')
                echo $currentCategory . ' Books';
            else
                echo 'All Books';
            ?>
        </h2>
    </div>

    <section class="books-section">
        <div class="books-grid" id="booksGrid">
            <div class="loading-message" style="grid-column: 1/-1; text-align: center; padding: 3rem; color: #666;">
                <p style="font-size: 1.2rem;">📚 Loading books...</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-brand">
                <a href="user-home.php" class="logo"><img src="images/book-icon.png" alt="Book Icon">BookCorner</a>
                <p>Your trusted destination for discovering and purchasing quality books from around the world.</p>
            </div>
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="browse.php">Browse Books</a></li>
                    <li><a href="browse.php?category=Science">Science</a></li>
                    <li><a href="browse.php?category=History">History</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 BookCorner. All rights reserved.</p>
        </div>
    </footer>

    <script>
        window.suppressDefaultBookLoad = true;

        function log(msg, isError = false) {
            console.log(msg);
            const debugLog = document.getElementById('debug-log');
            if (debugLog) {
                // debugLog.style.display = 'block'; // Uncomment to show debug log always
                if (isError) debugLog.style.display = 'block';
                const p = document.createElement('div');
                p.textContent = `> ${msg}`;
                if (isError) p.style.color = '#ef4444';
                debugLog.appendChild(p);
            }
        }

        window.onerror = function (msg, url, lineNo, columnNo, error) {
            log(`Global Error: ${msg} at line ${lineNo}`, true);
            return false;
        };
    </script>
    <script src="user-home-dynamic.js?v=<?php echo time(); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            log('DOM Loaded. Initializing browse page...');
            const urlParams = new URLSearchParams(window.location.search);
            const category = urlParams.get('category') || 'all';
            const search = urlParams.get('search') || urlParams.get('q') || '';

            log(`Filters detected - Category: ${category}, Search: ${search}`);
            loadBooksWithFilter(category, search);

            // Re-bind search
            // Re-bind search
            const searchInput = document.getElementById('searchInput');
            const searchIcon = document.querySelector('.search-icon');

            function performSearch(input) {
                const term = input.value.trim();
                // Allow empty search to clear filters
                const url = term ? `browse.php?search=${encodeURIComponent(term)}` : 'browse.php';
                window.location.href = url;
            }

            if (searchInput) {
                const newSearchInput = searchInput.cloneNode(true);
                searchInput.parentNode.replaceChild(newSearchInput, searchInput);
                newSearchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        performSearch(this);
                    }
                });

                // If icon exists, add click handler
                if (searchIcon) {
                    searchIcon.style.cursor = 'pointer';
                    // Remove old listeners by cloning or just add new one (cloning is safer if this runs multiple times)
                    const newSearchIcon = searchIcon.cloneNode(true);
                    searchIcon.parentNode.replaceChild(newSearchIcon, searchIcon);
                    newSearchIcon.addEventListener('click', function () {
                        const input = document.getElementById('searchInput');
                        if (input) performSearch(input);
                    });
                }
            }
        });

        async function loadBooksWithFilter(category, search) {
            const booksGrid = document.getElementById('booksGrid');
            try {
                let url = 'get_books.php?limit=100';
                if (category && category !== 'all') url += `&category=${encodeURIComponent(category)}`;
                if (search) url += `&search=${encodeURIComponent(search)}`;

                log(`Fetching: ${url}`);
                const response = await fetch(url);
                log(`Response status: ${response.status}`);

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const text = await response.text();
                log(`Response length: ${text.length} chars`);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    log(`JSON Parse Error: ${e.message}`, true);
                    log(`Raw content start: ${text.substring(0, 100)}`, true);
                    throw new Error("Invalid JSON response from server");
                }

                if (data.success) {
                    log(`Success. Found ${data.books.length} books.`);
                    if (data.books.length > 0) {
                        displayBooksLocal(data.books);
                    } else {
                        booksGrid.innerHTML = '<div style="text-align: center; padding: 3rem; grid-column: 1/-1;"><p>No books found matching your criteria.</p></div>';
                    }
                } else {
                    throw new Error(data.message || 'Server reported failure');
                }
            } catch (error) {
                log(`Catch Error: ${error.message}`, true);
                booksGrid.innerHTML = `<div style="text-align: center; padding: 3rem; grid-column: 1/-1;">
                    <p style="color: #ef4444; font-size: 1.2rem;">Error: ${error.message}</p>
                    <button onclick="location.reload()" style="margin-top:1rem; padding:0.5rem 1rem; background:#2563eb; color:white; border:none; border-radius:4px; cursor:pointer">Retry</button>
                    <button onclick="document.getElementById('debug-log').style.display='block'" style="margin-top:1rem; margin-left:0.5rem; padding:0.5rem 1rem; background:#4b5563; color:white; border:none; border-radius:4px; cursor:pointer">Show Logs</button>
                </div>`;
            }
        }

        function displayBooksLocal(books) {
            const booksGrid = document.getElementById('booksGrid');
            try {
                booksGrid.innerHTML = books.map(book => `
                    <div class="book-card">
                        <img src="https://images.unsplash.com/photo-1495446815901-a7297e633e8d?w=400&h=600&fit=crop" 
                             alt="${book.title}" class="book-image"
                             onerror="this.src='https://images.unsplash.com/photo-1495446815901-a7297e633e8d?w=400&h=600&fit=crop'">
                        <div class="book-info">
                            <p class="book-category">${book.category}</p>
                            <h3 class="book-title">${book.title} <span style="font-size: 0.8rem; font-weight: normal; color: #6b7280;">(${book.publication_year})</span></h3>
                            <p style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.1rem;">ISBN: ${book.ISBN}</p>
                            <p style="font-size: 0.8rem; color: #6b7280; margin-bottom: 0.1rem;">Publisher: ${book.publisher}</p>
                            <div style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.25rem;">
                                <strong>Publisher Details:</strong> <span>📍 ${book.publisher_address}</span> • <span>📞 ${book.publisher_phone}</span>
                            </div>
                            <p class="book-author">by ${book.authors || 'Unknown'}</p>
                            <div class="book-footer">
                                <span class="book-price">$${parseFloat(book.selling_price).toFixed(2)}</span>
                                <span class="stock-info" style="color: ${book.current_stock > 0 ? '#10b981' : '#ef4444'}">
                                    ${book.current_stock > 0 ? (book.current_stock < 5 ? 'Low Stock' : 'In Stock') : 'Out of Stock'}
                                    ${book.current_stock > 0 ? '(' + book.current_stock + ')' : ''}
                                </span>
                            </div>
                            <button class="btn btn-add-cart" onclick="addToCart('${book.ISBN}')" 
                                    ${book.current_stock <= 0 ? 'disabled style="background: #9ca3af; cursor: not-allowed;"' : ''}>
                                🛒 ${book.current_stock > 0 ? 'Add to Cart' : 'Out of Stock'}
                            </button>
                        </div>
                    </div>
                `).join('');
            } catch (e) {
                log(`Render Error: ${e.message}`, true);
                throw e;
            }
        }
    </script>
</body>

</html>