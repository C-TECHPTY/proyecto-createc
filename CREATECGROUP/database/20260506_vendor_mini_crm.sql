-- FASE 6: Mini CRM basico para panel vendedor.
-- Ejecutar antes de usar catalogos_vendedor/crm.php.
-- No modifica clients, orders ni tablas existentes.

CREATE TABLE IF NOT EXISTS `vendor_client_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_key` VARCHAR(190) NOT NULL,
  `client_name` VARCHAR(190) NOT NULL DEFAULT '',
  `contact_name` VARCHAR(190) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `phone` VARCHAR(80) NOT NULL DEFAULT '',
  `client_status` ENUM('frecuente','potencial','inactivo','mayorista') NOT NULL DEFAULT 'potencial',
  `last_note_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_client_profiles_seller_key` (`seller_id`, `client_key`),
  KEY `idx_vendor_client_profiles_seller_id` (`seller_id`),
  KEY `idx_vendor_client_profiles_client_id` (`client_id`),
  KEY `idx_vendor_client_profiles_status` (`client_status`),
  CONSTRAINT `fk_vendor_client_profiles_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_vendor_client_profiles_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vendor_client_notes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id` BIGINT UNSIGNED NOT NULL,
  `profile_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_key` VARCHAR(190) NOT NULL,
  `note` TEXT NOT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vendor_client_notes_seller_id` (`seller_id`),
  KEY `idx_vendor_client_notes_profile_id` (`profile_id`),
  KEY `idx_vendor_client_notes_client_key` (`client_key`),
  KEY `idx_vendor_client_notes_created_at` (`created_at`),
  CONSTRAINT `fk_vendor_client_notes_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_vendor_client_notes_profile_id`
    FOREIGN KEY (`profile_id`) REFERENCES `vendor_client_profiles` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_vendor_client_notes_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `catalog_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
