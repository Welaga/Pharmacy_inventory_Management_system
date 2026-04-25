# 💊 PharmaCare IMS — Pharmacy Inventory Management System

A complete, production-ready Pharmacy Inventory Management System built with **Core PHP + PDO** and **MySQL**, featuring a modern Bootstrap 5 UI.

---

## 📋 Table of Contents

- [Features](#features)
- [Screenshots](#screenshots)
- [Requirements](#requirements)
- [Installation](#installation)
- [Default Credentials](#default-credentials)
- [Project Structure](#project-structure)
- [Security Measures](#security-measures)
- [Database Schema](#database-schema)

---

## ✅ Features

### Authentication & Access Control
- Secure login with bcrypt password hashing
- Role-based access: **Admin** (full access) + **Pharmacist** (sales & view)
- CSRF token protection on every form
- Session management with `httponly` + `SameSite` cookies

### 📊 Dashboard
- Real-time stats: total medicines, low stock, expired, expiring soon
- Daily / weekly / monthly revenue cards
- 7-day sales trend bar chart (Chart.js)
- Top selling medicines this month
- Low stock alert table
- Expiry alert table

### 💊 Medicine Management
- Full CRUD (Create / Read / Update / Delete)
- Fields: name, category, batch number, expiry date, purchase price, selling price, quantity, low stock threshold, supplier, barcode, description
- Filter by category, stock status, expiry status
- Paginated search results
- Stock & expiry status badges

### 🏭 Supplier Management
- Full CRUD for suppliers
- Linked medicine count per supplier
- Active / Inactive status control
- Safe delete (blocked if linked medicines exist)

### 🛒 POS (Point of Sale)
- Visual medicine grid with real-time search & category filter
- Click-to-add shopping cart with quantity controls
- Automatic subtotal, discount, total & change calculation
- Cash / Card / Mobile payment modes
- Atomic stock deduction inside a DB transaction
- Printable receipt on completion

### 📦 Sales History
- Full list with date range, customer, cashier, status filters
- Revenue summary cards for filtered period
- Cancel sale (admin only)
- Link to printable receipt

### 📈 Reports
- **Sales Report** — daily / weekly / monthly revenue trend chart + table
- **Inventory Report** — full stock with value, status badges
- **Expired Medicines** — expired stock with estimated loss value
- **Top Medicines** — best sellers by quantity with bar chart
- **CSV Export** for all report types

### 👥 User Management (Admin)
- Create / edit / delete system users
- Assign Admin or Pharmacist role
- Activate / deactivate accounts
- Password change on edit

### 📋 Audit Logs (Admin)
- Every create, update, delete, login, logout is logged
- View old vs. new values in modal
- Filter by user, action, date range
- IP address tracking

---

## 🖥️ Requirements

| Component  | Version       |
|------------|--------------|
| PHP        | 7.4 or 8.x   |
| MySQL      | 5.7+ / MariaDB 10.3+ |
| Web Server | Apache (with `mod_rewrite`) or Nginx |
| Extensions | `pdo_mysql`, `session`, `json` |

> Works out of the box with **XAMPP**, **WAMP**, **MAMP**, or **Laragon**.

---

## 🚀 Installation

### Step 1 — Clone / Download

```bash
git clone https://github.com/your-repo/pharmacare-ims.git
# or extract the ZIP into your web root
```

Place the project folder inside:
- **XAMPP** → `C:/xampp/htdocs/pharmacy/`
- **Linux** → `/var/www/html/pharmacy/`

---

### Step 2 — Create the Database

Open **phpMyAdmin** (or MySQL CLI) and run:

```sql
SOURCE /path/to/pharmacy/setup.sql;
```

Or copy-paste the contents of `setup.sql` into phpMyAdmin's SQL tab.

This creates `pharmacy_db` with all tables and **seed data** including demo users and 15 sample medicines.

---

### Step 3 — Configure Database Connection

Edit `config/database.php` (or set environment variables):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password
define('DB_NAME', 'pharmacy_db');
```

---

### Step 4 — Open in Browser

```
http://localhost/pharmacy/
```

---

## 🔑 Default Credentials

| Role        | Email                  | Password    |
|-------------|------------------------|-------------|
| Admin       | admin@pharmacy.com     | `admin123`  |
| Pharmacist  | jane@pharmacy.com      | `pharma123` |

> ⚠️ **Change these passwords immediately after first login in production!**

---

## 📁 Project Structure

```
pharmacy/
├── index.php                  ← Login page
├── logout.php
├── dashboard.php              ← Main dashboard
├── setup.sql                  ← Database schema + seed data
├── .htaccess                  ← Security headers & rules
├── README.md
│
├── config/
│   └── database.php           ← DB connection (PDO singleton)
│
├── includes/
│   ├── auth.php               ← Auth helpers, CSRF, audit log
│   ├── functions.php          ← Helpers: format, paginate, stats
│   ├── header.php             ← Sidebar + top bar layout
│   └── footer.php             ← Bootstrap JS + scripts
│
├── medicines/
│   ├── index.php              ← List, search, filter
│   ├── add.php                ← Add new medicine
│   ├── edit.php               ← Edit medicine
│   └── delete.php             ← Delete handler
│
├── suppliers/
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
│
├── sales/
│   ├── pos.php                ← Point of Sale interface
│   ├── receipt.php            ← Printable receipt
│   ├── index.php              ← Sales history
│   └── cancel.php             ← Cancel sale handler
│
├── reports/
│   └── index.php              ← All reports + CSV export
│
├── users/
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   └── delete.php
│
├── audit_logs/
│   └── index.php
│
└── api/
    ├── medicines.php          ← JSON search endpoint
    └── stats.php              ← Dashboard stats JSON
```

---

## 🔒 Security Measures

| Measure | Implementation |
|---------|---------------|
| SQL Injection | PDO prepared statements everywhere |
| CSRF Protection | Per-session token validated on every POST |
| XSS Prevention | `htmlspecialchars()` on all output via `sanitize()` |
| Password Storage | `password_hash()` with `PASSWORD_BCRYPT` |
| Session Security | `httponly`, `SameSite=Strict`, `session_regenerate_id()` |
| Access Control | `requireLogin()` / `requireAdmin()` guards on every page |
| Input Validation | Server-side validation before every DB write |
| File Access | `.htaccess` blocks direct access to `config/` and `includes/` |
| Audit Trail | All write operations logged with user, IP, old/new values |

---

## 🗄️ Database Schema

```
users            — id, name, email, password (bcrypt), role, status
categories       — id, name, description
suppliers        — id, name, contact, email, address, status
medicines        — id, name, category_id→, expiry_date, prices, qty, supplier_id→
sales            — id, user_id→, customer_name, total, discount, paid, payment_method, status
sale_items       — id, sale_id→, medicine_id→, quantity, unit_price, total_price
audit_logs       — id, user_id→, action, table_name, record_id, old/new values, ip
```

All foreign keys are enforced at the database level with appropriate `ON DELETE` rules.

---

## 🎨 UI Highlights

- **Bootstrap 5.3** responsive grid
- **Bootstrap Icons** icon set
- **Inter** font family
- Dark sidebar with active-state highlighting
- Stat cards with color-coded icon boxes
- Responsive tables with hover states
- Flash messages with auto-dismiss
- Confirmation dialogs for destructive actions
- Fully printable receipt page
- Mobile sidebar toggle

---

## 📄 License

MIT — free to use and modify for personal and commercial projects.
#   P h a r m a c y _ i n v e n t o r y _ M a n a g e m e n t _ s y s t e m  
 