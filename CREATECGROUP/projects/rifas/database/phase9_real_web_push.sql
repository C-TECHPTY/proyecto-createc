CREATE TABLE IF NOT EXISTS push_subscriptions (
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

ALTER TABLE push_subscriptions
  ADD COLUMN IF NOT EXISTS status ENUM('active','disabled','error') NOT NULL DEFAULT 'active' AFTER active,
  ADD COLUMN IF NOT EXISTS last_success_at DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS last_error TEXT NULL AFTER last_success_at;
