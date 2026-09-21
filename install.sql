-- ============================================================
-- Hasi Panel Store — MySQL Schema for cPanel Hosting
-- Run this in phpMyAdmin or via MySQL CLI after creating the database
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- USERS (replaces Supabase auth.users)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `whatsapp_number` VARCHAR(20) DEFAULT NULL,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_secondary_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `referred_by` VARCHAR(50) DEFAULT NULL,
  `referral_code` VARCHAR(50) NOT NULL UNIQUE,
  `avatar_url` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('active','banned') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_referral` (`referral_code`),
  INDEX `idx_referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SECONDARY ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `secondary_admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `expiry_date` DATE NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `permissions` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PANELS (products)
-- ============================================================
CREATE TABLE IF NOT EXISTS `panels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `telegram` VARCHAR(500) DEFAULT NULL,
  `video` VARCHAR(500) DEFAULT NULL,
  `demo_video` VARCHAR(500) DEFAULT NULL,
  `setup_video` VARCHAR(500) DEFAULT NULL,
  `thumbnail_url` VARCHAR(500) DEFAULT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `update_file_2` VARCHAR(500) DEFAULT NULL,
  `demo_button_text` VARCHAR(100) DEFAULT 'Demo Video',
  `setup_button_text` VARCHAR(100) DEFAULT 'Setup Video',
  `update_file_1_text` VARCHAR(100) DEFAULT 'Update File 1',
  `update_file_2_text` VARCHAR(100) DEFAULT 'Update File 2',
  `premium_title` VARCHAR(255) DEFAULT NULL,
  `premium_description` TEXT DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `plans` JSON DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `price_currency` VARCHAR(10) NOT NULL DEFAULT 'PKR',
  `api_provider_id` INT DEFAULT NULL,
  `api_service_id` VARCHAR(255) DEFAULT NULL,
  `profit_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `badge_text` VARCHAR(100) DEFAULT 'Premium Quality',
  `badge_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- KEYS POOL (stock keys)
