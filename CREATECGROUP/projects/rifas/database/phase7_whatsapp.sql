CREATE TABLE IF NOT EXISTS whatsapp_configs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider ENUM('manual','cloud_api','twilio','wati','360dialog') NOT NULL DEFAULT 'manual',
  phone_number VARCHAR(60) NULL,
  phone_id VARCHAR(120) NULL,
  api_token TEXT NULL,
  webhook_verify_token VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL DEFAULT 'manual',
  direction ENUM('inbound','outbound') NOT NULL,
  from_phone VARCHAR(60) NULL,
  to_phone VARCHAR(60) NULL,
  message_text TEXT NULL,
  intent VARCHAR(80) NULL,
  payload JSON NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'received',
  reservation_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_whatsapp_phone (from_phone, to_phone),
  INDEX idx_whatsapp_intent (intent),
  CONSTRAINT fk_whatsapp_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO whatsapp_configs (provider, phone_number, active)
SELECT 'manual', '', 0
WHERE NOT EXISTS (SELECT 1 FROM whatsapp_configs);
