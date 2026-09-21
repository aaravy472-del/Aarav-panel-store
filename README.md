# Hasi Panel Store — PHP/cPanel Installation Guide

## Step 1: Upload Files
1. Upload the entire `php/` folder contents to your cPanel `public_html/` directory.
2. Your structure should look like:
   ```
   public_html/
   ├── index.php
   ├── .htaccess
   ├── api/
   │   └── api.php
   ├── includes/
   │   ├── config.php
   │   ├── db.php
   │   └── helpers.php
   ├── assets/
   │   ├── css/style.css
   │   └── js/app.js
   ├── uploads/
   └── install.sql
   ```

## Step 2: Create MySQL Database
1. In cPanel, go to **MySQL Databases**.
2. Create a new database (e.g., `youruser_hasi`).
3. Create a database user and assign it to the database with **ALL PRIVILEGES**.

## Step 3: Import Database Schema
1. Go to **phpMyAdmin** in cPanel.
2. Select your database.
3. Click **Import** tab.
4. Upload the `install.sql` file and click **Go**.

## Step 4: Configure Database Connection
1. Edit `includes/config.php` with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'youruser_hasi');      // your database name
   define('DB_USER', 'youruser_dbuser');    // your database user
   define('DB_PASS', 'your_password_here'); // your database password
   ```
2. Change the `JWT_SECRET` to a random string (at least 32 characters).

## Step 5: Set Upload Permissions
1. Make sure the `uploads/` directory is writable (chmod 755 or 775).

## Step 6: Default Admin Login
- Email: `admin@hasi.local`
- Password: `admin123`
- **CHANGE THIS IMMEDIATELY** after first login!
  - Go to `#admin` → Settings → Change Password

## Step 7: Access Your Site
- Store front: `https://yourdomain.com/`
- Admin panel: `https://yourdomain.com/#admin`
- Login page: `https://yourdomain.com/#login`

## Features Included
- User registration and login (email/password)
- Product store with categories and search
- Instant key delivery after purchase
- Balance system with deposit requests
- Multiple payment methods (admin configurable)
- Admin dashboard with stats
- Panel management (CRUD with drag-and-drop reorder)
- Stock key management (bulk inject, edit, delete)
- Delivery history with search
- Promotions/banners system
- User management (create, edit, delete, set discounts)
- Secondary admin system with per-feature permissions
- Site branding (name, logo, favicon, OG image)
- 8 theme system (Midnight, Graphite, Sapphire, Emerald, Amber, Rose, Teal, Ivory)
- Exchange rate management (USDT, PKR, INR, BDT, LKR, NPR, BRL)
- Currency switcher for users
- Maintenance mode with countdown
- Live chat widget integration
- Support channels (Telegram, WhatsApp, Instagram, YouTube)
- API provider management (external reseller APIs)
- API analytics (sales, profit, orders per provider)
- Referral system with bonus
- Welcome message after login
- Global discount system
- Signup bonus system

## Important Notes
- This is a completely separate project from your original React/Supabase app.
- The original database is untouched — this uses its own MySQL database.
- All data is stored in MySQL on your cPanel hosting.
- No external dependencies — pure PHP + vanilla JavaScript.
- Works on any cPanel hosting with PHP 7.4+ and MySQL 5.7+.
