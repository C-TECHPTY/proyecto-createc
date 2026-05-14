-- FASE 3: Token por vendedor y pedido asignado.
-- Ejecutar en la base de datos del hosting antes de usar enlaces ?t=TOKEN.
-- La migracion es idempotente y crea respaldos antes de alterar tablas criticas.

CREATE TABLE IF NOT EXISTS `sellers_backup_20260428_phase3` AS
SELECT * FROM `sellers`;

CREATE TABLE IF NOT EXISTS `orders_backup_20260428_phase3` AS
SELECT * FROM `orders`;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$
CREATE PROCEDURE add_column_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT,
  IN p_after_column VARCHAR(64)
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @ddl = CONCAT(
      'ALTER TABLE `',
      p_table_name,
      '` ADD COLUMN `',
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

CALL add_column_if_missing('sellers', 'public_token', 'CHAR(64) NULL DEFAULT NULL', 'territory') $$
CALL add_column_if_missing('orders', 'seller_token', 'CHAR(64) NOT NULL DEFAULT ''''', 'seller_id') $$

DROP PROCEDURE IF EXISTS add_column_if_missing $$

UPDATE `sellers`
SET `public_token` = SHA2(CONCAT('seller:', `id`, ':', UUID(), ':', RAND()), 256)
WHERE `public_token` IS NULL OR `public_token` = '' $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$
CREATE PROCEDURE add_index_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_index_columns VARCHAR(255),
  IN p_is_unique TINYINT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @ddl = CONCAT(
      IF(p_is_unique = 1, 'CREATE UNIQUE INDEX `', 'CREATE INDEX `'),
      p_index_name,
      '` ON `',
      p_table_name,
      '` (',
      p_index_columns,
      ')'
    );
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

CALL add_index_if_missing('sellers', 'uq_sellers_public_token', '`public_token`', 1) $$
CALL add_index_if_missing('orders', 'idx_orders_seller_token', '`seller_token`', 0) $$

DROP PROCEDURE IF EXISTS add_index_if_missing $$

DELIMITER ;
