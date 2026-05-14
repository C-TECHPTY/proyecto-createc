-- FASE 1: Seguridad y limpieza de pedidos de prueba.
-- Ejecutar en la base de datos del hosting antes de usar las nuevas acciones.
-- La migracion es idempotente y crea un respaldo de orders antes de alterar columnas.

CREATE TABLE IF NOT EXISTS `orders_backup_20260428_phase1` AS
SELECT * FROM `orders`;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_order_column_if_missing $$
CREATE PROCEDURE add_order_column_if_missing(
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT,
  IN p_after_column VARCHAR(64)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @ddl = CONCAT(
      'ALTER TABLE `orders` ADD COLUMN `',
      p_column_name,
      '` ',
      p_column_definition,
      IF(p_after_column IS NULL OR p_after_column = '', '', CONCAT(' AFTER `', p_after_column, '`'))
    );
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL add_order_column_if_missing('is_test', 'TINYINT(1) NOT NULL DEFAULT 0', 'status') $$
CALL add_order_column_if_missing('deleted_at', 'DATETIME NULL DEFAULT NULL', 'updated_at') $$
CALL add_order_column_if_missing('deleted_by', 'BIGINT UNSIGNED NULL DEFAULT NULL', 'deleted_at') $$

DROP PROCEDURE IF EXISTS add_order_column_if_missing $$

DELIMITER ;

ALTER TABLE `orders`
  MODIFY `status` ENUM(
    'new',
    'pendiente',
    'confirmado',
    'reviewed',
    'processing',
    'invoiced',
    'completed',
    'cancelled',
    'anulado',
    'archivado'
  ) NOT NULL DEFAULT 'new';

DELIMITER $$

DROP PROCEDURE IF EXISTS add_order_index_if_missing $$
CREATE PROCEDURE add_order_index_if_missing(
  IN p_index_name VARCHAR(64),
  IN p_index_columns VARCHAR(255)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @ddl = CONCAT('CREATE INDEX `', p_index_name, '` ON `orders` (', p_index_columns, ')');
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL add_order_index_if_missing('idx_orders_is_test', '`is_test`') $$
CALL add_order_index_if_missing('idx_orders_deleted_at', '`deleted_at`') $$

DROP PROCEDURE IF EXISTS add_order_index_if_missing $$

DELIMITER ;

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
  KEY `idx_activity_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
