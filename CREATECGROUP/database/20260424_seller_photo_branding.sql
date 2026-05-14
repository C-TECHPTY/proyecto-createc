ALTER TABLE `sellers`
  ADD COLUMN IF NOT EXISTS `photo_path` VARCHAR(255) NOT NULL DEFAULT '' AFTER `territory`;

INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('branding_company_name', 'Catalogo Rodeo B2B'),
('branding_login_title', 'Catalogo Rodeo B2B'),
('branding_login_subtitle', 'Administracion comercial, vendedores, links y pedidos trazables.'),
('branding_company_logo', ''),
('branding_login_logo', ''),
('branding_login_background', '')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
