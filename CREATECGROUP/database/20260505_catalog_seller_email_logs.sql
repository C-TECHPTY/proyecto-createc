-- Envio masivo de catalogos publicados a vendedores.
-- Ejecutar en el hosting antes de usar "Enviar a vendedores".

CREATE TABLE IF NOT EXISTS `catalog_seller_email_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `secure_link_id` BIGINT UNSIGNED DEFAULT NULL,
  `token` CHAR(64) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL,
  `status` ENUM('sent','error') NOT NULL DEFAULT 'error',
  `error_message` TEXT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_catalog_seller_email_logs_catalog_id` (`catalog_id`),
  KEY `idx_catalog_seller_email_logs_seller_id` (`seller_id`),
  KEY `idx_catalog_seller_email_logs_secure_link_id` (`secure_link_id`),
  KEY `idx_catalog_seller_email_logs_status` (`status`),
  KEY `idx_catalog_seller_email_logs_sent_at` (`sent_at`),
  CONSTRAINT `fk_catalog_seller_email_logs_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_seller_email_logs_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_seller_email_logs_secure_link_id`
    FOREIGN KEY (`secure_link_id`) REFERENCES `catalog_share_links` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
