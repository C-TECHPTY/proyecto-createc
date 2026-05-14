CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NULL,
  `type` ENUM('nueva_mercancia','promocion','liquidacion','producto_destacado','nuevo_catalogo') NOT NULL DEFAULT 'promocion',
  `status` ENUM('draft','approved','sent') NOT NULL DEFAULT 'draft',
  `catalog_url` VARCHAR(255) NOT NULL DEFAULT '',
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaigns_status` (`status`),
  KEY `idx_campaigns_type` (`type`),
  KEY `idx_campaigns_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `item` VARCHAR(120) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `price` VARCHAR(80) NOT NULL DEFAULT '',
  `image_url` VARCHAR(500) NOT NULL DEFAULT '',
  `catalog_url` VARCHAR(500) NOT NULL DEFAULT '',
  `regular_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_type` VARCHAR(20) NOT NULL DEFAULT 'none',
  `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_start` DATETIME DEFAULT NULL,
  `promo_end` DATETIME DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_products_campaign_id` (`campaign_id`),
  KEY `idx_campaign_products_item` (`item`),
  CONSTRAINT `fk_campaign_products_campaign_id`
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_promo_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `campaign_title` VARCHAR(255) NOT NULL DEFAULT '',
  `company_name` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_name` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_email` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_phone` VARCHAR(80) NOT NULL DEFAULT '',
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `total_regular` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_promo` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_savings` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('new','sent','failed') NOT NULL DEFAULT 'new',
  `email_status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_promo_orders_campaign_id` (`campaign_id`),
  KEY `idx_campaign_promo_orders_created_at` (`created_at`),
  CONSTRAINT `fk_campaign_promo_orders_campaign_id`
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_promo_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promo_order_id` BIGINT UNSIGNED NOT NULL,
  `campaign_product_id` BIGINT UNSIGNED NOT NULL,
  `item` VARCHAR(120) NOT NULL DEFAULT '',
  `description` TEXT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `regular_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `line_regular_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `line_promo_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `line_savings` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_promo_order_items_order_id` (`promo_order_id`),
  KEY `idx_campaign_promo_order_items_product_id` (`campaign_product_id`),
  CONSTRAINT `fk_campaign_promo_order_items_order_id`
    FOREIGN KEY (`promo_order_id`) REFERENCES `campaign_promo_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `campaigns`
  ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `catalog_url`;

ALTER TABLE `campaign_products`
  ADD COLUMN IF NOT EXISTS `regular_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `catalog_url`,
  ADD COLUMN IF NOT EXISTS `discount_type` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `regular_price`,
  ADD COLUMN IF NOT EXISTS `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`,
  ADD COLUMN IF NOT EXISTS `promo_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_value`,
  ADD COLUMN IF NOT EXISTS `promo_start` DATETIME DEFAULT NULL AFTER `promo_price`,
  ADD COLUMN IF NOT EXISTS `promo_end` DATETIME DEFAULT NULL AFTER `promo_start`,
  ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `promo_end`;

UPDATE `campaign_products`
SET
  `regular_price` = CASE
    WHEN `regular_price` <= 0 THEN CAST(REPLACE(REPLACE(REPLACE(`price`, 'USD', ''), '$', ''), ',', '') AS DECIMAL(10,2))
    ELSE `regular_price`
  END,
  `promo_price` = CASE
    WHEN `promo_price` <= 0 THEN CASE
      WHEN `regular_price` > 0 THEN `regular_price`
      ELSE CAST(REPLACE(REPLACE(REPLACE(`price`, 'USD', ''), '$', ''), ',', '') AS DECIMAL(10,2))
    END
    ELSE `promo_price`
  END
WHERE `price` <> '' OR `regular_price` > 0;

CREATE TABLE IF NOT EXISTS `campaign_recipients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `recipient_type` ENUM('client','seller') NOT NULL DEFAULT 'client',
  `name` VARCHAR(190) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(80) NOT NULL DEFAULT '',
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `error_message` VARCHAR(255) NOT NULL DEFAULT '',
  `sent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_recipients_campaign_id` (`campaign_id`),
  KEY `idx_campaign_recipients_seller_id` (`seller_id`),
  KEY `idx_campaign_recipients_status` (`status`),
  KEY `idx_campaign_recipients_email` (`email`),
  CONSTRAINT `fk_campaign_recipients_campaign_id`
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `status` ENUM('sent','failed') NOT NULL DEFAULT 'failed',
  `error_message` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_logs_campaign_id` (`campaign_id`),
  KEY `idx_campaign_logs_status` (`status`),
  KEY `idx_campaign_logs_created_at` (`created_at`),
  CONSTRAINT `fk_campaign_logs_campaign_id`
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`setting_key`, `setting_value`)
VALUES ('campaigns_enabled', '1')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
