-- CREATEC SaaS - esquema propuesto para base unica createc_saas
-- Estado: propuesta para revision/staging.
-- Solo crea estructuras nuevas o inexistentes.
-- Ejecutar solo despues de respaldo y aprobacion.

CREATE DATABASE IF NOT EXISTS `createc_saas`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- En cPanel puede ser mejor seleccionar manualmente la base en phpMyAdmin
-- y ejecutar desde aqui hacia abajo si el usuario MySQL no tiene permiso CREATE DATABASE.

CREATE TABLE IF NOT EXISTS `sa_companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(190) NOT NULL,
  `legal_name` VARCHAR(190) NULL,
  `slug` VARCHAR(120) NOT NULL,
  `contact_name` VARCHAR(160) NULL,
  `contact_email` VARCHAR(190) NULL,
  `contact_phone` VARCHAR(60) NULL,
  `domain` VARCHAR(190) NULL,
  `subdomain` VARCHAR(120) NULL,
  `logo_url` VARCHAR(500) NULL,
  `primary_color` VARCHAR(20) NULL,
  `plan_id` INT UNSIGNED NULL,
  `expires_at` DATE NULL,
  `max_catalogs` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_sellers` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `storage_mode` ENUM('hosting','backblaze','hybrid') NOT NULL DEFAULT 'hosting',
  `status` ENUM('active','suspended','inactive') NOT NULL DEFAULT 'active',
  `database_name` VARCHAR(190) NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_companies_slug` (`slug`),
  KEY `idx_sa_companies_status` (`status`),
  KEY `idx_sa_companies_plan_id` (`plan_id`),
  KEY `idx_sa_companies_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `annual_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_users` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_catalogs` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_sellers` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_raffles` INT UNSIGNED NOT NULL DEFAULT 0,
  `storage_gb` INT UNSIGNED NOT NULL DEFAULT 0,
  `storage_mb` INT UNSIGNED NOT NULL DEFAULT 0,
  `allow_custom_domain` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_backblaze` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_campaigns` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_ai` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_plans_code` (`code`),
  KEY `idx_sa_plans_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `base_path` VARCHAR(190) NULL,
  `status` ENUM('active','inactive','planned') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_modules_code` (`code`),
  KEY `idx_sa_modules_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NULL,
  `plan_name` VARCHAR(120) NOT NULL DEFAULT '',
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `annual_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('trial','active','past_due','expired','cancelled') NOT NULL DEFAULT 'trial',
  `billing_cycle` ENUM('monthly','annual','yearly','manual') NOT NULL DEFAULT 'monthly',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `starts_at` DATE NULL,
  `expires_at` DATE NULL,
  `cancelled_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_subscriptions_company` (`company_id`),
  KEY `idx_sa_subscriptions_plan` (`plan_id`),
  KEY `idx_sa_subscriptions_status_expiry` (`status`, `expires_at`),
  CONSTRAINT `fk_sa_subscriptions_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `sa_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NULL,
  `license_key` VARCHAR(160) NOT NULL,
  `status` ENUM('active','inactive','expired','revoked') NOT NULL DEFAULT 'active',
  `expires_at` DATE NULL,
  `last_validated_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_licenses_key` (`license_key`),
  KEY `idx_sa_licenses_company` (`company_id`),
  KEY `idx_sa_licenses_module` (`module_id`),
  KEY `idx_sa_licenses_status_expiry` (`status`, `expires_at`),
  CONSTRAINT `fk_sa_licenses_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_licenses_module` FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_company_domains` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `domain` VARCHAR(190) NOT NULL,
  `type` ENUM('subdomain','custom_domain') NOT NULL DEFAULT 'subdomain',
  `document_root` VARCHAR(255) NULL,
  `status` ENUM('pending','active','failed','disabled') NOT NULL DEFAULT 'pending',
  `ssl_status` ENUM('pending','active','failed','not_required') NOT NULL DEFAULT 'pending',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_company_domains_domain` (`domain`),
  KEY `idx_sa_company_domains_company` (`company_id`),
  KEY `idx_sa_company_domains_status` (`status`),
  CONSTRAINT `fk_sa_company_domains_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_company_modules` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `settings_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_company_modules` (`company_id`, `module_id`),
  KEY `idx_sa_company_modules_module` (`module_id`),
  CONSTRAINT `fk_sa_company_modules_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_company_modules_module` FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_project_instances` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `module_id` INT UNSIGNED NOT NULL,
  `instance_key` VARCHAR(120) NOT NULL,
  `project_path` VARCHAR(255) NOT NULL,
  `database_name` VARCHAR(190) NULL,
  `status` ENUM('active','maintenance','disabled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_project_instances_key` (`instance_key`),
  KEY `idx_sa_project_instances_company` (`company_id`),
  KEY `idx_sa_project_instances_module` (`module_id`),
  CONSTRAINT `fk_sa_project_instances_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_project_instances_module` FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `scope` ENUM('platform','company') NOT NULL DEFAULT 'company',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_roles_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_permissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(120) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `module_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_permissions_code` (`code`),
  KEY `idx_sa_permissions_module` (`module_id`),
  CONSTRAINT `fk_sa_permissions_module` FOREIGN KEY (`module_id`) REFERENCES `sa_modules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NULL,
  `role_id` INT UNSIGNED NULL,
  `name` VARCHAR(140) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active','inactive','invited','blocked') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_users_email` (`email`),
  KEY `idx_sa_users_company` (`company_id`),
  KEY `idx_sa_users_role` (`role_id`),
  CONSTRAINT `fk_sa_users_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_users_role` FOREIGN KEY (`role_id`) REFERENCES `sa_roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  CONSTRAINT `fk_sa_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `sa_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `sa_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `module_code` VARCHAR(80) NULL,
  `action` VARCHAR(140) NOT NULL,
  `entity_type` VARCHAR(100) NULL,
  `entity_id` VARCHAR(80) NULL,
  `metadata_json` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_audit_company` (`company_id`),
  KEY `idx_sa_audit_user` (`user_id`),
  KEY `idx_sa_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_sa_audit_created` (`created_at`),
  CONSTRAINT `fk_sa_audit_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sa_audit_user` FOREIGN KEY (`user_id`) REFERENCES `sa_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web principal CREATEC

CREATE TABLE IF NOT EXISTS `web_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(140) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(60) NULL,
  `company_name` VARCHAR(190) NULL,
  `service_interest` VARCHAR(120) NULL,
  `message` TEXT NULL,
  `source` VARCHAR(80) NULL,
  `status` ENUM('new','contacted','qualified','won','lost','archived') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_web_contacts_status` (`status`),
  KEY `idx_web_contacts_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `web_leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_id` BIGINT UNSIGNED NULL,
  `lead_type` VARCHAR(100) NOT NULL,
  `budget_range` VARCHAR(80) NULL,
  `status` ENUM('open','in_progress','quoted','closed') NOT NULL DEFAULT 'open',
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_web_leads_contact` (`contact_id`),
  KEY `idx_web_leads_status` (`status`),
  CONSTRAINT `fk_web_leads_contact` FOREIGN KEY (`contact_id`) REFERENCES `web_contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `web_services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_web_services_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `web_portfolio_projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `category` VARCHAR(120) NULL,
  `summary` TEXT NULL,
  `image_path` VARCHAR(255) NULL,
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_web_portfolio_slug` (`slug`),
  KEY `idx_web_portfolio_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogos B2B

CREATE TABLE IF NOT EXISTS `cat_sellers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(140) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(60) NULL,
  `public_token` VARCHAR(120) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_sellers_company_token` (`company_id`, `public_token`),
  KEY `idx_cat_sellers_company_status` (`company_id`, `status`),
  CONSTRAINT `fk_cat_sellers_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_clients` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NULL,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NULL,
  `phone` VARCHAR(60) NULL,
  `company_name` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_clients_company` (`company_id`),
  KEY `idx_cat_clients_seller` (`seller_id`),
  CONSTRAINT `fk_cat_clients_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_clients_seller` FOREIGN KEY (`seller_id`) REFERENCES `cat_sellers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_catalogs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NULL,
  `slug` VARCHAR(160) NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `source_catalog_id` INT UNSIGNED NULL COMMENT 'ID legado opcional de catalogs',
  `metadata_json` JSON NULL,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_catalogs_company_slug` (`company_id`, `slug`),
  KEY `idx_cat_catalogs_company_status` (`company_id`, `status`),
  KEY `idx_cat_catalogs_seller` (`seller_id`),
  CONSTRAINT `fk_cat_catalogs_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_catalogs_seller` FOREIGN KEY (`seller_id`) REFERENCES `cat_sellers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_id` INT UNSIGNED NULL,
  `sku` VARCHAR(120) NULL,
  `name` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `category` VARCHAR(160) NULL,
  `price` DECIMAL(12,2) NULL,
  `stock` DECIMAL(12,2) NULL,
  `metadata_json` JSON NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_products_company` (`company_id`),
  KEY `idx_cat_products_catalog` (`catalog_id`),
  KEY `idx_cat_products_sku` (`company_id`, `sku`),
  CONSTRAINT `fk_cat_products_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_products_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `cat_catalogs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_product_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500) NOT NULL,
  `alt_text` VARCHAR(190) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_product_images_product` (`product_id`),
  CONSTRAINT `fk_cat_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `cat_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_catalog_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_id` INT UNSIGNED NOT NULL,
  `seller_id` INT UNSIGNED NULL,
  `client_id` INT UNSIGNED NULL,
  `token` VARCHAR(160) NOT NULL,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_catalog_tokens_token` (`token`),
  KEY `idx_cat_catalog_tokens_company` (`company_id`),
  KEY `idx_cat_catalog_tokens_catalog` (`catalog_id`),
  CONSTRAINT `fk_cat_catalog_tokens_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_catalog_tokens_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `cat_catalogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_catalog_tokens_seller` FOREIGN KEY (`seller_id`) REFERENCES `cat_sellers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_catalog_tokens_client` FOREIGN KEY (`client_id`) REFERENCES `cat_clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_id` INT UNSIGNED NULL,
  `seller_id` INT UNSIGNED NULL,
  `client_id` INT UNSIGNED NULL,
  `order_number` VARCHAR(80) NULL,
  `customer_name` VARCHAR(160) NOT NULL,
  `customer_email` VARCHAR(190) NULL,
  `customer_phone` VARCHAR(60) NULL,
  `status` ENUM('new','confirmed','processing','completed','cancelled') NOT NULL DEFAULT 'new',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_orders_company_number` (`company_id`, `order_number`),
  KEY `idx_cat_orders_company_status` (`company_id`, `status`),
  KEY `idx_cat_orders_catalog` (`catalog_id`),
  CONSTRAINT `fk_cat_orders_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_orders_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `cat_catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `cat_sellers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_orders_client` FOREIGN KEY (`client_id`) REFERENCES `cat_clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NULL,
  `sku` VARCHAR(120) NULL,
  `name` VARCHAR(190) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_cat_order_items_order` (`order_id`),
  KEY `idx_cat_order_items_product` (`product_id`),
  CONSTRAINT `fk_cat_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `cat_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `cat_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_id` INT UNSIGNED NULL,
  `name` VARCHAR(190) NOT NULL,
  `status` ENUM('draft','active','paused','finished') NOT NULL DEFAULT 'draft',
  `starts_at` DATETIME NULL,
  `ends_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_campaigns_company` (`company_id`),
  KEY `idx_cat_campaigns_catalog` (`catalog_id`),
  CONSTRAINT `fk_cat_campaigns_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_campaigns_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `cat_catalogs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_access_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `catalog_id` INT UNSIGNED NULL,
  `seller_id` INT UNSIGNED NULL,
  `client_id` INT UNSIGNED NULL,
  `token_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `referrer` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_access_company_created` (`company_id`, `created_at`),
  KEY `idx_cat_access_catalog` (`catalog_id`),
  CONSTRAINT `fk_cat_access_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cat_access_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `cat_catalogs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_access_seller` FOREIGN KEY (`seller_id`) REFERENCES `cat_sellers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_access_client` FOREIGN KEY (`client_id`) REFERENCES `cat_clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cat_access_token` FOREIGN KEY (`token_id`) REFERENCES `cat_catalog_tokens` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cat_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_settings_company_key` (`company_id`, `setting_key`),
  CONSTRAINT `fk_cat_settings_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rifas

CREATE TABLE IF NOT EXISTS `rifa_customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(140) NOT NULL,
  `whatsapp` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rifa_customers_company_whatsapp` (`company_id`, `whatsapp`),
  CONSTRAINT `fk_rifa_customers_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_raffles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `title` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `description` TEXT NULL,
  `flyer_path` VARCHAR(255) NULL,
  `price_per_number` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `draw_date` DATETIME NULL,
  `number_min` INT NOT NULL DEFAULT 0,
  `number_max` INT NOT NULL DEFAULT 100,
  `reservation_minutes` INT NOT NULL DEFAULT 20,
  `status` ENUM('draft','active','closed','drawn') NOT NULL DEFAULT 'draft',
  `settings_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rifa_raffles_company_slug` (`company_id`, `slug`),
  KEY `idx_rifa_raffles_company_status` (`company_id`, `status`),
  CONSTRAINT `fk_rifa_raffles_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_raffles_created_by` FOREIGN KEY (`created_by`) REFERENCES `sa_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_numbers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `number_value` INT NOT NULL,
  `display_number` VARCHAR(12) NOT NULL,
  `status` ENUM('available','reserved','sold','winner') NOT NULL DEFAULT 'available',
  `reserved_until` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rifa_numbers_raffle_number` (`raffle_id`, `number_value`),
  KEY `idx_rifa_numbers_status` (`raffle_id`, `status`),
  CONSTRAINT `fk_rifa_numbers_raffle` FOREIGN KEY (`raffle_id`) REFERENCES `rifa_raffles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_reservations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'yappy',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `comment` TEXT NULL,
  `expires_at` DATETIME NULL,
  `paid_at` DATETIME NULL,
  `confirmed_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rifa_reservations_company_status` (`company_id`, `status`),
  KEY `idx_rifa_reservations_raffle` (`raffle_id`, `status`),
  KEY `idx_rifa_reservations_customer` (`customer_id`),
  CONSTRAINT `fk_rifa_reservations_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_reservations_raffle` FOREIGN KEY (`raffle_id`) REFERENCES `rifa_raffles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_reservations_customer` FOREIGN KEY (`customer_id`) REFERENCES `rifa_customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_reservations_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `sa_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_reservation_numbers` (
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `raffle_number_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`reservation_id`, `raffle_number_id`),
  CONSTRAINT `fk_rifa_resnum_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `rifa_reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_resnum_number` FOREIGN KEY (`raffle_number_id`) REFERENCES `rifa_numbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(40) NOT NULL,
  `status` ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `reference` VARCHAR(120) NULL,
  `confirmed_by` INT UNSIGNED NULL,
  `confirmed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rifa_payments_company_status` (`company_id`, `status`),
  KEY `idx_rifa_payments_reservation` (`reservation_id`),
  CONSTRAINT `fk_rifa_payments_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `rifa_reservations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_payments_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `sa_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_payment_receipts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(190) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rifa_receipts_reservation` (`reservation_id`),
  CONSTRAINT `fk_rifa_receipts_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `rifa_reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_winners` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `raffle_id` BIGINT UNSIGNED NOT NULL,
  `raffle_number_id` BIGINT UNSIGNED NOT NULL,
  `reservation_id` BIGINT UNSIGNED NULL,
  `prize_label` VARCHAR(80) NOT NULL,
  `prize_description` VARCHAR(190) NULL,
  `draw_number` VARCHAR(40) NULL,
  `secret_code` VARCHAR(80) NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rifa_winners_company` (`company_id`),
  KEY `idx_rifa_winners_raffle` (`raffle_id`),
  CONSTRAINT `fk_rifa_winners_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_winners_raffle` FOREIGN KEY (`raffle_id`) REFERENCES `rifa_raffles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_winners_number` FOREIGN KEY (`raffle_number_id`) REFERENCES `rifa_numbers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rifa_winners_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `rifa_reservations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(60) NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `body` TEXT NOT NULL,
  `url` VARCHAR(255) NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rifa_notifications_company_read` (`company_id`, `read_at`),
  CONSTRAINT `fk_rifa_notifications_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rifa_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(120) NOT NULL,
  `setting_value` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rifa_settings_company_key` (`company_id`, `setting_key`),
  CONSTRAINT `fk_rifa_settings_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modulos futuros: esqueletos minimos para reservar prefijos.

CREATE TABLE IF NOT EXISTS `barber_locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_barber_locations_company` (`company_id`),
  CONSTRAINT `fk_barber_locations_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tour_bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(160) NOT NULL,
  `status` ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tour_bookings_company` (`company_id`),
  CONSTRAINT `fk_tour_bookings_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `order_number` VARCHAR(80) NULL,
  `status` ENUM('new','paid','fulfilled','cancelled') NOT NULL DEFAULT 'new',
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_store_orders_company_status` (`company_id`, `status`),
  CONSTRAINT `fk_store_orders_company` FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
