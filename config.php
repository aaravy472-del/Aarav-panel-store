<?php
// ============================================================
// Hasi Panel Store — Configuration File
// Edit these values for your cPanel hosting environment
// ============================================================

// ── Database Configuration ──────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'hasi_panel');
define('DB_USER', 'hasi_user');
define('DB_PASS', 'change_this_password');
define('DB_CHARSET', 'utf8mb4');

// ── Site Configuration ────────────────────────────────────
define('SITE_NAME', 'Hasi');
define('SITE_TITLE', 'Hasi — Premium Panel Keys');
define('SITE_DESCRIPTION', 'Buy premium panel license keys instantly. Secure checkout, instant delivery, 24/7 customer support.');
define('SITE_URL', ''); // e.g. https://yourdomain.com — leave empty to auto-detect

// ── Security ───────────────────────────────────────────────
define('JWT_SECRET', 'CHANGE_THIS_TO_A_RANDOM_STRING_AT_LEAST_32_CHARS');
define('SESSION_LIFETIME', 86400 * 7); // 7 days
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');
define('MAX_UPLOAD_SIZE', 4 * 1024 * 1024); // 4MB

// ── API Providers (external reseller APIs) ────────────────
define('API_PROXY_TIMEOUT', 20); // seconds

// ── Pool Types ────────────────────────────────────────────
$GLOBALS['POOL_TYPES'] = ['1_hour','3_hour','6_hour','12_hour','1_day','3_days','7_days','15_days','30_days'];
$GLOBALS['POOL_LABELS'] = [
  '1_hour' => '1 Hour',
  '3_hour' => '3 Hour',
  '6_hour' => '6 Hour',
  '12_hour' => '12 Hour',
  '1_day' => '1 Day',
  '3_days' => '3 Days',
  '7_days' => '7 Days',
  '15_days' => '15 Days',
  '30_days' => '30 Days',
];

// ── Admin Features ────────────────────────────────────────
$GLOBALS['ADMIN_FEATURES'] = [
  ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'View admin dashboard & stats'],
  ['key' => 'panels', 'label' => 'Manage Panels', 'description' => 'Create / edit / delete store panels'],
  ['key' => 'manage-keys', 'label' => 'Manage Keys', 'description' => 'Add / delete keys in the stock pool'],
  ['key' => 'key-delivery', 'label' => 'Key Delivery', 'description' => 'View all delivered orders'],
  ['key' => 'promotions', 'label' => 'Promotions', 'description' => 'Manage promotional banners'],
  ['key' => 'payments', 'label' => 'Payments', 'description' => 'Approve / reject add-funds requests'],
  ['key' => 'users', 'label' => 'Users', 'description' => 'View users & update balances'],
  ['key' => 'tg-config', 'label' => 'Support Channels', 'description' => 'Edit customer support social links'],
  ['key' => 'settings', 'label' => 'Settings', 'description' => 'Edit payment UPI / QR / gateway key'],
  ['key' => 'payment-methods', 'label' => 'Payment Methods', 'description' => 'Manage payment methods'],
  ['key' => 'live-chat', 'label' => 'Live Chat', 'description' => 'Manage the live chat widget'],
  ['key' => 'api-management', 'label' => 'API Management', 'description' => 'Manage external API providers'],
  ['key' => 'api-analytics', 'label' => 'API Analytics', 'description' => 'View API sales and order analytics'],
];

// ── Error Reporting ────────────────────────────────────────
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
