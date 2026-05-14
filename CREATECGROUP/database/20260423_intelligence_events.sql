CREATE TABLE IF NOT EXISTS `catalog_behavior_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `catalog_id` BIGINT UNSIGNED NOT NULL,
  `share_link_id` BIGINT UNSIGNED DEFAULT NULL,
  `seller_id` BIGINT UNSIGNED DEFAULT NULL,
  `client_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `item_code` VARCHAR(120) NOT NULL DEFAULT '',
  `item_name` VARCHAR(255) NOT NULL DEFAULT '',
  `category` VARCHAR(160) NOT NULL DEFAULT '',
  `search_term` VARCHAR(190) NOT NULL DEFAULT '',
  `quantity` DECIMAL(12,2) DEFAULT NULL,
  `value_amount` DECIMAL(14,2) DEFAULT NULL,
  `session_id` VARCHAR(80) NOT NULL DEFAULT '',
  `visitor_id` VARCHAR(80) NOT NULL DEFAULT '',
  `metadata_json` JSON NULL,
  `ip_address` VARCHAR(64) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_catalog_behavior_catalog_id` (`catalog_id`),
  KEY `idx_catalog_behavior_share_link_id` (`share_link_id`),
  KEY `idx_catalog_behavior_seller_id` (`seller_id`),
  KEY `idx_catalog_behavior_client_id` (`client_id`),
  KEY `idx_catalog_behavior_event_type` (`event_type`),
  KEY `idx_catalog_behavior_item_code` (`item_code`),
  KEY `idx_catalog_behavior_created_at` (`created_at`),
  KEY `idx_catalog_behavior_session_id` (`session_id`),
  CONSTRAINT `fk_catalog_behavior_catalog_id`
    FOREIGN KEY (`catalog_id`) REFERENCES `catalogs` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_catalog_behavior_share_link_id`
    FOREIGN KEY (`share_link_id`) REFERENCES `catalog_share_links` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_behavior_seller_id`
    FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`)
    ON DELETE SET NULL,
  CONSTRAINT `fk_catalog_behavior_client_id`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
