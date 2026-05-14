-- FASE 4: Correos del pedido por vendedor y administracion.
-- No altera SMTP ni credenciales. Solo agrega configuracion administrable.

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('order_admin_emails', '')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
