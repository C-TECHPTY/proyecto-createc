CREATE DATABASE IF NOT EXISTS `catalog_platform`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `catalog_platform`;

CREATE TABLE IF NOT EXISTS `sellers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(60) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(80) NOT NULL DEFAULT '',
  `territory` VARCHAR(160) NOT NULL DEFAULT '',
  `public_token` CHAR(64) DEFAULT NULL,
  `photo_path` VARCHAR(255) NOT NULL DEFAULT '',
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sellers_code` (`code`),
  UNIQUE KEY `uq_sellers_public_token` (`public_token`),
  KEY `idx_sellers_active` (`is_active`),
  KEY `idx_sellers_name` (`name`)
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
  KEY `idx_clients_seller_id` (`seller_id`),
  KEY `idx_clients_business_name` (`business_name`),
  CONSTRAINT `fk_clients_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(140) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `role` ENUM('admin','sales','billing','operator','vendor') NOT NULL DEFAULT 'admin',
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalog_users_username` (`username`),
  KEY `idx_catalog_users_role` (`role`),
  KEY `idx_catalog_users_seller_id` (`seller_id`),
  CONSTRAINT `fk_catalog_users_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalogs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `template` VARCHAR(80) NOT NULL DEFAULT 'b2b-modern',
  `public_url` VARCHAR(255) NOT NULL DEFAULT '',
  `pdf_url` VARCHAR(255) NOT NULL DEFAULT '',
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME DEFAULT NULL,
  `status` ENUM('draft','active','expired','archived') NOT NULL DEFAULT 'active',
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_name` VARCHAR(140) NOT NULL DEFAULT '',
  `client_name` VARCHAR(190) NOT NULL DEFAULT '',
  `hero_title` VARCHAR(255) NOT NULL DEFAULT '',
  `hero_subtitle` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_title` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_text` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_image_url` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_video_url` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_link_url` VARCHAR(255) NOT NULL DEFAULT '',
  `promo_link_label` VARCHAR(120) NOT NULL DEFAULT '',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `legacy_pdf_url` VARCHAR(255) NOT NULL DEFAULT '',
  `modern_pdf_url` VARCHAR(255) NOT NULL DEFAULT '',
  `notes` TEXT NULL,
  `catalog_json_path` VARCHAR(255) NOT NULL DEFAULT '',
  `api_payload` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalogs_slug` (`slug`),
  KEY `idx_catalogs_status` (`status`),
  KEY `idx_catalogs_expires_at` (`expires_at`),
  KEY `idx_catalogs_seller_id` (`seller_id`),
  KEY `idx_catalogs_client_id` (`client_id`),
  CONSTRAINT `fk_catalogs_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalogs_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  UNIQUE KEY `uq_catalog_share_links_token` (`token`),
  KEY `idx_catalog_share_links_catalog_id` (`catalog_id`),
  KEY `idx_catalog_share_links_seller_id` (`seller_id`),
  KEY `idx_catalog_share_links_client_id` (`client_id`),
  KEY `idx_catalog_share_links_active` (`is_active`, `expires_at`),
  CONSTRAINT `fk_catalog_share_links_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_share_links_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_share_links_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_share_links_created_by_user_id`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `catalog_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_access_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `share_link_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `referrer` VARCHAR(255) NOT NULL DEFAULT '',
  `utm_source` VARCHAR(120) NOT NULL DEFAULT '',
  `utm_medium` VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_catalog_access_logs_catalog_id` (`catalog_id`),
  KEY `idx_catalog_access_logs_share_link_id` (`share_link_id`),
  KEY `idx_catalog_access_logs_visited_at` (`visited_at`),
  CONSTRAINT `fk_catalog_access_logs_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_access_logs_share_link_id`
    FOREIGN KEY (`share_link_id`) REFERENCES `catalog_share_links` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_access_logs_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_access_logs_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_behavior_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `share_link_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `item_code` VARCHAR(120) NOT NULL DEFAULT '',
  `item_name` VARCHAR(255) NOT NULL DEFAULT '',
  `category` VARCHAR(160) NOT NULL DEFAULT '',
  `search_term` VARCHAR(190) NOT NULL DEFAULT '',
  `quantity` DECIMAL(12,2) DEFAULT NULL,
  `value_amount` DECIMAL(14,2) DEFAULT NULL,
  `session_id` VARCHAR(80) NOT NULL DEFAULT '',
  `visitor_id` VARCHAR(80) NOT NULL DEFAULT '',
  `metadata_json` JSON NULL,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_catalog_behavior_catalog_id` (`catalog_id`),
  KEY `idx_catalog_behavior_share_link_id` (`share_link_id`),
  KEY `idx_catalog_behavior_seller_id` (`seller_id`),
  KEY `idx_catalog_behavior_client_id` (`client_id`),
  KEY `idx_catalog_behavior_event_type` (`event_type`),
  KEY `idx_catalog_behavior_item_code` (`item_code`),
  KEY `idx_catalog_behavior_created_at` (`created_at`),
  KEY `idx_catalog_behavior_session_id` (`session_id`),
  CONSTRAINT `fk_catalog_behavior_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_behavior_share_link_id`
    FOREIGN KEY (`share_link_id`) REFERENCES `catalog_share_links` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_behavior_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_behavior_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `share_link_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_token` CHAR(64) NOT NULL DEFAULT '',
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `order_number` VARCHAR(60) NOT NULL,
  `catalog_slug` VARCHAR(190) NOT NULL DEFAULT '',
  `company_name` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_name` VARCHAR(190) NOT NULL,
  `contact_email` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_phone` VARCHAR(80) NOT NULL DEFAULT '',
  `address_zone` VARCHAR(255) NOT NULL DEFAULT '',
  `seller_name` VARCHAR(140) NOT NULL DEFAULT '',
  `comments` TEXT NULL,
  `admin_notes` TEXT NULL,
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `status` ENUM('new','pendiente','confirmado','reviewed','processing','invoiced','completed','cancelled','anulado','archivado') NOT NULL DEFAULT 'new',
  `is_test` TINYINT(1) NOT NULL DEFAULT 0,
  `source_channel` ENUM('web','offline-sync','admin') NOT NULL DEFAULT 'web',
  `export_csv_path` VARCHAR(255) NOT NULL DEFAULT '',
  `export_xlsx_path` VARCHAR(255) NOT NULL DEFAULT '',
  `export_generated_at` DATETIME DEFAULT NULL,
  `email_sent_at` DATETIME DEFAULT NULL,
  `email_status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` BIGINT UNSIGNED DEFAULT NULL,
  `customer_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
  `confirmed_at` DATETIME DEFAULT NULL,
  `customer_ip` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_number` (`order_number`),
  KEY `idx_orders_catalog_id` (`catalog_id`),
  KEY `idx_orders_share_link_id` (`share_link_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_is_test` (`is_test`),
  KEY `idx_orders_deleted_at` (`deleted_at`),
  KEY `idx_orders_customer_confirmed` (`customer_confirmed`),
  KEY `idx_orders_created_at` (`created_at`),
  KEY `idx_orders_seller_id` (`seller_id`),
  KEY `idx_orders_seller_token` (`seller_token`),
  KEY `idx_orders_client_id` (`client_id`),
  CONSTRAINT `fk_orders_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_orders_share_link_id`
    FOREIGN KEY (`share_link_id`) REFERENCES `catalog_share_links` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_orders_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_orders_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `item_code` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_unit` VARCHAR(80) NOT NULL DEFAULT 'unidad',
  `package_label` VARCHAR(120) NOT NULL DEFAULT '',
  `package_qty` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `pieces_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_item_code` (`item_code`),
  CONSTRAINT `fk_order_items_order_id`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE
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
  KEY `idx_order_status_history_order_id` (`order_id`),
  KEY `idx_order_status_history_created_at` (`created_at`),
  CONSTRAINT `fk_order_status_history_order_id`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_order_status_history_changed_by_user_id`
    FOREIGN KEY (`changed_by_user_id`) REFERENCES `catalog_users` (`id`)
    ON DELETE SET NULL
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
  PRIMARY KEY (`id`),
  KEY `idx_notifications_log_order_id` (`order_id`),
  KEY `idx_notifications_log_status` (`status`),
  CONSTRAINT `fk_notifications_log_order_id`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_activity_logs_user_id` (`user_id`),
  KEY `idx_activity_logs_action` (`action`),
  KEY `idx_activity_logs_created_at` (`created_at`),
  CONSTRAINT `fk_activity_logs_user_id`
    FOREIGN KEY (`user_id`) REFERENCES `catalog_users` (`id`)
    ON DELETE SET NULL
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
('order_admin_emails', ''),
('mail_copy_seller', '1'),
('mail_copy_client', '1'),
('branding_company_name', 'Catalogo Rodeo B2B'),
('branding_login_title', 'Catalogo Rodeo B2B'),
('branding_login_subtitle', 'Administracion comercial, vendedores, links y pedidos trazables.'),
('branding_company_logo', ''),
('branding_login_logo', ''),
('branding_login_background', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `catalog_users` (`username`, `password_hash`, `full_name`, `email`, `role`)
VALUES (
  'admin',
  '$2y$10$.HF3V1wbw.B8D.CDNARf/u2Sk5nhrgyw8ewHXKxnQx2d9WWmbk8Gm',
  'Administrador catalogos',
  'admin@tuempresa.com',
  'admin'
)
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `email` = VALUES(`email`),
  `role` = VALUES(`role`);
