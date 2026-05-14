CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','admin') NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE raffles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description TEXT NULL,
  flyer_path VARCHAR(255) NULL,
  first_prize VARCHAR(180) NOT NULL,
  second_prize VARCHAR(180) NULL,
  third_prize VARCHAR(180) NULL,
  price_per_number DECIMAL(10,2) NOT NULL,
  draw_date DATETIME NULL,
  draw_method VARCHAR(180) NOT NULL DEFAULT 'Sorteo manual',
  number_min INT NOT NULL DEFAULT 0,
  number_max INT NOT NULL DEFAULT 100,
  reservation_minutes INT NOT NULL DEFAULT 20,
  yappy_number VARCHAR(60) NULL,
  contact_whatsapp VARCHAR(60) NULL,
  paypal_link VARCHAR(255) NULL,
  bank_info TEXT NULL,
  lnb_url VARCHAR(255) NOT NULL DEFAULT 'https://www.lnb.gob.pa/',
  status ENUM('draft','active','closed','drawn') NOT NULL DEFAULT 'draft',
  theme VARCHAR(40) NOT NULL DEFAULT 'clean_sky',
  primary_color VARCHAR(20) NOT NULL DEFAULT '#38aeea',
  accent_color VARCHAR(20) NOT NULL DEFAULT '#f06292',
  background_color VARCHAR(20) NOT NULL DEFAULT '#eaf8ff',
  grid_style VARCHAR(40) NOT NULL DEFAULT 'soft_cards',
  item_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  points_per_amount DECIMAL(10,2) NOT NULL DEFAULT 2.00,
  points_for_free_number INT NOT NULL DEFAULT 10,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_raffles_status (status),
  CONSTRAINT fk_raffles_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE raffle_numbers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raffle_id INT UNSIGNED NOT NULL,
  number_value INT NOT NULL,
  display_number VARCHAR(8) NOT NULL,
  status ENUM('available','reserved','sold','winner') NOT NULL DEFAULT 'available',
  reserved_until DATETIME NULL,
  reservation_id INT UNSIGNED NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_raffle_number (raffle_id, number_value),
  INDEX idx_number_status (raffle_id, status),
  CONSTRAINT fk_numbers_raffle FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  whatsapp VARCHAR(60) NOT NULL,
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customer_whatsapp (whatsapp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raffle_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','cancelled','expired') NOT NULL DEFAULT 'pending',
  payment_method ENUM('yappy','cash','transfer','paypal') NOT NULL DEFAULT 'yappy',
  total_amount DECIMAL(10,2) NOT NULL,
  comment TEXT NULL,
  expires_at DATETIME NULL,
  paid_at DATETIME NULL,
  confirmed_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reservations_status (status),
  INDEX idx_reservations_raffle (raffle_id, status),
  CONSTRAINT fk_reservations_raffle FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_reservations_admin FOREIGN KEY (confirmed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservation_numbers (
  reservation_id INT UNSIGNED NOT NULL,
  raffle_number_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (reservation_id, raffle_number_id),
  CONSTRAINT fk_resnum_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_resnum_number FOREIGN KEY (raffle_number_id) REFERENCES raffle_numbers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(40) NOT NULL,
  status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  reference VARCHAR(120) NULL,
  confirmed_by INT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_payments_admin FOREIGN KEY (confirmed_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipts_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  url VARCHAR(255) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_read (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  endpoint TEXT NOT NULL,
  p256dh_key VARCHAR(255) NULL,
  auth_token VARCHAR(255) NULL,
  user_agent VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','disabled','error') NOT NULL DEFAULT 'active',
  last_success_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_push_admin_active (admin_id, active),
  CONSTRAINT fk_push_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_configs (
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

CREATE TABLE whatsapp_messages (
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

CREATE TABLE winners (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raffle_id INT UNSIGNED NOT NULL,
  raffle_number_id INT UNSIGNED NOT NULL,
  reservation_id INT UNSIGNED NULL,
  prize_label VARCHAR(80) NOT NULL,
  prize_description VARCHAR(190) NOT NULL,
  draw_number VARCHAR(40) NULL,
  secret_code VARCHAR(40) NOT NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_winners_raffle FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE CASCADE,
  CONSTRAINT fk_winners_number FOREIGN KEY (raffle_number_id) REFERENCES raffle_numbers(id) ON DELETE CASCADE,
  CONSTRAINT fk_winners_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_points (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  raffle_id INT UNSIGNED NULL,
  points INT NOT NULL,
  reason VARCHAR(160) NOT NULL,
  reservation_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_points_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_points_raffle FOREIGN KEY (raffle_id) REFERENCES raffles(id) ON DELETE SET NULL,
  CONSTRAINT fk_points_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id INT UNSIGNED NULL,
  metadata JSON NULL,
  ip_address VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
