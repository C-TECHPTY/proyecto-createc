-- Ejecuta este archivo desde phpMyAdmin despues de seleccionar la base de datos real
-- del hosting en el panel izquierdo. No usamos USE aqui porque en cPanel el nombre
-- de la base cambia segun la cuenta, por ejemplo usuario_catalogs.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
  IN p_table VARCHAR(64),
  IN p_column VARCHAR(64),
  IN p_definition TEXT,
  IN p_after VARCHAR(64)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table
      AND COLUMN_NAME = p_column
  ) THEN
    SET @after_sql = '';
    IF p_after IS NOT NULL AND p_after <> '' AND EXISTS (
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = p_table
        AND COLUMN_NAME = p_after
    ) THEN
      SET @after_sql = CONCAT(' AFTER `', p_after, '`');
    END IF;

    SET @ddl = CONCAT(
      'ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition,
      @after_sql
    );
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

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
  UNIQUE KEY `uq_clients_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('catalog_users', 'seller_id', 'BIGINT UNSIGNED DEFAULT NULL', 'role');
CALL add_column_if_missing('catalog_users', 'last_login_at', 'DATETIME DEFAULT NULL', 'is_active');
ALTER TABLE `catalog_users`
  MODIFY COLUMN `role` ENUM('admin','sales','billing','operator','vendor') NOT NULL DEFAULT 'admin';

CALL add_column_if_missing('catalogs', 'seller_id', 'BIGINT UNSIGNED DEFAULT NULL', 'status');
CALL add_column_if_missing('catalogs', 'client_id', 'BIGINT UNSIGNED DEFAULT NULL', 'seller_id');
CALL add_column_if_missing('catalogs', 'hero_title', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'client_name');
CALL add_column_if_missing('catalogs', 'hero_subtitle', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'hero_title');
CALL add_column_if_missing('catalogs', 'promo_title', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'hero_subtitle');
CALL add_column_if_missing('catalogs', 'promo_text', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'promo_title');
CALL add_column_if_missing('catalogs', 'promo_image_url', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'promo_text');
CALL add_column_if_missing('catalogs', 'promo_video_url', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'promo_image_url');
CALL add_column_if_missing('catalogs', 'promo_link_url', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'promo_video_url');
CALL add_column_if_missing('catalogs', 'promo_link_label', 'VARCHAR(120) NOT NULL DEFAULT ''''', 'promo_link_url');
CALL add_column_if_missing('catalogs', 'currency', 'VARCHAR(10) NOT NULL DEFAULT ''USD''', 'promo_link_label');
CALL add_column_if_missing('catalogs', 'legacy_pdf_url', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'currency');
CALL add_column_if_missing('catalogs', 'modern_pdf_url', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'legacy_pdf_url');
CALL add_column_if_missing('catalogs', 'catalog_json_path', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'notes');

CALL add_column_if_missing('orders', 'share_link_id', 'BIGINT UNSIGNED DEFAULT NULL', 'catalog_id');
CALL add_column_if_missing('orders', 'seller_id', 'BIGINT UNSIGNED DEFAULT NULL', 'share_link_id');
CALL add_column_if_missing('orders', 'client_id', 'BIGINT UNSIGNED DEFAULT NULL', 'seller_id');
CALL add_column_if_missing('orders', 'catalog_slug', 'VARCHAR(190) NOT NULL DEFAULT ''''', 'order_number');
CALL add_column_if_missing('orders', 'company_name', 'VARCHAR(190) NOT NULL DEFAULT ''''', 'catalog_slug');
CALL add_column_if_missing('orders', 'contact_name', 'VARCHAR(190) NOT NULL DEFAULT ''''', 'company_name');
CALL add_column_if_missing('orders', 'contact_email', 'VARCHAR(190) NOT NULL DEFAULT ''''', 'contact_name');
CALL add_column_if_missing('orders', 'contact_phone', 'VARCHAR(80) NOT NULL DEFAULT ''''', 'contact_email');
CALL add_column_if_missing('orders', 'address_zone', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'contact_phone');
CALL add_column_if_missing('orders', 'seller_name', 'VARCHAR(140) NOT NULL DEFAULT ''''', 'address_zone');
CALL add_column_if_missing('orders', 'admin_notes', 'TEXT NULL', 'comments');
CALL add_column_if_missing('orders', 'source_channel', 'ENUM(''web'',''offline-sync'',''admin'') NOT NULL DEFAULT ''web''', 'status');
CALL add_column_if_missing('orders', 'export_csv_path', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'source_channel');
CALL add_column_if_missing('orders', 'export_xlsx_path', 'VARCHAR(255) NOT NULL DEFAULT ''''', 'export_csv_path');
CALL add_column_if_missing('orders', 'export_generated_at', 'DATETIME DEFAULT NULL', 'export_xlsx_path');
CALL add_column_if_missing('orders', 'email_sent_at', 'DATETIME DEFAULT NULL', 'export_generated_at');
CALL add_column_if_missing('orders', 'email_status', 'ENUM(''pending'',''sent'',''failed'') NOT NULL DEFAULT ''pending''', 'email_sent_at');

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL add_column_if_missing('catalog_access_logs', 'share_link_id', 'BIGINT UNSIGNED DEFAULT NULL', 'catalog_id');
CALL add_column_if_missing('catalog_access_logs', 'seller_id', 'BIGINT UNSIGNED DEFAULT NULL', 'share_link_id');
CALL add_column_if_missing('catalog_access_logs', 'client_id', 'BIGINT UNSIGNED DEFAULT NULL', 'seller_id');
CALL add_column_if_missing('catalog_access_logs', 'utm_source', 'VARCHAR(120) NOT NULL DEFAULT ''''', 'referrer');
CALL add_column_if_missing('catalog_access_logs', 'utm_medium', 'VARCHAR(120) NOT NULL DEFAULT ''''', 'utm_source');

CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(40) NOT NULL DEFAULT '',
  `to_status` VARCHAR(40) NOT NULL,
  `changed_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(120) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL DEFAULT '',
  `entity_id` BIGINT UNSIGNED DEFAULT NULL,
  `context_json` JSON NULL,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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

DROP PROCEDURE IF EXISTS add_column_if_missing;
