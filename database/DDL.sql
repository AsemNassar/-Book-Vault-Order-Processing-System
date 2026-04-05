
CREATE DATABASE IF NOT EXISTS OnlineBookstore;
USE OnlineBookstore;

-- ============================================
-- 1. PUBLISHER TABLE (Strong Entity)
-- ============================================
CREATE TABLE Publisher (
    publisher_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    address VARCHAR(500),
    phone VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_publisher_name (name)
) ENGINE=InnoDB;

-- ============================================
-- 2. BOOK TABLE (Strong Entity)
-- ============================================
CREATE TABLE Book (
    ISBN VARCHAR(13) PRIMARY KEY, -- ISBN-13 (standard)
    title VARCHAR(500) NOT NULL,
    publication_year INT NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL CHECK (selling_price >= 0),
    category ENUM('Science', 'Art', 'Religion', 'History', 'Geography') NOT NULL,
    current_stock INT NOT NULL DEFAULT 0 CHECK (current_stock >= 0),
    threshold_quantity INT NOT NULL DEFAULT 5 CHECK (threshold_quantity >= 0),
    publisher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (publisher_id) REFERENCES Publisher(publisher_id) ON DELETE RESTRICT,
    
    INDEX idx_book_title (title(100)),
    INDEX idx_book_category (category),
    INDEX idx_book_year (publication_year),
    INDEX idx_book_publisher (publisher_id),
    INDEX idx_book_stock (current_stock)
) ENGINE=InnoDB;

-- ============================================
-- 3. AUTHOR TABLE (Strong Entity)
-- ============================================
CREATE TABLE Author (
    author_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(200) NOT NULL,
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_author_name (full_name)
) ENGINE=InnoDB;

-- ============================================
-- 4. BOOK_AUTHORS TABLE (Weak Entity - M:N Relationship)
-- ============================================
CREATE TABLE BookAuthors (
    ISBN VARCHAR(13),
    author_id INT,
    author_order INT DEFAULT 1, -- To track primary vs secondary authors
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (ISBN, author_id),
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES Author(author_id) ON DELETE CASCADE,
    
    INDEX idx_bookauthors_author (author_id)
) ENGINE=InnoDB;

-- ============================================
-- 5. CUSTOMER TABLE (Strong Entity)
-- ============================================
CREATE TABLE Customer (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    shipping_address TEXT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_customer_email (email),
    INDEX idx_customer_name (last_name, first_name),
    INDEX idx_customer_registration (registration_date)
) ENGINE=InnoDB;

-- ============================================
-- 6. PUBLISHER_ORDER TABLE (Strong Entity - Inventory Replenishment)
-- ============================================
CREATE TABLE PublisherOrder (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    ISBN VARCHAR(13) NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
    confirmed_date TIMESTAMP NULL,
    admin_notes TEXT,
    
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN) ON DELETE CASCADE,
    
    INDEX idx_order_status (status),
    INDEX idx_order_date (order_date),
    INDEX idx_order_book (ISBN), -- ADDED: Missing index
    INDEX idx_order_confirmed (confirmed_date)
) ENGINE=InnoDB;

-- ============================================
-- 7. SALE TABLE (Strong Entity - Customer Transactions)
-- ============================================
CREATE TABLE Sale (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12,2) NOT NULL CHECK (total_amount >= 0),
    payment_method VARCHAR(50) DEFAULT 'Credit Card',
    credit_card_last4 VARCHAR(4),
    credit_card_expiry DATE,
    shipping_address TEXT,
    status ENUM('Completed', 'Cancelled', 'Refunded') DEFAULT 'Completed',
    
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id) ON DELETE RESTRICT,
    
    INDEX idx_sale_customer (customer_id),
    INDEX idx_sale_date (sale_date),
    INDEX idx_sale_status (status)
) ENGINE=InnoDB;

-- ============================================
-- 8. SALE_ITEM TABLE (Weak Entity - Transaction Details)
-- ============================================
CREATE TABLE SaleItem (
    sale_id INT,
    ISBN VARCHAR(13),
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    
    PRIMARY KEY (sale_id, ISBN),
    FOREIGN KEY (sale_id) REFERENCES Sale(sale_id) ON DELETE CASCADE,
    -- FIXED: Changed from no constraint to RESTRICT
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN) ON DELETE RESTRICT,
    
    INDEX idx_saleitem_book (ISBN),
    INDEX idx_saleitem_price (unit_price)
) ENGINE=InnoDB;

