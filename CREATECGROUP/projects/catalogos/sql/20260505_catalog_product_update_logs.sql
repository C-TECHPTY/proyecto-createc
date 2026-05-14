-- FASE 1: Actualizacion de datos comerciales de catalogos publicados.
-- Ejecutar antes de usar catalogos_admin/catalog_update_data.php.

CREATE TABLE IF NOT EXISTS `catalog_product_update_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `admin_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `filename` VARCHAR(190) NOT NULL DEFAULT '',
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `matched_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `out_of_stock_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `not_found_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_catalog_product_update_logs_catalog_id` (`catalog_id`),
  KEY `idx_catalog_product_update_logs_admin_user_id` (`admin_user_id`),
  KEY `idx_catalog_product_update_logs_created_at` (`created_at`),
  CONSTRAINT `fk_catalog_product_update_logs_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_product_update_logs_admin_user_id`
    FOREIGN KEY (`admin_user_id`) REFERENCES `catalog_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
