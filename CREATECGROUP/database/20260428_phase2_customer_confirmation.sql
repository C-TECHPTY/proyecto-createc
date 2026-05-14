-- FASE 2: Pedido confirmado por cliente antes de enviar.
-- Ejecutar en la base de datos del hosting antes de publicar el nuevo flujo.
-- La migracion es idempotente y crea un respaldo de orders antes de alterar columnas.

CREATE TABLE IF NOT EXISTS `orders_backup_20260428_phase2` AS
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

CALL add_order_column_if_missing('customer_confirmed', 'TINYINT(1) NOT NULL DEFAULT 0', 'deleted_by') $$
CALL add_order_column_if_missing('confirmed_at', 'DATETIME NULL DEFAULT NULL', 'customer_confirmed') $$
CALL add_order_column_if_missing('customer_ip', 'VARCHAR(64) NOT NULL DEFAULT ''''', 'confirmed_at') $$

DROP PROCEDURE IF EXISTS add_order_column_if_missing $$

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

CALL add_order_index_if_missing('idx_orders_customer_confirmed', '`customer_confirmed`') $$

DROP PROCEDURE IF EXISTS add_order_index_if_missing $$

DELIMITER ;
