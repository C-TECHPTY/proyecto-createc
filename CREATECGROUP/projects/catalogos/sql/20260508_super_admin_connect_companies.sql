-- Fase preparatoria SaaS: conecta Super Admin con empresas, planes y dominios.
-- Seguro para instalaciones existentes: no borra datos ni toca tablas operativas
-- como catalogs, orders, sellers, clients, campaigns o share links.

CREATE TABLE IF NOT EXISTS `sa_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `monthly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_catalogs` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_sellers` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_products` INT UNSIGNED NOT NULL DEFAULT 0,
  `storage_gb` INT UNSIGNED NOT NULL DEFAULT 0,
  `allow_custom_domain` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_backblaze` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_campaigns` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_ai` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_plans_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sa_company_domains` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `domain` VARCHAR(190) NOT NULL,
  `type` ENUM('subdomain','custom_domain') NOT NULL DEFAULT 'subdomain',
  `status` ENUM('pending','active','failed','disabled') NOT NULL DEFAULT 'pending',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `dns_target` VARCHAR(190) NULL,
  `ssl_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sa_company_domains_domain` (`domain`),
  KEY `idx_sa_company_domains_company_id` (`company_id`),
  KEY `idx_sa_company_domains_status` (`status`),
  KEY `idx_sa_company_domains_primary` (`company_id`, `is_primary`),
  CONSTRAINT `fk_sa_company_domains_company`
    FOREIGN KEY (`company_id`) REFERENCES `sa_companies` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP PROCEDURE IF EXISTS `sa_add_company_column_if_missing` $$
CREATE PROCEDURE `sa_add_company_column_if_missing`(
  IN column_name_value VARCHAR(64),
  IN column_definition_value TEXT,
  IN after_column_value VARCHAR(64)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sa_companies'
      AND COLUMN_NAME = column_name_value
  ) THEN
    SET @sa_alter_sql = CONCAT(
      'ALTER TABLE `sa_companies` ADD COLUMN `',
      column_name_value,
      '` ',
      column_definition_value,
      IF(after_column_value = '', '', CONCAT(' AFTER `', after_column_value, '`'))
    );
    PREPARE sa_alter_stmt FROM @sa_alter_sql;
    EXECUTE sa_alter_stmt;
    DEALLOCATE PREPARE sa_alter_stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS `sa_add_index_if_missing` $$
CREATE PROCEDURE `sa_add_index_if_missing`(
  IN table_name_value VARCHAR(64),
  IN index_name_value VARCHAR(64),
  IN index_definition_value TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_value
      AND INDEX_NAME = index_name_value
  ) THEN
    SET @sa_index_sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD ', index_definition_value);
    PREPARE sa_index_stmt FROM @sa_index_sql;
    EXECUTE sa_index_stmt;
    DEALLOCATE PREPARE sa_index_stmt;
  END IF;
END $$

CALL `sa_add_company_column_if_missing`('legal_name', 'VARCHAR(190) NULL', 'company_name') $$
CALL `sa_add_company_column_if_missing`('plan_id', 'INT UNSIGNED NULL', 'primary_color') $$
CALL `sa_add_company_column_if_missing`('expires_at', 'DATE NULL', 'plan_id') $$
CALL `sa_add_company_column_if_missing`('max_catalogs', 'INT UNSIGNED NOT NULL DEFAULT 0', 'expires_at') $$
CALL `sa_add_company_column_if_missing`('max_sellers', 'INT UNSIGNED NOT NULL DEFAULT 0', 'max_catalogs') $$
CALL `sa_add_company_column_if_missing`('max_products', 'INT UNSIGNED NOT NULL DEFAULT 0', 'max_sellers') $$
CALL `sa_add_company_column_if_missing`('storage_mode', 'ENUM(''hosting'',''backblaze'',''hybrid'') NOT NULL DEFAULT ''hosting''', 'max_products') $$

CALL `sa_add_index_if_missing`('sa_companies', 'idx_sa_companies_plan_id', 'KEY `idx_sa_companies_plan_id` (`plan_id`)') $$
CALL `sa_add_index_if_missing`('sa_companies', 'idx_sa_companies_expires_at', 'KEY `idx_sa_companies_expires_at` (`expires_at`)') $$

DROP PROCEDURE IF EXISTS `sa_add_company_column_if_missing` $$
DROP PROCEDURE IF EXISTS `sa_add_index_if_missing` $$

DELIMITER ;