-- ============================================================
CREATE TABLE IF NOT EXISTS `keys_pool` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `panel_id` INT NOT NULL,
  `pool_type` VARCHAR(50) NOT NULL,
  `key_value` TEXT NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`panel_id`) REFERENCES `panels`(`id`) ON DELETE CASCADE,
  INDEX `idx_panel_pool` (`panel_id`, `pool_type`, `is_used`),
  INDEX `idx_pool_type` (`pool_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PURCHASES (delivered orders)
-- ============================================================
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `panel_id` INT DEFAULT NULL,
  `panel_name` VARCHAR(255) DEFAULT NULL,
  `plan` VARCHAR(100) DEFAULT NULL,
  `label` VARCHAR(255) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `key_value` TEXT DEFAULT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `duration_days` INT DEFAULT NULL,
  `duration_hours` INT DEFAULT NULL,
  `expiry_date` DATETIME DEFAULT NULL,
  `hidden_from_user` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_panel` (`panel_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENTS (add-funds requests)
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USDT',
  `utr` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `account_holder` VARCHAR(255) DEFAULT NULL,
  `payment_method` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENT METHODS
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `logo` VARCHAR(500) DEFAULT NULL,
  `account_number` VARCHAR(255) DEFAULT NULL,
  `account_holder` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `qr_image` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USDT',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAYMENT SETTINGS (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `payment_settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `upi_id` VARCHAR(255) DEFAULT NULL,
  `qr_image` VARCHAR(500) DEFAULT NULL,
  `gateway_api_key` VARCHAR(500) DEFAULT NULL,
  `gateway_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `binance_pay_name` VARCHAR(100) DEFAULT 'Binance Pay',
  `binance_btn1_label` VARCHAR(100) DEFAULT 'Deposit $1',
  `binance_btn1_amount` DECIMAL(10,2) DEFAULT 1.00,
  `binance_btn1_link` VARCHAR(500) DEFAULT NULL,
  `binance_btn2_label` VARCHAR(100) DEFAULT 'Deposit $3',
  `binance_btn2_amount` DECIMAL(10,2) DEFAULT 3.00,
  `binance_btn2_link` VARCHAR(500) DEFAULT NULL,
  `binance_btn3_label` VARCHAR(100) DEFAULT 'Deposit $10',
  `binance_btn3_amount` DECIMAL(10,2) DEFAULT 10.00,
  `binance_btn3_link` VARCHAR(500) DEFAULT NULL,
  `binance_pay_visible` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROMOTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `promotions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(500) DEFAULT NULL,
  `link` VARCHAR(500) DEFAULT NULL,
  `button_text` VARCHAR(100) DEFAULT NULL,
  `button_link` VARCHAR(500) DEFAULT NULL,
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SUPPORT LINKS (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `support_links` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `telegram_login` VARCHAR(500) DEFAULT NULL,
  `telegram_dashboard` VARCHAR(500) DEFAULT NULL,
  `telegram_channel_floating` VARCHAR(500) DEFAULT NULL,
  `whatsapp` VARCHAR(500) DEFAULT NULL,
  `instagram` VARCHAR(500) DEFAULT NULL,
  `facebook` VARCHAR(500) DEFAULT NULL,
  `youtube` VARCHAR(500) DEFAULT NULL,
  `wa_support_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `wa_support_number` VARCHAR(20) DEFAULT NULL,
  `wa_support_icon` VARCHAR(500) DEFAULT NULL,
  `wa_support_message` VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- APP CONTROLS (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `app_controls` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `support_button_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `footer_icons_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `theme_switcher_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `developer_credit_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `developer_credit_text` VARCHAR(255) DEFAULT 'Developed by Hasi',
  `developer_credit_url` VARCHAR(500) DEFAULT 'https://whatsapp.com/channel/0029VaxTIEH1CYoSV4sZmq37',
  `floating_channel_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `floating_whatsapp_link` VARCHAR(500) DEFAULT NULL,
  `floating_whatsapp_number` VARCHAR(20) DEFAULT NULL,
  `floating_whatsapp_message` VARCHAR(500) DEFAULT NULL,
  `default_theme` VARCHAR(50) DEFAULT 'midnight',
  `user_profile_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `user_balance_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `referral_bonus_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `signup_bonus_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `signup_bonus_currency` VARCHAR(10) DEFAULT 'PKR',
  `welcome_message_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `welcome_message_line1` VARCHAR(255) DEFAULT 'Welcome back!',
  `welcome_message_line2` VARCHAR(255) DEFAULT 'Enjoy your premium panel keys.',
  `welcome_message_duration` INT DEFAULT 3500,
  `global_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `global_discount_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `maintenance_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `maintenance_ends_at` DATETIME DEFAULT NULL,
  `currency_switcher_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SITE BRANDING (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_branding` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `site_name` VARCHAR(255) DEFAULT 'Hasi',
  `site_title` VARCHAR(255) DEFAULT 'Hasi — Premium Panel Keys',
  `site_description` TEXT DEFAULT 'Buy premium panel license keys instantly. Secure checkout, instant delivery, 24/7 customer support.',
  `favicon_url` VARCHAR(500) DEFAULT NULL,
  `logo_url` VARCHAR(500) DEFAULT NULL,
  `og_image_url` VARCHAR(500) DEFAULT NULL,
  `site_url` VARCHAR(500) DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- EXCHANGE RATES (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `usdt_to_pkr` DECIMAL(10,2) NOT NULL DEFAULT 280.00,
  `usdt_to_inr` DECIMAL(10,2) NOT NULL DEFAULT 83.00,
  `usdt_to_bdt` DECIMAL(10,2) NOT NULL DEFAULT 120.00,
  `usdt_to_lkr` DECIMAL(10,2) NOT NULL DEFAULT 300.00,
  `usdt_to_npr` DECIMAL(10,2) NOT NULL DEFAULT 133.00,
  `usdt_to_brl` DECIMAL(10,2) NOT NULL DEFAULT 5.50,
  `usdt_to_usdt` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PANEL CATEGORIES (junction)
-- ============================================================
CREATE TABLE IF NOT EXISTS `panel_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `panel_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`panel_id`) REFERENCES `panels`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_panel_cat` (`panel_id`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAN DURATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `plan_durations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `value_hours` INT NOT NULL DEFAULT 0,
  `default_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_dur_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API PROVIDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_providers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `api_url` VARCHAR(500) NOT NULL,
  `api_key` VARCHAR(500) NOT NULL,
  `auth_header_name` VARCHAR(100) DEFAULT 'X-API-Token',
  `profit_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_synced_at` DATETIME DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `auto_sync_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `auto_sync_interval_minutes` INT NOT NULL DEFAULT 20,
  `last_auto_sync_at` DATETIME DEFAULT NULL,
  `auto_import_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API SERVICES
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `api_service_id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category` VARCHAR(255) DEFAULT NULL,
  `api_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `sell_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `panel_id` INT DEFAULT NULL,
  `removed_from_api` TINYINT(1) NOT NULL DEFAULT 0,
  `last_synced_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `api_providers`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_prov_svc` (`provider_id`, `api_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider_id` INT NOT NULL,
  `service_id` INT DEFAULT NULL,
  `purchase_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `api_order_id` VARCHAR(255) DEFAULT NULL,
  `api_key_value` TEXT DEFAULT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `unit_sell` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `profit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`provider_id`) REFERENCES `api_providers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LIVE CHAT (single row, id=1)
-- ============================================================
CREATE TABLE IF NOT EXISTS `live_chat` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `embed_code` TEXT DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT DATA INSERTS
-- ============================================================

INSERT INTO `app_controls` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;
INSERT INTO `site_branding` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;
INSERT INTO `exchange_rates` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;
INSERT INTO `payment_settings` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;
INSERT INTO `support_links` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;
INSERT INTO `live_chat` (`id`) VALUES (1) ON DUPLICATE KEY UPDATE `id` = 1;

-- Default plan durations
INSERT INTO `plan_durations` (`key`, `label`, `value_hours`, `default_price`, `is_default`, `sort_order`) VALUES
('1_hour', '1 Hour', 1, 50, 1, 1),
('3_hour', '3 Hour', 3, 80, 1, 2),
('6_hour', '6 Hour', 6, 120, 1, 3),
('12_hour', '12 Hour', 12, 180, 1, 4),
('1_day', '1 Day', 24, 250, 1, 5),
('3_days', '3 Days', 72, 500, 1, 6),
('7_days', '7 Days', 168, 800, 1, 7),
('15_days', '15 Days', 360, 1200, 1, 8),
('30_days', '30 Days', 720, 2000, 1, 9)
ON DUPLICATE KEY UPDATE `key` = VALUES(`key`);

-- ============================================================
-- DEFAULT ADMIN USER
-- Email: admin@hasi.local  Password: admin123
-- CHANGE THIS PASSWORD IMMEDIATELY AFTER INSTALL!
-- ============================================================
INSERT INTO `users` (`email`, `password_hash`, `is_admin`, `referral_code`) VALUES
('admin@hasi.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lC6gt7Wvz6QMk02nM0a', 1, 'ADMIN001')
ON DUPLICATE KEY UPDATE `email` = `email`;

SET FOREIGN_KEY_CHECKS = 1;
