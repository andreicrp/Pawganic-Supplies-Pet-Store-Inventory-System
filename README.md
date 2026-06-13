# 🐾 Pawganic Supplies

> A full-featured PHP-based pet store e-commerce web application built with XAMPP, MySQL, and Bootstrap 5.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
  - [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Customer Panel](#customer-panel)
  - [Admin Panel](#admin-panel)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

---

## 📌 Overview

**Pawganic Supplies** is a fully functional pet store e-commerce platform built using PHP and MySQL on a XAMPP local server stack. It supports customer-facing shopping features and a robust admin management panel — covering everything from product listings and cart management to order processing, discount codes, and user account management.

---

## ✨ Features

### 🛒 Customer Features
- **User Registration & Login** — Secure account creation with hashed passwords and session management
- **Product Browsing** — Browse pet products by category (Food, Toys, Accessories)
- **Product Detail Pages** — Detailed view with descriptions, pricing, and stock availability
- **Shopping Cart** — Add, update, and remove items before checkout
- **Favorites / Wishlist** — Save preferred products for later
- **Checkout & Payment** — Multiple payment methods: GCash, PayPal, MasterCard, Debit/Credit Card, Apple Pay
- **Order History** — View past purchases and transaction statuses
- **User Profile** — Update personal info, delivery address, and profile picture
- **Cat Care Tips** — Informational content page for pet care guidance
- **Coupon / Discount Validation** — Apply discount codes at checkout

### 🔧 Admin Features
- **Dashboard** — Overview of store activity and statistics
- **Product Management** — Add, edit, and delete products with image upload and expiry tracking
- **Order Management** — View and update the status of all customer transactions
- **User Account Management** — List, view, and manage registered users
- **Discount Management** — Create and manage promotional discount codes
- **Database Backup** — Download and delete database backups directly from the admin panel
- **Stock Updates** — Real-time inventory management

---

## 🛠 Tech Stack

| Layer       | Technology                          |
|-------------|-------------------------------------|
| Backend     | PHP 8.2                             |
| Database    | MySQL / MariaDB 10.4 (via XAMPP)    |
| Frontend    | HTML5, CSS3, Bootstrap 5.3          |
| Icons       | Font Awesome 6.4                    |
| Typography  | Google Fonts (Playfair Display, DM Sans) |
| Server      | Apache (XAMPP)                      |
| DB Admin    | phpMyAdmin                          |

---

## 📁 Project Structure

```
petv10/
├── admin/                  # Admin-only pages (dashboard, product mgmt, orders)
│   ├── admin.php           # Main admin dashboard
│   ├── add.php             # Add new product
│   ├── admin_purchases.php # Order/transaction management
│   ├── discount_management.php
│   ├── manage_accounts.php
│   ├── download_backup.php
│   └── delete_backup.php
│
├── auth/                   # Authentication handlers
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── config/                 # App configuration (protected)
│   ├── config.php          # DB credentials, session & security settings
│   ├── db.php              # Database connection & helpers
│   ├── mail.php            # Mail configuration
│   └── logs/              # Application error logs
│
├── database/               # SQL schema and backup files
│   ├── pet_store_inventoryv10.sql   # Latest schema with seed data
│   ├── add_login_attempts_table.sql
│   └── backups/
│
├── pages/                  # Customer-facing pages
│   ├── about.php
│   ├── cat_care_tips.php
│   ├── checkout.php
│   ├── process_payment.php
│   ├── cart/              # Cart views & actions
│   ├── product/           # Product detail pages
│   └── profile/           # User profile pages
│
├── includes/               # Shared PHP utilities & AJAX handlers
│   ├── validate_coupon.php
│   └── update_stock.php
│
├── assets/                 # Static assets (images, banners, logo)
├── uploads/                # User-uploaded files (profile pictures, etc.)
├── images/                 # Product images
├── favicon_io/             # Favicon assets
│
├── index.php               # Entry point — redirects to main
├── main.php                # Main storefront / homepage
├── shop.php                # Shop listing page
├── product.php             # Product detail entry point
├── cart.php                # Cart entry point
├── checkout.php            # Checkout entry point
├── login.php               # Login entry point
├── register.php            # Register entry point
├── profile.php             # Profile entry point
├── favorites.php           # Favorites/Wishlist entry point
├── purchase_history.php    # Order history entry point
├── about.php               # About page entry point
├── .htaccess               # Apache routing & security rules
└── README.md
```

> **Note:** Most root-level `.php` files are thin entry points that `require` the actual logic from subdirectories (`pages/`, `admin/`, `auth/`). This keeps routes clean while centralizing logic.

---

## 🗄 Database Schema

The application uses the `pet_store_inventory` MySQL database with the following tables:

| Table          | Description                                              |
|----------------|----------------------------------------------------------|
| `users`        | Registered customers and admins with roles and balances  |
| `products`     | Product catalog with categories, pricing, and stock      |
| `cart`         | Active shopping cart items per user                      |
| `transactions` | Completed orders with payment and delivery details       |

### Entity Relationships
- A **user** can have many **cart** items and **transactions**
- Each **cart** item and **transaction** references a single **product**
- Products have categories: `Food`, `Toy`, `Accessory`
- Users have roles: `admin` or `user`

---

## 🚀 Getting Started

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8.2+, Apache, MySQL/MariaDB)
- A web browser (Chrome recommended)
- phpMyAdmin (bundled with XAMPP)

### Installation

1. **Clone or download** this repository into your XAMPP `htdocs` directory:
   ```bash
   git clone <repository-url> C:/xampp/htdocs/petv10
   ```
   Or simply extract the project folder to:
   ```
   C:\xampp\htdocs\petv10\
   ```

2. **Start XAMPP** — Launch the XAMPP Control Panel and start:
   - ✅ Apache
   - ✅ MySQL

3. **Access the application** at:
   ```
   http://localhost/petv10
   ```

### Database Setup

1. Open **phpMyAdmin** in your browser:
   ```
   http://localhost/phpmyadmin
   ```

2. Create a new database named **`pet_store_inventory`**

3. Select the new database, go to the **Import** tab, and import:
   ```
   database/pet_store_inventoryv10.sql
   ```

4. *(Optional)* Import the login attempts table for rate limiting:
   ```
   database/add_login_attempts_table.sql
   ```

The database comes pre-seeded with sample products and test user accounts.

---

## ⚙️ Configuration

All application settings are managed in [`config/config.php`](config/config.php):

```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ⚠️ Change for production
define('DB_PASS', '');           // ⚠️ Change for production
define('DB_NAME', 'pet_store_inventory');

// Application Base URL
define('BASE_URL', 'http://localhost/petv10');

// Session
define('SESSION_TIMEOUT', 1800);  // 30 minutes

// Security
define('MAX_LOGIN_ATTEMPTS', 5);  // Brute-force protection
define('LOGIN_LOCKOUT_TIME', 900); // 15-minute lockout
```

> ⚠️ **For production deployment**, change the database credentials, set a strong password, create a dedicated MySQL user with limited privileges, and update `BASE_URL` to your live domain.

---

## 🖥 Usage

### Customer Panel

| Action             | URL                             |
|--------------------|---------------------------------|
| Homepage           | `http://localhost/petv10`       |
| Shop               | `http://localhost/petv10/shop`  |
| Cart               | `http://localhost/petv10/cart`  |
| Login              | `http://localhost/petv10/login` |
| Register           | `http://localhost/petv10/register` |
| Profile            | `http://localhost/petv10/profile` |
| Order History      | `http://localhost/petv10/purchase_history` |
| Favorites          | `http://localhost/petv10/favorites` |
| About              | `http://localhost/petv10/about` |

#### Sample Customer Credentials (from seed data)
```
Username: user1
Password: (use the hashed password via registration or reset)
```

### Admin Panel

| Action                  | URL                                        |
|-------------------------|--------------------------------------------|
| Admin Dashboard         | `http://localhost/petv10/admin`            |
| Manage Products         | `http://localhost/petv10/admin` → Products |
| Manage Orders           | `http://localhost/petv10/admin_purchases`  |
| Manage Accounts         | `http://localhost/petv10/manage_accounts`  |
| Discount Management     | `http://localhost/petv10/discount_management` |

> Admin access requires a user account with `role = 'admin'` in the database.

---

## 🔒 Security

This project implements several security measures:

- **Password Hashing** — All passwords stored using PHP's `password_hash()` with `bcrypt`
- **CSRF Protection** — CSRF tokens with 1-hour expiry on sensitive forms
- **Session Management** — Named sessions with 30-minute timeout (`pawganic_session`)
- **Brute-Force Protection** — Account lockout after 5 failed login attempts (15-minute cooldown)
- **Directory Protection** — Sensitive directories (`config/`, `auth/`, `admin/`, `database/`) protected via `.htaccess`
- **Input Sanitization** — Database queries use prepared statements to prevent SQL injection
- **Error Logging** — Application errors logged to `config/logs/errors.log`

---

## 🤝 Contributing

1. Fork the repository
2. Create a new branch: `git checkout -b feature/your-feature-name`
3. Commit your changes: `git commit -m "feat: add your feature"`
4. Push to your branch: `git push origin feature/your-feature-name`
5. Open a Pull Request

Please follow [Conventional Commits](https://www.conventionalcommits.org/) for commit messages.

---

## 📄 License

This project is intended for educational and academic purposes. All rights reserved.

---

<div align="center">
  <sub>Built with ❤️ for pets and their humans · Pawganic Supplies &copy; 2026</sub>
</div>