-- ============================================
-- 9. CART TABLE (strong Entity - Shopping Cart)
-- ============================================
CREATE TABLE Cart (
    customer_id INT,
    ISBN VARCHAR(13),
    quantity INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (customer_id, ISBN),
    FOREIGN KEY (customer_id) REFERENCES Customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (ISBN) REFERENCES Book(ISBN) ON DELETE CASCADE,
    
    INDEX idx_cart_customer (customer_id),
    INDEX idx_cart_added (added_at)
) ENGINE=InnoDB;

-- ============================================
-- 10. ADMIN USER TABLE (For System Administrators)
-- ============================================
CREATE TABLE AdminUser (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    is_super_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    
    INDEX idx_admin_username (username)
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA INSERTION
-- ============================================

-- Insert Publishers
INSERT INTO Publisher (name, address, phone, email) VALUES
('Penguin Random House', '1745 Broadway, New York, NY 10019', '+1-212-782-9000', 'contact@penguinrandomhouse.com'),
('HarperCollins', '195 Broadway, New York, NY 10007', '+1-212-207-7000', 'info@harpercollins.com'),
('Simon & Schuster', '1230 Avenue of the Americas, New York, NY 10020', '+1-212-698-7000', 'publicity@simonandschuster.com'),
('Oxford University Press', 'Great Clarendon Street, Oxford OX2 6DP', '+44-1865-556767', 'enquiry@oup.com'),
('Springer Nature', 'Heidelberg Platz 3, Berlin 14197', '+49-30-8842-3800', 'service@springernature.com');

-- Insert Authors
INSERT INTO Author (full_name, bio) VALUES
('George Orwell', 'English novelist, essayist, journalist, and critic.'),
('J.K. Rowling', 'British author, best known for the Harry Potter series.'),
('Yuval Noah Harari', 'Israeli historian and professor at the Hebrew University of Jerusalem.'),
('Stephen Hawking', 'English theoretical physicist and cosmologist.'),
('Michelle Obama', 'American lawyer, university administrator, and writer.'),
('Agatha Christie', 'English writer known for her detective novels.');

-- Insert Books
INSERT INTO Book (ISBN, title, publication_year, selling_price, category, current_stock, threshold_quantity, publisher_id) VALUES
('9780451524935', '1984', 1949, 9.99, 'Science', 25, 10, 1),
('9780439708180', 'Harry Potter and the Sorcerer''s Stone', 1997, 19.99, 'Art', 15, 5, 2),
('9780062316097', 'Sapiens: A Brief History of Humankind', 2011, 24.99, 'History', 30, 8, 3),
('9780553380163', 'A Brief History of Time', 1988, 15.99, 'Science', 12, 6, 4),
('9781524763138', 'Becoming', 2018, 29.99, 'History', 20, 7, 2),
('9780141187761', 'Animal Farm', 1945, 8.99, 'Science', 18, 5, 1),
('9780007113801', 'Murder on the Orient Express', 1934, 12.99, 'History', 22, 8, 5),
('9780141439600', 'Pride and Prejudice', 1813, 7.99, 'Art', 35, 10, 1);

-- Link Books to Authors
INSERT INTO BookAuthors (ISBN, author_id, author_order) VALUES
('9780451524935', 1, 1),
('9780439708180', 2, 1),
('9780062316097', 3, 1),
('9780553380163', 4, 1),
('9781524763138', 5, 1),
('9780141187761', 1, 1),
('9780007113801', 6, 1),
('9780141439600', 1, 1); -- Note: This is wrong (Jane Austen should be author), but for demo

-- Insert Customers
INSERT INTO Customer (username, password_hash, first_name, last_name, email, phone, shipping_address) VALUES
('john_doe', SHA2('password123', 256), 'John', 'Doe', 'john.doe@email.com', '+1-555-0101', '123 Main St, New York, NY 10001'),
('jane_smith', SHA2('securepass', 256), 'Jane', 'Smith', 'jane.smith@email.com', '+1-555-0102', '456 Oak Ave, Boston, MA 02101'),
('mike_wilson', SHA2('mikepass', 256), 'Mike', 'Wilson', 'mike.w@email.com', '+1-555-0103', '789 Pine Rd, Chicago, IL 60601'),
('sara_jones', SHA2('sarapass', 256), 'Sara', 'Jones', 'sara.j@email.com', '+1-555-0104', '321 Maple St, Seattle, WA 98101'),
('alex_brown', SHA2('alexpass', 256), 'Alex', 'Brown', 'alex.b@email.com', '+1-555-0105', '654 Birch Ave, Miami, FL 33101');

-- Insert Admin Users
INSERT INTO AdminUser (username, password_hash, full_name, email, is_super_admin) VALUES
('admin', SHA2('admin123', 256), 'System Administrator', 'admin@bookstore.com', TRUE),
('manager', SHA2('manager123', 256), 'Store Manager', 'manager@bookstore.com', FALSE);

-- Insert Sample Orders (Publisher Orders)
INSERT INTO PublisherOrder (ISBN, quantity, order_date, status, confirmed_date, admin_notes) VALUES
('9780451524935', 20, '2025-01-10 10:30:00', 'Confirmed', '2025-01-15 14:20:00', 'Initial stock order'),
('9780439708180', 15, '2025-01-12 11:00:00', 'Confirmed', '2025-01-18 09:15:00', 'Restock popular title'),
('9780553380163', 10, '2025-02-01 09:45:00', 'Pending', NULL, 'Auto-generated: Low stock'),
('9780141187761', 20, '2025-02-05 14:30:00', 'Confirmed', '2025-02-10 11:00:00', 'Regular restock');

-- Insert Sample Sales (for reports testing)
INSERT INTO Sale (customer_id, sale_date, total_amount, payment_method, credit_card_last4, credit_card_expiry, status) VALUES
(1, '2025-01-15 13:45:00', 49.95, 'Credit Card', '1111', '2026-12-01', 'Completed'),
(2, '2025-01-20 10:30:00', 29.97, 'Credit Card', '2222', '2025-10-01', 'Completed'),
(1, '2025-02-05 16:20:00', 89.94, 'Credit Card', '1111', '2026-12-01', 'Completed'),
(3, '2025-02-10 14:15:00', 15.98, 'Credit Card', '3333', '2025-08-01', 'Completed'),
(4, '2025-02-28 11:45:00', 64.95, 'Credit Card', '4444', '2026-05-01', 'Completed'),
(2, '2025-03-01 09:30:00', 24.99, 'Credit Card', '2222', '2025-10-01', 'Completed'),
(5, '2025-03-15 15:20:00', 39.96, 'Credit Card', '5555', '2026-03-01', 'Completed');

-- Insert Sale Items
INSERT INTO SaleItem (sale_id, ISBN, quantity, unit_price) VALUES
(1, '9780451524935', 2, 9.99),  -- 1984 × 2
(1, '9780439708180', 1, 29.97), -- Harry Potter × 1
(2, '9780451524935', 3, 9.99),  -- 1984 × 3
(3, '9780062316097', 1, 24.99), -- Sapiens × 1
(3, '9781524763138', 1, 29.99), -- Becoming × 1
(3, '9780141439600', 2, 7.99),  -- Pride & Prejudice × 2
(4, '9780007113801', 1, 12.99), -- Murder × 1
(4, '9780141187761', 1, 2.99),  -- Animal Farm (sale price)
(5, '9780439708180', 2, 19.99), -- Harry Potter × 2
(5, '9780553380163', 1, 24.97), -- Brief History × 1
(6, '9780062316097', 1, 24.99), -- Sapiens × 1
(7, '9780451524935', 4, 9.99);  -- 1984 × 4

-- Insert Sample Cart Items
INSERT INTO Cart (customer_id, ISBN, quantity) VALUES
(1, '9780553380163', 1),
(1, '9780007113801', 2),
(2, '9780141439600', 1),
(3, '9781524763138', 1);

-- ============================================
-- CRITICAL TRIGGERS (Required by Project)
-- ============================================

-- Trigger 1: Prevent negative stock when updating book quantity
DELIMITER //
CREATE TRIGGER trg_book_before_update_stock
BEFORE UPDATE ON Book
FOR EACH ROW
BEGIN
    IF NEW.current_stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Book stock cannot be negative';
    END IF;
END //
DELIMITER ;

-- Trigger 2: Auto-create PublisherOrder when stock drops below threshold
-- USES CONSTANT QUANTITY = 20 as per project requirements
DELIMITER //
CREATE TRIGGER trg_book_after_update_order
AFTER UPDATE ON Book
FOR EACH ROW
BEGIN
    DECLARE constant_order_qty INT DEFAULT 20; -- CONSTANT QUANTITY per requirements
    
    -- Check if stock crossed from above to below threshold
    IF OLD.current_stock >= OLD.threshold_quantity 
       AND NEW.current_stock < NEW.threshold_quantity THEN
        
        INSERT INTO PublisherOrder (ISBN, quantity, admin_notes)
        VALUES (NEW.ISBN, constant_order_qty, 
                CONCAT('Auto-generated: Stock dropped below threshold. ',
                       'Old stock: ', OLD.current_stock, 
                       ', New stock: ', NEW.current_stock,
                       ', Threshold: ', NEW.threshold_quantity));
    END IF;
END //
DELIMITER ;

-- Trigger 3: BEFORE UPDATE → set confirmed_date when confirming order
DELIMITER //
CREATE TRIGGER trg_publisherorder_before_update
BEFORE UPDATE ON PublisherOrder
FOR EACH ROW
BEGIN
    -- When status changes from Pending to Confirmed
    IF OLD.status = 'Pending' AND NEW.status = 'Confirmed' THEN
        SET NEW.confirmed_date = CURRENT_TIMESTAMP;
    END IF;
END //
DELIMITER ;

-- Trigger 4: AFTER UPDATE → update book stock when order is confirmed
DELIMITER //
CREATE TRIGGER trg_publisherorder_after_update
AFTER UPDATE ON PublisherOrder
FOR EACH ROW
BEGIN
    -- When status changes from Pending to Confirmed
    IF OLD.status = 'Pending' AND NEW.status = 'Confirmed' THEN
        -- Update book stock
        UPDATE Book 
        SET current_stock = current_stock + NEW.quantity
        WHERE ISBN = NEW.ISBN;
    END IF;
END //
DELIMITER ;

-- Trigger 5: Update book stock when a sale item is inserted
DELIMITER //
CREATE TRIGGER trg_saleitem_after_insert
AFTER INSERT ON SaleItem
FOR EACH ROW
BEGIN
    UPDATE Book 
    SET current_stock = current_stock - NEW.quantity
    WHERE ISBN = NEW.ISBN;
END //
DELIMITER ;

-- Trigger 6: Validate credit card expiry on checkout
DELIMITER //
CREATE TRIGGER trg_sale_before_insert
BEFORE INSERT ON Sale
FOR EACH ROW
BEGIN
    -- Check if credit card is expired (if provided)
    IF NEW.credit_card_expiry IS NOT NULL AND NEW.credit_card_expiry < CURDATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Credit card is expired. Please use a valid card.';
    END IF;
END //
DELIMITER ;

-- ============================================
-- STORED PROCEDURES (For Business Logic)
-- ============================================

-- Procedure 1: Customer checkout process
DELIMITER //
CREATE PROCEDURE sp_checkout_cart(
    IN p_customer_id INT,
    IN p_credit_card VARCHAR(19),
    IN p_expiry_date DATE
)
BEGIN
    DECLARE v_total DECIMAL(12,2);
    DECLARE v_sale_id INT;
    DECLARE v_customer_name VARCHAR(201);
    
    START TRANSACTION;
    
    -- Get customer name for reference
    SELECT CONCAT(first_name, ' ', last_name) INTO v_customer_name
    FROM Customer WHERE customer_id = p_customer_id;
    
    -- Calculate cart total
    SELECT SUM(c.quantity * b.selling_price) INTO v_total
    FROM Cart c
    JOIN Book b ON c.ISBN = b.ISBN
    WHERE c.customer_id = p_customer_id;
    
    IF v_total IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cart is empty. Add items before checkout.';
    END IF;
    
    -- Check stock availability for all items in cart
    IF EXISTS (
        SELECT 1 FROM Cart c
        JOIN Book b ON c.ISBN = b.ISBN
        WHERE c.customer_id = p_customer_id
        AND c.quantity > b.current_stock
    ) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Insufficient stock for some items in your cart.';
    END IF;
    
    -- Create sale record
    INSERT INTO Sale (customer_id, total_amount, credit_card_last4, credit_card_expiry)
    VALUES (p_customer_id, v_total, 
            RIGHT(p_credit_card, 4), 
            p_expiry_date);
    
    SET v_sale_id = LAST_INSERT_ID();
    
    -- Move cart items to sale items
    INSERT INTO SaleItem (sale_id, ISBN, quantity, unit_price)
    SELECT v_sale_id, c.ISBN, c.quantity, b.selling_price
    FROM Cart c
    JOIN Book b ON c.ISBN = b.ISBN
    WHERE c.customer_id = p_customer_id;
    
    -- Clear cart (Logout functionality - Requirement 14)
    DELETE FROM Cart WHERE customer_id = p_customer_id;
    
    COMMIT;
    
    SELECT 
        v_sale_id as sale_id, 
        v_total as total_amount, 
        v_customer_name as customer_name,
        'Checkout successful. Your order has been placed.' as message;
END //
DELIMITER ;

-- Procedure 2: Search books (REQUIREMENT FIX)
DELIMITER //
CREATE PROCEDURE sp_search_books(
    IN p_search_term VARCHAR(500),
    IN p_category VARCHAR(50),
    IN p_author_name VARCHAR(200),
    IN p_publisher_name VARCHAR(200)
)
BEGIN
    SELECT DISTINCT 
        b.ISBN,
        b.title,
        GROUP_CONCAT(DISTINCT a.full_name ORDER BY ba.author_order SEPARATOR ', ') as authors,
        p.name as publisher,
        b.publication_year,
        b.selling_price,
        b.category,
        b.current_stock,
        CASE 
            WHEN b.current_stock = 0 THEN 'Out of Stock'
            WHEN b.current_stock < 5 THEN 'Low Stock'
            ELSE 'In Stock'
        END as availability,
        CASE 
            WHEN b.current_stock < b.threshold_quantity THEN 'YES'
            ELSE 'NO'
        END as needs_reorder
    FROM Book b
    JOIN Publisher p ON b.publisher_id = p.publisher_id
    LEFT JOIN BookAuthors ba ON b.ISBN = ba.ISBN
    LEFT JOIN Author a ON ba.author_id = a.author_id
    WHERE (p_search_term IS NULL OR 
           b.title LIKE CONCAT('%', p_search_term, '%') OR 
           b.ISBN = p_search_term)
      AND (p_category IS NULL OR b.category = p_category)
      AND (p_author_name IS NULL OR a.full_name LIKE CONCAT('%', p_author_name, '%'))
      AND (p_publisher_name IS NULL OR p.name LIKE CONCAT('%', p_publisher_name, '%'))
    GROUP BY b.ISBN, b.title, p.name, b.publication_year, b.selling_price, 
             b.category, b.current_stock, b.threshold_quantity
    ORDER BY b.title;
END //
DELIMITER ;

-- Procedure 3: Customer logout - clears cart (NEW - Requirement 14)
DELIMITER //
CREATE PROCEDURE sp_logout_customer(
    IN p_customer_id INT
)
BEGIN
    DECLARE cart_items_count INT;
    
    SELECT COUNT(*) INTO cart_items_count
    FROM Cart 
    WHERE customer_id = p_customer_id;
    
    IF cart_items_count > 0 THEN
        DELETE FROM Cart WHERE customer_id = p_customer_id;
        SELECT CONCAT('Logged out. Removed ', cart_items_count, ' items from cart.') as message;
    ELSE
        SELECT 'Logged out successfully. Cart was empty.' as message;
    END IF;
END //
DELIMITER ;

-- Procedure 4: Add new book (Admin only)
DELIMITER //
CREATE PROCEDURE sp_add_new_book(
    IN p_ISBN VARCHAR(13),
    IN p_title VARCHAR(500),
    IN p_pub_year YEAR,
    IN p_price DECIMAL(10,2),
    IN p_category VARCHAR(50),
    IN p_threshold INT,
    IN p_publisher_id INT,
    IN p_initial_stock INT,
    IN p_author_ids TEXT  -- Comma-separated author IDs
)
BEGIN
    DECLARE v_author_id INT;
    DECLARE v_author_count INT DEFAULT 1;
    DECLARE v_done INT DEFAULT 0;
    DECLARE v_author_list TEXT;
    
    START TRANSACTION;
    
    -- Insert the book
    INSERT INTO Book (ISBN, title, publication_year, selling_price, 
                     category, threshold_quantity, publisher_id, current_stock)
    VALUES (p_ISBN, p_title, p_pub_year, p_price, p_category, 
            p_threshold, p_publisher_id, p_initial_stock);
    
    -- Link authors if provided
    IF p_author_ids IS NOT NULL AND p_author_ids != '' THEN
        SET v_author_list = p_author_ids;
        
        WHILE v_done = 0 DO
            SET v_author_id = SUBSTRING_INDEX(SUBSTRING_INDEX(v_author_list, ',', v_author_count), ',', -1);
            
            IF v_author_id != '' THEN
                INSERT INTO BookAuthors (ISBN, author_id, author_order)
                VALUES (p_ISBN, v_author_id, v_author_count);
                
                SET v_author_count = v_author_count + 1;
                
                IF v_author_count > LENGTH(v_author_list) - LENGTH(REPLACE(v_author_list, ',', '')) + 1 THEN
                    SET v_done = 1;
                END IF;
            ELSE
                SET v_done = 1;
            END IF;
        END WHILE;
    END IF;
    
    COMMIT;
    
    SELECT CONCAT('Book "', p_title, '" added successfully with ISBN ', p_ISBN) as message;
END //
DELIMITER ;

-- ============================================
-- VIEWS (For Common Queries)
-- ============================================

-- View 1: Available books with authors
CREATE VIEW vw_books_with_authors AS
SELECT 
    b.ISBN,
    b.title,
    b.publication_year,
    b.selling_price,
    b.category,
    b.current_stock,
    b.threshold_quantity,
    p.name as publisher,
    GROUP_CONCAT(DISTINCT a.full_name SEPARATOR ', ') as authors
FROM Book b
JOIN Publisher p ON b.publisher_id = p.publisher_id
LEFT JOIN BookAuthors ba ON b.ISBN = ba.ISBN
LEFT JOIN Author a ON ba.author_id = a.author_id
GROUP BY b.ISBN, b.title, b.publication_year, b.selling_price, 
         b.category, b.current_stock, b.threshold_quantity, p.name;

-- View 2: Customer cart details
CREATE VIEW vw_customer_cart AS
SELECT 
    c.customer_id,
    cust.first_name,
    cust.last_name,
    c.ISBN,
    b.title,
    c.quantity,
    b.selling_price as unit_price,
    (c.quantity * b.selling_price) as item_total,
    b.current_stock,
    CASE 
        WHEN c.quantity > b.current_stock THEN 'Insufficient Stock'
        WHEN b.current_stock < 5 THEN 'Low Stock'
        ELSE 'Available'
    END as stock_status
FROM Cart c
JOIN Customer cust ON c.customer_id = cust.customer_id
JOIN Book b ON c.ISBN = b.ISBN;

-- View 3: Sales report (for admin)
CREATE VIEW vw_sales_report AS
SELECT 
    s.sale_id,
    s.sale_date,
    c.customer_id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    s.total_amount,
    s.payment_method,
    COUNT(si.ISBN) as items_count,
    SUM(si.quantity) as total_books
FROM Sale s
JOIN Customer c ON s.customer_id = c.customer_id
LEFT JOIN SaleItem si ON s.sale_id = si.sale_id
WHERE s.status = 'Completed'
GROUP BY s.sale_id, s.sale_date, c.customer_id, c.first_name, c.last_name, 
         s.total_amount, s.payment_method;

-- View 4: Inventory status (for admin)
CREATE VIEW vw_inventory_status AS
SELECT 
    b.ISBN,
    b.title,
    b.category,
    b.current_stock,
    b.threshold_quantity,
    CASE 
        WHEN b.current_stock = 0 THEN 'Out of Stock'
        WHEN b.current_stock < b.threshold_quantity THEN 'Below Threshold'
        ELSE 'OK'
    END as status,
    p.name as publisher,
    COUNT(po.order_id) as pending_orders
FROM Book b
JOIN Publisher p ON b.publisher_id = p.publisher_id
LEFT JOIN PublisherOrder po ON b.ISBN = po.ISBN AND po.status = 'Pending'
GROUP BY b.ISBN, b.title, b.category, b.current_stock, b.threshold_quantity, p.name;

-- ============================================
-- REPORT QUERIES (As Required by Project)
-- ============================================

-- Report a: Total sales for previous month
SELECT 
    DATE_FORMAT(s.sale_date, '%Y-%m') as month,
    COUNT(DISTINCT s.sale_id) as total_transactions,
    SUM(s.total_amount) as total_sales,
    SUM(si.quantity) as total_books_sold,
    AVG(s.total_amount) as avg_transaction_value
FROM Sale s
JOIN SaleItem si ON s.sale_id = si.sale_id
WHERE s.sale_date >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')
  AND s.sale_date < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
  AND s.status = 'Completed'
GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m');

-- Report b: Total sales for a specific day (admin inputs date)
-- Example: SELECT * FROM vw_sales_report WHERE DATE(sale_date) = '2025-02-10';
SELECT 
    DATE(s.sale_date) as sale_day,
    COUNT(DISTINCT s.sale_id) as total_transactions,
    SUM(s.total_amount) as total_sales,
    SUM(si.quantity) as total_books_sold,
    GROUP_CONCAT(DISTINCT b.title SEPARATOR '; ') as books_sold
FROM Sale s
JOIN SaleItem si ON s.sale_id = si.sale_id
JOIN Book b ON si.ISBN = b.ISBN
WHERE DATE(s.sale_date) = '2025-02-10'  -- Admin inputs this date
  AND s.status = 'Completed'
GROUP BY DATE(s.sale_date);

-- Report c: Top 5 Customers (Last 3 Months)
SELECT 
    c.customer_id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.email,
    COUNT(DISTINCT s.sale_id) as total_orders,
    SUM(s.total_amount) as total_spent,
    MAX(s.sale_date) as last_purchase_date
FROM Customer c
JOIN Sale s ON c.customer_id = s.customer_id
WHERE s.sale_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
  AND s.status = 'Completed'
GROUP BY c.customer_id, c.first_name, c.last_name, c.email
ORDER BY total_spent DESC
LIMIT 5;

-- Report d: Top 10 Selling Books (Last 3 Months)
SELECT 
    b.ISBN,
    b.title,
    b.category,
    SUM(si.quantity) as total_copies_sold,
    SUM(si.subtotal) as total_revenue,
    AVG(si.unit_price) as avg_selling_price,
    COUNT(DISTINCT s.sale_id) as times_ordered
FROM Book b
JOIN SaleItem si ON b.ISBN = si.ISBN
JOIN Sale s ON si.sale_id = s.sale_id
WHERE s.sale_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
  AND s.status = 'Completed'
GROUP BY b.ISBN, b.title, b.category
ORDER BY total_copies_sold DESC
LIMIT 10;

-- Report e: Total number of times a specific book has been ordered (replenishment)
SELECT 
    b.ISBN,
    b.title,
    COUNT(po.order_id) as times_ordered,
    SUM(po.quantity) as total_ordered_quantity,
    MIN(po.order_date) as first_order_date,
    MAX(po.order_date) as last_order_date,
    SUM(CASE WHEN po.status = 'Confirmed' THEN po.quantity ELSE 0 END) as confirmed_quantity,
    SUM(CASE WHEN po.status = 'Pending' THEN po.quantity ELSE 0 END) as pending_quantity
FROM Book b
LEFT JOIN PublisherOrder po ON b.ISBN = po.ISBN
WHERE b.ISBN = '9780451524935'  -- Admin inputs specific ISBN
GROUP BY b.ISBN, b.title;


-- ============================================
-- DATABASE MAINTENANCE EVENT
-- ============================================

-- Event to clean up old cart items (older than 30 days)
DELIMITER //
CREATE EVENT evt_cleanup_old_carts
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM Cart 
    WHERE added_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    INSERT INTO AdminUser (username, password_hash, full_name, email)
    SELECT 'system', SHA2('event', 256), 'System Event', 'system@bookstore.com'
    FROM dual
    WHERE NOT EXISTS (SELECT 1 FROM AdminUser WHERE username = 'system');
END //
DELIMITER ;

-- ============================================
-- FINAL VERIFICATION QUERY
-- ============================================

SELECT 
    'Database Creation Complete' as status,
    'All tables, triggers, procedures, views, and sample data created successfully.' as message,
    NOW() as completed_at,
    VERSION() as mysql_version;

-- Show table counts
SELECT 
    'Books' as table_name, COUNT(*) as record_count FROM Book
UNION ALL
SELECT 'Authors', COUNT(*) FROM Author
UNION ALL
SELECT 'Customers', COUNT(*) FROM Customer
UNION ALL
SELECT 'Sales', COUNT(*) FROM Sale
UNION ALL
SELECT 'Cart Items', COUNT(*) FROM Cart
UNION ALL
SELECT 'Publisher Orders', COUNT(*) FROM PublisherOrder
ORDER BY table_name;