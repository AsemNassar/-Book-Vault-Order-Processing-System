# 📚 BookVault — Order Processing System

> A full-stack web application for managing an online bookstore — from customer browsing and checkout to admin inventory control and sales analytics.

---

## 🌐 Overview

**BookVault** is a complete, database-driven bookstore management system built with PHP, MySQL, HTML/CSS, and JavaScript. It supports two types of users — **customers** and **admins** — each with a dedicated interface and role-specific features.

The system handles the full lifecycle of a book sale: browsing, cart management, checkout, stock deduction, and automatic reorder triggering when inventory runs low.

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, JavaScript (AJAX) |
| Backend | PHP |
| Database | MySQL |
| Architecture | MVC-inspired, procedural PHP |
| DB Objects | Triggers, Stored Procedures, Views, Events |

---

## ✨ Features

### 👤 Customer
- Register / Login / Logout (session-based)
- Browse and search books by category, author, publisher, or ISBN
- Real-time stock status with color indicators
- Shopping cart — add, update, remove items
- Checkout with credit card validation
- View full order history with itemized details
- Edit profile and change password

### 🛠️ Admin
- Secure admin login with separate session
- Dashboard with live KPIs (books, customers, orders, low stock alerts)
- Full book management — add, edit, and update stock
- Auto-triggered publisher orders when stock falls below threshold
- Manual order confirmation / cancellation
- Advanced reporting:
  - Previous month & daily sales
  - Top 5 customers (last 3 months)
  - Top 10 selling books (last 3 months)
  - Low stock report
  - Publisher order history
  - Full customer list with order counts

---

## 🗄️ Database Design

### Entities
- `Publisher` — Book publishers with contact info
- `Book` — Full book catalog with stock and pricing
- `Author` — Author profiles
- `BookAuthors` — M:N relationship between books and authors
- `Customer` — Registered customers
- `Sale` + `SaleItem` — Transaction records
- `Cart` — Active shopping carts
- `PublisherOrder` — Inventory replenishment orders
- `AdminUser` — System administrators

### Key DB Objects

**Triggers (6)**
- Prevent negative stock on update
- Auto-create publisher orders when stock drops below threshold
- Set confirmed date when order status changes
- Restock books when publisher order is confirmed
- Deduct stock on sale item insert
- Validate credit card expiry before sale

**Stored Procedures (4)**
- `sp_checkout_cart` — Full checkout flow
- `sp_search_books` — Filtered book search
- `sp_logout_customer` — Clear cart on logout
- `sp_add_new_book` — Add book with authors atomically

**Views (4)**
- `vw_books_with_authors`
- `vw_customer_cart`
- `vw_sales_report`
- `vw_inventory_status`

**Event**
- `evt_cleanup_old_carts` — Daily cleanup of carts older than 30 days

---

## 📁 Project Structure

```
bookvault/
│
├── admin/                  # Admin interface pages
│   ├── admin_login.html
│   ├── admin_dashboard.php
│   ├── admin_books.php
│   ├── admin_orders.php
│   └── admin_reports.php
│
├── customer/               # Customer-facing pages
│   ├── browse.php
│   ├── cart.php
│   ├── checkout_process.php
│   ├── orders.php
│   └── profile.php
│
├── auth/                   # Authentication
│   ├── login.html
│   ├── signup.html
│   ├── auth.js
│   └── logout.php
│
├── assets/                 # Static assets
│   ├── css/
│   ├── js/
│   └── images/
│
├── database/
│   └── DDL.sql             # Full schema: tables, triggers, procedures, views, sample data
│
├── config.php              # DB connection (excluded from repo — see setup)
└── README.md
```

---

## ⚙️ Setup & Installation

### Prerequisites
- PHP 7.4+
- MySQL 8.0+
- Apache / XAMPP / WAMP

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/AsemNassar/bookvault.git
   cd bookvault
   ```

2. **Import the database**
   - Open phpMyAdmin or MySQL CLI
   - Create a database named `bookvault`
   - Import `database/DDL.sql`

3. **Configure the connection**
   - Copy the example config:
     ```bash
     cp config.example.php config.php
     ```
   - Edit `config.php` with your DB credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'bookvault');
     ```

4. **Run the app**
   - Place the project folder in your web server's root (e.g., `htdocs/` for XAMPP)
   - Visit `http://localhost/bookvault/home.html`

---

## 👥 Team

| Name | ID |
|---|---|
| Asem Wael Nassar | 2204043 |
| Mahmoud Ibrahim Khalil | 2204086 |
| Mohammed Hisham Hamdoun | 2204085 |

Alexandria National University — Computer & Communications Engineering

---

## 📄 License

This project was developed for academic purposes at Alexandria National University.
