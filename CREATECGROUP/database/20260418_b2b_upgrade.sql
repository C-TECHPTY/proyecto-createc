USE `catalog_platform`;

CREATE TABLE IF NOT EXISTS `sellers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(80) NOT NULL DEFAULT '',
  `territory` VARCHAR(160) NOT NULL DEFAULT '',
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sellers_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(60) NOT NULL,
  `business_name` VARCHAR(190) NOT NULL,
  `contact_name` VARCHAR(160) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(80) NOT NULL DEFAULT '',
  `address_line` VARCHAR(255) NOT NULL DEFAULT '',
  `zone` VARCHAR(160) NOT NULL DEFAULT '',
  `city` VARCHAR(120) NOT NULL DEFAULT '',
  `country` VARCHAR(120) NOT NULL DEFAULT '',
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_code` (`code`),
  KEY `idx_clients_seller_id` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `catalog_users`
  ADD COLUMN IF NOT EXISTS `seller_id` BIGINT UNSIGNED DEFAULT NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME DEFAULT NULL AFTER `is_active`;

ALTER TABLE `catalog_users`
  MODIFY COLUMN `role` ENUM('admin','sales','billing','operator','vendor') NOT NULL DEFAULT 'admin';

ALTER TABLE `catalogs`
  ADD COLUMN IF NOT EXISTS `seller_id` BIGINT UNSIGNED DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `client_id` BIGINT UNSIGNED DEFAULT NULL AFTER `seller_id`,
  ADD COLUMN IF NOT EXISTS `hero_title` VARCHAR(255) NOT NULL DEFAULT '' AFTER `client_name`,
  ADD COLUMN IF NOT EXISTS `hero_subtitle` VARCHAR(255) NOT NULL DEFAULT '' AFTER `hero_title`,
  ADD COLUMN IF NOT EXISTS `promo_title` VARCHAR(255) NOT NULL DEFAULT '' AFTER `hero_subtitle`,
  ADD COLUMN IF NOT EXISTS `promo_text` VARCHAR(255) NOT NULL DEFAULT '' AFTER `promo_title`,
  ADD COLUMN IF NOT EXISTS `promo_image_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `promo_text`,
  ADD COLUMN IF NOT EXISTS `promo_video_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `promo_image_url`,
  ADD COLUMN IF NOT EXISTS `promo_link_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `promo_video_url`,
  ADD COLUMN IF NOT EXISTS `promo_link_label` VARCHAR(120) NOT NULL DEFAULT '' AFTER `promo_link_url`,
  ADD COLUMN IF NOT EXISTS `currency` VARCHAR(10) NOT NULL DEFAULT 'USD' AFTER `promo_link_label`,
  ADD COLUMN IF NOT EXISTS `legacy_pdf_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `currency`,
  ADD COLUMN IF NOT EXISTS `modern_pdf_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `legacy_pdf_url`;

ALTER TABLE `catalog_access_logs`
  ADD COLUMN IF NOT EXISTS `share_link_id` BIGINT UNSIGNED DEFAULT NULL AFTER `catalog_id`,
  ADD COLUMN IF NOT EXISTS `seller_id` BIGINT UNSIGNED DEFAULT NULL AFTER `share_link_id`,
  ADD COLUMN IF NOT EXISTS `client_id` BIGINT UNSIGNED DEFAULT NULL AFTER `seller_id`,
  ADD COLUMN IF NOT EXISTS `utm_source` VARCHAR(120) NOT NULL DEFAULT '' AFTER `referrer`,
  ADD COLUMN IF NOT EXISTS `utm_medium` VARCHAR(120) NOT NULL DEFAULT '' AFTER `utm_source`;

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `share_link_id` BIGINT UNSIGNED DEFAULT NULL AFTER `catalog_id`,
  ADD COLUMN IF NOT EXISTS `seller_id` BIGINT UNSIGNED DEFAULT NULL AFTER `share_link_id`,
  ADD COLUMN IF NOT EXISTS `client_id` BIGINT UNSIGNED DEFAULT NULL AFTER `seller_id`,
  ADD COLUMN IF NOT EXISTS `catalog_slug` VARCHAR(190) NOT NULL DEFAULT '' AFTER `order_number`,
  ADD COLUMN IF NOT EXISTS `company_name` VARCHAR(190) NOT NULL DEFAULT '' AFTER `catalog_slug`,
  ADD COLUMN IF NOT EXISTS `contact_name` VARCHAR(190) NOT NULL DEFAULT '' AFTER `company_name`,
  ADD COLUMN IF NOT EXISTS `contact_email` VARCHAR(190) NOT NULL DEFAULT '' AFTER `contact_name`,
  ADD COLUMN IF NOT EXISTS `contact_phone` VARCHAR(80) NOT NULL DEFAULT '' AFTER `contact_email`,
  ADD COLUMN IF NOT EXISTS `address_zone` VARCHAR(255) NOT NULL DEFAULT '' AFTER `contact_phone`,
  ADD COLUMN IF NOT EXISTS `admin_notes` TEXT NULL AFTER `comments`,
  ADD COLUMN IF NOT EXISTS `source_channel` ENUM('web','offline-sync','admin') NOT NULL DEFAULT 'web' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `export_csv_path` VARCHAR(255) NOT NULL DEFAULT '' AFTER `source_channel`,
  ADD COLUMN IF NOT EXISTS `export_xlsx_path` VARCHAR(255) NOT NULL DEFAULT '' AFTER `export_csv_path`,
  ADD COLUMN IF NOT EXISTS `export_generated_at` DATETIME DEFAULT NULL AFTER `export_xlsx_path`,
  ADD COLUMN IF NOT EXISTS `email_sent_at` DATETIME DEFAULT NULL AFTER `export_generated_at`,
  ADD COLUMN IF NOT EXISTS `email_status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending' AFTER `email_sent_at`;

