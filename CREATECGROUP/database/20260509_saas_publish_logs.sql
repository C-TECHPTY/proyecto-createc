-- Logs no bloqueantes de publicacion SaaS.
-- No modifica tablas operativas ni guarda licencias completas en texto plano.

CREATE TABLE IF NOT EXISTS `saas_publish_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NULL,
  `company_slug` VARCHAR(120) NULL,
  `license_id` INT UNSIGNED NULL,
  `license_key_hash` CHAR(64) NULL,
  `device_id` VARCHAR(190) NULL,
  `app_version` VARCHAR(60) NULL,
  `endpoint` VARCHAR(120) NOT NULL,
  `catalog_slug` VARCHAR(190) NULL,
  `catalog_title` VARCHAR(255) NULL,
  `publish_url` VARCHAR(500) NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'legacy',
  `allowed_publish` TINYINT(1) NOT NULL DEFAULT 1,
  `warning_message` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saas_publish_logs_company_id` (`company_id`),
  KEY `idx_saas_publish_logs_company_slug` (`company_slug`),
  KEY `idx_saas_publish_logs_license_id` (`license_id`),
  KEY `idx_saas_publish_logs_status` (`status`),
  KEY `idx_saas_publish_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