UPDATE `orders`
SET
  `contact_name` = CASE WHEN `contact_name` = '' THEN `customer_name` ELSE `contact_name` END,
  `contact_email` = CASE WHEN `contact_email` = '' THEN `customer_email` ELSE `contact_email` END,
  `contact_phone` = CASE WHEN `contact_phone` = '' THEN `customer_phone` ELSE `contact_phone` END,
  `company_name` = CASE WHEN `company_name` = '' THEN `customer_name` ELSE `company_name` END;

ALTER TABLE `order_items`
  ADD COLUMN IF NOT EXISTS `sale_unit` VARCHAR(80) NOT NULL DEFAULT 'unidad' AFTER `quantity`,
  ADD COLUMN IF NOT EXISTS `package_label` VARCHAR(120) NOT NULL DEFAULT '' AFTER `sale_unit`,
  ADD COLUMN IF NOT EXISTS `package_qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00 AFTER `package_label`,
  ADD COLUMN IF NOT EXISTS `pieces_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `package_qty`,
  ADD COLUMN IF NOT EXISTS `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `pieces_total`;

UPDATE `order_items`
SET
  `unit_price` = CASE WHEN `unit_price` = 0 THEN `price` ELSE `unit_price` END,
  `pieces_total` = CASE WHEN `pieces_total` = 0 THEN `quantity` ELSE `pieces_total` END;

CREATE TABLE IF NOT EXISTS `catalog_share_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `token` CHAR(64) NOT NULL,
  `label` VARCHAR(190) NOT NULL DEFAULT '',
  `notes` TEXT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `open_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_opened_at` DATETIME DEFAULT NULL,
  `last_order_at` DATETIME DEFAULT NULL,
  `created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalog_share_links_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(40) NOT NULL DEFAULT '',
  `to_status` VARCHAR(40) NOT NULL,
  `changed_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_status_history_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `channel` ENUM('email') NOT NULL DEFAULT 'email',
  `recipient` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(255) NOT NULL DEFAULT '',
  `payload` TEXT NULL,
  `attachments_json` JSON NULL,
  `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `response_message` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `notifications_log`
  ADD COLUMN IF NOT EXISTS `attachments_json` JSON NULL AFTER `payload`;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(120) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL DEFAULT '',
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `context_json` JSON NULL,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('mail_sales', 'ventas@tuempresa.com'),
('mail_billing', 'facturacion@tuempresa.com'),
('mail_logistics', 'logistica@tuempresa.com'),
('mail_supervision', 'supervision@tuempresa.com'),
('mail_copy_seller', '1'),
('mail_copy_client', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
